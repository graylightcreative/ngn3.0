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

$pageTitle = 'Ads';
$currentPage = 'ads';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

<!-- Ads List -->
<div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
  <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <div class="font-semibold text-lg">Ads</div>
    <div class="flex flex-wrap gap-2">
      <input id="searchQ" type="text" placeholder="Search ads..." class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10 w-64" />
      <select id="filterStatus" class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="scheduled">Scheduled</option>
        <option value="expired">Expired</option>
      </select>
      <button id="searchBtn" class="inline-flex items-center px-4 h-10 rounded bg-brand text-white text-sm">Search</button>
      <button id="addBtn" class="inline-flex items-center px-4 h-10 rounded bg-green-600 text-white text-sm">+ Add Ad</button>
    </div>
  </div>
  
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="text-left text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-white/10">
        <tr>
          <th class="py-2 pr-3">ID</th>
          <th class="py-2 pr-3">Title</th>
          <th class="py-2 pr-3">Slug</th>
          <th class="py-2 pr-3">Start Date</th>
          <th class="py-2 pr-3">End Date</th>
          <th class="py-2 pr-3">Status</th>
          <th class="py-2 pr-3">Actions</th>
        </tr>
      </thead>
      <tbody id="adsTable" class="divide-y divide-gray-100 dark:divide-white/5"></tbody>
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
<div id="adModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-lg mx-4">
    <h3 id="modalTitle" class="text-lg font-semibold mb-4">Add Ad</h3>
    <form id="adForm" class="space-y-4">
      <input type="hidden" id="adId" />
      <div>
        <label class="block text-sm mb-1">Title</label>
        <input type="text" id="adTitle" class="w-full rounded border border-gray-300 dark:border-white/10 bg-white dark:bg-white/10 px-3 h-10" required />
      </div>
      <div>
        <label class="block text-sm mb-1">Slug</label>
        <input type="text" id="adSlug" class="w-full rounded border border-gray-300 dark:border-white/10 bg-white dark:bg-white/10 px-3 h-10" />
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm mb-1">Start Date</label>
          <input type="date" id="adStartDate" class="w-full rounded border border-gray-300 dark:border-white/10 bg-white dark:bg-white/10 px-3 h-10" />
        </div>
        <div>
          <label class="block text-sm mb-1">End Date</label>
          <input type="date" id="adEndDate" class="w-full rounded border border-gray-300 dark:border-white/10 bg-white dark:bg-white/10 px-3 h-10" />
        </div>
      </div>
      <div>
        <label class="block text-sm mb-1">Image URL</label>
        <input type="url" id="adImageUrl" class="w-full rounded border border-gray-300 dark:border-white/10 bg-white dark:bg-white/10 px-3 h-10" />
      </div>
      <div>
        <label class="block text-sm mb-1">Link URL</label>
        <input type="url" id="adLinkUrl" class="w-full rounded border border-gray-300 dark:border-white/10 bg-white dark:bg-white/10 px-3 h-10" />
      </div>
      <div class="flex justify-end gap-2 pt-4">
        <button type="button" id="cancelBtn" class="px-4 h-10 rounded border border-gray-300 dark:border-white/10">Cancel</button>
        <button type="submit" class="px-4 h-10 rounded bg-brand text-white">Save</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__.'/_footer.php'; ?>

<script>
let ads = [];
let page = 1;
const perPage = 20;

async function loadAds() {
  try {
    const res = await apiCall('GET', '/legacy/ads');
    ads = res.json?.data || [];
    renderTable();
  } catch (e) {
    console.error('Failed to load ads:', e);
    ads = [];
    renderTable();
  }
}

function getStatus(ad) {
  const now = new Date();
  const start = ad.StartDate ? new Date(ad.StartDate) : null;
  const end = ad.EndDate ? new Date(ad.EndDate) : null;
  
  if (end && now > end) return 'expired';
  if (start && now < start) return 'scheduled';
  return 'active';
}

