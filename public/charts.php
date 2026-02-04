<?php
$root = dirname(__DIR__);
require_once $root.'/lib/bootstrap.php';
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>NGN 2.0 · Charts (Preview)</title>
  <style>
    body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#0b1020;color:#f8fafc}
    .wrap{max-width:960px;margin:0 auto;padding:24px}
    .card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:16px}
    .muted{color:#9ca3af}
    a.btn{display:inline-block;background:#1DB954;color:#06120a;padding:8px 12px;border-radius:10px;text-decoration:none;font-weight:700}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>Charts (NGN 2.0 Preview)</h1>
      <p class="muted">Public 2.0 placeholder. Final UI gated by feature flag; data comes from <code>/api/v1</code>.</p>
      <div style="margin:10px 0">
        <a class="btn" href="/frontend/index.php">Back</a>
      </div>
      <pre id="out" class="muted" style="white-space:pre-wrap"></pre>
    </div>
  </div>
<script>
(async function(){
  const el = document.getElementById('out');
  try{
    const r = await fetch('/api/v1/admin/charts/latest-run?chart='+encodeURIComponent('ngn:artists:weekly'), {headers:{'Authorization': localStorage.getItem('ngn_admin_token')?('Bearer '+localStorage.getItem('ngn_admin_token')):''}});
    const j = await r.json();
    el.textContent = JSON.stringify(j?.data||j,null,2);
  }catch(e){ el.textContent = 'Failed to load sample: '+(e?.message||e); }
})();
</script>
</body>
</html>
