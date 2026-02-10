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

$pageTitle = 'Pages';
$currentPage = 'pages';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

<!-- Pages List -->
<div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
  <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <div class="font-semibold text-lg">Static Pages</div>
    <div class="flex flex-wrap gap-2">
      <input id="searchQ" type="text" placeholder="Search pages..." class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10 w-64" />
      <button id="searchBtn" class="inline-flex items-center px-4 h-10 rounded bg-brand text-white text-sm">Search</button>
      <button id="addBtn" class="inline-flex items-center px-4 h-10 rounded bg-green-600 text-white text-sm">+ Add Page</button>
    </div>
  </div>
  
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="text-left text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-white/10">
        <tr>
          <th class="py-2 pr-3">ID</th>
          <th class="py-2 pr-3">Title</th>
          <th class="py-2 pr-3">Slug</th>
          <th class="py-2 pr-3">Status</th>
          <th class="py-2 pr-3">Updated</th>
          <th class="py-2 pr-3">Actions</th>
        </tr>
      </thead>
      <tbody id="pagesTable" class="divide-y divide-gray-100 dark:divide-white/5"></tbody>
    </table>
  </div>
  
  <div class="mt-4 flex items-center justify-between">
    <div id="pagerInfo" class="text-sm text-gray-500 dark:text-gray-400"></div>
    <div class="flex gap-2">
      <button id="prevPage" class="px-3 h-8 rounded border border-gray-200 dark:border-white/10 text-sm disabled:opacity-40">Prev</button>
      <button id="nextPage" class="px-3 h-8 rounded border border-gray-200 dark:border-white/10 text-sm disabled:opacity-40">Next</button>
    </div>
  </div>
</div>

<!-- Add/Edit Modal -->
<div id="pageModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
    <h3 id="modalTitle" class="text-lg font-semibold mb-4">Add Page</h3>
    <form id="pageForm" class="space-y-4">
      <input type="hidden" id="pageId" />
      <div>
        <label class="block text-sm mb-1">Title</label>
        <input type="text" id="pageTitle" class="w-full rounded border border-gray-300 dark:border-white/10 bg-white dark:bg-white/10 px-3 h-10" required />
      </div>
      <div>
        <label class="block text-sm mb-1">Slug</label>
        <input type="text" id="pageSlug" class="w-full rounded border border-gray-300 dark:border-white/10 bg-white dark:bg-white/10 px-3 h-10" />
      </div>
      <div>
        <label class="block text-sm mb-1">Content</label>
        <textarea id="pageContent" rows="10" class="w-full rounded border border-gray-300 dark:border-white/10 bg-white dark:bg-white/10 px-3 py-2"></textarea>
      </div>
      <div>
        <label class="flex items-center gap-2">
          <input type="checkbox" id="pagePublished" class="rounded" />
          <span class="text-sm">Published</span>
        </label>
      </div>
      <div class="flex justify-end gap-2 pt-4">
        <button type="button" id="cancelBtn" class="px-4 h-10 rounded border border-gray-300 dark:border-white/10">Cancel</button>
        <button type="submit" class="px-4 h-10 rounded bg-brand text-white">Save</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__.'/_footer.php'; ?>
<?php include __DIR__.'/_token_store.php'; ?>

<script>
let pages = [];
let page = 1;
const perPage = 20;

async function loadPages() {
  try {
    const res = await apiCall('GET', '/legacy/pages');
    pages = res.json?.data || [];
    renderTable();
  } catch (e) {
    console.error('Failed to load pages:', e);
    pages = [];
    renderTable();
  }
}

function renderTable() {
  const q = document.getElementById('searchQ').value.toLowerCase();
  
  let filtered = pages.filter(p => {
    return !q || (p.Title || '').toLowerCase().includes(q) || (p.Slug || '').toLowerCase().includes(q);
  });
  
  const total = filtered.length;
  const start = (page - 1) * perPage;
  const paged = filtered.slice(start, start + perPage);
  
  const tbody = document.getElementById('pagesTable');
  tbody.innerHTML = paged.map(p => {
    const published = p.Published == 1 || p.Published === true;
    const statusClass = published ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' :
                        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400';
    return `<tr>
      <td class="py-2 pr-3">${p.Id || ''}</td>
      <td class="py-2 pr-3">${escapeHtml(p.Title || '')}</td>
      <td class="py-2 pr-3">${escapeHtml(p.Slug || '')}</td>
      <td class="py-2 pr-3"><span class="px-2 py-1 rounded text-xs ${statusClass}">${published ? 'Published' : 'Draft'}</span></td>
      <td class="py-2 pr-3">${p.Updated || p.Created || '-'}</td>
      <td class="py-2 pr-3">
        <button onclick="editPage(${p.Id})" class="text-brand hover:underline text-sm mr-2">Edit</button>
        <button onclick="deletePage(${p.Id})" class="text-red-500 hover:underline text-sm">Delete</button>
      </td>
    </tr>`;
  }).join('') || '<tr><td colspan="6" class="py-4 text-center text-gray-500">No pages found</td></tr>';
  
  document.getElementById('pagerInfo').textContent = `Showing ${start + 1}-${Math.min(start + perPage, total)} of ${total}`;
  document.getElementById('prevPage').disabled = page <= 1;
  document.getElementById('nextPage').disabled = start + perPage >= total;
}

function showModal(p = null) {
  document.getElementById('modalTitle').textContent = p ? 'Edit Page' : 'Add Page';
  document.getElementById('pageId').value = p?.Id || '';
  document.getElementById('pageTitle').value = p?.Title || '';
  document.getElementById('pageSlug').value = p?.Slug || '';
  document.getElementById('pageContent').value = p?.Content || '';
  document.getElementById('pagePublished').checked = p?.Published == 1 || p?.Published === true;
  document.getElementById('pageModal').classList.remove('hidden');
  document.getElementById('pageModal').classList.add('flex');
}

function hideModal() {
  document.getElementById('pageModal').classList.add('hidden');
  document.getElementById('pageModal').classList.remove('flex');
}

function editPage(id) {
  const p = pages.find(x => x.Id == id);
  if (p) showModal(p);
}

async function deletePage(id) {
  if (!confirm('Delete this page?')) return;
  try {
    await apiCall('DELETE', '/legacy/pages/' + id);
    loadPages();
  } catch (e) {
    alert('Failed to delete page');
  }
}

document.getElementById('addBtn').onclick = () => showModal();
document.getElementById('cancelBtn').onclick = hideModal;
document.getElementById('searchBtn').onclick = () => { page = 1; renderTable(); };
document.getElementById('searchQ').onkeyup = (e) => { if (e.key === 'Enter') { page = 1; renderTable(); } };
document.getElementById('prevPage').onclick = () => { page--; renderTable(); };
document.getElementById('nextPage').onclick = () => { page++; renderTable(); };

document.getElementById('pageForm').onsubmit = async (e) => {
  e.preventDefault();
  const id = document.getElementById('pageId').value;
  const data = {
    Title: document.getElementById('pageTitle').value,
    Slug: document.getElementById('pageSlug').value,
    Content: document.getElementById('pageContent').value,
    Published: document.getElementById('pagePublished').checked ? 1 : 0
  };
  try {
    if (id) {
      await apiCall('PUT', '/legacy/pages/' + id, data);
    } else {
      await apiCall('POST', '/legacy/pages', data);
    }
    hideModal();
    loadPages();
  } catch (e) {
    alert('Failed to save page');
  }
};

loadPages();
</script>

