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

// Check Spotify configuration
$spotifyConfigured = false;
$spotifyClientId = getenv('SPOTIFY_CLIENT_ID') ?: '';
$spotifyRedirectUri = getenv('SPOTIFY_REDIRECT_URI') ?: '';
if ($spotifyClientId && getenv('SPOTIFY_CLIENT_SECRET')) {
    $spotifyConfigured = true;
}

$pageTitle = 'Spotify Analytics';
$currentPage = 'analytics-spotify';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

  <main class="flex-1 p-4 md:p-6 overflow-y-auto">
    <section class="max-w-6xl mx-auto space-y-6">

      <!-- Configuration Status -->
      <div class="rounded-lg border <?= $spotifyConfigured ? 'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/50 dark:bg-emerald-500/5' : 'border-amber-200 dark:border-amber-500/30 bg-amber-50/50 dark:bg-amber-500/5' ?> p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full flex items-center justify-center <?= $spotifyConfigured ? 'bg-emerald-100 dark:bg-emerald-500/20' : 'bg-amber-100 dark:bg-amber-500/20' ?>">
            <svg class="w-5 h-5 <?= $spotifyConfigured ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' ?>" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/>
            </svg>
          </div>
          <div class="flex-1">
            <h3 class="font-semibold <?= $spotifyConfigured ? 'text-emerald-800 dark:text-emerald-200' : 'text-amber-800 dark:text-amber-200' ?>">
              <?= $spotifyConfigured ? 'Spotify API Configured' : 'Spotify API Not Configured' ?>
            </h3>
            <p class="text-sm <?= $spotifyConfigured ? 'text-emerald-600 dark:text-emerald-300' : 'text-amber-600 dark:text-amber-300' ?>">
              <?php if ($spotifyConfigured): ?>
                Client ID: <?= htmlspecialchars(substr($spotifyClientId, 0, 8)) ?>...
              <?php else: ?>
                Set SPOTIFY_CLIENT_ID, SPOTIFY_CLIENT_SECRET, and SPOTIFY_REDIRECT_URI in your .env file
              <?php endif; ?>
            </p>
          </div>
          <?php if (!$spotifyConfigured): ?>
          <a href="/admin/env.php" class="px-4 py-2 rounded bg-amber-500 text-white text-sm hover:bg-amber-600">Configure</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($spotifyConfigured): ?>
      <!-- OAuth Connection -->
      <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <h3 class="font-semibold mb-4">Connect Artist Accounts</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
          Artists can connect their Spotify accounts to pull analytics data including popularity, followers, and monthly listeners.
        </p>
        <div class="flex gap-3">
          <button id="btnConnect" class="px-4 py-2 rounded bg-brand text-white text-sm hover:bg-brand-dark">
            Connect Spotify Account
          </button>
          <button id="btnSync" class="px-4 py-2 rounded border border-gray-200 dark:border-white/10 text-sm hover:bg-gray-50 dark:hover:bg-white/10">
            Sync All Artists
          </button>
        </div>
        <div id="connectStatus" class="mt-3 text-sm hidden"></div>
      </div>

      <!-- Connected Accounts -->
      <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-semibold">Connected Accounts</h3>
          <button id="btnRefresh" class="text-sm text-brand hover:underline">Refresh</button>
        </div>
        <div id="accountsList" class="space-y-2">
          <div class="text-gray-500 dark:text-gray-400 text-sm">Loading...</div>
        </div>
      </div>

      <!-- Analytics Snapshots -->
      <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <h3 class="font-semibold mb-4">Recent Analytics Snapshots</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 dark:border-white/10">
                <th class="text-left py-2 px-3 font-medium">Artist</th>
                <th class="text-left py-2 px-3 font-medium">Spotify ID</th>
                <th class="text-right py-2 px-3 font-medium">Popularity</th>
                <th class="text-right py-2 px-3 font-medium">Followers</th>
                <th class="text-right py-2 px-3 font-medium">Monthly Listeners</th>
                <th class="text-left py-2 px-3 font-medium">Snapshot Date</th>
              </tr>
            </thead>
            <tbody id="snapshotsTable">
              <tr><td colspan="6" class="py-4 text-center text-gray-500 dark:text-gray-400">Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Manual Lookup -->
      <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <h3 class="font-semibold mb-4">Manual Artist Lookup</h3>
        <div class="flex gap-3 mb-4">
          <input type="text" id="spotifyIdInput" placeholder="Spotify Artist ID (e.g., 0OdUWJ0sBjDrqHygGUXeCF)" 
                 class="flex-1 px-3 py-2 rounded border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 text-sm">
          <button id="btnLookup" class="px-4 py-2 rounded bg-brand text-white text-sm hover:bg-brand-dark">Lookup</button>
        </div>
        <div id="lookupResult" class="hidden">
          <pre class="p-3 rounded bg-gray-50 dark:bg-white/5 text-xs overflow-x-auto"></pre>
        </div>
      </div>
      <?php endif; ?>

    </section>
  </main>
</div>

<?php include __DIR__.'/_footer.php'; ?>

