<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/_guard.php';
$root = dirname(__DIR__, 2);
// require_once $root.'/lib/bootstrap.php';

use NGN\Lib\Env;
use NGN\Lib\Config;

if (!class_exists('NGN\\Lib\\Env')) { exit('Bootstrap failed'); }
Env::load($root);
$cfg = new Config();

@header('Cache-Control: no-store, no-cache, must-revalidate');
@header('Pragma: no-cache');
@header('Expires: 0');

include __DIR__.'/_mint_token.php';

$pageTitle = 'API Keys';
$currentPage = 'keys';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

      <section class="max-w-7xl mx-auto px-4 py-6 space-y-6">
        <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
          <div class="flex items-center justify-between">
            <div class="text-sm text-gray-500 dark:text-gray-400">Environment</div>
            <div id="envPath" class="text-sm"></div>
          </div>
          <div id="msg" class="mt-2 text-sm text-gray-500 dark:text-gray-400"></div>
        </div>

        <div id="form" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
      </section>
    </main>
  </div>

<?php include __DIR__.'/_token_store.php'; ?>
  <script>
    const api = p => `/api/v1${p}`;
    const token = () => localStorage.getItem('ngn_admin_token') || localStorage.getItem('admin_token') || '';
    const auth = () => token() ? { 'Authorization': 'Bearer ' + token() } : {};

    let schema = {}; let values = {}; let keys = [];

    function fieldRow(k, meta, val){
      const isSecret = (meta.type === 'secret');
      const id = `key_${k}`;
      const masked = val?.masked || '';
      const has = !!val?.has_value;
      return `<div class="rounded border border-gray-200 dark:border-white/10 p-3 bg-white/70 dark:bg-white/5">
        <label class="text-xs text-gray-500 dark:text-gray-400">${k}${isSecret? ' (secret)':''}</label>
        <div class="mt-1 flex gap-2 items-center">
          <input id="${id}" type="${isSecret ? 'password':'text'}" class="flex-1 rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" placeholder="${isSecret? (has? masked:''): ''}" />
          ${isSecret? `<button data-reveal="${id}" class="px-3 h-10 rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 text-sm">Reveal</button>`:''}
        </div>
        <div id="err_${id}" class="mt-1 text-xs text-red-500"></div>
      </div>`;
    }

    function render(){
      const container = document.getElementById('form');
      container.innerHTML = keys.map(k => fieldRow(k, schema[k]||{type:'text'}, values[k]||{})).join('');
      container.querySelectorAll('button[data-reveal]').forEach(btn=>{
        btn.addEventListener('click', (e)=>{
          const id = btn.getAttribute('data-reveal');
          const inp = document.getElementById(id);
          inp.type = inp.type === 'password' ? 'text' : 'password';
        });
      });
    }

    async function loadMeta(){
      const res = await fetch(api('/admin/keys/meta'), { headers: { ...auth() } });
      const j = await res.json();
      if (res.status!==200){ document.getElementById('msg').textContent = 'Error ' + res.status; return; }
      schema = j?.data?.schema || {}; values = j?.data?.values || {}; keys = j?.data?.keys || [];
      document.getElementById('envPath').textContent = j?.data?.env_path || '';
      render();
    }

    async function validate(){
      const payload = {};
      keys.forEach(k=>{ const v = document.getElementById('key_'+k)?.value; if (v) payload[k]=v; });
      const res = await fetch(api('/admin/keys/validate'), { method: 'POST', headers: { 'Content-Type':'application/json', ...auth() }, body: JSON.stringify(payload) });
      const j = await res.json();
      if (res.status===200){ document.getElementById('msg').innerHTML = '<span class="text-emerald-500">Validation passed</span>'; clearErrors(); return true; }
      showErrors(j?.errors||[]); return false;
    }

    async function save(){
      const ok = await validate(); if (!ok) return;
      const payload = {}; keys.forEach(k=>{ const v = document.getElementById('key_'+k)?.value; if (v!==undefined && v!=='') payload[k]=v; });
      const res = await fetch(api('/admin/keys'), { method: 'PUT', headers: { 'Content-Type':'application/json', ...auth() }, body: JSON.stringify(payload) });
      const j = await res.json();
      if (res.status===200){ document.getElementById('msg').innerHTML = '<span class="text-emerald-500">Saved</span>'; await loadMeta(); }
      else { showErrors(j?.errors||[]); }
    }

    function clearErrors(){ keys.forEach(k=>{ const el = document.getElementById('err_key_'+k); if (el) el.textContent='';}); }
    function showErrors(errors){
      document.getElementById('msg').innerHTML = '<span class="text-red-500">Validation failed</span>';
      errors.forEach(e=>{ const id = 'err_key_'+(e.field||''); const el = document.getElementById(id); if (el) el.textContent = e.message || e.code; });
    }

    document.getElementById('validateBtn').addEventListener('click', validate);
    document.getElementById('saveBtn').addEventListener('click', save);

    loadMeta();
  </script>
</body>
</html>
