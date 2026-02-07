<?php
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);
require_once $root.'/lib/bootstrap.php';

use NGN\Lib\Env;
use NGN\Lib\Config;

if (!class_exists('NGN\\Lib\\Env')) { exit('Bootstrap failed'); }
Env::load($root);
$cfg = new Config();
include __DIR__.'/_mint_token.php';

$pageTitle = 'Migration Preview';
$currentPage = 'migration-preview';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

  <section class="max-w-7xl mx-auto px-4 py-6">
    <p class="text-sm text-gray-600 mb-3">This tool fetches legacy vs CDM counts to plan the 2.0 migration. It will try the admin endpoint first using your admin token; if that fails, it will try the maintenance alias.</p>
    <div id="status" class="text-sm text-gray-600 mb-2">Loading…</div>
    <pre id="out" class="text-xs p-3 rounded border bg-white overflow-auto mb-4">—</pre>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="p-4 rounded border bg-white">
        <div class="font-semibold mb-2">Migrate Posts → cdm_posts</div>
        <div class="grid grid-cols-2 gap-2 text-sm mb-2">
          <label class="flex items-center gap-2">Batch <input id="pBatch" type="number" value="100" class="border rounded px-2 py-1 w-full" /></label>
          <label class="flex items-center gap-2">Offset <input id="pOffset" type="number" value="0" class="border rounded px-2 py-1 w-full" /></label>
          <label class="flex items-center gap-2 col-span-2"><input id="pDry" type="checkbox" class="scale-90" checked /> Dry run</label>
        </div>
        <div class="flex gap-2 mb-2">
          <button id="btnPostsDry" class="px-3 h-9 rounded bg-gray-200">Dry‑run</button>
          <button id="btnPostsRun" class="px-3 h-9 rounded bg-emerald-600 text-white">Run</button>
          <button id="btnPostsAllDry" class="px-3 h-9 rounded bg-gray-200">Dry‑run (All)</button>
          <button id="btnPostsAllRun" class="px-3 h-9 rounded bg-emerald-700 text-white">Run All</button>
        </div>
        <pre id="outPosts" class="text-xs p-2 rounded border bg-gray-50">—</pre>
      </div>

      <div class="p-4 rounded border bg-white">
        <div class="font-semibold mb-2">Migrate Videos → cdm_media (video)</div>
        <div class="grid grid-cols-2 gap-2 text-sm mb-2">
          <label class="flex items-center gap-2">Batch <input id="vBatch" type="number" value="100" class="border rounded px-2 py-1 w-full" /></label>
          <label class="flex items-center gap-2">Offset <input id="vOffset" type="number" value="0" class="border rounded px-2 py-1 w-full" /></label>
          <label class="flex items-center gap-2 col-span-2"><input id="vDry" type="checkbox" class="scale-90" checked /> Dry run</label>
        </div>
        <div class="flex gap-2 mb-2">
          <button id="btnVideosDry" class="px-3 h-9 rounded bg-gray-200">Dry‑run</button>
          <button id="btnVideosRun" class="px-3 h-9 rounded bg-emerald-600 text-white">Run</button>
          <button id="btnVideosAllDry" class="px-3 h-9 rounded bg-gray-200">Dry‑run (All)</button>
          <button id="btnVideosAllRun" class="px-3 h-9 rounded bg-emerald-700 text-white">Run All</button>
        </div>
        <pre id="outVideos" class="text-xs p-2 rounded border bg-gray-50">—</pre>
      </div>

      <div class="p-4 rounded border bg-white">
        <div class="font-semibold mb-2">Backfill Users → user_roles (RBAC)</div>
        <div class="grid grid-cols-2 gap-2 text-sm mb-2">
          <label class="flex items-center gap-2">Batch <input id="uBatch" type="number" value="500" class="border rounded px-2 py-1 w-full" /></label>
          <label class="flex items-center gap-2">Offset <input id="uOffset" type="number" value="0" class="border rounded px-2 py-1 w-full" /></label>
          <label class="flex items-center gap-2 col-span-2"><input id="uDry" type="checkbox" class="scale-90" checked /> Dry run</label>
        </div>
        <div class="flex gap-2 mb-2">
          <button id="btnUsersDry" class="px-3 h-9 rounded bg-gray-200">Dry‑run</button>
          <button id="btnUsersRun" class="px-3 h-9 rounded bg-emerald-600 text-white">Run</button>
          <button id="btnUsersAllDry" class="px-3 h-9 rounded bg-gray-200">Dry‑run (All)</button>
          <button id="btnUsersAllRun" class="px-3 h-9 rounded bg-emerald-700 text-white">Run All</button>
        </div>
        <pre id="outUsers" class="text-xs p-2 rounded border bg-gray-50">—</pre>
      </div>
    </div>
  </div>
