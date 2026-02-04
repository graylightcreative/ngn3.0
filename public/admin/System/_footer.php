  </section>
</main>
</div>
<?php include __DIR__.'/_token_store.php'; ?>
<script>
  // Common admin utilities
  const api = path => `/api/v1${path}`;
  const token = () => localStorage.getItem('ngn_admin_token') || localStorage.getItem('admin_token') || '';
  const authHeader = () => token() ? { 'Authorization': 'Bearer ' + token() } : {};

  function escapeHtml(s) {
    return (s || '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  }

  function fmtStatus(s, onValue = 'active') {
    const on = s === onValue;
    return `<span class="inline-flex items-center px-2 py-1 rounded-full text-xs border ${on ? 'bg-brand/10 text-brand border-brand/30' : 'bg-gray-200 dark:bg-white/10 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-white/10'}">${s}</span>`;
  }

  async function apiCall(method, path, body) {
    const res = await fetch(api(path), {
      method,
      headers: { 'Content-Type': 'application/json', ...authHeader() },
      body: body ? JSON.stringify(body) : undefined
    });
    let json = null;
    try { json = await res.json(); } catch (e) {}
    return { status: res.status, json };
  }
</script>

