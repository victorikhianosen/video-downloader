<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>YouTube Downloader</title>

  <!-- Simple Bootstrap CDN for quick UI (optional, remove if you want pure CSS) -->
  {{-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f8f9fa; }
    .card { box-shadow: 0 2px 10px rgba(0,0,0,.03); }
    .format-note { font-size:.85rem; color:#6c757d; }
    .small-muted { font-size:.85rem; color:#6c757d; }
  </style>
</head> --}}
<body>
  <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom mb-4">
    <div class="container">
      <a class="navbar-brand" href="{{ url('/') }}">YT Downloader</a>
      <div class="ms-auto small-muted">Server: yt-dlp • Public UI — use responsibly</div>
    </div>
  </nav>

  <main class="container">
    @yield('content')
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  @yield('scripts')
</body>
</html>
