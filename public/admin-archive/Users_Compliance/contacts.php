<?php
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);
// require_once $root.'/lib/bootstrap.php'; // Handled by _guard.php

use NGN\Lib\Env;
use NGN\Lib\Config;

if (!class_exists('NGN\\Lib\\Env')) { exit('Bootstrap failed'); }
Env::load($root);
$cfg = new Config();
include __DIR__.'/_mint_token.php';

$pageTitle = 'Contacts';
$currentPage = 'contacts';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

<!-- Contacts List -->
<div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
  <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <div class="font-semibold text-lg">Contacts</div>
    <div class="flex flex-wrap gap-2">
      <input id="searchQ" type="text" placeholder="Search by name or email..." class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10 w-64" />
      <select id="filterStatus" class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10">
        <option value="">All Status</option>
        <option value="new">New</option>
        <option value="contacted">Contacted</option>
        <option value="resolved">Resolved</option>
      </select>
      <button id="searchBtn" class="inline-flex items-center px-4 h-10 rounded bg-brand text-white text-sm">Search</button>
    </div>
  </div>
  
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="text-left text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-white/10">
        <tr>
          <th class="py-2 pr-3">ID</th>
          <th class="py-2 pr-3">Name</th>
          <th class="py-2 pr-3">Email</th>
          <th class="py-2 pr-3">Status</th>
          <th class="py-2 pr-3">Created</th>
          <th class="py-2 pr-3">Actions</th>
        </tr>
      </thead>
      <tbody id="contactsTable" class="divide-y divide-gray-100 dark:divide-white/5"></tbody>
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
<div id="contactModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-lg mx-4">
    <h3 class="text-lg font-semibold mb-4">Contact Details</h3>
    <div id="contactDetails" class="space-y-3 text-sm"></div>
    <div class="flex justify-between gap-2 pt-4 border-t border-gray-200 dark:border-white/10 mt-4">
      <div class="flex gap-2">
        <button id="markContactedBtn" class="px-4 h-10 rounded bg-blue-600 text-white text-sm">Mark Contacted</button>
        <button id="markResolvedBtn" class="px-4 h-10 rounded bg-green-600 text-white text-sm">Mark Resolved</button>
      </div>
      <button id="closeModalBtn" class="px-4 h-10 rounded border border-gray-300 dark:border-white/10">Close</button>
    </div>
  </div>
</div>

<?php include __DIR__.'/_footer.php'; ?>

<script>
let contacts = [];
let page = 1;
let currentContact = null;
const perPage = 20;

async function loadContacts() {
  try {
    const res = await apiCall('/api/v1/legacy/contacts');
    contacts = res.data || [];
    renderTable();
  } catch (e) {
    console.error('Failed to load contacts:', e);
    contacts = [];
    renderTable();
  }
}

function renderTable() {
  const q = document.getElementById('searchQ').value.toLowerCase();
  const status = document.getElementById('filterStatus').value;
  
  let filtered = contacts.filter(c => {
    const name = ((c.FirstName || '') + ' ' + (c.LastName || '')).toLowerCase();
    const matchQ = !q || name.includes(q) || (c.Email || '').toLowerCase().includes(q);
    const matchStatus = !status || (c.Status || 'new').toLowerCase() === status;
    return matchQ && matchStatus;
  });
  
  const total = filtered.length;
  const start = (page - 1) * perPage;
  const paged = filtered.slice(start, start + perPage);
  
  const tbody = document.getElementById('contactsTable');
  tbody.innerHTML = paged.map(c => {
    const status = (c.Status || 'new').toLowerCase();
    const statusClass = status === 'resolved' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' :
                        status === 'contacted' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' :
                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
    return `<tr>
      <td class="py-2 pr-3">${c.Id || ''}</td>
      <td class="py-2 pr-3">${escapeHtml((c.FirstName || '') + ' ' + (c.LastName || ''))}</td>
      <td class="py-2 pr-3">${escapeHtml(c.Email || '')}</td>
      <td class="py-2 pr-3"><span class="px-2 py-1 rounded text-xs ${statusClass}">${status}</span></td>
      <td class="py-2 pr-3">${c.Created || '-'}</td>
      <td class="py-2 pr-3">
        <button onclick="viewContact(${c.Id})" class="text-brand hover:underline text-sm mr-2">View</button>
        <button onclick="deleteContact(${c.Id})" class="text-red-500 hover:underline text-sm">Delete</button>
      </td>
    </tr>`;
  }).join('') || '<tr><td colspan="6" class="py-4 text-center text-gray-500">No contacts found</td></tr>';
  
  document.getElementById('pagerInfo').textContent = `Showing ${start + 1}-${Math.min(start + perPage, total)} of ${total}`;
  document.getElementById('prevPage').disabled = page <= 1;
  document.getElementById('nextPage').disabled = start + perPage >= total;
}

function viewContact(id) {
  currentContact = contacts.find(c => c.Id == id);
  if (!currentContact) return;
  
  document.getElementById('contactDetails').innerHTML = `
    <div><strong>Name:</strong> ${escapeHtml((currentContact.FirstName || '') + ' ' + (currentContact.LastName || ''))}</div>
    <div><strong>Email:</strong> ${escapeHtml(currentContact.Email || '')}</div>
    <div><strong>Phone:</strong> ${escapeHtml(currentContact.Phone || '-')}</div>
    <div><strong>Status:</strong> ${escapeHtml(currentContact.Status || 'new')}</div>
    <div><strong>Created:</strong> ${currentContact.Created || '-'}</div>
    <div><strong>Message:</strong></div>
    <div class="p-3 bg-gray-100 dark:bg-white/10 rounded">${escapeHtml(currentContact.Message || 'No message')}</div>
  `;
  
  document.getElementById('contactModal').classList.remove('hidden');
  document.getElementById('contactModal').classList.add('flex');
}

function hideModal() {
  document.getElementById('contactModal').classList.add('hidden');
  document.getElementById('contactModal').classList.remove('flex');
  currentContact = null;
}

async function updateStatus(status) {
  if (!currentContact) return;
  try {
    await apiCall('/api/v1/legacy/contacts/' + currentContact.Id, 'PUT', { Status: status });
    hideModal();
    loadContacts();
  } catch (e) {
    alert('Failed to update status');
  }
}

async function deleteContact(id) {
  if (!confirm('Delete this contact?')) return;
  try {
    await apiCall('/api/v1/legacy/contacts/' + id, 'DELETE');
    loadContacts();
  } catch (e) {
    alert('Failed to delete contact');
  }
}

document.getElementById('closeModalBtn').onclick = hideModal;
document.getElementById('markContactedBtn').onclick = () => updateStatus('contacted');
document.getElementById('markResolvedBtn').onclick = () => updateStatus('resolved');
document.getElementById('searchBtn').onclick = () => { page = 1; renderTable(); };
document.getElementById('searchQ').onkeyup = (e) => { if (e.key === 'Enter') { page = 1; renderTable(); } };
document.getElementById('filterStatus').onchange = () => { page = 1; renderTable(); };
document.getElementById('prevPage').onclick = () => { page--; renderTable(); };
document.getElementById('nextPage').onclick = () => { page++; renderTable(); };

loadContacts();
</script>

