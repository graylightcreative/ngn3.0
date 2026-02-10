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

// Check platform configurations
$spotifyConfigured = (bool)(getenv('SPOTIFY_CLIENT_ID') && getenv('SPOTIFY_CLIENT_SECRET'));
$metaConfigured = (bool)(getenv('FACEBOOK_APP_ID') && getenv('FACEBOOK_APP_SECRET'));
$tiktokConfigured = (bool)(getenv('TIKTOK_CLIENT_KEY') && getenv('TIKTOK_CLIENT_SECRET'));

$pageTitle = 'Unified Analytics';
$currentPage = 'analytics';
include dirname(__DIR__).'/_header.php';

include dirname(__DIR__).'/_topbar.php';
?>

  <main class="flex-1 p-4 md:p-6 overflow-y-auto">
    <section class="max-w-7xl mx-auto space-y-6">

      <!-- Platform Status Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Spotify -->
        <div class="rounded-lg border <?= $spotifyConfigured ? 'border-emerald-200 dark:border-emerald-500/30' : 'border-gray-200 dark:border-white/10' ?> p-4 bg-white/70 dark:bg-white/5">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full flex items-center justify-center bg-green-500/20">
              <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" stroke-width="2"/>
                <path stroke-linecap="round" stroke-width="2" d="M8 15c2-1 4-1 6 0M7 12c2.5-1.5 5.5-1.5 8 0M6 9c3-2 7-2 10 0"/>
              </svg>
            </div>
            <div>
              <h3 class="font-semibold">Spotify</h3>
              <p class="text-xs <?= $spotifyConfigured ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500' ?>"><?= $spotifyConfigured ? 'Configured' : 'Not configured' ?></p>
            </div>
          </div>
          <div id="spotifyStats" class="space-y-1 text-sm">
            <div class="flex justify-between"><span class="text-gray-500">Accounts:</span><span id="spotifyAccounts">—</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Snapshots:</span><span id="spotifySnapshots">—</span></div>
          </div>
          <a href="/admin/analytics-spotify.php" class="mt-3 block text-center text-sm text-brand hover:underline">Manage →</a>
        </div>

        <!-- Meta/Facebook -->
        <div class="rounded-lg border <?= $metaConfigured ? 'border-emerald-200 dark:border-emerald-500/30' : 'border-gray-200 dark:border-white/10' ?> p-4 bg-white/70 dark:bg-white/5">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full flex items-center justify-center bg-blue-500/20">
              <svg class="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 24 24">
                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
              </svg>
            </div>
            <div>
              <h3 class="font-semibold">Meta/Facebook</h3>
              <p class="text-xs <?= $metaConfigured ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500' ?>"><?= $metaConfigured ? 'Configured' : 'Not configured' ?></p>
            </div>
          </div>
          <div id="metaStats" class="space-y-1 text-sm">
            <div class="flex justify-between"><span class="text-gray-500">Accounts:</span><span id="metaAccounts">—</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Snapshots:</span><span id="metaSnapshots">—</span></div>
          </div>
          <a href="/admin/analytics-meta.php" class="mt-3 block text-center text-sm text-brand hover:underline">Manage →</a>
        </div>

        <!-- TikTok -->
        <div class="rounded-lg border <?= $tiktokConfigured ? 'border-emerald-200 dark:border-emerald-500/30' : 'border-gray-200 dark:border-white/10' ?> p-4 bg-white/70 dark:bg-white/5">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full flex items-center justify-center bg-gray-800/20 dark:bg-white/10">
              <svg class="w-5 h-5 text-gray-800 dark:text-white" fill="currentColor" viewBox="0 0 24 24">
                <path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-5.2 1.74 2.89 2.89 0 012.31-4.64 2.93 2.93 0 01.88.13V9.4a6.84 6.84 0 00-1-.05A6.33 6.33 0 005 20.1a6.34 6.34 0 0010.86-4.43v-7a8.16 8.16 0 004.77 1.52v-3.4a4.85 4.85 0 01-1-.1z"/>
              </svg>
            </div>
            <div>
              <h3 class="font-semibold">TikTok</h3>
              <p class="text-xs <?= $tiktokConfigured ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500' ?>"><?= $tiktokConfigured ? 'Configured' : 'Not configured' ?></p>
            </div>
          </div>
          <div id="tiktokStats" class="space-y-1 text-sm">
            <div class="flex justify-between"><span class="text-gray-500">Accounts:</span><span id="tiktokAccounts">—</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Snapshots:</span><span id="tiktokSnapshots">—</span></div>
          </div>
          <a href="/admin/analytics-tiktok.php" class="mt-3 block text-center text-sm text-brand hover:underline">Manage →</a>
        </div>
      </div>

      <!-- Recent Snapshots Across All Platforms -->
      <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-semibold">Recent Analytics Snapshots (All Platforms)</h3>
          <button id="btnRefresh" class="text-sm text-brand hover:underline">Refresh</button>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 dark:border-white/10">
                <th class="text-left py-2 px-3 font-medium">Platform</th>
                <th class="text-left py-2 px-3 font-medium">Entity</th>
                <th class="text-right py-2 px-3 font-medium">Followers</th>
                <th class="text-right py-2 px-3 font-medium">Engagement</th>
                <th class="text-left py-2 px-3 font-medium">Date</th>
              </tr>
            </thead>
            <tbody id="allSnapshotsTable">
              <tr><td colspan="5" class="py-4 text-center text-gray-500 dark:text-gray-400">Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Top Artists by Platform -->
      <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <h3 class="font-semibold mb-4">Top Artists by Followers</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <h4 class="text-sm font-medium text-gray-500 mb-2">Spotify</h4>
            <div id="topSpotify" class="space-y-2 text-sm">Loading...</div>
          </div>
          <div>
            <h4 class="text-sm font-medium text-gray-500 mb-2">Facebook/Instagram</h4>
            <div id="topMeta" class="space-y-2 text-sm">Loading...</div>
          </div>
          <div>
            <h4 class="text-sm font-medium text-gray-500 mb-2">TikTok</h4>
            <div id="topTiktok" class="space-y-2 text-sm">Loading...</div>
          </div>
        </div>
      </div>

    </section>
  </main>
