@extends('layouts.app')

@section('content')
<div class="container">
    <h1>YouTube Downloader (owner-only)</h1>

    @auth
        @php
            $connected = \App\Models\GoogleToken::where('user_id', auth()->id())->exists();
        @endphp

        @if(!$connected)
            <a href="{{ route('google.connect') }}" class="btn btn-primary">Connect Google / Link YouTube</a>
        @else
            <form method="POST" action="{{ route('google.unlink') }}">
                @csrf
                <button class="btn btn-warning">Unlink Google</button>
            </form>
        @endif
    @else
        <a href="{{ route('login') }}" class="btn btn-primary">Login to connect Google</a>
    @endauth

    <hr/>

    <form id="videoForm" method="GET" action="{{ url('/youtube/video') }}">
        <label>Video ID or URL</label>
        <input type="text" id="videoInput" class="form-control" placeholder="e.g. https://www.youtube.com/watch?v=VIDEOID or VIDEOID" />
        <button id="infoBtn" type="button" class="btn btn-outline-info mt-2">Get Info</button>
    </form>

    <div id="videoInfo" class="mt-3" style="display:none;">
        <h3 id="vtitle"></h3>
        <p id="vchannel"></p>
        <p><strong>License:</strong> <span id="vlicense"></span></p>

        <form id="downloadForm" method="POST" action="">
            @csrf
            <button id="downloadBtn" type="button" class="btn btn-success">Download (owner-only)</button>
        </form>
    </div>

</div>

<script>
    function extractId(input) {
        const urlRegex = /v=([A-Za-z0-9_-]{11})/;
        const shortRegex = /youtu\.be\/([A-Za-z0-9_-]{11})/;
        let m;
        if ((m = urlRegex.exec(input))) return m[1];
        if ((m = shortRegex.exec(input))) return m[1];
        if (input.length === 11) return input;
        return null;
    }

    document.getElementById('infoBtn').addEventListener('click', async () => {
        const val = document.getElementById('videoInput').value.trim();
        const id = extractId(val);
        if (!id) { alert('Invalid ID / URL'); return; }

        const res = await fetch(`/youtube/video/${id}`);
        if (!res.ok) { alert('Video not found'); return; }
        const data = await res.json();
        document.getElementById('vtitle').innerText = data.title;
        document.getElementById('vchannel').innerText = data.channelTitle + ' (' + data.channelId + ')';
        document.getElementById('vlicense').innerText = data.license || 'youtube';
        document.getElementById('videoInfo').style.display = 'block';
        document.getElementById('downloadForm').action = `/youtube/download/${id}`;
    });

    document.getElementById('downloadBtn').addEventListener('click', async () => {
        if (!confirm('Confirm you own this video and have permission to download it.')) return;
        const form = document.getElementById('downloadForm');
        const url = form.action;
        const token = document.querySelector('input[name="_token"]').value;

        const res = await fetch(url, {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': token}
        });

        if (res.status === 200) {
            // response is a file download; navigator may handle it automatically based on headers
            const blob = await res.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            // attempt to get filename from response headers (fallback)
            let filename = 'video.mp4';
            const disposition = res.headers.get('content-disposition');
            if (disposition && disposition.indexOf('filename=') !== -1) {
                filename = disposition.split('filename=')[1].replace(/"/g, '');
            }
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(url);
        } else {
            const j = await res.json();
            alert('Error: ' + (j.error || 'Download failed'));
        }
    });
</script>
@endsection
