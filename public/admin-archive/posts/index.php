<?php
// Define the absolute path to the project root.
// This ensures consistent file resolution regardless of the script's execution context.
$projectRoot = $_SERVER['DOCUMENT_ROOT'];

// Require _guard.php using its absolute path. This bypasses potential issues with include_path or relative resolution.
// The _guard.php file itself will then handle loading bootstrap.php.
require_once $projectRoot . '/_guard.php';

// The rest of the original script follows...

use NGN\Lib\Env;
use NGN\Lib\Config;

// The check for Env class existence and loading env variables are now handled by _guard.php and bootstrap.php
// so we can proceed directly with other initializations.

$cfg = new Config();
include dirname(__DIR__).'/_mint_token.php';

$pageTitle = 'Editorial Posts';
$currentPage = 'posts';
include dirname(__DIR__).'/_header.php';

include dirname(__DIR__).'/_topbar.php';
?>

      <section class="max-w-7xl mx-auto px-4 py-6 space-y-6">
        <!-- Env info -->
        <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
          <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Environment</div>
          <div id="envInfo" class="text-base">Loading…</div>
        </div>

        <!-- Create Post -->
        <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
          <div class="flex items-center justify-between mb-3">
            <div class="font-semibold">Create Post</div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
              <label class="text-xs text-gray-500 dark:text-gray-400">Title</label>
              <input id="title" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" placeholder="Title" />
            </div>
            <div>
              <label class="text-xs text-gray-500 dark:text-gray-400">Slug (optional)</label>
              <input id="slug" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" placeholder="auto-from-title if blank" />
            </div>
            <div>
              <label class="text-xs text-gray-500 dark:text-gray-400">Status</label>
              <select id="status" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10">
                <option value="draft">draft</option>
                <option value="published">published</option>
              </select>
            </div>
          </div>
          <div class="mt-3">
            <label class="text-xs text-gray-500 dark:text-gray-400">Body</label>
            <textarea id="body" rows="4" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 py-2" placeholder="Write something…"></textarea>
          </div>
          <div class="mt-3 flex items-center gap-3">
            <button id="createBtn" class="inline-flex items-center px-3 h-9 rounded bg-brand text-white text-sm">Create</button>
            <div id="createMsg" class="text-sm text-gray-500 dark:text-gray-400"></div>
          </div>
        </div>

        <!-- Posts list -->
        <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
          <div class="flex flex-wrap items-end justify-between gap-3 mb-3">
            <div class="font-semibold">Posts</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 w-full md:w-auto">
              <div>
                <label class="text-xs text-gray-500 dark:text-gray-400">Search</label>
                <input id="q" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" placeholder="search…" />
              </div>
              <div>
                <label class="text-xs text-gray-500 dark:text-gray-400">Status</label>
                <select id="filterStatus" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10">
                  <option value="">any</option>
                  <option value="draft">draft</option>
                  <option value="published">published</option>
                </select>
              </div>
              <div class="flex items-end">
                <button id="applyFilters" class="inline-flex items-center px-3 h-10 rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 text-sm w-full">Apply</button>
              </div>
            </div>
          </div>
          <div class="overflow-auto">
            <table class="min-w-full text-sm">
              <thead class="text-left text-gray-600 dark:text-gray-300">
                <tr>
                  <th class="py-2 pr-3">ID</th>
                  <th class="py-2 pr-3">Title</th>
                  <th class="py-2 pr-3">Slug</th>
                  <th class="py-2 pr-3">Status</th>
                  <th class="py-2 pr-3">Published</th>
                  <th class="py-2 pr-3">Actions</th>
                </tr>
              </thead>
              <tbody id="rows" class="border-t border-gray-200 dark:border-white/10"></tbody>
            </table>
          </div>
          <div id="listMeta" class="mt-2 text-sm text-gray-500 dark:text-gray-400"></div>
          <div id="listMsg" class="mt-2 text-sm text-gray-500 dark:text-gray-400"></div>
        </div>
      </section>
    </main>
  </div>

