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

$pageTitle = '2025 Data';
$currentPage = 'data-2025';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

      <section class="max-w-7xl mx-auto px-4 py-6 space-y-4">
        <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-4">
          <h1 class="text-lg font-semibold mb-1">NGN 2.0 Data</h1>
          <p class="text-sm text-gray-600 dark:text-gray-300">Spot-check what was migrated into the 2025 databases. Use the tabs to switch between entity types.</p>
        </div>

        <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-4">
          <div class="flex flex-wrap gap-2 mb-3 text-xs">
            <button data-tab="artists" class="tab-btn px-3 py-1.5 rounded bg-brand/10 text-brand border border-brand/20">Artists</button>
            <button data-tab="labels" class="tab-btn px-3 py-1.5 rounded hover:bg-gray-100 dark:hover:bg-white/10">Labels</button>
            <button data-tab="venues" class="tab-btn px-3 py-1.5 rounded hover:bg-gray-100 dark:hover:bg-white/10">Venues</button>
            <button data-tab="stations" class="tab-btn px-3 py-1.5 rounded hover:bg-gray-100 dark:hover:bg-white/10">Stations</button>
            <button data-tab="releases" class="tab-btn px-3 py-1.5 rounded hover:bg-gray-100 dark:hover:bg-white/10">Releases</button>
            <button data-tab="tracks" class="tab-btn px-3 py-1.5 rounded hover:bg-gray-100 dark:hover:bg-white/10">Tracks</button>
            <button data-tab="videos" class="tab-btn px-3 py-1.5 rounded hover:bg-gray-100 dark:hover:bg-white/10">Videos</button>
            <button data-tab="posts" class="tab-btn px-3 py-1.5 rounded hover:bg-gray-100 dark:hover:bg-white/10">Posts</button>
            <button data-tab="spins" class="tab-btn px-3 py-1.5 rounded hover:bg-gray-100 dark:hover:bg-white/10">Spins</button>
          </div>
          <div id="tabSummary" class="text-xs text-gray-500 dark:text-gray-400 mb-2"></div>
          <div class="overflow-auto -mx-4 px-4">
            <table class="min-w-full text-xs" id="dataTable">
              <thead class="border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                <tr id="tableHead"></tr>
              </thead>
              <tbody id="tableBody" class="divide-y divide-gray-100 dark:divide-white/5"></tbody>
            </table>
          </div>
          <div class="mt-3 flex items-center justify-between text-xs">
            <div id="pagerMeta" class="text-gray-500 dark:text-gray-400"></div>
            <div class="flex items-center gap-2">
              <button id="prevPage" class="px-2 py-1 rounded border border-gray-200 dark:border-white/10 disabled:opacity-40">Prev</button>
              <button id="nextPage" class="px-2 py-1 rounded border border-gray-200 dark:border-white/10 disabled:opacity-40">Next</button>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>