<?php include __DIR__.'/_token_store.php'; ?>
  <script>
    (async function(){
      const $ = id=>document.getElementById(id);
      const adminTok = (localStorage.getItem('ngn_admin_token') || localStorage.getItem('admin_token') || '').trim();
      const tryFetch = async (url, useBearer)=>{
        const opt = { headers: {} };
        if (useBearer && adminTok) opt.headers['Authorization'] = 'Bearer '+adminTok;
        const r = await fetch(url, opt);
        const ct = r.headers.get('content-type')||''; const j = ct.includes('application/json')? await r.json():null;
        return { status:r.status, json:j };
      };
      try {
        $('status').textContent = 'Calling admin endpoint…';
        let res = await tryFetch('/api/v1/admin/migration/preview', true);
        if (res.status===200) { $('out').textContent = JSON.stringify(res.json?.data||res.json, null, 2); $('status').textContent = 'OK (admin endpoint)'; return; }
        $('status').textContent = 'Admin endpoint failed ('+res.status+'), trying maintenance alias…';
        res = await tryFetch('/api/v1/maintenance/migration/preview', false);
        if (res.status===200) { $('out').textContent = JSON.stringify(res.json?.data||res.json, null, 2); $('status').textContent = 'OK (maintenance alias)'; return; }
        $('status').textContent = 'Both calls failed. See body below.';
        $('out').textContent = JSON.stringify({ admin_status:res.status, admin_body:res.json }, null, 2);
      } catch (e) {
        $('status').textContent = 'Request failed: '+(e?.message||e);
      }
    })();

    async function postJson(url, body, bearer){
      const opt = { method:'POST', headers: { 'Content-Type':'application/json' }, body: JSON.stringify(body||{}) };
      if (bearer) opt.headers['Authorization'] = 'Bearer '+bearer;
      const r = await fetch(url, opt);
      const ct = r.headers.get('content-type')||''; const j = ct.includes('application/json')? await r.json():null;
      return { status:r.status, json:j };
    }
    (function(){
      const tok = (localStorage.getItem('ngn_admin_token')||localStorage.getItem('admin_token')||'').trim();
      const outP = document.getElementById('outPosts');
      const outV = document.getElementById('outVideos');
      const outU = document.getElementById('outUsers');
      async function runPosts(dry){
        if (!tok) { outP.textContent = 'Missing admin token. Open /admin/ once.'; return; }
        const batch = parseInt(document.getElementById('pBatch').value||'100',10);
        const offset= parseInt(document.getElementById('pOffset').value||'0',10);
        outP.textContent = 'Running...';
        const r = await postJson('/api/v1/admin/migration/migrate-posts', { dry_run: dry, batch, offset }, tok);
        outP.textContent = JSON.stringify(r.json?.data||r.json, null, 2);
      }
      document.getElementById('btnPostsDry').addEventListener('click', ()=> runPosts(true));
      document.getElementById('btnPostsRun').addEventListener('click', ()=> runPosts(false));
      document.getElementById('btnPostsAllDry').addEventListener('click', async ()=>{
        if (!tok) { outP.textContent = 'Missing admin token. Open /admin/ once.'; return; }
        outP.textContent = 'Running posts DRY (all pages)...';
        const r = await postJson('/api/v1/admin/migration/migrate-posts-all', { dry_run: true, batch: 200, max_pages: 1000 }, tok);
        outP.textContent = JSON.stringify(r.json?.data||r.json, null, 2);
      });
      document.getElementById('btnPostsAllRun').addEventListener('click', async ()=>{
        if (!tok) { outP.textContent = 'Missing admin token. Open /admin/ once.'; return; }
        outP.textContent = 'Running posts RUN (all pages)...';
        const r = await postJson('/api/v1/admin/migration/migrate-posts-all', { dry_run: false, batch: 200, max_pages: 1000 }, tok);
        outP.textContent = JSON.stringify(r.json?.data||r.json, null, 2);
      });
      // the above expression ensures Run uses dry=false regardless of checkbox

      async function runVideos(dry){
        if (!tok) { outV.textContent = 'Missing admin token. Open /admin/ once.'; return; }
        const batch = parseInt(document.getElementById('vBatch').value||'100',10);
        const offset= parseInt(document.getElementById('vOffset').value||'0',10);
        outV.textContent = 'Running...';
        const r = await postJson('/api/v1/admin/migration/migrate-videos', { dry_run: dry, batch, offset }, tok);
        outV.textContent = JSON.stringify(r.json?.data||r.json, null, 2);
      }
      document.getElementById('btnVideosDry').addEventListener('click', ()=> runVideos(true));
      document.getElementById('btnVideosRun').addEventListener('click', ()=> runVideos(false));
      document.getElementById('btnVideosAllDry').addEventListener('click', async ()=>{
        if (!tok) { outV.textContent = 'Missing admin token. Open /admin/ once.'; return; }
        outV.textContent = 'Running videos DRY (all pages)...';
        const r = await postJson('/api/v1/admin/migration/migrate-videos-all', { dry_run: true, batch: 200, max_pages: 1000 }, tok);
        outV.textContent = JSON.stringify(r.json?.data||r.json, null, 2);
      });
      document.getElementById('btnVideosAllRun').addEventListener('click', async ()=>{
        if (!tok) { outV.textContent = 'Missing admin token. Open /admin/ once.'; return; }
        outV.textContent = 'Running videos RUN (all pages)...';
        const r = await postJson('/api/v1/admin/migration/migrate-videos-all', { dry_run: false, batch: 200, max_pages: 1000 }, tok);
        outV.textContent = JSON.stringify(r.json?.data||r.json, null, 2);
      });

      async function runUsers(dry){
        if (!tok) { outU.textContent = 'Missing admin token. Open /admin/ once.'; return; }
        const batch = parseInt(document.getElementById('uBatch').value||'500',10);
        const offset= parseInt(document.getElementById('uOffset').value||'0',10);
        outU.textContent = 'Running...';
        const r = await postJson('/api/v1/admin/migration/migrate-users-roles', { dry_run: dry, batch, offset }, tok);
        outU.textContent = JSON.stringify(r.json?.data||r.json, null, 2);
      }
      document.getElementById('btnUsersDry').addEventListener('click', ()=> runUsers(true));
      document.getElementById('btnUsersRun').addEventListener('click', ()=> runUsers(false));
      document.getElementById('btnUsersAllDry').addEventListener('click', async ()=>{
        if (!tok) { outU.textContent = 'Missing admin token. Open /admin/ once.'; return; }
        outU.textContent = 'Running users DRY (all pages)...';
        const r = await postJson('/api/v1/admin/migration/migrate-users-roles-all', { dry_run: true, batch: 500, max_pages: 2000 }, tok);
        outU.textContent = JSON.stringify(r.json?.data||r.json, null, 2);
      });
      document.getElementById('btnUsersAllRun').addEventListener('click', async ()=>{
        if (!tok) { outU.textContent = 'Missing admin token. Open /admin/ once.'; return; }
        outU.textContent = 'Running users RUN (all pages)...';
        const r = await postJson('/api/v1/admin/migration/migrate-users-roles-all', { dry_run: false, batch: 500, max_pages: 2000 }, tok);
        outU.textContent = JSON.stringify(r.json?.data||r.json, null, 2);
      });
    })();
  </script>
</body>
</html>
