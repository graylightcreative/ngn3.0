<?php
$root = dirname(__DIR__);
require_once $root.'/lib/bootstrap.php';
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>NGN 2.0 · Stations (Preview)</title>
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
      <h1>Stations (NGN 2.0 Preview)</h1>
      <p class="muted">Directory/feeds placeholder. Public 2.0 view is gated by feature flag.</p>
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
    // Example parity probe from admin verify for last 7d window (read requires admin token)
    const until = new Date(); const since = new Date(until.getTime()-7*24*3600*1000);
    const s = since.toISOString().slice(0,10); const u = until.toISOString().slice(0,10);
    const r = await fetch('/api/v1/admin/verify/spins-parity', { method:'POST', headers:{ 'Content-Type':'application/json', 'Authorization': localStorage.getItem('ngn_admin_token')?('Bearer '+localStorage.getItem('ngn_admin_token')):'' }, body: JSON.stringify({ since:s, until:u, source_conn:'ngnspins', source_table:'spindata' }) });
    const j = await r.json(); el.textContent = JSON.stringify(j?.data||j,null,2);
  }catch(e){ el.textContent = 'Failed to load sample: '+(e?.message||e); }
})();
</script>
</body>
</html>
