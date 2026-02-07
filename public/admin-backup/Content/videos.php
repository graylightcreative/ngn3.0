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

$pageTitle = 'videos';
$currentPage = 'videos';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

<!-- Add Video Form -->
<div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
  <div class="font-semibold mb-3">Add New Video</div>
  <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
    <div>
      <label class="text-xs text-gray-500 dark:text-gray-400">Title</label>
      <input id="addTitle" type="text" placeholder="Video title" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" />
    </div>
    <div>
      <label class="text-xs text-gray-500 dark:text-gray-400">YouTube ID</label>
      <input id="addVideoId" type="text" placeholder="e.g. dQw4w9WgXcQ" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" />
    </div>
    <div>
      <label class="text-xs text-gray-500 dark:text-gray-400">Entity Type</label>
      <select id="addEntityType" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10">
        <option value="artist">Artist</option>
        <option value="label">Label</option>
        <option value="venue">Venue</option>
        <option value="station">Station</option>
      </select>
    </div>
    <div class="flex items-end">
      <button id="addVideoBtn" class="w-full h-10 rounded bg-brand text-white text-sm">Add Video</button>
    </div>
  </div>
  <div id="addMsg" class="mt-2 text-sm"></div>
</div>

<!-- Videos List -->
<div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
  <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <div class="font-semibold text-lg">Videos</div>
    <div class="flex flex-wrap gap-2">
      <input id="searchQ" type="text" placeholder="Search by title..." class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10 w-64" />
      <select id="filterType" class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10">
        <option value="">All Types</option>
        <option value="music_video">Music Video</option>
        <option value="live">Live</option>
        <option value="interview">Interview</option>
        <option value="behind_scenes">Behind the Scenes</option>
      </select>
      <button id="searchBtn" class="inline-flex items-center px-4 h-10 rounded bg-brand text-white text-sm">Search</button>
    </div>
  </div>
  
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="text-left text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-white/10">
        <tr>
          <th class="py-2 pr-3">ID</th>
          <th class="py-2 pr-3">Thumbnail</th>
          <th class="py-2 pr-3">Title</th>
          <th class="py-2 pr-3">Entity</th>
          <th class="py-2 pr-3">Type</th>
          <th class="py-2 pr-3">Published</th>
          <th class="py-2 pr-3">Actions</th>
        </tr>
      </thead>
      <tbody id="videosTable" class="divide-y divide-gray-100 dark:divide-white/5"></tbody>
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
const perPage = 25;
let totalVideos = 0;

function ytThumb(videoId) {
  if (!videoId) return '';
  return `<img src="https://img.youtube.com/vi/${videoId}/default.jpg" alt="" class="w-20 h-auto rounded" />`;
}

async function loadVideos() {
  const q = document.getElementById('searchQ').value.trim();
  const type = document.getElementById('filterType').value;
  
  let url = api(`/admin/2025/videos?per_page=${perPage}&page=${currentPage}`);
  if (q) url += `&q=${encodeURIComponent(q)}`;
  if (type) url += `&video_type=${encodeURIComponent(type)}`;
  
  const res = await fetch(url, { headers: authHeader() });
  const json = await res.json();
  
  const videos = json?.data?.items || [];
  totalVideos = json?.meta?.total || videos.length;
  
  const tbody = document.getElementById('videosTable');
  tbody.innerHTML = '';
  
  videos.forEach(v => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="py-2 pr-3">${v.id}</td>
      <td class="py-2 pr-3">${ytThumb(v.video_id)}</td>
      <td class="py-2 pr-3 font-medium">${escapeHtml(v.title || '')}</td>
      <td class="py-2 pr-3">${escapeHtml(v.entity_type || '-')}</td>
      <td class="py-2 pr-3">${escapeHtml(v.video_type || '-')}</td>
      <td class="py-2 pr-3">${(v.published_at || '').split('T')[0]}</td>
      <td class="py-2 pr-3 flex gap-1">
        <a href="https://youtube.com/watch?v=${v.video_id}" target="_blank" class="px-2 h-7 rounded bg-gray-200 dark:bg-white/10 text-xs inline-flex items-center">Watch</a>
        <button class="px-2 h-7 rounded bg-red-600 text-white text-xs" onclick="deleteVideo(${v.id})">Delete</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
  
  document.getElementById('pagerInfo').textContent = `Showing ${videos.length} of ${totalVideos}`;
  document.getElementById('prevPage').disabled = currentPage <= 1;
  document.getElementById('nextPage').disabled = (currentPage * perPage) >= totalVideos;
}

async function addVideo() {
  const title = document.getElementById('addTitle').value.trim();
  const videoId = document.getElementById('addVideoId').value.trim();
  const entityType = document.getElementById('addEntityType').value;
  const msg = document.getElementById('addMsg');
  
  if (!title || !videoId) {
    msg.innerHTML = '<span class="text-red-500">Title and YouTube ID required</span>';
    return;
  }
  
  const { status, json } = await apiCall('POST', '/admin/videos', { 
    title, 
    video_id: videoId, 
    entity_type: entityType,
    video_type: 'music_video'
  });
  
  if (status === 201 || status === 200) {
    msg.innerHTML = '<span class="text-emerald-500">Video added!</span>';
    document.getElementById('addTitle').value = '';
    document.getElementById('addVideoId').value = '';
    loadVideos();
  } else {
    msg.innerHTML = `<span class="text-red-500">Error: ${json?.errors?.[0]?.message || status}</span>`;
  }
}

async function deleteVideo(id) {
  if (!confirm('Delete video #' + id + '?')) return;
  const { status } = await apiCall('DELETE', `/admin/videos/${id}`);
  if (status === 200) {
    loadVideos();
  } else {
    alert('Delete failed: ' + status);
  }
}

document.getElementById('addVideoBtn').addEventListener('click', addVideo);
document.getElementById('searchBtn').addEventListener('click', () => { currentPage = 1; loadVideos(); });
document.getElementById('prevPage').addEventListener('click', () => { if (currentPage > 1) { currentPage--; loadVideos(); } });
document.getElementById('nextPage').addEventListener('click', () => { currentPage++; loadVideos(); });
document.getElementById('refreshBtn').addEventListener('click', loadVideos);

loadVideos();
</script>

