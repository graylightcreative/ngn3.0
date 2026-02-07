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

// Check Meta configuration
$metaConfigured = false;
$fbAppId = \NGN\Lib\Env::get('FACEBOOK_APP_ID', '') ?: '';
$fbAppSecret = \NGN\Lib\Env::get('FACEBOOK_APP_SECRET', '') ?: '';
if ($fbAppId && $fbAppSecret) {
    $metaConfigured = true;
}

$pageTitle = 'Meta Analytics';
$currentPage = 'analytics-meta';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

  <main class="flex-1 p-4 md:p-6 overflow-y-auto">
    <section class="max-w-6xl mx-auto space-y-6">

      <!-- Configuration Status -->
      <div class="rounded-lg border <?= $metaConfigured ? 'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/50 dark:bg-emerald-500/5' : 'border-amber-200 dark:border-amber-500/30 bg-amber-50/50 dark:bg-amber-500/5' ?> p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full flex items-center justify-center <?= $metaConfigured ? 'bg-emerald-100 dark:bg-emerald-500/20' : 'bg-amber-100 dark:bg-amber-500/20' ?>">
            <svg class="w-5 h-5 <?= $metaConfigured ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' ?>" fill="currentColor" viewBox="0 0 24 24">
              <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
            </svg>
          </div>
          <div class="flex-1">
            <h3 class="font-semibold <?= $metaConfigured ? 'text-emerald-800 dark:text-emerald-200' : 'text-amber-800 dark:text-amber-200' ?>">
              <?= $metaConfigured ? 'Meta API Configured' : 'Meta API Not Configured' ?>
            </h3>
            <p class="text-sm <?= $metaConfigured ? 'text-emerald-600 dark:text-emerald-300' : 'text-amber-600 dark:text-amber-300' ?>">
              <?php if ($metaConfigured): ?>
                App ID: <?= htmlspecialchars(substr($fbAppId, 0, 8)) ?>...
              <?php else: ?>
                Set FACEBOOK_APP_ID and FACEBOOK_APP_SECRET in your .env file
              <?php endif; ?>
            </p>
          </div>
          <?php if (!$metaConfigured): ?>
          <a href="/admin/env.php" class="px-4 py-2 rounded bg-amber-500 text-white text-sm hover:bg-amber-600">Configure</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($metaConfigured): ?>
      <!-- OAuth Connection -->
      <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <h3 class="font-semibold mb-4">Connect Facebook Pages</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
          Connect Facebook Pages to pull insights including reach, engagement, and follower metrics. Instagram Business accounts linked to pages will also be available.
        </p>
        <div class="flex gap-3">
          <button id="btnConnect" class="px-4 py-2 rounded bg-blue-600 text-white text-sm hover:bg-blue-700">
            Connect Facebook Page
          </button>
        </div>
        <div id="connectStatus" class="mt-3 text-sm hidden"></div>
      </div>

      <!-- Connected Accounts -->
      <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-semibold">Connected Accounts</h3>
          <div class="flex gap-2">
            <button id="btnFetchInsights" class="px-3 py-1 rounded bg-emerald-600 text-white text-sm hover:bg-emerald-700">Fetch Insights</button>
            <button id="btnRefresh" class="text-sm text-brand hover:underline">Refresh</button>
          </div>
        </div>
        <div id="accountsList" class="space-y-2">
          <div class="text-gray-500 dark:text-gray-400 text-sm">Loading...</div>
        </div>
        <div id="fetchStatus" class="mt-3 text-sm hidden"></div>
      </div>

      <!-- Facebook Insights -->
      <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <h3 class="font-semibold mb-4">Facebook Page Insights</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 dark:border-white/10">
                <th class="text-left py-2 px-3 font-medium">Entity</th>
                <th class="text-left py-2 px-3 font-medium">Page</th>
                <th class="text-right py-2 px-3 font-medium">Followers</th>
                <th class="text-right py-2 px-3 font-medium">Reach</th>
                <th class="text-right py-2 px-3 font-medium">Engagement</th>
                <th class="text-left py-2 px-3 font-medium">Snapshot Date</th>
              </tr>
            </thead>
            <tbody id="fbSnapshotsTable">
              <tr><td colspan="6" class="py-4 text-center text-gray-500 dark:text-gray-400">Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Instagram Insights -->
      <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <h3 class="font-semibold mb-4">Instagram Business Insights</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 dark:border-white/10">
                <th class="text-left py-2 px-3 font-medium">Entity</th>
                <th class="text-left py-2 px-3 font-medium">Account</th>
                <th class="text-right py-2 px-3 font-medium">Followers</th>
                <th class="text-right py-2 px-3 font-medium">Reach</th>
                <th class="text-right py-2 px-3 font-medium">Profile Views</th>
                <th class="text-left py-2 px-3 font-medium">Snapshot Date</th>
              </tr>
            </thead>
            <tbody id="igSnapshotsTable">
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
      const r = await api('/admin/analytics/meta/accounts');
      if (r.status === 200 && r.json?.data) {
        const accounts = r.json.data;
        if (accounts.length === 0) {
          el.innerHTML = '<div class="text-gray-500 dark:text-gray-400 text-sm">No connected accounts yet</div>';
        } else {
          el.innerHTML = accounts.map(a => `
            <div class="flex items-center justify-between p-3 rounded bg-gray-50 dark:bg-white/5">
              <div>
                <div class="font-medium">${escapeHtml(a.entity_name || 'Unknown')}</div>
                <div class="text-xs text-gray-500">Page: ${a.provider_page_id || 'N/A'} · Expires: ${a.expires_at || 'N/A'}</div>
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
    const fbEl = document.getElementById('fbSnapshotsTable');
    const igEl = document.getElementById('igSnapshotsTable');
    
    try {
      const r = await api('/admin/analytics/meta/snapshots?limit=20');
      if (r.status === 200 && r.json?.data) {
        const snaps = r.json.data;
        const fbSnaps = snaps.filter(s => s.provider === 'facebook');
        const igSnaps = snaps.filter(s => s.provider === 'instagram');
        
        if (fbEl) {
          fbEl.innerHTML = fbSnaps.length ? fbSnaps.map(s => `
            <tr class="border-b border-gray-100 dark:border-white/5">
              <td class="py-2 px-3">${escapeHtml(s.entity_name || 'ID:' + s.entity_id)}</td>
              <td class="py-2 px-3">${escapeHtml(s.external_id || '')}</td>
              <td class="py-2 px-3 text-right">${formatNumber(s.followers)}</td>
              <td class="py-2 px-3 text-right">${formatNumber(s.data?.reach)}</td>
              <td class="py-2 px-3 text-right">${formatNumber(s.data?.engagement)}</td>
              <td class="py-2 px-3">${s.snapshot_date || ''}</td>
            </tr>
          `).join('') : '<tr><td colspan="6" class="py-4 text-center text-gray-500">No Facebook snapshots</td></tr>';
        }
        
        if (igEl) {
          igEl.innerHTML = igSnaps.length ? igSnaps.map(s => `
            <tr class="border-b border-gray-100 dark:border-white/5">
              <td class="py-2 px-3">${escapeHtml(s.entity_name || 'ID:' + s.entity_id)}</td>
              <td class="py-2 px-3">${escapeHtml(s.external_id || '')}</td>
              <td class="py-2 px-3 text-right">${formatNumber(s.followers)}</td>
              <td class="py-2 px-3 text-right">${formatNumber(s.data?.reach)}</td>
              <td class="py-2 px-3 text-right">${formatNumber(s.data?.profile_views)}</td>
              <td class="py-2 px-3">${s.snapshot_date || ''}</td>
            </tr>
          `).join('') : '<tr><td colspan="6" class="py-4 text-center text-gray-500">No Instagram snapshots</td></tr>';
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
      const r = await api('/admin/analytics/meta/auth-url', { method: 'POST' });
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

  document.getElementById('btnFetchInsights')?.addEventListener('click', async () => {
    const status = document.getElementById('fetchStatus');
    status.className = 'mt-3 text-sm text-gray-600';
    status.textContent = 'Fetching insights from Meta API...';
    status.classList.remove('hidden');
    try {
      const r = await api('/admin/analytics/meta/fetch-insights', { method: 'POST' });
      if (r.status === 200 && r.json?.data) {
        const d = r.json.data;
        if (d.fetched === 0) {
          status.className = 'mt-3 text-sm text-amber-600';
          status.textContent = d.message || 'No accounts to fetch';
        } else {
          status.className = 'mt-3 text-sm text-emerald-600';
          const errors = (d.results || []).filter(x => !x.success);
          if (errors.length > 0) {
            status.innerHTML = `Fetched ${d.fetched} result(s). <span class="text-red-500">${errors.length} error(s): ${errors.map(e => e.error || 'Unknown').join(', ')}</span>`;
          } else {
            status.textContent = `Successfully fetched ${d.fetched} result(s)`;
          }
          loadSnapshots();
        }
      } else {
        status.className = 'mt-3 text-sm text-red-500';
        status.textContent = r.json?.errors?.[0]?.message || 'Failed to fetch insights';
      }
    } catch (e) {
      status.className = 'mt-3 text-sm text-red-500';
      status.textContent = 'Error: ' + e.message;
    }
  });

  loadAccounts();
  loadSnapshots();
})();
</script>

