<?php
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);
require_once $root.'/lib/bootstrap.php';

use NGN\Lib\Env;
use NGN\Lib\Config;

if (!class_exists('NGN\\Lib\\Env')) { exit('Bootstrap failed'); }
Env::load($root);
$cfg = new Config();

@header('Cache-Control: no-store, no-cache, must-revalidate');
@header('Pragma: no-cache');
@header('Expires: 0');

include __DIR__.'/_mint_token.php';

$pageTitle = 'Environment';
$currentPage = 'env';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

      <section class="max-w-7xl mx-auto px-4 py-6 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Environment</div>
            <div id="envInfo" class="text-sm">—</div>
          </div>
          <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Result</div>
            <div id="msg" class="text-sm">—</div>
          </div>
          <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Tips</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Backups are created on save (.env.bak-YYYYMMDDHHMMSS-*). After saving, reload PHP-FPM/OPcache to propagate across workers.</div>
          </div>
        </div>

        <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
          <div class="flex items-center justify-between mb-2">
            <div class="text-sm text-gray-500 dark:text-gray-400">Active .env</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Editing raw file; ensure one KEY=VALUE per line.</div>
          </div>
          <textarea id="content" rows="28" class="w-full font-mono text-xs rounded border border-gray-300 dark:border-white/10 bg-white dark:bg-[#0a0f1e] p-3" spellcheck="false"></textarea>
        </div>
      </section>
    </main>
  </div>

<?php include __DIR__.'/_token_store.php'; ?>
  <script>
    const api = p => `/api/v1${p}`;
    const token = () => localStorage.getItem('ngn_admin_token') || localStorage.getItem('admin_token') || '';
    const auth = () => token() ? { 'Authorization': 'Bearer ' + token() } : {};

    async function loadEnv(){
      const res = await fetch(api('/admin/env'), { headers: { ...auth() } });
      const j = await res.json().catch(()=>({}));
      if (res.status!==200){ document.getElementById('msg').innerHTML = '<span class="text-red-500">Error '+res.status+'</span>'; return; }
      const d = j?.data || {};
      const path = d.env_path || '';
      const mtime = d.env_mtime ? new Date(d.env_mtime*1000).toLocaleString() : '';
      document.getElementById('envInfo').innerHTML = `<div>Path: <span class='font-mono'>${path}</span></div><div>Mtime: ${mtime}</div>`;
      document.getElementById('content').value = d.content || '';
      document.getElementById('msg').textContent = 'Loaded';
    }

    function basicValidate(text){
      const lines = (text||'').split(/\n/);
      const errs = [];
      for (let i=0;i<lines.length;i++){
        const t = lines[i].trim();
        if (!t || t.startsWith('#')) continue;
        if (!t.includes('=')) errs.push('Line '+(i+1)+': missing =');
        // disallow spaces around key
        const k = t.split('=')[0];
        if (!/^[-A-Z0-9_]+$/.test(k)) errs.push('Line '+(i+1)+': invalid key '+k);
      }
      return errs;
    }

    async function validate(){
      const text = document.getElementById('content').value;
      const errs = basicValidate(text);
      if (errs.length){ document.getElementById('msg').innerHTML = '<span class="text-red-500">'+errs[0]+'</span>'; return false; }
      document.getElementById('msg').innerHTML = '<span class="text-emerald-500">Looks valid</span>';
      return true;
    }

    async function save(){
      const ok = await validate(); if (!ok) return;
      const text = document.getElementById('content').value;
      document.getElementById('msg').textContent = 'Saving…';
      const res = await fetch(api('/admin/env'), { method: 'PUT', headers: { 'Content-Type':'application/json', ...auth() }, body: JSON.stringify({ content: text }) });
      const j = await res.json().catch(()=>({}));
      if (res.status===200){
        const d = j?.data || {};
        document.getElementById('msg').innerHTML = '<span class="text-emerald-500">Saved</span> · checksum ' + (d.checksum||'');
        loadEnv();
      } else {
        const msg = j?.errors?.[0]?.message || ('Error '+res.status);
        document.getElementById('msg').innerHTML = '<span class="text-red-500">'+msg+'</span>';
      }
    }

    async function resetOpcache(){
      const btn = document.getElementById('opcacheBtn');
      const out = document.getElementById('opcacheMsg');
      if (btn) btn.disabled = true;
      if (out) out.textContent = 'Resetting…';
      try {
        const res = await fetch(api('/admin/opcache/reset'), { method: 'POST', headers: { 'Content-Type': 'application/json', ...auth() } });
        const j = await res.json().catch(()=>({}));
        if (res.status === 200 && j?.data?.available){
          if (j?.data?.reset) { out.innerHTML = '<span class="text-emerald-600">OPcache reset OK</span>'; }
          else { out.innerHTML = '<span class="text-red-600">Reset call failed</span>'; }
        } else if (res.status === 403) {
          out.innerHTML = '<span class="text-red-600">Forbidden (dev-only)</span>';
        } else {
          out.innerHTML = '<span class="text-red-600">Not available</span>';
        }
      } catch(e){ out.innerHTML = '<span class="text-red-600">Error</span>'; }
      if (btn) btn.disabled = false;
    }

    document.getElementById('validateBtn').addEventListener('click', validate);
    document.getElementById('saveBtn').addEventListener('click', save);
    const opBtn = document.getElementById('opcacheBtn');
    if (opBtn) { opBtn.addEventListener('click', resetOpcache); }

    loadEnv();
  </script>
</body>
</html>