<?php include __DIR__.'/_token_store.php'; ?>
  <script>
    const api = (p) => `/api/v1${p}`;
    const authHeader = () => {
      const t = localStorage.getItem('ngn_admin_token') || localStorage.getItem('ngn_access_token') || '';
      return t ? { 'Authorization': 'Bearer ' + t } : {};
    };

    const state = { tab: 'artists', page: 1, perPage: 25 };

    const tabConfig = {
      artists: {
        path: '/admin/2025/artists',
        columns: [
          { key: 'id', label: 'ID' },
          { key: 'name', label: 'Name' },
          { key: 'label_name', label: 'Label' },
          { key: 'latest_release_date', label: 'Latest Release' },
          { key: 'popularity', label: 'Popularity' },
          { key: 'legacy_id', label: 'Legacy ID' },
        ],
      },
      labels: {
        path: '/admin/2025/labels',
        columns: [
          { key: 'id', label: 'ID' },
          { key: 'name', label: 'Name' },
          { key: 'website', label: 'Website' },
          { key: 'legacy_id', label: 'Legacy ID' },
        ],
      },
      venues: {
        path: '/admin/2025/venues',
        columns: [
          { key: 'id', label: 'ID' },
          { key: 'name', label: 'Name' },
          { key: 'city', label: 'City' },
          { key: 'region', label: 'Region' },
          { key: 'country', label: 'Country' },
          { key: 'legacy_id', label: 'Legacy ID' },
        ],
      },
      stations: {
        path: '/admin/2025/stations',
        columns: [
          { key: 'id', label: 'ID' },
          { key: 'name', label: 'Name' },
          { key: 'call_sign', label: 'Callsign' },
          { key: 'region', label: 'Region' },
          { key: 'format', label: 'Format' },
          { key: 'legacy_id', label: 'Legacy ID' },
        ],
      },
      releases: {
        path: '/admin/2025/releases',
        columns: [
          { key: 'id', label: 'ID' },
          { key: 'title', label: 'Title' },
          { key: 'artist_name', label: 'Artist' },
          { key: 'released_at', label: 'Released' },
          { key: 'legacy_id', label: 'Legacy ID' },
        ],
      },
      tracks: {
        path: '/admin/2025/tracks',
        columns: [
          { key: 'id', label: 'ID' },
          { key: 'title', label: 'Title' },
          { key: 'artist_name', label: 'Artist' },
          { key: 'release_title', label: 'Release' },
          { key: 'duration_ms', label: 'Duration (ms)' },
          { key: 'legacy_id', label: 'Legacy ID' },
        ],
      },
      videos: {
        path: '/admin/2025/videos',
        columns: [
          { key: 'id', label: 'ID' },
          { key: 'title', label: 'Title' },
          { key: 'entity_type', label: 'Entity Type' },
          { key: 'video_type', label: 'Video Type' },
          { key: 'video_id', label: 'Video ID' },
          { key: 'published_at', label: 'Published' },
        ],
      },
      posts: {
        path: '/admin/2025/posts',
        columns: [
          { key: 'id', label: 'ID' },
          { key: 'title', label: 'Title' },
          { key: 'entity_type', label: 'Entity Type' },
          { key: 'status', label: 'Status' },
          { key: 'published_at', label: 'Published' },
        ],
      },
      spins: {
        path: '/admin/2025/spins',
        columns: [
          { key: 'id', label: 'ID' },
          { key: 'station_name', label: 'Station' },
          { key: 'artist_name', label: 'Artist' },
          { key: 'track_title', label: 'Track' },
          { key: 'played_at', label: 'Played At' },
        ],
      },
    };

    function setTab(tab){
      state.tab = tab;
      state.page = 1;
      document.querySelectorAll('.tab-btn').forEach(btn => {
        const t = btn.getAttribute('data-tab');
        if (t === tab) {
          btn.classList.add('bg-brand/10','text-brand','border','border-brand/20');
        } else {
          btn.classList.remove('bg-brand/10','text-brand','border','border-brand/20');
        }
      });
      loadData();
    }

    async function loadData(){
      const cfg = tabConfig[state.tab];
      if (!cfg) return;
      const url = new URL(api(cfg.path), window.location.origin);
      url.searchParams.set('page', String(state.page));
      url.searchParams.set('per_page', String(state.perPage));
      try {
        const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json', ...authHeader() } });
        if (!res.ok) {
          document.getElementById('tableBody').innerHTML = `<tr><td class="px-2 py-2 text-red-500" colspan="99">Error ${res.status}</td></tr>`;
          return;
        }
        const payload = await res.json();
        const data = payload && payload.data ? payload.data : payload;
        const items = (data && Array.isArray(data.items)) ? data.items : [];
        const total = (data && typeof data.total === 'number') ? data.total : items.length;
        renderTable(cfg, items);
        const from = items.length ? ((state.page-1)*state.perPage + 1) : 0;
        const to = items.length ? ((state.page-1)*state.perPage + items.length) : 0;
        document.getElementById('pagerMeta').textContent = total ? `${from}–${to} of ${total}` : 'No records';
        document.getElementById('prevPage').disabled = state.page <= 1;
        document.getElementById('nextPage').disabled = (state.page * state.perPage) >= total;
        document.getElementById('tabSummary').textContent = total ? `${total} records in this view` : 'No records found in this view.';
      } catch (e) {
        document.getElementById('tableBody').innerHTML = `<tr><td class="px-2 py-2 text-red-500" colspan="99">Failed to load data</td></tr>`;
      }
    }

    function renderTable(cfg, items){
      const head = document.getElementById('tableHead');
      const body = document.getElementById('tableBody');
      head.innerHTML = '';
      body.innerHTML = '';
      cfg.columns.forEach(col => {
        const th = document.createElement('th');
        th.className = 'px-2 py-1 text-left font-semibold';
        th.textContent = col.label;
        head.appendChild(th);
      });
      if (!items.length) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = cfg.columns.length;
        td.className = 'px-2 py-3 text-center text-gray-500 dark:text-gray-400';
        td.textContent = 'No records to display';
        tr.appendChild(td);
        body.appendChild(tr);
        return;
      }
      items.forEach(row => {
        const tr = document.createElement('tr');
        cfg.columns.forEach(col => {
          const td = document.createElement('td');
          td.className = 'px-2 py-1 whitespace-nowrap max-w-xs truncate';
          let v = row[col.key];
          if (v === null || v === undefined || v === '') v = '—';
          td.textContent = String(v);
          tr.appendChild(td);
        });
        body.appendChild(tr);
      });
    }

    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => setTab(btn.getAttribute('data-tab')));
      });
      document.getElementById('prevPage').addEventListener('click', () => {
        if (state.page > 1) { state.page -= 1; loadData(); }
      });
      document.getElementById('nextPage').addEventListener('click', () => {
        state.page += 1; loadData();
      });
      loadData();
    });
  </script>
</body>
</html>

