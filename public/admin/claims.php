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

$pageTitle = 'Claims';
$currentPage = 'claims';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

<!-- Claims List -->
<div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
  <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <div class="font-semibold text-lg">Profile Claims</div>
    <div class="flex flex-wrap gap-2">
      <input id="searchQ" type="text" placeholder="Search claims..." class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10 w-64" />
      <select id="filterType" class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10">
        <option value="">All Types</option>
        <option value="artist">Artist</option>
        <option value="label">Label</option>
        <option value="station">Station</option>
        <option value="venue">Venue</option>
      </select>
      <select id="filterStatus" class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10">
        <option value="">All Status</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
      </select>
      <button id="searchBtn" class="inline-flex items-center px-4 h-10 rounded bg-brand text-white text-sm">Search</button>
    </div>
  </div>
  
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="text-left text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-white/10">
        <tr>
          <th class="py-2 pr-3">Status</th>
          <th class="py-2 pr-3">Type</th>
          <th class="py-2 pr-3">Claiming</th>
          <th class="py-2 pr-3">Claimer</th>
          <th class="py-2 pr-3">Email</th>
          <th class="py-2 pr-3">Company</th>
          <th class="py-2 pr-3">Created</th>
          <th class="py-2 pr-3">Actions</th>
        </tr>
      </thead>
      <tbody id="claimsTable" class="divide-y divide-gray-100 dark:divide-white/5"></tbody>
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

<!-- View Modal -->
<div id="claimModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-lg mx-4">
    <h3 class="text-lg font-semibold mb-4">Claim Details</h3>
    <div id="claimDetails" class="space-y-3 text-sm"></div>
    <div class="flex justify-between gap-2 pt-4 border-t border-gray-200 dark:border-white/10 mt-4">
      <div class="flex gap-2">
        <button id="approveBtn" class="px-4 h-10 rounded bg-green-600 text-white text-sm">Approve</button>
        <button id="rejectBtn" class="px-4 h-10 rounded bg-red-600 text-white text-sm">Reject</button>
      </div>
      <button id="closeModalBtn" class="px-4 h-10 rounded border border-gray-300 dark:border-white/10">Close</button>
    </div>
  </div>
</div>

<?php include __DIR__.'/_footer.php'; ?>

<script>
let claims = [];
let page = 1;
let currentClaim = null;
const perPage = 20;

async function loadClaims() {
  try {
    const res = await apiCall('/api/v1/legacy/claims');
    claims = res.data || [];
    renderTable();
  } catch (e) {
    console.error('Failed to load claims:', e);
    claims = [];
    renderTable();
  }
}

function renderTable() {
  const q = document.getElementById('searchQ').value.toLowerCase();
  const type = document.getElementById('filterType').value;
  const status = document.getElementById('filterStatus').value;
  
  let filtered = claims.filter(c => {
    const matchQ = !q || (c.Title || '').toLowerCase().includes(q) || (c.Email || '').toLowerCase().includes(q);
    const matchType = !type || (c.Type || '').toLowerCase() === type;
    const matchStatus = !status || (c.Status || 'pending').toLowerCase() === status;
    return matchQ && matchType && matchStatus;
  });
  
  const total = filtered.length;
  const start = (page - 1) * perPage;
  const paged = filtered.slice(start, start + perPage);
  
  const tbody = document.getElementById('claimsTable');
  tbody.innerHTML = paged.map(c => {
    const type = (c.Type || '').toLowerCase();
    const status = (c.Status || 'pending').toLowerCase();
    const typeClass = type === 'artist' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400' :
                      type === 'label' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' :
                      type === 'station' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' :
                      'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
    const statusClass = status === 'approved' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' :
                        status === 'rejected' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' :
                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
    return `<tr>
      <td class="py-2 pr-3"><span class="px-2 py-1 rounded text-xs ${statusClass}">${status}</span></td>
      <td class="py-2 pr-3"><span class="px-2 py-1 rounded text-xs ${typeClass}">${type}</span></td>
      <td class="py-2 pr-3">${escapeHtml(c.ProfileTitle || c.UserId || '')}</td>
      <td class="py-2 pr-3">${escapeHtml(c.Title || '')}</td>
      <td class="py-2 pr-3">${escapeHtml(c.Email || '')}</td>
      <td class="py-2 pr-3">${escapeHtml(c.Company || '-')}</td>
      <td class="py-2 pr-3">${c.Created || '-'}</td>
      <td class="py-2 pr-3">
        <button onclick="viewClaim(${c.Id || c.id})" class="text-brand hover:underline text-sm mr-2">View</button>
        <button onclick="deleteClaim(${c.Id || c.id})" class="text-red-500 hover:underline text-sm">Delete</button>
      </td>
    </tr>`;
  }).join('') || '<tr><td colspan="8" class="py-4 text-center text-gray-500">No claims found</td></tr>';
  
  document.getElementById('pagerInfo').textContent = `Showing ${start + 1}-${Math.min(start + perPage, total)} of ${total}`;
  document.getElementById('prevPage').disabled = page <= 1;
  document.getElementById('nextPage').disabled = start + perPage >= total;
}

