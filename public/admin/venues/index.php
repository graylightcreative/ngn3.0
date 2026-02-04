<?php
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);
// require_once $root.'/lib/bootstrap.php';

use NGN\Lib\Env;
use NGN\Lib\Config;

if (!class_exists('NGN\\Lib\\Env')) { exit('Bootstrap failed'); }
Env::load($root);
$cfg = new Config();
include dirname(__DIR__).'/_mint_token.php';

$pageTitle = 'Venues';
$currentPage = 'venues';
include dirname(__DIR__).'/_header.php';

include dirname(__DIR__).'/_topbar.php';
?>

<!-- Venues List -->
<div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
  <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <div class="font-semibold text-lg">Venues</div>
    <div class="flex flex-wrap gap-2">
      <input id="searchQ" type="text" placeholder="Search by name or city..." class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10 w-64" />
      <select id="filterClaimed" class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10">
        <option value="">All</option>
        <option value="1">Claimed</option>
        <option value="0">Unclaimed</option>
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
          <th class="py-2 pr-3">City</th>
          <th class="py-2 pr-3">Region</th>
          <th class="py-2 pr-3">Country</th>
          <th class="py-2 pr-3">Claimed</th>
          <th class="py-2 pr-3">Actions</th>
        </tr>
      </thead>
      <tbody id="venuesTable" class="divide-y divide-gray-100 dark:divide-white/5"></tbody>
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
      <h3 class="text-lg font-semibold">Edit Venue</h3>
      <button id="closeModal" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 text-2xl">&times;</button>
    </div>
    <input type="hidden" id="editId" />
    <div class="space-y-3">
      <div>
        <label class="text-xs text-gray-500 dark:text-gray-400">Name</label>
        <input id="editName" type="text" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" />
      </div>
      <div>
        <label class="text-xs text-gray-500 dark:text-gray-400">Bio</label>
        <textarea id="editBio" rows="3" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 py-2"></textarea>
      </div>
      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="text-xs text-gray-500 dark:text-gray-400">City</label>
          <input id="editCity" type="text" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" />
        </div>
        <div>
          <label class="text-xs text-gray-500 dark:text-gray-400">Region</label>
          <input id="editRegion" type="text" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" />
        </div>
        <div>
          <label class="text-xs text-gray-500 dark:text-gray-400">Country</label>
          <input id="editCountry" type="text" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" />
        </div>
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

<?php include dirname(__DIR__).'/_footer.php'; ?>

<script>
let currentPage = 1;
const perPage = 25;
let totalVenues = 0;

async function loadVenues() {
  const q = document.getElementById('searchQ').value.trim();
  const claimed = document.getElementById('filterClaimed').value;
  
  let url = api(`/admin/2025/venues?per_page=${perPage}&page=${currentPage}`);
  if (q) url += `&q=${encodeURIComponent(q)}`;
  if (claimed) url += `&claimed=${claimed}`;
  
  const res = await fetch(url, { headers: authHeader() });
  const json = await res.json();
  
  const venues = json?.data?.items || [];
  totalVenues = json?.meta?.total || venues.length;
  
  const tbody = document.getElementById('venuesTable');
  tbody.innerHTML = '';
  
  venues.forEach(v => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="py-2 pr-3">${v.id}</td>
      <td class="py-2 pr-3 font-medium">${escapeHtml(v.name || '')}</td>
      <td class="py-2 pr-3">${escapeHtml(v.city || '-')}</td>
      <td class="py-2 pr-3">${escapeHtml(v.region || '-')}</td>
      <td class="py-2 pr-3">${escapeHtml(v.country || '-')}</td>
      <td class="py-2 pr-3">${v.claimed ? '✓' : '-'}</td>
      <td class="py-2 pr-3">
        <button class="px-2 h-7 rounded bg-gray-200 dark:bg-white/10 text-xs" onclick="editVenue(${v.id})">Edit</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
  
  document.getElementById('pagerInfo').textContent = `Showing ${venues.length} of ${totalVenues}`;
  document.getElementById('prevPage').disabled = currentPage <= 1;
  document.getElementById('nextPage').disabled = (currentPage * perPage) >= totalVenues;
}

function editVenue(id) {
  fetch(api(`/venues/${id}`), { headers: authHeader() })
    .then(r => r.json())
    .then(json => {
      const v = json?.data || {};
      document.getElementById('editId').value = id;
      document.getElementById('editName').value = v.name || '';
      document.getElementById('editBio').value = v.bio || '';
      document.getElementById('editCity').value = v.city || '';
      document.getElementById('editRegion').value = v.region || '';
      document.getElementById('editCountry').value = v.country || '';
      document.getElementById('editWebsite').value = v.website || '';
      document.getElementById('editClaimed').checked = !!v.claimed;
      document.getElementById('editVerified').checked = !!v.verified;
      document.getElementById('editModal').classList.remove('hidden');
      document.getElementById('editModal').classList.add('flex');
    });
}

async function saveVenue() {
  const id = document.getElementById('editId').value;
  const payload = {
    name: document.getElementById('editName').value,
    bio: document.getElementById('editBio').value,
    city: document.getElementById('editCity').value,
    region: document.getElementById('editRegion').value,
    country: document.getElementById('editCountry').value,
    website: document.getElementById('editWebsite').value,
    claimed: document.getElementById('editClaimed').checked,
    verified: document.getElementById('editVerified').checked
  };
  
  const { status, json } = await apiCall('PUT', `/admin/venues/${id}`, payload);
  const msg = document.getElementById('editMsg');
  
  if (status === 200) {
    msg.innerHTML = '<span class="text-emerald-500">Saved!</span>';
    setTimeout(() => { closeModal(); loadVenues(); }, 500);
  } else {
    msg.innerHTML = `<span class="text-red-500">Error: ${json?.errors?.[0]?.message || status}</span>`;
  }
}

function closeModal() {
  document.getElementById('editModal').classList.add('hidden');
  document.getElementById('editModal').classList.remove('flex');
  document.getElementById('editMsg').textContent = '';
}

document.getElementById('searchBtn').addEventListener('click', () => { currentPage = 1; loadVenues(); });
document.getElementById('prevPage').addEventListener('click', () => { if (currentPage > 1) { currentPage--; loadVenues(); } });
document.getElementById('nextPage').addEventListener('click', () => { currentPage++; loadVenues(); });
document.getElementById('refreshBtn').addEventListener('click', loadVenues);
document.getElementById('closeModal').addEventListener('click', closeModal);
document.getElementById('cancelEdit').addEventListener('click', closeModal);
document.getElementById('saveEdit').addEventListener('click', saveVenue);
document.getElementById('searchQ').addEventListener('keypress', e => { if (e.key === 'Enter') { currentPage = 1; loadVenues(); } });

loadVenues();
</script>

