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

$pageTitle = 'AI Sparks';
$currentPage = 'sparks';
$env = $cfg->appEnv();
include dirname(__DIR__).'/_header.php';

include dirname(__DIR__).'/_topbar.php';
?>

<!-- Sparks Overview -->
<div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5 mb-4">
  <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
    <div class="font-semibold text-lg">Sparks Overview</div>
    <div class="text-xs text-gray-500 dark:text-gray-400">
      Env: <?php echo htmlspecialchars($env); ?> · Mode: <span id="sparksMode">&hellip;</span> · Enforce: <span id="sparksEnforce">&hellip;</span>
    </div>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4" id="sparksSummary">
    <div class="rounded border border-gray-200 dark:border-white/10 p-3 bg-white/80 dark:bg-white/10">
      <div class="text-xs text-gray-500">Total Granted</div>
      <div class="text-xl font-semibold" id="summaryGranted">&hellip;</div>
    </div>
    <div class="rounded border border-gray-200 dark:border-white/10 p-3 bg-white/80 dark:bg-white/10">
      <div class="text-xs text-gray-500">Total Spent</div>
      <div class="text-xl font-semibold" id="summarySpent">&hellip;</div>
    </div>
    <div class="rounded border border-gray-200 dark:border-white/10 p-3 bg-white/80 dark:bg-white/10">
      <div class="text-xs text-gray-500">Net Sparks</div>
      <div class="text-xl font-semibold" id="summaryNet">&hellip;</div>
    </div>
    <div class="rounded border border-gray-200 dark:border-white/10 p-3 bg-white/80 dark:bg-white/10">
      <div class="text-xs text-gray-500">Users with Activity</div>
      <div class="text-xl font-semibold" id="summaryUsers">&hellip;</div>
    </div>
  </div>
  <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
    To change Sparks mode/enforcement, go to <a href="/admin/settings.php" class="text-brand hover:underline">All Settings &rarr; Feature Flags</a>.
  </div>
</div>

<!-- Sparks Ledger -->
<div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
  <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <div class="font-semibold text-lg">Sparks Ledger</div>
    <div class="flex flex-wrap gap-2 items-end">
      <input id="filterUserId" type="number" min="1" placeholder="User ID" class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-9 w-24" />
      <input id="filterReason" type="text" placeholder="Reason contains..." class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-9 w-40" />
      <select id="filterType" class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-9">
        <option value="">All Types</option>
        <option value="grant">Grants</option>
        <option value="spend">Spends</option>
      </select>
      <input id="filterFrom" type="date" class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-9" />
      <input id="filterTo" type="date" class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-9" />
      <button id="searchBtn" class="inline-flex items-center px-4 h-9 rounded bg-brand text-white text-sm">Filter</button>
    </div>
  </div>

  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="text-left text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-white/10">
        <tr>
          <th class="py-2 pr-3">ID</th>
          <th class="py-2 pr-3">User ID</th>
          <th class="py-2 pr-3">Change</th>
          <th class="py-2 pr-3">Reason</th>
          <th class="py-2 pr-3">Metadata</th>
          <th class="py-2 pr-3">Created At</th>
        </tr>
      </thead>
      <tbody id="sparksTable" class="divide-y divide-gray-100 dark:divide-white/5"></tbody>
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

<?php include dirname(__DIR__).'/_footer.php'; ?>

<script>
let currentPage = 1;
const perPage = 25;
let totalRows = 0;

function fmtInt(x) {
  return typeof x === 'number' ? x.toLocaleString() : (x || '0');
}

function fmtMeta(meta) {
  if (!meta) return '';
  try {
    return JSON.stringify(meta);
  } catch (e) {
    return String(meta);
  }
}

async function loadSummary() {
  const res = await fetch(api('/admin/sparks/summary'), { headers: authHeader() });
  const json = await res.json();
  const d = json?.data || {};
  document.getElementById('summaryGranted').textContent = fmtInt(d.total_granted);
  document.getElementById('summarySpent').textContent = fmtInt(d.total_spent);
  document.getElementById('summaryNet').textContent = fmtInt(d.net_sparks);
  document.getElementById('summaryUsers').textContent = fmtInt(d.users_with_activity);
  document.getElementById('sparksMode').textContent = d.mode || 'production';
  document.getElementById('sparksEnforce').textContent = d.enforce_charges ? 'On' : 'Off';
}

async function loadLedger() {
  const uid = document.getElementById('filterUserId').value.trim();
  const reason = document.getElementById('filterReason').value.trim();
  const type = document.getElementById('filterType').value;
  const from = document.getElementById('filterFrom').value;
  const to = document.getElementById('filterTo').value;

  let url = api(`/admin/sparks/ledger?per_page=${perPage}&page=${currentPage}`);
  if (uid) url += `&user_id=${encodeURIComponent(uid)}`;
  if (reason) url += `&reason=${encodeURIComponent(reason)}`;
  if (type) url += `&type=${encodeURIComponent(type)}`;
  if (from) url += `&from=${from}`;
  if (to) url += `&to=${to}`;

  const res = await fetch(url, { headers: authHeader() });
  const json = await res.json();
  const items = json?.data?.items || [];
  totalRows = json?.meta?.total || items.length;

  const tbody = document.getElementById('sparksTable');
  tbody.innerHTML = '';
  items.forEach(row => {
    const tr = document.createElement('tr');
    const change = row.change_sparks || 0;
    const changeClass = change > 0 ? 'text-emerald-600' : (change < 0 ? 'text-red-600' : '');
    tr.innerHTML = `
      <td class="py-2 pr-3">${row.id}</td>
      <td class="py-2 pr-3">${row.user_id}</td>
      <td class="py-2 pr-3 ${changeClass}">${change}</td>
      <td class="py-2 pr-3">${escapeHtml(row.reason || '')}</td>
      <td class="py-2 pr-3 text-xs text-gray-500">${escapeHtml(fmtMeta(row.metadata))}</td>
      <td class="py-2 pr-3">${row.created_at}</td>
    `;
    tbody.appendChild(tr);
  });

  document.getElementById('pagerInfo').textContent = `Showing ${items.length} of ${totalRows}`;
  document.getElementById('prevPage').disabled = currentPage <= 1;
  document.getElementById('nextPage').disabled = (currentPage * perPage) >= totalRows;
}

document.getElementById('searchBtn').addEventListener('click', () => { currentPage = 1; loadLedger(); });
document.getElementById('prevPage').addEventListener('click', () => { if (currentPage > 1) { currentPage--; loadLedger(); } });
document.getElementById('nextPage').addEventListener('click', () => { currentPage++; loadLedger(); });
document.getElementById('refreshBtn').addEventListener('click', () => { loadSummary(); loadLedger(); });

loadSummary();
loadLedger();
</script>
