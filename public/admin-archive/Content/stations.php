<?php
require_once dirname(__DIR__, 3) . '/_guard.php';
$root = dirname(__DIR__);
require_once $root.'/lib/bootstrap.php';

use NGN\Lib\Env;
use NGN\Lib\Config;

if (!class_exists('NGN\\Lib\\Env')) { exit('Bootstrap failed'); }
Env::load($root);
$cfg = new Config();
include __DIR__.'/_mint_token.php';

$pageTitle = 'Stations';
$currentPage = 'stations';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

<!-- Stations List -->
<div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
  <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <div class="font-semibold text-lg">Stations</div>
    <div class="flex flex-wrap gap-2">
      <input id="searchQ" type="text" placeholder="Search by name or callsign..." class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10 w-64" />
      <select id="filterFormat" class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10">
        <option value="">All Formats</option>
        <option value="rock">Rock</option>
        <option value="metal">Metal</option>
        <option value="alternative">Alternative</option>
        <option value="indie">Indie</option>
        <option value="punk">Punk</option>
      </select>
      <button id="searchBtn" class="inline-flex items-center px-4 h-10 rounded bg-brand text-white text-sm">Search</button>
    </div>
  </div>
  
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="text-left text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-white/10">
        <tr>
          <th class="py-2 pr-3">ID</th>
          <th class="py-2 pr-3">Name</th>
          <th class="py-2 pr-3">Callsign</th>
          <th class="py-2 pr-3">Region</th>
          <th class="py-2 pr-3">Format</th>
          <th class="py-2 pr-3">Claimed</th>
          <th class="py-2 pr-3">Actions</th>
        </tr>
      </thead>
      <tbody id="stationsTable" class="divide-y divide-gray-100 dark:divide-white/5"></tbody>
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

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white dark:bg-gray-900 rounded-lg p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold">Edit Station</h3>
      <button id="closeModal" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 text-2xl">&times;</button>
    </div>
    <input type="hidden" id="editId" />
    <div class="space-y-3">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs text-gray-500 dark:text-gray-400">Name</label>
          <input id="editName" type="text" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" />
        </div>
        <div>
          <label class="text-xs text-gray-500 dark:text-gray-400">Callsign</label>
          <input id="editCallsign" type="text" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" />
        </div>
      </div>
      <div>
        <label class="text-xs text-gray-500 dark:text-gray-400">Bio</label>
        <textarea id="editBio" rows="3" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 py-2"></textarea>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs text-gray-500 dark:text-gray-400">Region</label>
          <input id="editRegion" type="text" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" />
        </div>
        <div>
          <label class="text-xs text-gray-500 dark:text-gray-400">Format</label>
          <input id="editFormat" type="text" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" />
        </div>
      </div>
      <div>
        <label class="text-xs text-gray-500 dark:text-gray-400">Stream URL</label>
        <input id="editStreamUrl" type="url" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" />
      </div>
      <div>
        <label class="text-xs text-gray-500 dark:text-gray-400">Website</label>
        <input id="editWebsite" type="url" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" />
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div class="flex items-center gap-2">
          <input id="editClaimed" type="checkbox" class="rounded" />
          <label class="text-sm">Claimed</label>
        </div>
        <div class="flex items-center gap-2">
          <input id="editVerified" type="checkbox" class="rounded" />
          <label class="text-sm">Verified</label>
        </div>
      </div>
    </div>
    <div class="mt-4 flex justify-end gap-2">
      <button id="cancelEdit" class="px-4 h-9 rounded border border-gray-200 dark:border-white/10 text-sm">Cancel</button>
      <button id="saveEdit" class="px-4 h-9 rounded bg-brand text-white text-sm">Save</button>
    </div>
    <div id="editMsg" class="mt-2 text-sm"></div>
  </div>
</div>

<?php include __DIR__.'/_footer.php'; ?>

<script>
let currentPage = 1;
const perPage = 25;
let totalStations = 0;

