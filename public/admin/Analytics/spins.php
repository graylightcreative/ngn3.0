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

$pageTitle = 'Spins';
$currentPage = 'spins';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

<!-- Add Spin Form -->
<div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
  <div class="font-semibold mb-3">Add New Spin</div>
  <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
    <div>
      <label class="text-xs text-gray-500 dark:text-gray-400">Artist</label>
      <input id="addArtist" type="text" placeholder="Artist name" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" />
    </div>
    <div>
      <label class="text-xs text-gray-500 dark:text-gray-400">Song</label>
      <input id="addSong" type="text" placeholder="Song title" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" />
    </div>
    <div>
      <label class="text-xs text-gray-500 dark:text-gray-400">Station</label>
      <input id="addStation" type="text" placeholder="Station name" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" />
    </div>
    <div class="flex items-end">
      <button id="addSpinBtn" class="w-full h-10 rounded bg-brand text-white text-sm">Add Spin</button>
    </div>
  </div>
  <div id="addMsg" class="mt-2 text-sm"></div>
</div>

<!-- Spins List -->
<div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
  <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <div class="font-semibold text-lg">Recent Spins</div>
    <div class="flex flex-wrap gap-2">
      <input id="searchQ" type="text" placeholder="Search artist or song..." class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10 w-64" />
      <input id="filterDate" type="date" class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" />
      <button id="searchBtn" class="inline-flex items-center px-4 h-10 rounded bg-brand text-white text-sm">Search</button>
    </div>
  </div>
  
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="text-left text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-white/10">
        <tr>
          <th class="py-2 pr-3">ID</th>
          <th class="py-2 pr-3">Artist</th>
          <th class="py-2 pr-3">Song</th>
          <th class="py-2 pr-3">Station</th>
          <th class="py-2 pr-3">Timestamp</th>
          <th class="py-2 pr-3">Actions</th>
        </tr>
      </thead>
      <tbody id="spinsTable" class="divide-y divide-gray-100 dark:divide-white/5"></tbody>
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

<?php include __DIR__.'/_footer.php'; ?>

<script>
let currentPage = 1;
const perPage = 50;
let totalSpins = 0;

async function loadSpins() {
  const q = document.getElementById('searchQ').value.trim();
  const date = document.getElementById('filterDate').value;
  const offset = (currentPage - 1) * perPage;
  
  let url = api(`/admin/2025/spins?per_page=${perPage}&page=${currentPage}`);
  if (q) url += `&q=${encodeURIComponent(q)}`;
  if (date) url += `&date=${date}`;
  
  const res = await fetch(url, { headers: authHeader() });
  const json = await res.json();
  
  const spins = json?.data?.items || [];
  totalSpins = json?.meta?.total || spins.length;
  
  const tbody = document.getElementById('spinsTable');
  tbody.innerHTML = '';
  
  spins.forEach(s => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="py-2 pr-3">${s.id || s.Id}</td>
      <td class="py-2 pr-3">${escapeHtml(s.artist_name || s.Artist || '')}</td>
      <td class="py-2 pr-3">${escapeHtml(s.song_title || s.Song || '')}</td>
      <td class="py-2 pr-3">${escapeHtml(s.station_name || s.Station || '')}</td>
      <td class="py-2 pr-3">${s.spun_at || s.Timestamp || ''}</td>
      <td class="py-2 pr-3">
        <button class="px-2 h-7 rounded bg-red-600 text-white text-xs" onclick="deleteSpin(${s.id || s.Id})">Delete</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
  
  document.getElementById('pagerInfo').textContent = `Showing ${spins.length} of ${totalSpins}`;
  document.getElementById('prevPage').disabled = currentPage <= 1;
  document.getElementById('nextPage').disabled = (currentPage * perPage) >= totalSpins;
}

async function addSpin() {
  const artist = document.getElementById('addArtist').value.trim();
  const song = document.getElementById('addSong').value.trim();
  const station = document.getElementById('addStation').value.trim();
  const msg = document.getElementById('addMsg');
  
  if (!artist || !song || !station) {
    msg.innerHTML = '<span class="text-red-500">All fields required</span>';
    return;
  }
  
  const { status, json } = await apiCall('POST', '/admin/spins', { artist, song, station });
  
  if (status === 201 || status === 200) {
    msg.innerHTML = '<span class="text-emerald-500">Spin added!</span>';
    document.getElementById('addArtist').value = '';
    document.getElementById('addSong').value = '';
    document.getElementById('addStation').value = '';
    loadSpins();
  } else {
    msg.innerHTML = `<span class="text-red-500">Error: ${json?.errors?.[0]?.message || status}</span>`;
  }
}

async function deleteSpin(id) {
  if (!confirm('Delete spin #' + id + '?')) return;
  const { status } = await apiCall('DELETE', `/admin/spins/${id}`);
  if (status === 200) {
    loadSpins();
  } else {
    alert('Delete failed: ' + status);
  }
}

document.getElementById('addSpinBtn').addEventListener('click', addSpin);
document.getElementById('searchBtn').addEventListener('click', () => { currentPage = 1; loadSpins(); });
document.getElementById('prevPage').addEventListener('click', () => { if (currentPage > 1) { currentPage--; loadSpins(); } });
document.getElementById('nextPage').addEventListener('click', () => { currentPage++; loadSpins(); });
document.getElementById('refreshBtn').addEventListener('click', loadSpins);

loadSpins();
</script>

