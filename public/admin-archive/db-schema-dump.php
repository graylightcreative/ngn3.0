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

$pageTitle = 'Schema Dump';
$currentPage = 'db-schema-dump';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

      <section class="max-w-7xl mx-auto px-4 py-6">
            <?php if ($mintedToken): ?>
              <span class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs border bg-brand/10 text-brand border-brand/30">Admin token minted</span>
            <?php endif; ?>
          </div>
          <div class="flex items-center gap-2">
            <label class="inline-flex items-center cursor-pointer select-none">
              <input id="darkToggle" type="checkbox" class="sr-only peer" onchange="__toggleTheme()" />
              <div class="w-11 h-6 bg-gray-200 dark:bg-gray-700 rounded-full relative transition-colors peer-checked:bg-brand">
                <div class="absolute top-[2px] left-[2px] h-5 w-5 bg-white rounded-full shadow transform transition-transform peer-checked:translate-x-5"></div>
              </div>
              <span class="ml-2 text-sm text-gray-600 dark:text-gray-300">Dark</span>
            </label>
            <button id="dumpAllBtn" class="inline-flex items-center px-3 h-9 rounded bg-brand text-white text-sm">Dump All</button>
            <button id="downloadBtn" class="inline-flex items-center px-3 h-9 rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 text-sm">Download JSON</button>
          </div>
        </div>
      </header>

      <section class="max-w-7xl mx-auto px-4 py-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">Options</div>
            <div class="space-y-2">
              <label class="block text-xs text-gray-500 dark:text-gray-400">Connection (optional)</label>
              <div class="grid grid-cols-1 gap-2">
                <select id="connSel" class="w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10"></select>
                <input id="conn" class="w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" placeholder="primary or named (e.g., ngnnotes)" />
                <div class="text-[11px] text-gray-500 dark:text-gray-400">Tip: pick from detected connections, or type a name.</div>
              </div>
              <label class="block text-xs text-gray-500 dark:text-gray-400">Max tables (optional)</label>
              <input id="maxTables" type="number" class="w-full rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10 px-3 h-10" placeholder="e.g., 500" />
              <div id="info" class="text-xs text-gray-500 dark:text-gray-400">Schema only; no data rows returned.</div>
            </div>
          </div>
          <div class="md:col-span-2 rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
            <div class="flex items-center justify-between mb-2">
              <div class="text-sm text-gray-500 dark:text-gray-400">Output (<span id="size">0</span> bytes) — generated <span id="ts">—</span></div>
              <button id="copyBtn" class="inline-flex items-center px-3 h-8 rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 text-xs">Copy</button>
            </div>
            <textarea id="out" rows="24" class="w-full font-mono text-xs rounded border border-gray-300 dark:border-white/10 bg-white dark:bg-[#0a0f1e] p-3" spellcheck="false"></textarea>
          </div>
        </div>
      </section>
    </main>
  </div>

<?php include __DIR__.'/_token_store.php'; ?>
  <script>
    const api = p => `/api/v1${p}`;
    const token = () => localStorage.getItem('ngn_admin_token') || localStorage.getItem('admin_token') || '';
    const auth = () => token() ? { 'Authorization': 'Bearer ' + token() } : {};

    function setOut(str){
      const ta = document.getElementById('out');
      ta.value = str || '';
      document.getElementById('size').textContent = (new Blob([ta.value]).size).toLocaleString();
      document.getElementById('ts').textContent = new Date().toLocaleString();
    }

    async function loadConnections(){
      const sel = document.getElementById('connSel');
      if (!sel) return;
      try {
        const res = await fetch(api('/admin/db/connections'), { headers: { ...auth() } });
        const j = await res.json().catch(()=>({}));
        const conns = j?.data?.connections || [];
        // Prefer order: primary, then named
        const options = conns.map(c=>({name:c.name, ok:!!c.ok}));
        sel.innerHTML = options.map(o=>`<option value="${o.name}">${o.name}${o.ok?'':' (err)'}<\/option>`).join('');
        // Preselect primary or first item
        const idx = options.findIndex(o=>o.name==='primary');
        sel.selectedIndex = idx >= 0 ? idx : 0;
        // Reflect into text input
        const input = document.getElementById('conn');
        if (input) { input.value = sel.value; }
      } catch (e) { /* ignore */ }
    }

    async function dump(){
      const connSel = document.getElementById('connSel');
      const connInput = document.getElementById('conn');
      const conn = (connInput?.value || connSel?.value || '').trim();
      const maxTables = document.getElementById('maxTables').value.trim();
      const url = new URL(location.origin + api('/admin/db/schema-dump'));
      if (conn) url.searchParams.set('conn', conn);
      if (maxTables) url.searchParams.set('max_tables', maxTables);
      const res = await fetch(url.toString(), { headers: { ...auth() } });
      let json = null; try { json = await res.json(); } catch(e){}
      if (res.status!==200){ setOut(JSON.stringify(json||{error: res.status}, null, 2)); return; }
      setOut(JSON.stringify(json.data || json, null, 2));
    }

    function download(){
      const blob = new Blob([document.getElementById('out').value], {type:'application/json'});
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'ngn-schema-dump.json';
      a.click();
      URL.revokeObjectURL(a.href);
    }

    document.getElementById('connSel').addEventListener('change', function(){ const input=document.getElementById('conn'); if (input) input.value=this.value; });
    document.getElementById('dumpAllBtn').addEventListener('click', dump);
    document.getElementById('downloadBtn').addEventListener('click', download);
    document.getElementById('copyBtn').addEventListener('click', ()=>{ const ta=document.getElementById('out'); ta.select(); document.execCommand('copy'); });

    // Initialize connection dropdown on load
    loadConnections();
  </script>
</body>
</html>
