<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class YouTubeController extends Controller
{
    // Show the page
    public function index()
    {
        return view('youtube.index');
    }

    /**
     * Return video metadata + formats using yt-dlp JSON output.
     * Expects POST with 'url' param (YouTube URL or ID)
     */
    public function formats(Request $request)
    {
        $request->validate(['url' => 'required|string']);

        $url = $this->extractYoutubeUrl($request->input('url'));
        if (!$url) {
            return response()->json(['error' => 'Invalid YouTube URL or ID'], 422);
        }

        // Path to yt-dlp binary
        $ytDlp = '/usr/local/bin/yt-dlp'; // adjust if different
        $cmd = [$ytDlp, '--no-warnings', '-j', $url];

        $process = new Process($cmd);
        $process->setTimeout(60); // metadata should be quick

        try {
            $process->mustRun();
            $output = $process->getOutput();
            $json = json_decode($output, true);
            if (!$json) {
                return response()->json(['error' => 'Failed to parse metadata from yt-dlp'], 500);
            }

            // Normalize formats: pick relevant fields

            $formats = [];

            foreach ($json['formats'] as $fmt) {

                // 1️⃣ Only MP4 container
                if (($fmt['ext'] ?? '') !== 'mp4') {
                    continue;
                }

                // 2️⃣ Only video formats (exclude audio-only)
                if (($fmt['vcodec'] ?? 'none') === 'none') {
                    continue;
                }

                // 3️⃣ Optional: skip weird fragment/manifest-only formats
                if (in_array($fmt['protocol'] ?? '', ['m3u8', 'm3u8_native', 'dash', 'ism'])) {
                    continue;
                }

                // Add filtered MP4 format
                $formats[] = [
                    'format_id' => $fmt['format_id'] ?? null,
                    'ext' => $fmt['ext'] ?? null,
                    'format_note' => $fmt['format_note'] ?? null,
                    'height' => $fmt['height'] ?? null,
                    'width' => $fmt['width'] ?? null,
                    'resolution' => $fmt['resolution'] ?? null,
                    'filesize' => $fmt['filesize'] ?? ($fmt['filesize_approx'] ?? null),
                    'fps' => $fmt['fps'] ?? null,
                    'protocol' => $fmt['protocol'] ?? null,
                    'acodec' => $fmt['acodec'] ?? null,
                    'vcodec' => $fmt['vcodec'] ?? null,
                    'abr' => $fmt['abr'] ?? null,
                    'tbr' => $fmt['tbr'] ?? null,
                ];
            }


            // Basic video metadata
            $meta = [
                'id' => $json['id'] ?? null,
                'title' => $json['title'] ?? null,
                'uploader' => $json['uploader'] ?? null,
                'duration' => $json['duration'] ?? null,
                'thumbnail' => $json['thumbnail'] ?? null,
            ];

            return response()->json(['meta' => $meta, 'formats' => $formats]);

        } catch (ProcessFailedException $e) {
            \Log::error('yt-dlp metadata failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to get formats: ' . $e->getMessage()], 500);
        }
    }

    public function download(Request $request)
    {
        $request->validate([
            'url' => 'required|string',
            'format_id' => 'required|string',
        ]);

        $url = $request->input('url');
        $format = $request->input('format_id');

        // Normalize the URL/ID into a usable URL (reuse your helper if present)
        $urlNormalized = $this->extractYoutubeUrl($url);
        if (!$urlNormalized) {
            return response()->json(['error' => 'Invalid YouTube URL or ID'], 422);
        }

        // Ensure storage directory exists and writable
        Storage::makeDirectory('public/videos');
        $videosDir = storage_path('app/public/videos');
        if (!is_dir($videosDir) || !is_writable($videosDir)) {
            return response()->json(['error' => 'Server error: videos directory missing or not writable', 'dir' => $videosDir], 500);
        }

        // Path to yt-dlp binary — change if yours is different
        $ytDlp = '/usr/local/bin/yt-dlp';

        // --- Step 1: get video metadata to build a sane filename ---
        $metaCmd = [$ytDlp, '--no-warnings', '-j', $urlNormalized];
        $metaProc = new Process($metaCmd);
        $metaProc->setTimeout(30);

        try {
            $metaProc->mustRun();
            $metaJson = json_decode($metaProc->getOutput(), true);
            $videoId = $metaJson['id'] ?? Str::random(8);
            $title = $metaJson['title'] ?? $videoId;
        } catch (\Throwable $e) {
            // metadata failed — fallback to random id, still attempt download
            \Log::warning('yt-dlp metadata failed before download: ' . $e->getMessage());
            $videoId = Str::random(8);
            $title = $videoId;
        }

        $safe = Str::slug(substr($title, 0, 100)) ?: $videoId;
        $outPattern = "{$videosDir}/{$videoId}_{$safe}.%(ext)s";

        // Build yt-dlp command for the requested format.
        // --no-part avoids .part temporary files and helps us detect final file name.
        // --no-playlist to avoid accidentally downloading a playlist.
        $cmd = [
            $ytDlp,
            '-f',
            $format,
            '--no-warnings',
            '--no-playlist',
            '--no-part',
            '-o',
            $outPattern,
            $urlNormalized
        ];

        $process = new Process($cmd);
        $process->setTimeout(7200); // adjust if you expect very long downloads

        try {
            // run (not mustRun) to capture stdout/stderr even if non-zero exit
            $process->run();
            $stdout = $process->getOutput();
            $stderr = $process->getErrorOutput();

            \Log::info('yt-dlp stdout: ' . substr($stdout, 0, 1000));
            \Log::info('yt-dlp stderr: ' . substr($stderr, 0, 1000));

            // if exit code non-zero, return yt-dlp errors
            if ($process->getExitCode() !== 0) {
                return response()->json([
                    'error' => 'yt-dlp reported an error',
                    'exit_code' => $process->getExitCode(),
                    'stdout' => $stdout,
                    'stderr' => $stderr,
                ], 500);
            }

            // --- Find the downloaded file with multiple fallbacks ---

            // A: files that start with "videoId_"
            $foundPath = null;
            $files = @scandir($videosDir) ?: [];

            foreach ($files as $f) {
                if ($f === '.' || $f === '..')
                    continue;
                if (strpos($f, "{$videoId}_") !== false) {
                    $foundPath = $videosDir . DIRECTORY_SEPARATOR . $f;
                    break;
                }
            }

            // B: any file containing the videoId
            if (!$foundPath) {
                foreach ($files as $f) {
                    if ($f === '.' || $f === '..')
                        continue;
                    if (strpos($f, $videoId) !== false) {
                        $foundPath = $videosDir . DIRECTORY_SEPARATOR . $f;
                        break;
                    }
                }
            }

            // C: newest file modified within last N seconds (10 minutes)
            if (!$foundPath) {
                $now = time();
                $candidates = [];
                foreach ($files as $f) {
                    if ($f === '.' || $f === '..')
                        continue;
                    $full = $videosDir . DIRECTORY_SEPARATOR . $f;
                    $mtime = @filemtime($full);
                    if (!$mtime)
                        continue;
                    if (($now - $mtime) < 600) {
                        $candidates[$full] = $mtime;
                    }
                }
                if (!empty($candidates)) {
                    arsort($candidates);
                    $foundPath = array_key_first($candidates);
                }
            }

            // D: parse "Destination:" out of yt-dlp stdout (some yt-dlp versions show it)
            if (!$foundPath && preg_match('/Destination:\s*(.+)$/mi', $stdout, $m)) {
                $maybe = trim($m[1]);
                // if relative path, make it absolute under videosDir
                if (!Str::startsWith($maybe, ['/', '\\'])) {
                    $maybe = $videosDir . DIRECTORY_SEPARATOR . $maybe;
                }
                if (file_exists($maybe)) {
                    $foundPath = $maybe;
                }
            }

            if (!$foundPath) {
                // Return debug for diagnosis (stdout, stderr, directory listing)
                return response()->json([
                    'error' => 'Downloaded file not found after yt-dlp completed.',
                    'debug' => [
                        'videoId' => $videoId,
                        'outPattern' => $outPattern,
                        'videosDir' => $videosDir,
                        'cmd' => $cmd,
                        'exitCode' => $process->getExitCode(),
                        'stdout' => $stdout,
                        'stderr' => $stderr,
                        'dir_files' => array_values(array_diff($files, ['.', '..'])),
                    ]
                ], 500);
            }

            // Stream the file to the client and delete it after send
            return response()->download($foundPath)->deleteFileAfterSend(true);

        } catch (ProcessFailedException $e) {
            \Log::error('yt-dlp process failed: ' . $e->getMessage());
            return response()->json(['error' => 'yt-dlp failed to download', 'details' => $e->getMessage()], 500);
        } catch (\Throwable $e) {
            \Log::error('Unexpected error in download: ' . $e->getMessage());
            return response()->json(['error' => 'Unexpected server error', 'details' => $e->getMessage()], 500);
        }
    }


    /**
     * Helper: accept either a full YouTube URL or a bare 11-char ID and return full watch URL
     */
    protected function extractYoutubeUrl(string $input): ?string
    {
        $input = trim($input);

        // If ID (11 characters typical), return full url
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) {
            return "https://www.youtube.com/watch?v={$input}";
        }

        // Try to parse v= or youtu.be short url
        if (preg_match('/v=([A-Za-z0-9_-]{11})/', $input, $m)) {
            return "https://www.youtube.com/watch?v={$m[1]}";
        }
        if (preg_match('/youtu\.be\/([A-Za-z0-9_-]{11})/', $input, $m)) {
            return "https://www.youtube.com/watch?v={$m[1]}";
        }

        // otherwise, if it looks like a url, return as-is (yt-dlp can handle many forms)
        if (filter_var($input, FILTER_VALIDATE_URL)) {
            return $input;
        }

        return null;
    }
}
