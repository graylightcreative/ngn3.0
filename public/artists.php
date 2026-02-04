<?php
$root = dirname(__DIR__);
require_once $root.'/lib/bootstrap.php';
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>NGN 2.0 · Artists (Preview)</title>
  <style>
    body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#0b1020;color:#f8fafc}
    .wrap{max-width:960px;margin:0 auto;padding:24px}
    .card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:16px}
    .muted{color:#9ca3af}
    a.btn{display:inline-block;background:#1DB954;color:#06120a;padding:8px 12px;border-radius:10px;text-decoration:none;font-weight:700}
    input{background:#0f172a;border:1px solid rgba(255,255,255,.12);border-radius:8px;color:#f8fafc;padding:8px 10px}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>Artists (NGN 2.0 Preview)</h1>
      <p class="muted">Search placeholder. Final UI behind feature flag; data via <code>/api/v1</code>.</p>
      <div style="margin:10px 0">
        <a class="btn" href="/frontend/index.php">Back</a>
      </div>
      <div style="display:flex;gap:8px;align-items:center;margin:8px 0 12px">
        <input id="q" placeholder="Search artist name…" />
        <a class="btn" href="#" id="go">Search</a>
      </div>
      <pre id="out" class="muted" style="white-space:pre-wrap"></pre>
    </div>
  </div>
<script>
(function(){
  const $ = id=>document.getElementById(id);
  $('go').addEventListener('click', async (e)=>{
    e.preventDefault();
    const q = ($('q').value||'').trim();
    const out = $('out'); out.textContent = 'Searching…';
    try{
      const r = await fetch('/api/v1/artists?search='+encodeURIComponent(q));
      const j = await r.json(); out.textContent = JSON.stringify(j?.data||j,null,2);
    }catch(err){ out.textContent = 'Error: '+(err?.message||err); }
  });
})();
</script>
</body>
</html>
