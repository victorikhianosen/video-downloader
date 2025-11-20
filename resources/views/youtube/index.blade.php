<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>ArcaneStream Downloader - Sweet UI</title>

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <style>
    :root{
      --primary-accent: #00bcd4;
      --secondary-accent: #ff4081;
      --background-deep: #0f1320;
      --background-mid: #171726;
      --background-light: #222233;
      --text-light: #f0f0f0;
      --text-muted: #9fa8da;
      --shadow-glow: 0 0 18px rgba(0,188,212,0.55);
      --shadow-inset: inset 0 0 12px rgba(0,188,212,0.06);
      --transition-speed: 0.35s cubic-bezier(.25,.8,.25,1);
    }
    *{ box-sizing: border-box; margin:0; padding:0; }
    html,body{ height:100%; }
    body{
      font-family: 'Poppins', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
      background: radial-gradient(1200px 600px at 10% 10%, rgba(0,188,212,0.03), transparent),
                  linear-gradient(180deg, var(--background-deep), #0b0c11 120%);
      color: var(--text-light);
      min-height:100vh;
      display:flex;
      flex-direction:column;
    }
    body::before{
      content: '';
      position: fixed; inset:0; z-index:-1;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='0.03'/%3E%3C/svg%3E");
      pointer-events:none;
    }
    .accent-text{ color:var(--primary-accent); text-shadow:0 0 8px rgba(0,188,212,0.5); }

    /* header */
    .app-header{ background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)); border-bottom: 1px solid rgba(0,188,212,0.07); padding: 1rem 2rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; }
    .logo{ font-size:1.35rem; font-weight:800; display:flex; align-items:center; gap:.6rem; }
    .nav-links a{ color:var(--text-muted); margin-left:1.25rem; text-decoration:none; font-weight:500; }
    .nav-links a:hover{ color:var(--primary-accent); }

    /* main */
    .main-content{ flex:1; padding:3.5rem 1rem; display:flex; flex-direction:column; align-items:center; gap:2rem; }
    .card{ width:100%; max-width:980px; padding:2.25rem; border-radius:14px; background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(0,0,0,0.06)); border:1px solid rgba(0,188,212,0.06); box-shadow: 0 10px 30px rgba(0,0,0,0.6), 0 0 30px rgba(0,188,212,0.04); }
    .headline{ font-size:2.6rem; font-weight:900; text-align:center; letter-spacing:1px; background: linear-gradient(90deg, var(--primary-accent), var(--secondary-accent)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; margin-bottom:.5rem; }
    .sub-headline{ text-align:center; color:var(--text-muted); margin-bottom:1.6rem; max-width:780px; margin-left:auto; margin-right:auto; }

    .input-container{ display:flex; gap:0.9rem; max-width:760px; margin: 0 auto; }
    #video-url{ flex:1; padding:1rem 1.25rem; font-size:1rem; border-radius:10px; background:var(--background-mid); color:var(--text-light); border:1px solid rgba(0,188,212,0.07); box-shadow: var(--shadow-inset); outline:none; }
    #video-url:focus{ box-shadow: 0 6px 24px rgba(0,188,212,0.08); border-color:var(--secondary-accent); }

    .btn{ cursor:pointer; border:none; border-radius:10px; padding:0.9rem 1.25rem; font-weight:700; transition:all var(--transition-speed); }
    .btn-action{ background: linear-gradient(135deg,var(--primary-accent), #00838f); color:#071118; box-shadow: 0 8px 30px rgba(0,188,212,0.18); display:flex; align-items:center; gap:.6rem; }
    .spinner-inline{ width:18px; height:18px; border-radius:50%; border:3px solid rgba(255,255,255,0.08); border-left-color:var(--secondary-accent); animation:spin 0.9s linear infinite; display:inline-block; vertical-align:middle; }
    @keyframes spin{ 0%{transform:rotate(0)} 100%{transform:rotate(360deg)} }

    .results-wrap{ margin-top:1.25rem; display:flex; gap:1rem; flex-direction:column; }
    .video-info{ display:flex; gap:1.25rem; align-items:center; padding:1rem; border-radius:10px; background:linear-gradient(180deg, rgba(255,255,255,0.01), rgba(0,0,0,0.04)); border:1px solid rgba(0,188,212,0.04); }
    .thumbnail{ width:180px; height:110px; object-fit:cover; border-radius:8px; border:2px solid rgba(0,188,212,0.12); box-shadow: 0 0 18px rgba(0,188,212,0.06); }
    .meta-data{ color:var(--text-muted); font-size:.95rem; }
    .selection-title{ margin-top:1.6rem; text-align:center; font-size:1.1rem; color:var(--text-light); margin-bottom:1rem; }

    .format-grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:1rem; }
    .format-group{ padding:1rem; border-radius:10px; background:var(--background-mid); border:1px solid rgba(255,64,129,0.03); box-shadow:var(--shadow-inset); }
    .format-heading{ color:var(--secondary-accent); font-weight:700; margin-bottom:.75rem; font-size:.95rem; display:flex; align-items:center; gap:.5rem; }
    .btn-option{ width:100%; padding:.9rem 1rem; border-radius:8px; background:var(--background-light); border:1px solid rgba(255,255,255,0.03); color:var(--text-light); text-align:left; margin-bottom:.6rem; cursor:pointer; font-weight:600; }
    .btn-option:hover{ transform:translateY(-3px); box-shadow:var(--shadow-glow); }
    .format-note{ font-size:13px; color:var(--text-muted); margin-top:6px; display:block; }

    .formats-list-legacy .list-group-item{ display:flex; justify-content:space-between; gap:12px; padding:12px; border-radius:10px; background:linear-gradient(180deg,#151522,#191926); border:1px solid rgba(255,255,255,0.02); margin-bottom:8px; }

    /* preloader overlay */
    #global-preloader{ position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center; background: linear-gradient(180deg, rgba(0,0,0,0.45), rgba(0,0,0,0.6)); backdrop-filter: blur(4px); visibility:hidden; opacity:0; transition: all .25s ease; }
    #global-preloader.show{ visibility:visible; opacity:1; }
    .preloader-card{ padding:2rem 2.6rem; background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(0,0,0,0.06)); border-radius:12px; border:1px solid rgba(0,188,212,0.06); display:flex; gap:1rem; align-items:center; box-shadow:0 30px 80px rgba(0,0,0,0.6); }
    .preloader-spinner{ width:56px; height:56px; border-radius:50%; border:6px solid rgba(255,255,255,0.06); border-left-color:var(--primary-accent); animation:spin 0.9s linear infinite; }
    .preloader-text{ color:var(--text-muted); font-size:1rem; }

    @media (max-width:768px){ .input-container{ flex-direction:column; } .thumbnail{ width:100%; max-width:260px; height:auto; } .headline{ font-size:1.9rem; } .card{ padding:1.25rem; } }
  </style>
</head>
<body>

  <!-- Preloader overlay -->
  <div id="global-preloader" role="status" aria-hidden="true">
    <div class="preloader-card" role="presentation">
      <div class="preloader-spinner" aria-hidden="true"></div>
      <div>
        <div style="font-weight:800; color:var(--text-light); margin-bottom:6px;">Downloading...</div>
        <div class="preloader-text" id="preloader-subtext">Connecting to ArcaneStream...</div>
      </div>
    </div>
  </div>

  <!-- Header -->
  <header class="app-header">
    <div class="logo"><i class="fas fa-microchip"></i> ArcaneStream</div>
    <nav class="nav-links">
      <a href="#">Protocols</a>
      <a href="#">FAQ Matrix</a>
      <a href="#">Contact Node</a>
    </nav>
  </header>

  <main class="main-content">
    <section class="hero-section card" aria-labelledby="main-heading">
      <h1 id="main-heading" class="headline"><span class="accent-text">Streamline</span> Video Acquisition.</h1>
      <p class="sub-headline">Access high-fidelity video streams with zero latency via our downloader. Paste a YouTube URL or video ID and select a format below.</p>

      <div class="input-container" role="search">
        <input id="video-url" aria-label="YouTube URL or ID" placeholder="Paste video URL or ID..." />
        <button id="fetch-button" class="btn btn-action" title="Fetch formats">
          <i class="fas fa-link"></i>&nbsp; Sync Link
        </button>
      </div>
      <div id="loading-inline" style="margin-top:.75rem; text-align:center; color:var(--text-muted); display:none;">
        <span class="spinner-inline" aria-hidden="true"></span>&nbsp;<small id="inline-loading-text">Resolving link...</small>
      </div>
    </section>

    <!-- Results (hidden until formats loaded) -->
    <section id="results-panel" class="card results-wrap" style="display:none;">
      <div id="metaCard" class="video-info" aria-live="polite">
        <!-- default thumbnail uses uploaded file path -->
        <img id="video-thumbnail" class="thumbnail" src="/mnt/data/Screenshot from 2025-11-20 17-52-08.png" alt="thumbnail" />
        <div class="details">
          <h2 id="video-title">—</h2>
          <div id="metaUploader" class="meta-data">Uploader: —</div>
          <div id="metaId" class="meta-data" style="margin-top:.35rem;">ID: —</div>
        </div>
      </div>

      <h3 class="selection-title">Select Data Format and Resolution</h3>

      <div id="formats-ui" class="format-grid" aria-label="Available formats"></div>

      <div id="formats-legacy" class="formats-list-legacy" style="display:none; margin-top:1rem;">
        <h4 style="color:var(--text-muted); margin-bottom:.6rem;">Available formats (legacy list)</h4>
        <div id="formatsListLegacy"></div>
      </div>

      <!-- BEGIN DATA EXTRACTION button removed on purpose -->
      <div id="progress-area" style="margin-top:1rem; display:none;">
        <div style="height:12px; background:rgba(255,255,255,0.03); border-radius:8px; overflow:hidden; border:1px solid rgba(0,188,212,0.04);">
          <div id="progress-fill" style="height:100%; width:0%; background:linear-gradient(90deg,#1de9b6,#00e5ff); transition:width .35s;"></div>
        </div>
        <p id="progress-text" class="meta-data" style="margin-top:.6rem;">Decrypting protocol... 0%</p>
      </div>
    </section>

  </main>

  <footer class="app-footer">
    <p>&copy; 2025 ArcaneStream Technologies | <a style="color:var(--text-muted); text-decoration:underline;" href="#">Security Manifest</a></p>
  </footer>

  <script>
  (function(){
    const formatsRoute = '{{ route("youtube.formats") }}';
    const downloadRoute = '{{ route("youtube.download") }}';
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    const videoUrl = document.getElementById('video-url');
    const fetchButton = document.getElementById('fetch-button');
    const loadingInline = document.getElementById('loading-inline');
    const inlineLoadingText = document.getElementById('inline-loading-text');
    const resultsPanel = document.getElementById('results-panel');
    const thumbEl = document.getElementById('video-thumbnail');
    const titleEl = document.getElementById('video-title');
    const uploaderEl = document.getElementById('metaUploader');
    const idEl = document.getElementById('metaId');
    const formatsUI = document.getElementById('formats-ui');
    const formatsLegacy = document.getElementById('formats-legacy');
    const formatsListLegacy = document.getElementById('formatsListLegacy');
    const preloader = document.getElementById('global-preloader');
    const preloaderSubtext = document.getElementById('preloader-subtext');

    function showPreloader(text){
      if(!preloader) return;
      preloaderSubtext && (preloaderSubtext.textContent = text || 'Preparing download...');
      preloader.classList.add('show');
      preloader.style.visibility = 'visible';
      preloader.style.opacity = '1';
      preloader.setAttribute('aria-hidden','false');
    }
    function hidePreloader(){
      if(!preloader) return;
      preloader.classList.remove('show');
      preloader.style.visibility = 'hidden';
      preloader.style.opacity = '0';
      preloader.setAttribute('aria-hidden','true');
    }

    function showInlineLoading(text){
      if(!loadingInline) return;
      inlineLoadingText.textContent = text || 'Resolving link...';
      loadingInline.style.display = 'block';
    }
    function hideInlineLoading(){
      if(!loadingInline) return;
      loadingInline.style.display = 'none';
    }

    function toMB(bytes){
      if(!bytes || isNaN(bytes)) return '';
      const mb = bytes / (1024*1024);
      return (Math.round(mb * 100) / 100) + ' MB';
    }

    async function postJson(url, data){
      return fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json'
        },
        body: JSON.stringify(data)
      });
    }

    // Build UI: group video/audio; each item click initiates immediate download (no confirmation)
    function buildFormatsUI(formats){
      formatsUI.innerHTML = '';
      formatsListLegacy.innerHTML = '';
      formatsLegacy.style.display = 'none';

      if(!Array.isArray(formats) || formats.length === 0){
        formatsLegacy.style.display = 'block';
        formatsListLegacy.innerHTML = '<div class="meta-data">No formats found.</div>';
        return;
      }

      // Sort similar to original
      formats.sort((a,b)=>{
        const score = f => ((f.vcodec && f.vcodec!=='none') && (f.acodec && f.acodec!=='none') ? 1000 : 0)
                          + (f.height||0) + ((f.filesize||0)/1000000 || 0);
        return score(b) - score(a);
      });

      // Legacy list (also immediate)
      for(const f of formats){
        const item = document.createElement('div');
        item.className = 'list-group-item';
        const left = document.createElement('div');
        left.innerHTML = `<div><strong>${f.format_id}</strong> — ${f.ext || ''} ${f.resolution || (f.height ? f.height+'p':'' )} ${f.format_note ? '('+f.format_note+')':''}</div>
                          <div class="format-note">v:${f.vcodec||'none'} a:${f.acodec||'none'} ${f.fps?f.fps+'fps':''} • ${f.filesize? toMB(f.filesize) : ''}</div>`;
        const right = document.createElement('div');
        const btn = document.createElement('button');
        btn.className = 'px-3 py-1 rounded-md bg-gradient-to-br from-arc-cyan to-[#00838f] text-black font-bold';
        btn.textContent = 'Download';
        btn.addEventListener('click', ()=> initiateDownload(f.format_id, videoUrl.value.trim()));
        right.appendChild(btn);
        item.appendChild(left);
        item.appendChild(right);
        formatsListLegacy.appendChild(item);
      }

      const videoFormats = formats.filter(f => f.vcodec && f.vcodec !== 'none');
      const audioFormats = formats.filter(f => !f.vcodec || f.vcodec === 'none');

      function createGroup(titleHtml, arr){
        const group = document.createElement('div');
        group.className = 'format-group';
        const h = document.createElement('div');
        h.className = 'format-heading';
        h.innerHTML = titleHtml;
        group.appendChild(h);

        arr.forEach(f => {
          const btn = document.createElement('button');
          btn.className = 'btn-option';
          btn.type = 'button';
          btn.dataset.formatId = f.format_id || (f.format || f.itag) || '';
          btn.dataset.ext = f.ext || '';
          btn.dataset.res = f.format_note || f.resolution || (f.height ? (f.height + 'p') : '');
const mbSize = f.filesize ? (f.filesize / 1024 / 1024).toFixed(2) + ' MB' : '';

btn.innerHTML = `
  <div class="text-lg font-semibold">${btn.dataset.ext.toUpperCase()} ${btn.dataset.res}</div>
  <div class="format-note">
    v:${btn.dataset.vcodec || 'none'}
    a:${btn.dataset.acodec || 'none'}
    ${f.fps ? f.fps + 'fps' : ''}
    ${mbSize ? '• ' + mbSize : ''}
  </div>
`;


          // immediate download on click (no confirm)
          btn.addEventListener('click', ()=> {
            document.querySelectorAll('.btn-option').forEach(x => x.classList.remove('selected'));
            btn.classList.add('selected');
            initiateDownload(btn.dataset.formatId, videoUrl.value.trim());
          });

          group.appendChild(btn);
        });

        return group;
      }

      if(videoFormats.length) formatsUI.appendChild(createGroup('<i class="fas fa-video"></i> Video Stream (MP4/WebM)', videoFormats));
      if(audioFormats.length) formatsUI.appendChild(createGroup('<i class="fas fa-waveform-path"></i> Audio (MP3/M4A)', audioFormats));
    }

    // Submit hidden form to start server download; show preloader until form.submit() returns control or fallback
    function initiateDownload(formatId, url){
      if(!formatId || !url){
        alert('Missing URL or format');
        return;
      }

      showPreloader('Preparing server transfer...');

      const form = document.createElement('form');
      form.method = 'POST';
      form.action = downloadRoute;
      form.style.display = 'none';

      const token = document.createElement('input'); token.type='hidden'; token.name='_token'; token.value = csrfToken; form.appendChild(token);
      const urlInput = document.createElement('input'); urlInput.type='hidden'; urlInput.name='url'; urlInput.value = url; form.appendChild(urlInput);
      const fmt = document.createElement('input'); fmt.type='hidden'; fmt.name='format_id'; fmt.value = formatId; form.appendChild(fmt);

      document.body.appendChild(form);

      const fallback = setTimeout(()=> { hidePreloader(); }, 30000);

      form.submit();

      setTimeout(()=> { hidePreloader(); clearTimeout(fallback); }, 2000);
    }

    // Fetch formats button
    fetchButton.addEventListener('click', async ()=>{
      const input = (videoUrl.value || '').trim();
      if(!input){ alert('Enter a YouTube URL or ID'); return; }

      showInlineLoading('Loading formats...');
      resultsPanel.style.display = 'none';
      formatsUI.innerHTML = '';
      formatsLegacy.style.display = 'none';
      formatsListLegacy.innerHTML = '';

      try {
        const res = await postJson(formatsRoute, { url: input });
        if(!res.ok){
          let body; try { body = await res.json(); } catch(e){ body = { error:'Unknown error' }; }
          hideInlineLoading();
          alert('Error fetching formats: ' + (body.error || 'Server returned an error'));
          return;
        }
        const data = await res.json();

        const meta = data.meta || {};
        titleEl.textContent = meta.title || (meta.fulltitle || 'Untitled');
        uploaderEl.textContent = meta.uploader ? ('Uploader: ' + meta.uploader) : 'Uploader: —';
        idEl.textContent = meta.id ? ('ID: ' + meta.id) : 'ID: —';
        if(meta.thumbnail) thumbEl.src = meta.thumbnail;

        if(Array.isArray(data.formats)) buildFormatsUI(data.formats);
        else buildFormatsUI(data.formats || []);

        resultsPanel.style.display = '';
        hideInlineLoading();
      } catch(err){
        console.error(err);
        hideInlineLoading();
        alert('Unexpected error: ' + (err && err.message ? err.message : 'unknown'));
      }
    });

    function showInlineLoading(text){ inlineLoadingText.textContent = text || 'Resolving link...'; loadingInline.style.display = 'block'; }
    function hideInlineLoading(){ loadingInline.style.display = 'none'; }

    async function postJson(url, data){
      return fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type':'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept':'application/json'
        },
        body: JSON.stringify(data)
      });
    }

    // allow enter to fetch
    videoUrl.addEventListener('keydown', (e)=> { if(e.key === 'Enter'){ e.preventDefault(); fetchButton.click(); } });

  })();
  </script>
</body>
</html>