</div>

<?php include dirname(__DIR__).'/_footer.php'; ?>

<script>
(function(){
  const token = () => localStorage.getItem('ngn_admin_token') || localStorage.getItem('admin_token') || '';
  const api = (path) => fetch('/api/v1' + path, {
    headers: { 'Authorization': 'Bearer ' + token(), 'Content-Type': 'application/json' }
  }).then(r => r.json().then(j => ({ status: r.status, json: j })).catch(() => ({ status: r.status, json: null })));

  function escapeHtml(s) { return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : ''; }
  function formatNumber(n) { return n != null ? Number(n).toLocaleString() : '—'; }

  function platformBadge(p) {
    const colors = { spotify: 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400', facebook: 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400', instagram: 'bg-pink-100 text-pink-700 dark:bg-pink-500/20 dark:text-pink-400', tiktok: 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300' };
    return `<span class="px-2 py-0.5 rounded text-xs font-medium ${colors[p] || colors.tiktok}">${p}</span>`;
  }

  async function loadStats() {
    // Spotify
    try {
      const [acc, snap] = await Promise.all([api('/admin/analytics/spotify/accounts'), api('/admin/analytics/spotify/snapshots?limit=1')]);
      document.getElementById('spotifyAccounts').textContent = acc.json?.data?.length ?? 0;
      document.getElementById('spotifySnapshots').textContent = snap.json?.data?.length ?? 0;
    } catch(e) {}
    // Meta
    try {
      const [acc, snap] = await Promise.all([api('/admin/analytics/meta/accounts'), api('/admin/analytics/meta/snapshots?limit=1')]);
      document.getElementById('metaAccounts').textContent = acc.json?.data?.length ?? 0;
      document.getElementById('metaSnapshots').textContent = snap.json?.data?.length ?? 0;
    } catch(e) {}
    // TikTok
    try {
      const [acc, snap] = await Promise.all([api('/admin/analytics/tiktok/accounts'), api('/admin/analytics/tiktok/snapshots?limit=1')]);
      document.getElementById('tiktokAccounts').textContent = acc.json?.data?.length ?? 0;
      document.getElementById('tiktokSnapshots').textContent = snap.json?.data?.length ?? 0;
    } catch(e) {}
  }

  async function loadAllSnapshots() {
    const el = document.getElementById('allSnapshotsTable');
    try {
      const [sp, meta, tt] = await Promise.all([
        api('/admin/analytics/spotify/snapshots?limit=10'),
        api('/admin/analytics/meta/snapshots?limit=10'),
        api('/admin/analytics/tiktok/snapshots?limit=10')
      ]);
      const all = [...(sp.json?.data || []), ...(meta.json?.data || []), ...(tt.json?.data || [])];
      all.sort((a,b) => (b.snapshot_date || '').localeCompare(a.snapshot_date || ''));
      const top20 = all.slice(0, 20);
      if (top20.length === 0) {
        el.innerHTML = '<tr><td colspan="5" class="py-4 text-center text-gray-500">No snapshots yet</td></tr>';
      } else {
        el.innerHTML = top20.map(s => `
          <tr class="border-b border-gray-100 dark:border-white/5">
            <td class="py-2 px-3">${platformBadge(s.provider)}</td>
            <td class="py-2 px-3">${escapeHtml(s.entity_name || 'ID:' + s.entity_id)}</td>
            <td class="py-2 px-3 text-right">${formatNumber(s.followers)}</td>
            <td class="py-2 px-3 text-right">${s.engagement_rate ? (s.engagement_rate * 100).toFixed(1) + '%' : '—'}</td>
            <td class="py-2 px-3">${s.snapshot_date || ''}</td>
          </tr>
        `).join('');
      }
    } catch(e) { el.innerHTML = '<tr><td colspan="5" class="py-4 text-center text-red-500">Error loading</td></tr>'; }
  }

  async function loadTopArtists() {
    // Spotify
    try {
      const r = await api('/admin/analytics/spotify/snapshots?limit=5');
      const el = document.getElementById('topSpotify');
      const snaps = (r.json?.data || []).sort((a,b) => (b.followers || 0) - (a.followers || 0)).slice(0,5);
      el.innerHTML = snaps.length ? snaps.map(s => `<div class="flex justify-between"><span>${escapeHtml(s.entity_name || 'ID:'+s.entity_id)}</span><span class="text-gray-500">${formatNumber(s.followers)}</span></div>`).join('') : '<div class="text-gray-500">No data</div>';
    } catch(e) {}
    // Meta
    try {
      const r = await api('/admin/analytics/meta/snapshots?limit=10');
      const el = document.getElementById('topMeta');
      const snaps = (r.json?.data || []).sort((a,b) => (b.followers || 0) - (a.followers || 0)).slice(0,5);
      el.innerHTML = snaps.length ? snaps.map(s => `<div class="flex justify-between"><span>${escapeHtml(s.entity_name || 'ID:'+s.entity_id)}</span><span class="text-gray-500">${formatNumber(s.followers)}</span></div>`).join('') : '<div class="text-gray-500">No data</div>';
    } catch(e) {}
    // TikTok
    try {
      const r = await api('/admin/analytics/tiktok/snapshots?limit=5');
      const el = document.getElementById('topTiktok');
      const snaps = (r.json?.data || []).sort((a,b) => (b.followers || 0) - (a.followers || 0)).slice(0,5);
      el.innerHTML = snaps.length ? snaps.map(s => `<div class="flex justify-between"><span>${escapeHtml(s.entity_name || 'ID:'+s.entity_id)}</span><span class="text-gray-500">${formatNumber(s.followers)}</span></div>`).join('') : '<div class="text-gray-500">No data</div>';
    } catch(e) {}
  }

  document.getElementById('btnRefresh')?.addEventListener('click', () => { loadStats(); loadAllSnapshots(); loadTopArtists(); });
  loadStats(); loadAllSnapshots(); loadTopArtists();
})();
</script>

