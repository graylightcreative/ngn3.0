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

$pageTitle = 'users';
$currentPage = 'users';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

<!-- Users List -->
<div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
  <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <div class="font-semibold text-lg">Users</div>
    <div class="flex flex-wrap gap-2">
      <input id="searchQ" type="text" placeholder="Search by email or name..." class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10 w-64" />
      <select id="filterRole" class="rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10">
        <option value="">All Roles</option>
        <option value="1">Admin</option>
        <option value="2">Moderator</option>
        <option value="3">Agent</option>
        <option value="4">Reader</option>
        <option value="5">VIP</option>
        <option value="6">Label</option>
        <option value="7">Venue</option>
        <option value="8">Station</option>
        <option value="9">Artist</option>
        <option value="10">Fan</option>
      </select>
      <button id="searchBtn" class="inline-flex items-center px-4 h-10 rounded bg-brand text-white text-sm">Search</button>
    </div>
  </div>
  
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="text-left text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-white/10">
        <tr>
          <th class="py-2 pr-3">ID</th>
          <th class="py-2 pr-3">Email</th>
          <th class="py-2 pr-3">Name</th>
          <th class="py-2 pr-3">Role</th>
          <th class="py-2 pr-3">Status</th>
          <th class="py-2 pr-3">Created</th>
          <th class="py-2 pr-3">Actions</th>
        </tr>
      </thead>
      <tbody id="usersTable" class="divide-y divide-gray-100 dark:divide-white/5"></tbody>
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

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white dark:bg-gray-900 rounded-lg p-6 w-full max-w-md mx-4">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold">Edit User</h3>
      <button id="closeModal" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">&times;</button>
    </div>
    <input type="hidden" id="editId" />
    <div class="space-y-3">
      <div>
        <label class="text-xs text-gray-500 dark:text-gray-400">Email</label>
        <input id="editEmail" type="email" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" />
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs text-gray-500 dark:text-gray-400">First Name</label>
          <input id="editFirstName" type="text" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" />
        </div>
        <div>
          <label class="text-xs text-gray-500 dark:text-gray-400">Last Name</label>
          <input id="editLastName" type="text" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" />
        </div>
      </div>
      <div>
        <label class="text-xs text-gray-500 dark:text-gray-400">Role</label>
        <select id="editRole" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10">
          <option value="1">Admin</option>
          <option value="2">Moderator</option>
          <option value="3">Agent</option>
          <option value="4">Reader</option>
          <option value="5">VIP</option>
          <option value="6">Label</option>
          <option value="7">Venue</option>
          <option value="8">Station</option>
          <option value="9">Artist</option>
          <option value="10">Fan</option>
        </select>
      </div>
      <div>
        <label class="text-xs text-gray-500 dark:text-gray-400">Status</label>
        <select id="editStatus" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
          <option value="banned">Banned</option>
        </select>
      </div>
      <div class="grid grid-cols-[1fr,auto] gap-3 items-end mt-2">
        <div>
          <label class="text-xs text-gray-500 dark:text-gray-400">Grant Sparks (admin)</label>
          <input id="grantSparksAmount" type="number" min="1" step="1" placeholder="e.g. 30" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" />
        </div>
        <button type="button" id="grantSparksBtn" class="px-3 h-9 rounded bg-amber-500 text-white text-xs font-semibold">Grant</button>
      </div>
    </div>
    <div class="mt-4 flex justify-end gap-2">
      <button id="cancelEdit" class="px-4 h-9 rounded border border-gray-200 dark:border-white/10 text-sm">Cancel</button>
      <button id="saveEdit" class="px-4 h-9 rounded bg-brand text-white text-sm">Save</button>
    </div>
    <div id="editMsg" class="mt-2 text-sm"></div>
  </div>
</div>

<?php include __DIR__.'/_footer.php'; ?>

<script>
const roleNames = {1:'Admin',2:'Moderator',3:'Agent',4:'Reader',5:'VIP',6:'Label',7:'Venue',8:'Station',9:'Artist',10:'Fan'};
let currentPage = 1;
const perPage = 25;
let totalUsers = 0;