async function loadStations() {
  const q = document.getElementById('searchQ').value.trim();
  const format = document.getElementById('filterFormat').value;
  
  let url = api(`/admin/2025/stations?per_page=${perPage}&page=${currentPage}`);
  if (q) url += `&q=${encodeURIComponent(q)}`;
  if (format) url += `&format=${encodeURIComponent(format)}`;
  
  const res = await fetch(url, { headers: authHeader() });
  const json = await res.json();
  
  const stations = json?.data?.items || [];
  totalStations = json?.meta?.total || stations.length;
  
  const tbody = document.getElementById('stationsTable');
  tbody.innerHTML = '';
  
  stations.forEach(s => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="py-2 pr-3">${s.id}</td>
      <td class="py-2 pr-3 font-medium">${escapeHtml(s.name || '')}</td>
      <td class="py-2 pr-3">${escapeHtml(s.call_sign || '-')}</td>
      <td class="py-2 pr-3">${escapeHtml(s.region || '-')}</td>
      <td class="py-2 pr-3">${escapeHtml(s.format || '-')}</td>
      <td class="py-2 pr-3">${s.claimed ? '✓' : '-'}</td>
      <td class="py-2 pr-3">
        <button class="px-2 h-7 rounded bg-gray-200 dark:bg-white/10 text-xs" onclick="editStation(${s.id})">Edit</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
  
  document.getElementById('pagerInfo').textContent = `Showing ${stations.length} of ${totalStations}`;
  document.getElementById('prevPage').disabled = currentPage <= 1;
  document.getElementById('nextPage').disabled = (currentPage * perPage) >= totalStations;
}

function editStation(id) {
  fetch(api(`/stations/${id}`), { headers: authHeader() })
    .then(r => r.json())
    .then(json => {
      const s = json?.data || {};
      document.getElementById('editId').value = id;
      document.getElementById('editName').value = s.name || '';
      document.getElementById('editCallsign').value = s.call_sign || '';
      document.getElementById('editBio').value = s.bio || '';
      document.getElementById('editRegion').value = s.region || '';
      document.getElementById('editFormat').value = s.format || '';
      document.getElementById('editStreamUrl').value = s.stream_url || '';
      document.getElementById('editWebsite').value = s.website || '';
      document.getElementById('editClaimed').checked = !!s.claimed;
      document.getElementById('editVerified').checked = !!s.verified;
      document.getElementById('editModal').classList.remove('hidden');
      document.getElementById('editModal').classList.add('flex');
    });
}

async function saveStation() {
  const id = document.getElementById('editId').value;
  const payload = {
    name: document.getElementById('editName').value,
    call_sign: document.getElementById('editCallsign').value,
    bio: document.getElementById('editBio').value,
    region: document.getElementById('editRegion').value,
    format: document.getElementById('editFormat').value,
    stream_url: document.getElementById('editStreamUrl').value,
    website: document.getElementById('editWebsite').value,
    claimed: document.getElementById('editClaimed').checked,
    verified: document.getElementById('editVerified').checked
  };
  
  const { status, json } = await apiCall('PUT', `/admin/stations/${id}`, payload);
  const msg = document.getElementById('editMsg');
  
  if (status === 200) {
    msg.innerHTML = '<span class="text-emerald-500">Saved!</span>';
    setTimeout(() => { closeModal(); loadStations(); }, 500);
  } else {
    msg.innerHTML = `<span class="text-red-500">Error: ${json?.errors?.[0]?.message || status}</span>`;
  }
}

function closeModal() {
  document.getElementById('editModal').classList.add('hidden');
  document.getElementById('editModal').classList.remove('flex');
  document.getElementById('editMsg').textContent = '';
}

document.getElementById('searchBtn').addEventListener('click', () => { currentPage = 1; loadStations(); });
document.getElementById('prevPage').addEventListener('click', () => { if (currentPage > 1) { currentPage--; loadStations(); } });
document.getElementById('nextPage').addEventListener('click', () => { currentPage++; loadStations(); });
document.getElementById('refreshBtn').addEventListener('click', loadStations);
document.getElementById('closeModal').addEventListener('click', closeModal);
document.getElementById('cancelEdit').addEventListener('click', closeModal);
document.getElementById('saveEdit').addEventListener('click', saveStation);
document.getElementById('searchQ').addEventListener('keypress', e => { if (e.key === 'Enter') { currentPage = 1; loadStations(); } });

loadStations();
</script>