<?php include __DIR__.'/_token_store.php'; ?>
  <script>
    const api = path => `/api/v1${path}`;
    const token = () => localStorage.getItem('ngn_admin_token') || localStorage.getItem('admin_token') || '';
    const authHeader = () => token() ? { 'Authorization': 'Bearer ' + token() } : {};

    function fmtStatus(s){
      const on = s==='published';
      return `<span class="inline-flex items-center px-2 py-1 rounded-full text-xs border ${on ? 'bg-brand/10 text-brand border-brand/30' : 'bg-gray-200 dark:bg-white/10 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-white/10'}">${s}</span>`;
    }

    async function apiCall(method, path, body){
      const res = await fetch(api(path), {
        method,
        headers: { 'Content-Type': 'application/json', ...authHeader() },
        body: body ? JSON.stringify(body) : undefined
      });
      let json = null;
      try { json = await res.json(); } catch(e){}
      return { status: res.status, json };
    }

    async function loadEnv(){
      const h = await fetch(api('/health'));
      const j = await h.json();
      const env = j?.meta?.env || '';
      const ver = j?.data?.version || '';
      document.getElementById('envInfo').textContent = `Env: ${env} · v${ver}`;
    }

    async function loadList(){
      const q = document.getElementById('q').value.trim();
      const status = document.getElementById('filterStatus').value;
      const url = new URL(location.origin + api('/posts'));
      url.searchParams.set('limit', '50');
      url.searchParams.set('offset', '0');
      if (q) url.searchParams.set('q', q);
      if (status) url.searchParams.set('status', status);
      const res = await fetch(url.toString(), { headers: { ...authHeader() } });
      const ct = res.headers.get('content-type')||'';
      const json = ct.includes('application/json') ? await res.json() : null;
      const rows = json?.data?.items || [];
      const total = json?.meta?.total ?? rows.length;
      const tbody = document.getElementById('rows');
      tbody.innerHTML = '';
      rows.forEach(r => {
        const tr = document.createElement('tr');
        tr.className = 'border-b border-gray-100 dark:border-white/10';
        tr.innerHTML = `<td class=\"py-2 pr-3\">${r.Id}</td>
          <td class=\"py-2 pr-3\"><input class=\"w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-2 h-9\" data-id=\"${r.Id}\" data-field=\"Title\" value=\"${escapeHtml(r.Title||'')}\"/></td>
          <td class=\"py-2 pr-3\"><input class=\"w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-2 h-9\" data-id=\"${r.Id}\" data-field=\"Slug\" value=\"${escapeHtml(r.Slug||'')}\"/></td>
          <td class=\"py-2 pr-3\">${fmtStatus(r.Status||'draft')}</td>
          <td class=\"py-2 pr-3\">${r.PublishedAt||''}</td>
          <td class=\"py-2 pr-3 flex gap-2\">
            <button class=\"inline-flex items-center px-2 h-8 rounded bg-brand text-white text-xs\" data-act=\"publish\" data-id=\"${r.Id}\">Publish</button>
            <button class=\"inline-flex items-center px-2 h-8 rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 text-xs\" data-act=\"save\" data-id=\"${r.Id}\">Save</button>
            <button class=\"inline-flex items-center px-2 h-8 rounded bg-red-600 text-white text-xs\" data-act=\"delete\" data-id=\"${r.Id}\">Delete</button>
          </td>`;
        tbody.appendChild(tr);
      });
      document.getElementById('listMeta').textContent = `Showing ${rows.length} of ${total}`;
      document.getElementById('listMsg').textContent = (res.status===200? '': `Error ${res.status}`);
    }

    function escapeHtml(s){
      return (s||'').replace(/[&<>\"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;'}[c]));
    }

    async function createPost(){
      const title = document.getElementById('title').value.trim();
      const slug = document.getElementById('slug').value.trim();
      const status = document.getElementById('status').value;
      const body = document.getElementById('body').value;
      const msg = document.getElementById('createMsg');
      msg.textContent = 'Saving…';
      const {status:code, json} = await apiCall('POST', '/admin/posts', { title, slug, status, body });
      if (code===201){
        msg.innerHTML = '<span class="text-emerald-500">Created</span>';
        document.getElementById('title').value = '';
        document.getElementById('slug').value = '';
        document.getElementById('body').value = '';
        await loadList();
      } else {
        const errs = (json?.errors||[]).map(e=>`${e.field||''} ${e.message||e.code}`).join('; ');
        msg.innerHTML = `<span class=\"text-red-500\">Error ${code}: ${errs||'Failed'}<\/span>`;
      }
    }

    async function onTableClick(e){
      const btn = e.target.closest('button');
      if (!btn) return;
      const id = btn.getAttribute('data-id');
      const act = btn.getAttribute('data-act');
      if (!id) return;
      if (act==='delete'){
        if (!confirm('Delete post #' + id + '?')) return;
        const {status} = await apiCall('DELETE', `/admin/posts/${id}`);
        if (status===200){ loadList(); } else { alert('Delete failed: ' + status); }
        return;
      }
      if (act==='save' || act==='publish'){
        const inputs = Array.from(document.querySelectorAll(`input[data-id="${id}"]`));
        const payload = {};
        inputs.forEach(i=>{ const f = i.getAttribute('data-field'); if (f==='Title') payload.title = i.value; if (f==='Slug') payload.slug = i.value; });
        if (act==='publish') payload.status = 'published';
        const {status, json} = await apiCall('PUT', `/admin/posts/${id}`, payload);
        if (status===200){ loadList(); } else { alert('Save failed: ' + (json?.errors?.[0]?.message || status)); }
      }
    }

    document.getElementById('createBtn').addEventListener('click', createPost);
    document.getElementById('rows').addEventListener('click', onTableClick);
    document.getElementById('applyFilters').addEventListener('click', loadList);
    document.getElementById('refreshBtn').addEventListener('click', loadList);
  </script>
</body>
</html>