async function loadUsers() {
  const q = document.getElementById('searchQ').value.trim();
  const role = document.getElementById('filterRole').value;
  const offset = (currentPage - 1) * perPage;
  
  let url = api(`/admin/users?limit=${perPage}&offset=${offset}`);
  if (q) url += `&q=${encodeURIComponent(q)}`;
  if (role) url += `&role_id=${role}`;
  
  const res = await fetch(url, { headers: authHeader() });
  const json = await res.json();
  
  const users = json?.data?.items || json?.data || [];
  totalUsers = json?.meta?.total || users.length;
  
  const tbody = document.getElementById('usersTable');
  tbody.innerHTML = '';
  
  users.forEach(u => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="py-2 pr-3">${u.Id || u.id}</td>
      <td class="py-2 pr-3">${escapeHtml(u.Email || u.email || '')}</td>
      <td class="py-2 pr-3">${escapeHtml((u.FirstName || u.first_name || '') + ' ' + (u.LastName || u.last_name || ''))}</td>
      <td class="py-2 pr-3">${roleNames[u.RoleId || u.role_id] || 'Unknown'}</td>
      <td class="py-2 pr-3">${fmtStatus(u.Status || u.status || 'active', 'active')}</td>
      <td class="py-2 pr-3">${(u.CreatedAt || u.created_at || '').split('T')[0]}</td>
      <td class="py-2 pr-3">
        <button class="px-2 h-7 rounded bg-gray-200 dark:bg-white/10 text-xs" onclick="editUser(${u.Id || u.id})">Edit</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
  
  document.getElementById('pagerInfo').textContent = `Showing ${users.length} of ${totalUsers}`;
  document.getElementById('prevPage').disabled = currentPage <= 1;
  document.getElementById('nextPage').disabled = (currentPage * perPage) >= totalUsers;
}

function editUser(id) {
  // Fetch user and populate modal
  fetch(api(`/admin/users/${id}`), { headers: authHeader() })
    .then(r => r.json())
    .then(json => {
      const u = json?.data || {};
      document.getElementById('editId').value = id;
      document.getElementById('editEmail').value = u.Email || u.email || '';
      document.getElementById('editFirstName').value = u.FirstName || u.first_name || '';
      document.getElementById('editLastName').value = u.LastName || u.last_name || '';
      document.getElementById('editRole').value = u.RoleId || u.role_id || 10;
      document.getElementById('editStatus').value = u.Status || u.status || 'active';
      document.getElementById('grantSparksAmount').value = '';
      document.getElementById('editModal').classList.remove('hidden');
      document.getElementById('editModal').classList.add('flex');
    });
}

async function saveUser() {
  const id = document.getElementById('editId').value;
  const payload = {
    email: document.getElementById('editEmail').value,
    first_name: document.getElementById('editFirstName').value,
    last_name: document.getElementById('editLastName').value,
    role_id: parseInt(document.getElementById('editRole').value),
    status: document.getElementById('editStatus').value
  };
  
  const { status, json } = await apiCall('PUT', `/admin/users/${id}`, payload);
  const msg = document.getElementById('editMsg');
  
  if (status === 200) {
    msg.innerHTML = '<span class="text-emerald-500">Saved!</span>';
    setTimeout(() => {
      closeModal();
      loadUsers();
    }, 500);
  } else {
    msg.innerHTML = `<span class="text-red-500">Error: ${json?.errors?.[0]?.message || status}</span>`;
  }
}

async function grantSparks() {
  const id = document.getElementById('editId').value;
  const amtStr = document.getElementById('grantSparksAmount').value;
  const msg = document.getElementById('editMsg');
  const amount = parseInt(amtStr, 10);
  if (!id || !amount || amount <= 0) {
    msg.innerHTML = '<span class="text-red-500">Enter a positive Sparks amount.</span>';
    return;
  }
  msg.innerHTML = '<span class="text-gray-500">Granting Sparks...</span>';
  const { status, json } = await apiCall('POST', '/admin/users/grant-sparks', {
    user_id: parseInt(id, 10),
    amount: amount,
    reason: 'admin_seed',
  });
  if (status === 200) {
    const bal = json?.data?.balance;
    msg.innerHTML = `<span class="text-emerald-500">Granted ${amount} Sparks. New balance: ${bal ?? 'n/a'}.</span>`;
    document.getElementById('grantSparksAmount').value = '';
  } else {
    msg.innerHTML = `<span class="text-red-500">Error granting Sparks: ${json?.errors?.[0]?.message || status}</span>`;
  }
}

function closeModal() {
  document.getElementById('editModal').classList.add('hidden');
  document.getElementById('editModal').classList.remove('flex');
  document.getElementById('editMsg').textContent = '';
}

document.getElementById('searchBtn').addEventListener('click', () => { currentPage = 1; loadUsers(); });
document.getElementById('prevPage').addEventListener('click', () => { if (currentPage > 1) { currentPage--; loadUsers(); } });
document.getElementById('nextPage').addEventListener('click', () => { currentPage++; loadUsers(); });
document.getElementById('refreshBtn').addEventListener('click', loadUsers);
document.getElementById('closeModal').addEventListener('click', closeModal);
document.getElementById('cancelEdit').addEventListener('click', closeModal);
document.getElementById('saveEdit').addEventListener('click', saveUser);
document.getElementById('grantSparksBtn').addEventListener('click', grantSparks);
document.getElementById('searchQ').addEventListener('keypress', e => { if (e.key === 'Enter') { currentPage = 1; loadUsers(); } });

loadUsers();
</script>