function viewClaim(id) {
  currentClaim = claims.find(c => (c.Id || c.id) == id);
  if (!currentClaim) return;
  
  document.getElementById('claimDetails').innerHTML = `
    <div><strong>Type:</strong> ${escapeHtml(currentClaim.Type || '')}</div>
    <div><strong>Profile:</strong> ${escapeHtml(currentClaim.ProfileTitle || currentClaim.UserId || '')}</div>
    <div><strong>Claimer:</strong> ${escapeHtml(currentClaim.Title || '')}</div>
    <div><strong>Email:</strong> ${escapeHtml(currentClaim.Email || '')}</div>
    <div><strong>Company:</strong> ${escapeHtml(currentClaim.Company || '-')}</div>
    <div><strong>Status:</strong> ${escapeHtml(currentClaim.Status || 'pending')}</div>
    <div><strong>Created:</strong> ${currentClaim.Created || '-'}</div>
    ${currentClaim.Notes ? `<div><strong>Notes:</strong></div><div class="p-3 bg-gray-100 dark:bg-white/10 rounded">${escapeHtml(currentClaim.Notes)}</div>` : ''}
  `;
  
  document.getElementById('claimModal').classList.remove('hidden');
  document.getElementById('claimModal').classList.add('flex');
}

function hideModal() {
  document.getElementById('claimModal').classList.add('hidden');
  document.getElementById('claimModal').classList.remove('flex');
  currentClaim = null;
}

async function approveClaim() {
  if (!currentClaim) return;
  try {
    await apiCall('/api/v1/legacy/claims/' + (currentClaim.Id || currentClaim.id) + '/approve', 'POST');
    hideModal();
    loadClaims();
  } catch (e) {
    alert('Failed to approve claim');
  }
}

async function rejectClaim() {
  if (!currentClaim) return;
  try {
    await apiCall('/api/v1/legacy/claims/' + (currentClaim.Id || currentClaim.id) + '/reject', 'POST');
    hideModal();
    loadClaims();
  } catch (e) {
    alert('Failed to reject claim');
  }
}

async function deleteClaim(id) {
  if (!confirm('Delete this claim?')) return;
  try {
    await apiCall('/api/v1/legacy/claims/' + id, 'DELETE');
    loadClaims();
  } catch (e) {
    alert('Failed to delete claim');
  }
}

document.getElementById('closeModalBtn').onclick = hideModal;
document.getElementById('approveBtn').onclick = approveClaim;
document.getElementById('rejectBtn').onclick = rejectClaim;
document.getElementById('searchBtn').onclick = () => { page = 1; renderTable(); };
document.getElementById('searchQ').onkeyup = (e) => { if (e.key === 'Enter') { page = 1; renderTable(); } };
document.getElementById('filterType').onchange = () => { page = 1; renderTable(); };
document.getElementById('filterStatus').onchange = () => { page = 1; renderTable(); };
document.getElementById('prevPage').onclick = () => { page--; renderTable(); };
document.getElementById('nextPage').onclick = () => { page++; renderTable(); };

loadClaims();
</script>

