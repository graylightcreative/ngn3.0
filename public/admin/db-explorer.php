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

$pageTitle = 'DB Explorer';
$currentPage = 'db-explorer';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

      <section class="max-w-7xl mx-auto px-4 py-6 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">Connections</div>
            <div id="conns" class="text-sm"></div>
          </div>
          <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">Tables</div>
            <div class="mb-2">
              <label class="text-xs text-gray-500 dark:text-gray-400">Connection</label>
              <select id="connSel" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10"></select>
            </div>
            <div id="tables" class="text-sm"></div>
          </div>
          <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">Details</div>
            <div id="details" class="text-sm"></div>
          </div>
        </div>
        <div id="msg" class="text-sm text-gray-500 dark:text-gray-400"></div>
      </section>
    </main>
  </div>

<?php include __DIR__.'/_token_store.php'; ?>
  <script>
    const api = p => `/api/v1${p}`;
    const token = () => localStorage.getItem('ngn_admin_token') || localStorage.getItem('admin_token') || '';
    const auth = () => token() ? { 'Authorization': 'Bearer ' + token() } : {};
    let selectedTable = '';

    async function loadConnections(){
      const res = await fetch(api('/admin/db/connections'), { headers: { ...auth() } });
      const j = await res.json().catch(()=>({}));
      if (res.status!==200){ document.getElementById('msg').textContent = 'Error ' + res.status; return; }
      const conns = j?.data?.connections || [];
      const wrap = document.getElementById('conns');
      wrap.innerHTML = conns.map(c=>`<div class='flex items-center justify-between border-b border-gray-100 dark:border-white/10 py-1'><div>${c.name}</div><div>${c.ok? '<span class="text-emerald-500">OK</span>':'<span class="text-red-500">ERR</span>'}</div></div>`).join('');
      const sel = document.getElementById('connSel');
      sel.innerHTML = conns.map(c=>`<option value='${c.name}'>${c.name}</option>`).join('');
    }

    function fmtBytes(n){ n = Number(n||0); if (n<1024) return n+' B'; const u=['KB','MB','GB','TB']; let i=-1; do{ n/=1024; i++; }while(n>=1024 && i<u.length-1); return n.toFixed(1)+' '+u[i]; }

    async function loadSchemas(){
      const conn = document.getElementById('connSel').value || 'primary';
      const res = await fetch(api('/admin/db/schemas?conn=' + encodeURIComponent(conn)), { headers: { ...auth() } });
      const j = await res.json().catch(()=>({}));
      if (res.status!==200){ document.getElementById('msg').textContent = 'Error ' + res.status + ' ' + (j?.errors?.[0]?.message||''); return; }
      const db = j?.data?.database || '';
      const tbls = j?.data?.tables || [];
      const tbody = tbls.map(t=>`<tr class='border-t border-gray-100 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-white/5 cursor-pointer' data-table='${t.table_name}'>
          <td class='py-1 pr-3'>${t.table_name}</td>
          <td class='py-1 pr-3 text-right'>${t.table_rows??''}</td>
          <td class='py-1 pr-3 text-right'>${fmtBytes(t.data_length)}</td>
          <td class='py-1 pr-3 text-right'>${fmtBytes(t.index_length)}</td>
        </tr>`).join('');
      document.getElementById('tables').innerHTML = `<div class='text-xs mb-2'>DB: <span class='font-mono'>${db}</span></div>` +
        (tbls.length ? `<table class='min-w-full text-xs'><thead><tr><th class='text-left pr-3 py-1'>Table</th><th class='text-right pr-3 py-1'>Rows~</th><th class='text-right pr-3 py-1'>Data</th><th class='text-right pr-3 py-1'>Index</th></tr></thead><tbody>${tbody}</tbody></table>` : '<div>—</div>');
      document.getElementById('details').textContent = '';
      document.getElementById('msg').textContent = '';
      selectedTable = '';
      document.getElementById('exportTableBtn').disabled = true;
      // bind row clicks
      document.querySelectorAll('#tables [data-table]').forEach(tr=>{
        tr.addEventListener('click', ()=>{
          selectedTable = tr.getAttribute('data-table');
          loadTableDetails();
        });
      });
    }

    async function loadTableDetails(){
      const conn = document.getElementById('connSel').value || 'primary';
      if (!selectedTable){ document.getElementById('details').textContent=''; return; }
      document.getElementById('details').textContent = 'Loading…';
      try {
        const url = api('/admin/db/table-schema?conn='+encodeURIComponent(conn)+'&table='+encodeURIComponent(selectedTable));
        const res = await fetch(url, { headers: { ...auth() } });
        const j = await res.json().catch(()=>({}));
        if (res.status!==200){ document.getElementById('details').textContent = 'Error ' + res.status + ' ' + (j?.errors?.[0]?.message||''); document.getElementById('exportTableBtn').disabled = true; return; }
        const cols = j?.data?.columns || [];
        const idx  = j?.data?.indexes || [];
        const ddl  = j?.data?.create || '';
        const colsHtml = cols.length ? `<table class='min-w-full text-[11px] mb-3'>
          <thead><tr><th class='text-left pr-2 py-1'>Column</th><th class='text-left pr-2 py-1'>Type</th><th class='text-left pr-2 py-1'>Null</th><th class='text-left pr-2 py-1'>Key</th><th class='text-left pr-2 py-1'>Default</th><th class='text-left pr-2 py-1'>Extra</th></tr></thead>
          <tbody>${cols.map(c=>`<tr class='border-t border-gray-100 dark:border-white/10'><td class='py-1 pr-2'>${c.Field||c.field||''}</td><td class='py-1 pr-2'>${c.Type||c.type||''}</td><td class='py-1 pr-2'>${c.Null||c.null||''}</td><td class='py-1 pr-2'>${c.Key||c.key||''}</td><td class='py-1 pr-2'>${(c.Default??c.default)??''}</td><td class='py-1 pr-2'>${c.Extra||c.extra||''}</td></tr>`).join('')}</tbody>
        </table>` : '<div class="text-xs">No columns</div>';
        const idxHtml = idx.length ? `<div class='text-xs mb-2'>Indexes: ${idx.length}</div>` : '';
        const ddlHtml = ddl ? `<div class='text-xs mb-1'>CREATE TABLE</div><pre class='text-[11px] whitespace-pre-wrap rounded border border-gray-200 dark:border-white/10 p-2 bg-white/70 dark:bg-white/5'>${ddl.replaceAll('<','&lt;')}</pre>` : '';
        document.getElementById('details').innerHTML = `<div class='text-sm font-semibold mb-2'>${selectedTable}</div>${colsHtml}${idxHtml}${ddlHtml}`;
        document.getElementById('exportTableBtn').disabled = false;
      } catch(e){ document.getElementById('details').textContent = 'Error'; document.getElementById('exportTableBtn').disabled = true; }
    }

    function download(filename, text){
      const blob = new Blob([text], {type:'application/json'});
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = filename;
      a.click();
      URL.revokeObjectURL(a.href);
    }

    document.getElementById('refreshBtn').addEventListener('click', function(){ loadConnections().then(loadSchemas); });
    document.getElementById('connSel').addEventListener('change', loadSchemas);

    document.getElementById('exportDbBtn').addEventListener('click', async function(){
      const conn = document.getElementById('connSel').value || 'primary';
      const url = new URL(location.origin + api('/admin/db/schema-dump'));
      if (conn) url.searchParams.set('conn', conn);
      const res = await fetch(url, { headers: { ...auth() } });
      const j = await res.json().catch(()=>({}));
      if (res.status!==200){ document.getElementById('msg').textContent = 'Export error ' + res.status + ' ' + (j?.errors?.[0]?.message||''); return; }
      download(`schema-${conn}.json`, JSON.stringify(j?.data||j, null, 2));
    });

    document.getElementById('exportTableBtn').addEventListener('click', async function(){
      const conn = document.getElementById('connSel').value || 'primary';
      if (!selectedTable) return;
      const url = new URL(location.origin + api('/admin/db/table-schema'));
      url.searchParams.set('conn', conn);
      url.searchParams.set('table', selectedTable);
      const res = await fetch(url, { headers: { ...auth() } });
      const j = await res.json().catch(()=>({}));
      if (res.status!==200){ document.getElementById('msg').textContent = 'Export error ' + res.status + ' ' + (j?.errors?.[0]?.message||''); return; }
      download(`schema-${conn}-${selectedTable}.json`, JSON.stringify(j?.data||j, null, 2));
    });

    loadConnections().then(loadSchemas);
  </script>
</body>
</html>