<script>
(function(){
  const token = () => localStorage.getItem('ngn_admin_token') || localStorage.getItem('admin_token') || '';
  const api = (path, opts = {}) => fetch('/api/v1' + path, {
    ...opts,
    headers: { 'Authorization': 'Bearer ' + token(), 'Content-Type': 'application/json', ...(opts.headers || {}) }
  }).then(r => r.json().then(j => ({ status: r.status, json: j })).catch(() => ({ status: r.status, json: null })));

  // Load connected accounts
  async function loadAccounts() {
    const el = document.getElementById('accountsList');
    if (!el) return;
    try {
      const r = await api('/admin/analytics/spotify/accounts');
      if (r.status === 200 && r.json?.data) {
        const accounts = r.json.data;
        if (accounts.length === 0) {
          el.innerHTML = '<div class="text-gray-500 dark:text-gray-400 text-sm">No connected accounts yet</div>';
        } else {
          el.innerHTML = accounts.map(a => `
            <div class="flex items-center justify-between p-3 rounded bg-gray-50 dark:bg-white/5">
              <div>
                <div class="font-medium">${escapeHtml(a.entity_name || 'Unknown')}</div>
                <div class="text-xs text-gray-500">${a.provider_user_id || ''} · Expires: ${a.expires_at || 'N/A'}</div>
              </div>
              <button onclick="disconnectAccount(${a.id})" class="text-red-500 text-sm hover:underline">Disconnect</button>
            </div>
          `).join('');
        }
      } else {
        el.innerHTML = '<div class="text-amber-500 text-sm">Could not load accounts</div>';
      }
    } catch (e) {
      el.innerHTML = '<div class="text-red-500 text-sm">Error loading accounts</div>';
    }
  }

  // Load snapshots
  async function loadSnapshots() {
    const el = document.getElementById('snapshotsTable');
    if (!el) return;
    try {
      const r = await api('/admin/analytics/spotify/snapshots?limit=20');
      if (r.status === 200 && r.json?.data) {
        const snaps = r.json.data;
        if (snaps.length === 0) {
          el.innerHTML = '<tr><td colspan="6" class="py-4 text-center text-gray-500 dark:text-gray-400">No snapshots yet</td></tr>';
        } else {
          el.innerHTML = snaps.map(s => `
            <tr class="border-b border-gray-100 dark:border-white/5">
              <td class="py-2 px-3">${escapeHtml(s.entity_name || 'ID:' + s.entity_id)}</td>
              <td class="py-2 px-3 font-mono text-xs">${escapeHtml(s.external_id || '')}</td>
              <td class="py-2 px-3 text-right">${s.popularity ?? '—'}</td>
              <td class="py-2 px-3 text-right">${formatNumber(s.followers)}</td>
              <td class="py-2 px-3 text-right">${formatNumber(s.monthly_listeners)}</td>
              <td class="py-2 px-3">${s.snapshot_date || ''}</td>
            </tr>
          `).join('');
        }
      } else {
        el.innerHTML = '<tr><td colspan="6" class="py-4 text-center text-amber-500">Could not load snapshots</td></tr>';
      }
    } catch (e) {
      el.innerHTML = '<tr><td colspan="6" class="py-4 text-center text-red-500">Error loading snapshots</td></tr>';
    }
  }

  function escapeHtml(s) { return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : ''; }
  function formatNumber(n) { return n != null ? Number(n).toLocaleString() : '—'; }

  // Connect button
  document.getElementById('btnConnect')?.addEventListener('click', async () => {
    const status = document.getElementById('connectStatus');
    status.className = 'mt-3 text-sm text-gray-600';
    status.textContent = 'Generating authorization URL...';
    status.classList.remove('hidden');
    try {
      const r = await api('/admin/analytics/spotify/auth-url', { method: 'POST' });
      if (r.status === 200 && r.json?.data?.url) {
        window.location.href = r.json.data.url;
      } else {
        status.className = 'mt-3 text-sm text-red-500';
        status.textContent = r.json?.errors?.[0]?.message || 'Failed to generate auth URL';
      }
    } catch (e) {
      status.className = 'mt-3 text-sm text-red-500';
      status.textContent = 'Error: ' + e.message;
    }
  });

  // Sync button
  document.getElementById('btnSync')?.addEventListener('click', async () => {
    const status = document.getElementById('connectStatus');
    status.className = 'mt-3 text-sm text-gray-600';
    status.textContent = 'Syncing all artists...';
    status.classList.remove('hidden');
    try {
      const r = await api('/admin/analytics/spotify/sync', { method: 'POST' });
      if (r.status === 200) {
        status.className = 'mt-3 text-sm text-emerald-600';
        status.textContent = 'Sync complete: ' + (r.json?.data?.synced || 0) + ' artists updated';
        loadSnapshots();
      } else {
        status.className = 'mt-3 text-sm text-red-500';
        status.textContent = r.json?.errors?.[0]?.message || 'Sync failed';
      }
    } catch (e) {
      status.className = 'mt-3 text-sm text-red-500';
      status.textContent = 'Error: ' + e.message;
    }
  });

  // Lookup button
  document.getElementById('btnLookup')?.addEventListener('click', async () => {
    const input = document.getElementById('spotifyIdInput');
    const result = document.getElementById('lookupResult');
    const spotifyId = input?.value?.trim();
    if (!spotifyId) return;
    result.classList.remove('hidden');
    result.querySelector('pre').textContent = 'Looking up...';
    try {
      const r = await api('/admin/analytics/spotify/lookup?id=' + encodeURIComponent(spotifyId));
      result.querySelector('pre').textContent = JSON.stringify(r.json?.data || r.json, null, 2);
    } catch (e) {
      result.querySelector('pre').textContent = 'Error: ' + e.message;
    }
  });

  // Refresh button
  document.getElementById('btnRefresh')?.addEventListener('click', () => {
    loadAccounts();
    loadSnapshots();
  });

  // Initial load
  loadAccounts();
  loadSnapshots();
})();
</script>

