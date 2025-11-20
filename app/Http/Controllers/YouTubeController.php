<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class YouTubeController extends Controller
{
    /**
     * Show the UI page
     */
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

        $urlNormalized = $this->extractYoutubeUrl($request->input('url'));
        if (!$urlNormalized) {
            return response()->json(['error' => 'Invalid YouTube URL or ID'], 422);
        }

        $ytDlp = env('YTDLP_PATH', '/usr/local/bin/yt-dlp');
        $oauthFlags = $this->getOauthFlags(); // may be empty array

        // Build command: [yt-dlp, ...oauthFlags..., --no-warnings, -j, url]
        $cmd = array_merge([$ytDlp], $oauthFlags, ['--no-warnings', '-j', $urlNormalized]);

        $process = new Process($cmd);
        $process->setTimeout(60);

        try {
            $process->mustRun();
            $output = $process->getOutput();
            $json = json_decode($output, true);
            if (!$json || !isset($json['formats'])) {
                return response()->json(['error' => 'Failed to parse metadata from yt-dlp', 'raw' => $output], 500);
            }

            // Filter to MP4 video formats only
            $formats = [];
            foreach ($json['formats'] as $fmt) {
                if (($fmt['ext'] ?? '') !== 'mp4') continue;
                if (($fmt['vcodec'] ?? 'none') === 'none') continue;
                if (in_array($fmt['protocol'] ?? '', ['m3u8', 'm3u8_native', 'dash', 'ism'])) continue;

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

            $meta = [
                'id' => $json['id'] ?? null,
                'title' => $json['title'] ?? null,
                'uploader' => $json['uploader'] ?? null,
                'duration' => $json['duration'] ?? null,
                'thumbnail' => $json['thumbnail'] ?? null,
            ];

            return response()->json(['meta' => $meta, 'formats' => $formats]);
        } catch (ProcessFailedException $e) {
            Log::error('yt-dlp metadata failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to get formats: ' . $e->getMessage()], 500);
        } catch (\Throwable $e) {
            Log::error('yt-dlp metadata unexpected: ' . $e->getMessage());
            return response()->json(['error' => 'Unexpected error when fetching formats', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Stream the selected format to the browser (no intermediate file).
     * Expects POST with 'url' and 'format_id'.
     */
    public function download(Request $request)
    {
        $request->validate([
            'url' => 'required|string',
            'format_id' => 'required|string',
        ]);

        $url = $request->input('url');
        $format = $request->input('format_id');

        $urlNormalized = $this->extractYoutubeUrl($url);
        if (!$urlNormalized) {
            return response('Invalid URL', 422);
        }

        $ytDlp = env('YTDLP_PATH', '/usr/local/bin/yt-dlp');
        $oauthFlags = $this->getOauthFlags();

        // Try to fetch metadata to build friendly filename (use Process here)
        try {
            $metaCmd = array_merge([$ytDlp], $oauthFlags, ['--no-warnings', '-j', $urlNormalized]);
            $metaProc = new Process($metaCmd);
            $metaProc->setTimeout(20);
            $metaProc->mustRun();
            $metaJson = json_decode($metaProc->getOutput(), true);
            $videoId = $metaJson['id'] ?? Str::random(8);
            $title = $metaJson['title'] ?? $videoId;
        } catch (\Throwable $e) {
            Log::warning('yt-dlp metadata failed before streaming: ' . $e->getMessage());
            $videoId = Str::random(8);
            $title = $videoId;
        }

        $safe = Str::slug(substr($title, 0, 100)) ?: $videoId;
        $filename = "{$videoId}_{$safe}.mp4";

        // Build command to stream to stdout
        $cmd = array_merge([$ytDlp], $oauthFlags, ['-f', $format, '--no-warnings', '--no-playlist', '--no-part', '-o', '-', $urlNormalized]);

        // Use StreamedResponse with proc_open to reduce buffering and flush quickly
        $response = new StreamedResponse(function () use ($cmd) {
            // turn off PHP buffering
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');
            @set_time_limit(0);
            ignore_user_abort(false);

            // open process
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $proc = proc_open($cmd, $descriptors, $pipes);

            if (!is_resource($proc)) {
                echo ''; // nothing to stream
                return;
            }

            // non-blocking
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $stdout = $pipes[1];
            $stderr = $pipes[2];
            $lastFlush = microtime(true);
            $clientDisconnected = false;

            while (true) {
                $read = [$stdout, $stderr];
                $write = null;
                $except = null;
                $num = @stream_select($read, $write, $except, 0, 200000);

                if ($num === false) break;

                if (is_resource($stdout) && in_array($stdout, $read, true)) {
                    $chunk = stream_get_contents($stdout);
                    if ($chunk !== false && $chunk !== '') {
                        echo $chunk;
                    }
                }

                if (is_resource($stderr) && in_array($stderr, $read, true)) {
                    $err = stream_get_contents($stderr);
                    if ($err !== false && $err !== '') {
                        Log::debug('yt-dlp stderr (stream): ' . substr($err, 0, 1000));
                    }
                }

                $now = microtime(true);
                if (($now - $lastFlush) > 0.15) {
                    @flush();
                    @ob_flush();
                    $lastFlush = $now;
                }

                $status = proc_get_status($proc);
                if (!$status['running']) {
                    // drain remaining
                    $rem = stream_get_contents($stdout);
                    if ($rem !== false && $rem !== '') {
                        echo $rem;
                    }
                    @flush();
                    @ob_flush();
                    break;
                }

                if (connection_aborted() || connection_status() !== CONNECTION_NORMAL) {
                    $clientDisconnected = true;
                    break;
                }

                usleep(100000);
            }

            // cleanup pipes/process
            if (is_resource($stdout)) @fclose($stdout);
            if (is_resource($stderr)) @fclose($stderr);

            if ($clientDisconnected) {
                @proc_terminate($proc);
                sleep(1);
                proc_close($proc);
                return;
            }

            proc_close($proc);
        });

        // force browser to save
        $response->headers->set('Content-Type', 'video/mp4');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.str_replace('"', '', $filename).'"');
        // helpful header to request no buffering in nginx
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    /**
     * Helper: return oauth flags array for yt-dlp if configured
     * If YTDLP_USE_OAUTH=true, we return ['--username','oauth2','--password','']
     * You can toggle in .env: YTDLP_USE_OAUTH=true
     */
    protected function getOauthFlags(): array
    {
        $use = env('YTDLP_USE_OAUTH', false);
        if ($use === 'true' || $use === true || $use === '1' || $use === 1) {
            return ['--username', 'oauth2', '--password', ''];
        }
        return [];
    }

    /**
     * Helper: accept either a full YouTube URL or a bare 11-char ID and return full watch URL
     */
    protected function extractYoutubeUrl(string $input): ?string
    {
        $input = trim($input);

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) {
            return "https://www.youtube.com/watch?v={$input}";
        }

        if (preg_match('/v=([A-Za-z0-9_-]{11})/', $input, $m)) {
            return "https://www.youtube.com/watch?v={$m[1]}";
        }

        if (preg_match('/youtu\.be\/([A-Za-z0-9_-]{11})/', $input, $m)) {
            return "https://www.youtube.com/watch?v={$m[1]}";
        }

        if (filter_var($input, FILTER_VALIDATE_URL)) {
            return $input;
        }

        return null;
    }
}
