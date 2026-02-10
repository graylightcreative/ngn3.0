<?php
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);
// require_once $root.'/lib/bootstrap.php';

use NGN\Lib\Env;
use NGN\Lib\Config;
use NGN\Lib\Auth\TokenService;

if (!class_exists('NGN\\Lib\\Env')) { exit('Bootstrap failed'); }
Env::load($root);
$cfg = new Config();
$env = $cfg->appEnv();
$featureAdmin = $cfg->featureAdmin();

@header('Cache-Control: no-store, no-cache, must-revalidate');
@header('Pragma: no-cache');
@header('Expires: 0');

// Auto-mint admin token - if we got past _guard.php, we're authorized
$mintedToken = null;
$expires = null;
try {
    $svc = new TokenService($cfg);
    $sub = !empty($_SESSION['User']['Email']) ? (string)$_SESSION['User']['Email'] : 'admin@session';
    $issued = $svc->issueAccessToken(['sub' => $sub, 'role' => 'admin']);
    $mintedToken = $issued['token'] ?? null;
    $expires = (int)($issued['expires_in'] ?? 0);
} catch (\Throwable $e) {
    error_log('Token mint failed: ' . $e->getMessage());
}

$pageTitle = 'Settings';
$currentPage = 'settings';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

      <section class="max-w-7xl mx-auto px-4 py-6 space-y-6">
        <?php if (!$featureAdmin || $env !== 'development'): ?>
          <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
            <div class="text-sm text-red-600 dark:text-red-400 font-semibold mb-2">Admin not enabled</div>
            <div class="text-sm text-gray-600 dark:text-gray-300">Set APP_ENV=development and FEATURE_ADMIN=true in .env, then reload PHP-FPM/OPcache.</div>
          </div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">API Status</div>
            <div id="apiStatus" class="text-base">Checking…</div>
          </div>
          <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Environment</div>
            <div id="envInfo" class="text-base">—</div>
          </div>
          <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Save Result</div>
            <div id="saveResult" class="text-base">—</div>
          </div>
        </div>

        <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
          <div class="flex items-center justify-between mb-3">
            <div class="font-semibold">Settings</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Changes write to .env and may require PHP-FPM reload.</div>
          </div>
          <div id="authAlert" class="hidden mb-3 rounded border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800"></div>
          <div id="loginBlock" class="mb-3 rounded border border-blue-300 bg-blue-50 p-3 text-sm text-blue-800">
            <div class="font-medium mb-2">API Login</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <div>
                <label class="block text-xs text-gray-600">Email</label>
                <input id="apiLoginEmail" type="email" class="mt-1 w-full rounded border border-gray-300 px-2 h-9" placeholder="admin@example.com" />
              </div>
              <div>
                <label class="block text-xs text-gray-600">Password</label>
                <input id="apiLoginPassword" type="password" class="mt-1 w-full rounded border border-gray-300 px-2 h-9" placeholder="••••••••" />
              </div>
            </div>
            <div class="mt-2 flex items-center gap-2">
              <button id="apiLoginBtn" class="inline-flex items-center px-3 h-9 rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 text-sm">Login</button>
              <span id="apiLoginErr" class="text-sm text-red-600"></span>
            </div>
            <p class="mt-2 text-xs text-gray-600">This authenticates against your legacy users table. Only admins (RoleId=1) are allowed.</p>
          </div>
          <form id="settingsForm" class="grid grid-cols-1 md:grid-cols-2 gap-4"></form>
          <div class="mt-4 flex items-center gap-3">
            <button id="validateBtn" class="inline-flex items-center px-3 h-9 rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 text-sm">Validate</button>
            <button id="saveBtn" class="inline-flex items-center px-3 h-9 rounded bg-brand text-white text-sm">Save</button>
            <div id="statusMsg" class="text-sm text-gray-500 dark:text-gray-400">Ready</div>
          </div>
          <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Tip: after saving, reload PHP-FPM/clear OPcache across workers for values to propagate.</p>
        </div>
        <?php endif; ?>
      </section>
    </main>
  </div>

  <script>
    const api = p => `/api/v1${p}`;
    const minted = <?php echo $mintedToken ? 'true' : 'false'; ?>;
    const mintedToken = <?php echo $mintedToken ? json_encode($mintedToken) : 'null'; ?>;

    if (minted && mintedToken) {
      try {
        localStorage.setItem('ngn_admin_token', mintedToken);
        localStorage.setItem('admin_token', mintedToken);
        // Also set same-origin cookie for API fallback
        var cookie = 'NGN_ADMIN_BEARER=' + encodeURIComponent(mintedToken) + '; Path=/; SameSite=Lax';
        if (location.protocol === 'https:') cookie += '; Secure';
        document.cookie = cookie;
      } catch (e) {}
    }

    const token = () => localStorage.getItem('ngn_admin_token')
      || localStorage.getItem('admin_token')
      || localStorage.getItem('ngn_access_token')
      || '';
    const authHeader = () => token() ? { 'Authorization': 'Bearer ' + token() } : {};

    function parseJwtPayload(t){
      try { const parts = t.split('.'); if (parts.length<2) return null; const json = atob(parts[1].replace(/-/g,'+').replace(/_/g,'/')); return JSON.parse(decodeURIComponent(escape(json))); } catch(e){ return null; }
    }
    function getRolesFromToken(){
      const t = token(); if (!t) return [];
      const p = parseJwtPayload(t) || {};
      if (Array.isArray(p.roles) && p.roles.length) return p.roles.map(String);
      if (typeof p.role === 'string' && p.role) return [p.role];
      return [];
    }
    function renderRolesBadge(){
      const el = document.getElementById('rolesBadge'); if (!el) return;
      const roles = getRolesFromToken();
      if (!roles.length) { el.classList.add('hidden'); return; }
      el.textContent = 'Roles: ' + roles.join(', ');
      el.classList.remove('hidden');
    }

    renderRolesBadge();

    function showAuthAlert(msg){
      const el = document.getElementById('authAlert');
      if (!el) return;
      el.textContent = msg;
      el.classList.remove('hidden');
    }

    async function resetOpcache(){
      const btn = document.getElementById('opcacheBtn');
      const out = document.getElementById('opcacheMsg');
      if (btn) btn.disabled = true;
      if (out) out.textContent = 'Resetting…';
      try {
        const res = await fetch(api('/admin/opcache/reset'), { method: 'POST', headers: { 'Content-Type': 'application/json', ...authHeader() } });
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

    const opBtn = document.getElementById('opcacheBtn');
    if (opBtn) { opBtn.addEventListener('click', resetOpcache); }

    function hasToken(){ return !!token(); }

    async function checkAdminRole(){
      if (!hasToken()) return false;
      try {
        const r = await fetch(api('/me'), { headers: { ...authHeader() } });
        if (r.status !== 200) return false;
        const j = await r.json();
        const data = j?.data || j || {};
        const role = (data.role || '').toString().toLowerCase();
        return role === 'admin';
      } catch (_) { return false; }
    }

    async function requireAuthOrWarn(context){
      const isAdmin = await checkAdminRole();
      if (isAdmin) return true;
      showAuthAlert('Admin token missing or invalid. Use the API Login below with your legacy admin credentials.');
      const status = document.getElementById('statusMsg'); if (status) status.textContent = `Authorization required${context? ' · '+context : ''}`;
      const vb = document.getElementById('validateBtn'); if (vb) vb.disabled = true;
      const sb = document.getElementById('saveBtn'); if (sb) sb.disabled = true;
      return false;
    }

    function renderKV(el, obj){
      if (!el) return;
      el.innerHTML = '';
      const list = document.createElement('div');
      list.className = 'grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1';
      Object.entries(obj||{}).forEach(([k,v])=>{
        const row = document.createElement('div');
        row.innerHTML = `<span class="text-sm text-gray-500 dark:text-gray-400">${k}</span> <span class="ml-2 text-sm font-medium">${v ?? ''}</span>`;
        list.appendChild(row);
      });
      el.appendChild(list);
    }

    async function checkHealth(){
      try {
        const r = await fetch(api('/health'));
        const j = await r.json();
        const env = j?.meta?.env || '';
        const ver = j?.data?.version || '';
        document.getElementById('apiStatus').innerHTML = r.status===200 ? `OK · ${env} · v${ver}` : `Error ${r.status}`;
      } catch (e) { document.getElementById('apiStatus').textContent = 'Error'; }
    }

    function createInput(name, spec, value){
      const wrap = document.createElement('div');
      wrap.className = 'flex flex-col';
      const label = document.createElement('label');
      label.className = 'text-xs text-gray-500 dark:text-gray-400';
      label.textContent = name + (spec.hint ? ` (${spec.hint})` : '');
      const err = document.createElement('div');
      err.className = 'mt-1 text-xs text-red-500 hidden';
      err.dataset.errFor = name;
      let control;
      if (spec.type === 'bool') {
        control = document.createElement('input');
        control.type = 'checkbox';
        control.className = 'mt-1 h-4 w-4';
        control.checked = ['true', true, '1', 1, 'on', 'yes'].includes(value);
      } else if (spec.type === 'enum') {
        control = document.createElement('select');
        control.className = 'mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10';
        (spec.enum||[]).forEach(opt=>{ const o=document.createElement('option'); o.value=opt; o.textContent=opt; control.appendChild(o); });
        control.value = (value||'').toString();
      } else {
        control = document.createElement('input');
        control.type = (spec.type === 'int') ? 'number' : 'text';
        control.className = 'mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10';
        control.value = (value ?? '').toString();
        if (spec.type === 'int') { if (spec.min!=null) control.min = spec.min; if (spec.max!=null) control.max = spec.max; }
      }
      control.name = name;
      wrap.appendChild(label);
      wrap.appendChild(control);
      wrap.appendChild(err);
      return wrap;
    }

    function collectForm(form){
      const data = {};
      Array.from(form.elements).forEach(el=>{
        if (!el.name) return;
        if (el.type === 'checkbox') data[el.name] = el.checked; else data[el.name] = el.value;
      });
      return data;
    }

    function showErrors(form, errors){
      const map = {};
      (errors||[]).forEach(e=>{ if (e.field) map[e.field] = e.message || e.code; });
      form.querySelectorAll('[data-err-for]').forEach(el=>{
        const f = el.dataset.errFor;
        const msg = map[f];
        if (msg) { el.textContent = msg; el.classList.remove('hidden'); }
        else { el.textContent=''; el.classList.add('hidden'); }
      });
    }

    async function loadMetaAndValues(){
      if (!(await requireAuthOrWarn('load settings'))) return;
      const form = document.getElementById('settingsForm');
      form.innerHTML = '';
      const [metaRes, getRes] = await Promise.all([
        fetch(api('/admin/settings/meta'), { headers: { ...authHeader() } }),
        fetch(api('/admin/settings'), { headers: { ...authHeader() } }),
      ]);
      const meta = (await metaRes.json())?.data || {};
      const vals = (await getRes.json())?.data || {};
      const schema = meta.schema || {};
      const values = vals.settings || meta.values || {};
      const envPath = vals.env_path || meta.env_path || '';
      const envMtime = vals.env_mtime ? new Date(vals.env_mtime*1000).toLocaleString() : '';
      renderKV(document.getElementById('envInfo'), { path: envPath, mtime: envMtime });
      Object.keys(schema).forEach(k=>{ form.appendChild(createInput(k, schema[k], values[k])); });
    }

    async function validateSettings(){
      if (!requireAuthOrWarn('validate')) return;
      const form = document.getElementById('settingsForm');
      const payload = collectForm(form);
      document.getElementById('statusMsg').textContent = 'Validating…';
      const r = await fetch(api('/admin/settings/validate'), { method:'POST', headers: { 'Content-Type':'application/json', ...authHeader() }, body: JSON.stringify(payload) });
      let j = {};
      try { j = await r.json(); } catch(e) { j = {}; }
      if (r.status === 200){
        const norm = j?.data?.normalized || {};
        const keys = Object.keys(norm);
        const msg = keys.length ? ('Looks good: ' + keys.join(', ')) : 'Looks good (no changes)';
        document.getElementById('statusMsg').textContent = msg;
        showErrors(form, []);
      } else if (r.status === 422){
        document.getElementById('statusMsg').textContent = 'Validation errors';
        showErrors(form, j.errors||[]);
      } else {
        const reqId = j?.meta?.request_id ? (' · request ' + j.meta.request_id) : '';
        document.getElementById('statusMsg').textContent = 'Validation failed ('+r.status+')' + reqId;
      }
    }

    async function saveSettings(){
      if (!requireAuthOrWarn('save')) return;
      const form = document.getElementById('settingsForm');
      const payload = collectForm(form);
      document.getElementById('statusMsg').textContent = 'Saving…';
      const r = await fetch(api('/admin/settings'), { method:'PUT', headers: { 'Content-Type':'application/json', ...authHeader() }, body: JSON.stringify(payload) });
      const j = await r.json().catch(()=>({}));
      if (r.status === 200){
        document.getElementById('statusMsg').textContent = 'Saved: ' + (j?.data?.saved||[]).join(', ');
        const envPath = j?.data?.env_path || '';
        const mtime = j?.data?.env_mtime ? new Date(j.data.env_mtime*1000).toLocaleString() : '';
        const backup = j?.data?.backup_path || '';
        const checksum = j?.data?.checksum || '';
        renderKV(document.getElementById('saveResult'), { backup, checksum });
        renderKV(document.getElementById('envInfo'), { path: envPath, mtime });
        showErrors(form, []);
      } else if (r.status === 422){
        document.getElementById('statusMsg').textContent = 'Validation errors';
        showErrors(form, j.errors||[]);
      } else {
        document.getElementById('statusMsg').textContent = 'Save failed ('+r.status+')';
      }
    }

    function updateAuthUI(){
      const has = hasToken();
      const lb = document.getElementById('loginBlock');
      const vb = document.getElementById('validateBtn');
      const sb = document.getElementById('saveBtn');
      if (lb) lb.classList.toggle('hidden', has);
      if (vb) vb.disabled = !has;
      if (sb) sb.disabled = !has;
    }

    document.getElementById('apiLoginBtn')?.addEventListener('click', async function(e){
      e.preventDefault();
      const email = document.getElementById('apiLoginEmail')?.value?.trim() || '';
      const password = document.getElementById('apiLoginPassword')?.value || '';
      const err = document.getElementById('apiLoginErr'); if (err) err.textContent = '';
      try {
        const r = await fetch(api('/auth/login'), { method:'POST', headers: { 'Content-Type':'application/json' }, body: JSON.stringify({ email, password }) });
        const j = await r.json();
        if (r.status !== 200) { throw new Error(j?.errors?.[0]?.message || 'Login failed'); }
        const access = j?.data?.access_token || j?.access_token;
        if (access) { localStorage.setItem('ngn_admin_token', access); }
        updateAuthUI();
        // Reload settings now that we have a token
        loadMetaAndValues();
        document.getElementById('statusMsg').textContent = 'Logged in';
      } catch (ex) {
        if (err) err.textContent = ex.message || 'Login failed';
      }
    });

    document.getElementById('validateBtn')?.addEventListener('click', function(e){ e.preventDefault(); validateSettings(); });
    document.getElementById('saveBtn')?.addEventListener('click', function(e){ e.preventDefault(); saveSettings(); });

    checkHealth();
    updateAuthUI();
    loadMetaAndValues();
  </script>
</body>
</html>