function renderTable() {
  const q = document.getElementById('searchQ').value.toLowerCase();
  const status = document.getElementById('filterStatus').value;
  
  let filtered = ads.filter(a => {
    const matchQ = !q || (a.Title || '').toLowerCase().includes(q) || (a.Slug || '').toLowerCase().includes(q);
    const matchStatus = !status || getStatus(a) === status;
    return matchQ && matchStatus;
  });
  
  const total = filtered.length;
  const start = (page - 1) * perPage;
  const paged = filtered.slice(start, start + perPage);
  
  const tbody = document.getElementById('adsTable');
  tbody.innerHTML = paged.map(a => {
    const status = getStatus(a);
    const statusClass = status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' :
                        status === 'scheduled' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' :
                        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400';
    return `<tr>
      <td class="py-2 pr-3">${a.Id || ''}</td>
      <td class="py-2 pr-3">${escapeHtml(a.Title || '')}</td>
      <td class="py-2 pr-3">${escapeHtml(a.Slug || '')}</td>
      <td class="py-2 pr-3">${a.StartDate || '-'}</td>
      <td class="py-2 pr-3">${a.EndDate || '-'}</td>
      <td class="py-2 pr-3"><span class="px-2 py-1 rounded text-xs ${statusClass}">${status}</span></td>
      <td class="py-2 pr-3">
        <button onclick="editAd(${a.Id})" class="text-brand hover:underline text-sm mr-2">Edit</button>
        <button onclick="deleteAd(${a.Id})" class="text-red-500 hover:underline text-sm">Delete</button>
      </td>
    </tr>`;
  }).join('') || '<tr><td colspan="7" class="py-4 text-center text-gray-500">No ads found</td></tr>';
  
  document.getElementById('pagerInfo').textContent = `Showing ${start + 1}-${Math.min(start + perPage, total)} of ${total}`;
  document.getElementById('prevPage').disabled = page <= 1;
  document.getElementById('nextPage').disabled = start + perPage >= total;
}

function showModal(ad = null) {
  document.getElementById('modalTitle').textContent = ad ? 'Edit Ad' : 'Add Ad';
  document.getElementById('adId').value = ad?.Id || '';
  document.getElementById('adTitle').value = ad?.Title || '';
  document.getElementById('adSlug').value = ad?.Slug || '';
  document.getElementById('adStartDate').value = ad?.StartDate || '';
  document.getElementById('adEndDate').value = ad?.EndDate || '';
  document.getElementById('adImageUrl').value = ad?.ImageUrl || '';
  document.getElementById('adLinkUrl').value = ad?.LinkUrl || '';
  document.getElementById('adModal').classList.remove('hidden');
  document.getElementById('adModal').classList.add('flex');
}

function hideModal() {
  document.getElementById('adModal').classList.add('hidden');
  document.getElementById('adModal').classList.remove('flex');
}

function editAd(id) {
  const ad = ads.find(a => a.Id == id);
  if (ad) showModal(ad);
}

async function deleteAd(id) {
  if (!confirm('Delete this ad?')) return;
  try {
    await apiCall('DELETE', '/legacy/ads/' + id);
    loadAds();
  } catch (e) {
    alert('Failed to delete ad');
  }
}

document.getElementById('addBtn').onclick = () => showModal();
document.getElementById('cancelBtn').onclick = hideModal;
document.getElementById('searchBtn').onclick = () => { page = 1; renderTable(); };
document.getElementById('searchQ').onkeyup = (e) => { if (e.key === 'Enter') { page = 1; renderTable(); } };
document.getElementById('filterStatus').onchange = () => { page = 1; renderTable(); };
document.getElementById('prevPage').onclick = () => { page--; renderTable(); };
document.getElementById('nextPage').onclick = () => { page++; renderTable(); };

document.getElementById('adForm').onsubmit = async (e) => {
  e.preventDefault();
  const id = document.getElementById('adId').value;
  const data = {
    Title: document.getElementById('adTitle').value,
    Slug: document.getElementById('adSlug').value,
    StartDate: document.getElementById('adStartDate').value,
    EndDate: document.getElementById('adEndDate').value,
    ImageUrl: document.getElementById('adImageUrl').value,
    LinkUrl: document.getElementById('adLinkUrl').value
  };
  try {
    if (id) {
      await apiCall('PUT', '/legacy/ads/' + id, data);
    } else {
      await apiCall('POST', '/legacy/ads', data);
    }
    hideModal();
    loadAds();
  } catch (e) {
    alert('Failed to save ad');
  }
};

loadAds();
</script>

