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
    const status = document.getElementById('filterStatus').value;
    const type = document.getElementById('filterType').value;
    let url = '/api/v1/claims/list?page=' + page;
    if (status) url += '&status=' + encodeURIComponent(status);
    if (type) url += '&entity_type=' + encodeURIComponent(type);

    const res = await apiCall(url);
    claims = res.data || [];
    renderTable(res.pagination || {});
  } catch (e) {
    console.error('Failed to load claims:', e);
    claims = [];
    renderTable({});
  }
}

function renderTable(pagination) {
  const q = document.getElementById('searchQ').value.toLowerCase();

  let filtered = claims.filter(c => {
    const matchQ = !q || (c.claimant_name || '').toLowerCase().includes(q) ||
                         (c.claimant_email || '').toLowerCase().includes(q);
    return matchQ;
  });

  const tbody = document.getElementById('claimsTable');
  tbody.innerHTML = filtered.map(c => {
    const type = (c.entity_type || '').toLowerCase();
    const status = (c.status || 'pending').toLowerCase();
    const typeClass = type === 'artist' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400' :
                      type === 'label' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' :
                      type === 'station' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' :
                      'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
    const statusClass = status === 'approved' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' :
                        status === 'rejected' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' :
                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
    const createdDate = new Date(c.created_at).toLocaleDateString();
    return `<tr>
      <td class="py-2 pr-3"><span class="px-2 py-1 rounded text-xs ${statusClass}">${status}</span></td>
      <td class="py-2 pr-3"><span class="px-2 py-1 rounded text-xs ${typeClass}">${type}</span></td>
      <td class="py-2 pr-3">${escapeHtml(c.entity_id || '')}</td>
      <td class="py-2 pr-3">${escapeHtml(c.claimant_name || '')}</td>
      <td class="py-2 pr-3">${escapeHtml(c.claimant_email || '')}</td>
      <td class="py-2 pr-3">${escapeHtml(c.company || '-')}</td>
      <td class="py-2 pr-3">${createdDate}</td>
      <td class="py-2 pr-3">
        <button onclick="viewClaim(${c.id})" class="text-brand hover:underline text-sm mr-2">View</button>
      </td>
    </tr>`;
  }).join('') || '<tr><td colspan="8" class="py-4 text-center text-gray-500">No claims found</td></tr>';

  const paginationInfo = pagination.total || 0;
  const currentPage = pagination.page || 1;
  document.getElementById('pagerInfo').textContent = `Page ${currentPage} of ${pagination.pages || 1} (${paginationInfo} total)`;
  document.getElementById('prevPage').disabled = currentPage <= 1;
  document.getElementById('nextPage').disabled = currentPage >= (pagination.pages || 1);
}

function viewClaim(id) {
  currentClaim = claims.find(c => c.id == id);
  if (!currentClaim) return;

  const emailStatus = currentClaim.email_verified ? '✓ Verified' : '✗ Not Verified';
  const createdDate = new Date(currentClaim.created_at).toLocaleString();
  const expiresDate = new Date(currentClaim.expires_at).toLocaleString();

  document.getElementById('claimDetails').innerHTML = `
    <div><strong>Entity Type:</strong> ${escapeHtml(currentClaim.entity_type || '')}</div>
    <div><strong>Entity ID:</strong> ${currentClaim.entity_id}</div>
    <div><strong>Claimant Name:</strong> ${escapeHtml(currentClaim.claimant_name || '')}</div>
    <div><strong>Email:</strong> ${escapeHtml(currentClaim.claimant_email || '')} (${emailStatus})</div>
    <div><strong>Phone:</strong> ${escapeHtml(currentClaim.claimant_phone || '-')}</div>
    <div><strong>Company:</strong> ${escapeHtml(currentClaim.company || '-')}</div>
    <div><strong>Relationship:</strong> ${escapeHtml(currentClaim.relationship || '-')}</div>
    <div><strong>Status:</strong> ${escapeHtml(currentClaim.status || 'pending')}</div>
    <div><strong>Created:</strong> ${createdDate}</div>
    <div><strong>Expires:</strong> ${expiresDate}</div>
    ${currentClaim.admin_notes ? `<div><strong>Admin Notes:</strong></div><div class="p-3 bg-gray-100 dark:bg-white/10 rounded">${escapeHtml(currentClaim.admin_notes)}</div>` : ''}
  `;

  // Enable/disable buttons based on status
  const approveBtn = document.getElementById('approveBtn');
  const rejectBtn = document.getElementById('rejectBtn');
  approveBtn.disabled = currentClaim.status !== 'pending';
  rejectBtn.disabled = currentClaim.status !== 'pending';

  document.getElementById('claimModal').classList.remove('hidden');
  document.getElementById('claimModal').classList.add('flex');
}

function hideModal() {
  document.getElementById('claimModal').classList.add('hidden');
  document.getElementById('claimModal').classList.remove('flex');
  currentClaim = null;
}

async function approveClaim() {
  if (!currentClaim || currentClaim.status !== 'pending') return;

  const notes = prompt('Add any admin notes (optional):', '');
  if (notes === null) return; // User cancelled

  try {
    const body = { admin_notes: notes || '' };
    await apiCall('/api/v1/claims/approve/' + currentClaim.id, 'POST', body);
    alert('Claim approved successfully!');
    hideModal();
    page = 1; // Reset to first page
    loadClaims();
  } catch (e) {
    console.error('Failed to approve claim:', e);
    alert('Failed to approve claim: ' + (e.message || 'Unknown error'));
  }
}

async function rejectClaim() {
  if (!currentClaim || currentClaim.status !== 'pending') return;

  const reason = prompt('Enter reason for rejection:', '');
  if (reason === null || reason.trim() === '') {
    alert('Rejection reason is required.');
    return;
  }

  try {
    const body = { admin_notes: reason };
    await apiCall('/api/v1/claims/reject/' + currentClaim.id, 'POST', body);
    alert('Claim rejected successfully!');
    hideModal();
    page = 1; // Reset to first page
    loadClaims();
  } catch (e) {
    console.error('Failed to reject claim:', e);
    alert('Failed to reject claim: ' + (e.message || 'Unknown error'));
  }
}

document.getElementById('closeModalBtn').onclick = hideModal;
document.getElementById('approveBtn').onclick = approveClaim;
document.getElementById('rejectBtn').onclick = rejectClaim;
document.getElementById('searchBtn').onclick = () => { page = 1; loadClaims(); };
document.getElementById('searchQ').onkeyup = (e) => { if (e.key === 'Enter') { page = 1; loadClaims(); } };
document.getElementById('filterType').onchange = () => { page = 1; loadClaims(); };
document.getElementById('filterStatus').onchange = () => { page = 1; loadClaims(); };
document.getElementById('prevPage').onclick = () => { page--; loadClaims(); };
document.getElementById('nextPage').onclick = () => { page++; loadClaims(); };

function escapeHtml(text) {
  const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
  return (text || '').replace(/[&<>"']/g, m => map[m]);
}

loadClaims();
</script>

