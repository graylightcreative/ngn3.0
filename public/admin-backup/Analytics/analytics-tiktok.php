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

// Check TikTok configuration
$tiktokConfigured = false;
$tiktokClientKey = getenv('TIKTOK_CLIENT_KEY') ?: '';
if ($tiktokClientKey && getenv('TIKTOK_CLIENT_SECRET')) {
    $tiktokConfigured = true;
}

$pageTitle = 'TikTok Analytics';
$currentPage = 'analytics-tiktok';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

  <main class="flex-1 p-4 md:p-6 overflow-y-auto">
    <section class="max-w-6xl mx-auto space-y-6">

      <!-- Configuration Status -->
      <div class="rounded-lg border <?= $tiktokConfigured ? 'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/50 dark:bg-emerald-500/5' : 'border-amber-200 dark:border-amber-500/30 bg-amber-50/50 dark:bg-amber-500/5' ?> p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full flex items-center justify-center <?= $tiktokConfigured ? 'bg-emerald-100 dark:bg-emerald-500/20' : 'bg-amber-100 dark:bg-amber-500/20' ?>">
            <svg class="w-5 h-5 <?= $tiktokConfigured ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' ?>" fill="currentColor" viewBox="0 0 24 24">
              <path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-5.2 1.74 2.89 2.89 0 012.31-4.64 2.93 2.93 0 01.88.13V9.4a6.84 6.84 0 00-1-.05A6.33 6.33 0 005 20.1a6.34 6.34 0 0010.86-4.43v-7a8.16 8.16 0 004.77 1.52v-3.4a4.85 4.85 0 01-1-.1z"/>
            </svg>
          </div>
          <div class="flex-1">
            <h3 class="font-semibold <?= $tiktokConfigured ? 'text-emerald-800 dark:text-emerald-200' : 'text-amber-800 dark:text-amber-200' ?>">
              <?= $tiktokConfigured ? 'TikTok API Configured' : 'TikTok API Not Configured' ?>
            </h3>
            <p class="text-sm <?= $tiktokConfigured ? 'text-emerald-600 dark:text-emerald-300' : 'text-amber-600 dark:text-amber-300' ?>">
              <?php if ($tiktokConfigured): ?>
                Client Key: <?= htmlspecialchars(substr($tiktokClientKey, 0, 8)) ?>...
              <?php else: ?>
                Set TIKTOK_CLIENT_KEY and TIKTOK_CLIENT_SECRET in your .env file
              <?php endif; ?>
            </p>
          </div>
          <?php if (!$tiktokConfigured): ?>
          <a href="/admin/env.php" class="px-4 py-2 rounded bg-amber-500 text-white text-sm hover:bg-amber-600">Configure</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($tiktokConfigured): ?>
      <!-- OAuth Connection -->
      <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <h3 class="font-semibold mb-4">Connect TikTok Accounts</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
          Connect TikTok creator accounts to pull analytics including followers, likes, and video performance.
        </p>
        <div class="flex gap-3">
          <button id="btnConnect" class="px-4 py-2 rounded text-white text-sm" style="background: #000">
            Connect TikTok Account
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
        <h3 class="font-semibold mb-4">TikTok Analytics Snapshots</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 dark:border-white/10">
                <th class="text-left py-2 px-3 font-medium">Entity</th>
                <th class="text-left py-2 px-3 font-medium">TikTok ID</th>
                <th class="text-right py-2 px-3 font-medium">Followers</th>
                <th class="text-right py-2 px-3 font-medium">Likes</th>
                <th class="text-right py-2 px-3 font-medium">Videos</th>
                <th class="text-left py-2 px-3 font-medium">Snapshot Date</th>
              </tr>
            </thead>
            <tbody id="snapshotsTable">
              <tr><td colspan="6" class="py-4 text-center text-gray-500 dark:text-gray-400">Loading...</td></tr>
            </tbody>
          </table>
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

  async function loadAccounts() {
    const el = document.getElementById('accountsList');
    if (!el) return;
    try {
      const r = await api('/admin/analytics/tiktok/accounts');
      if (r.status === 200 && r.json?.data) {
        const accounts = r.json.data;
        if (accounts.length === 0) {
          el.innerHTML = '<div class="text-gray-500 dark:text-gray-400 text-sm">No connected accounts yet</div>';
        } else {
          el.innerHTML = accounts.map(a => `
            <div class="flex items-center justify-between p-3 rounded bg-gray-50 dark:bg-white/5">
              <div>
                <div class="font-medium">${escapeHtml(a.entity_name || 'Unknown')}</div>
                <div class="text-xs text-gray-500">@${a.provider_user_id || 'N/A'} · Expires: ${a.expires_at || 'N/A'}</div>
              </div>
              <button onclick="disconnectAccount(${a.id})" class="text-red-500 text-sm hover:underline">Disconnect</button>
            </div>
          `).join('');
        }
      }
    } catch (e) {
      el.innerHTML = '<div class="text-red-500 text-sm">Error loading accounts</div>';
    }
  }

  async function loadSnapshots() {
    const el = document.getElementById('snapshotsTable');
    if (!el) return;
    try {
      const r = await api('/admin/analytics/tiktok/snapshots?limit=20');
      if (r.status === 200 && r.json?.data) {
        const snaps = r.json.data;
        if (snaps.length === 0) {
          el.innerHTML = '<tr><td colspan="6" class="py-4 text-center text-gray-500 dark:text-gray-400">No snapshots yet</td></tr>';
        } else {
          el.innerHTML = snaps.map(s => {
            const data = typeof s.data === 'string' ? JSON.parse(s.data) : (s.data || {});
            return `
            <tr class="border-b border-gray-100 dark:border-white/5">
              <td class="py-2 px-3">${escapeHtml(s.entity_name || 'ID:' + s.entity_id)}</td>
              <td class="py-2 px-3 font-mono text-xs">${escapeHtml(s.external_id || '')}</td>
              <td class="py-2 px-3 text-right">${formatNumber(s.followers)}</td>
              <td class="py-2 px-3 text-right">${formatNumber(data.likes_count)}</td>
              <td class="py-2 px-3 text-right">${formatNumber(s.videos_count)}</td>
              <td class="py-2 px-3">${s.snapshot_date || ''}</td>
            </tr>
          `}).join('');
        }
      }
    } catch (e) {}
  }

  function escapeHtml(s) { return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : ''; }
  function formatNumber(n) { return n != null ? Number(n).toLocaleString() : '—'; }

  document.getElementById('btnConnect')?.addEventListener('click', async () => {
    const status = document.getElementById('connectStatus');
    status.className = 'mt-3 text-sm text-gray-600';
    status.textContent = 'Generating authorization URL...';
    status.classList.remove('hidden');
    try {
      const r = await api('/admin/analytics/tiktok/auth-url', { method: 'POST' });
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

  document.getElementById('btnRefresh')?.addEventListener('click', () => {
    loadAccounts();
    loadSnapshots();
  });

  loadAccounts();
  loadSnapshots();
})();
</script>

