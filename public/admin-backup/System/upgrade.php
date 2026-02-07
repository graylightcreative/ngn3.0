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

$pageTitle = 'Upgrade Wizard';
$currentPage = 'upgrade';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

      <section class="max-w-7xl mx-auto px-4 py-6 grid grid-cols-1 lg:grid-cols-[280px,1fr] gap-6">
        <!-- Stages -->
        <aside class="space-y-2">
          <div class="text-xs text-gray-500 dark:text-gray-400">Stages</div>
          <ul class="space-y-2">
            <li><button data-stage="checks" class="w-full text-left px-3 py-2 rounded border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 text-sm">1) Checks</button></li>
            <li><button data-stage="backups" class="w-full text-left px-3 py-2 rounded border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 text-sm">2) Backups</button></li>
            <li><button data-stage="migrate" class="w-full text-left px-3 py-2 rounded border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 text-sm">3) Migrate</button></li>
            <li><button data-stage="etl" class="w-full text-left px-3 py-2 rounded border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 text-sm">4) Data migration (ETL)</button></li>
            <li><button data-stage="seeds" class="w-full text-left px-3 py-2 rounded border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 text-sm">5) Seeds</button></li>
            <li><button data-stage="branding" class="w-full text-left px-3 py-2 rounded border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 text-sm">6) Branding / Meta</button></li>
            <li><button data-stage="maintenance" class="w-full text-left px-3 py-2 rounded border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 text-sm">7) Maintenance</button></li>
            <li><button data-stage="verify" class="w-full text-left px-3 py-2 rounded border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 text-sm">8) Verify</button></li>
            <li><button data-stage="rollback" class="w-full text-left px-3 py-2 rounded border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 text-sm">9) Rollback</button></li>
          </ul>
          <div class="mt-4 text-xs text-gray-500 dark:text-gray-400">Activity Log</div>
          <pre id="activityLog" class="mt-1 h-64 overflow-auto rounded bg-gray-50 dark:bg-white/5 p-2 text-xs"></pre>
        </aside>

        <!-- Content -->
        <div id="stageContent" class="space-y-6">
          <div id="checks" class="rounded-lg border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-4">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-semibold">Checks</h2>
              <button id="runChecks" class="rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 px-3 py-1.5 text-sm">Run checks</button>
            </div>
            <div id="checksOut" class="mt-3 text-sm"></div>
          </div>

          <div id="backups" class="rounded-lg border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-4">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-semibold">Backups</h2>
              <div class="flex items-center gap-2">
                <label class="inline-flex items-center text-sm"><input id="includeUploads" type="checkbox" class="mr-2">Include uploads (can be large)</label>
                <button id="runBackups" class="rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 px-3 py-1.5 text-sm">Run automatic backups</button>
              </div>
            </div>
            <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">Step 2 of 5. First, try automatic backups. If your host blocks them, export each database manually in your hosting panel, then confirm below.</p>
            <div class="mt-3">
              <div class="text-sm text-gray-600 dark:text-gray-300">Select DB worlds to back up:</div>
              <div id="dbWorlds" class="mt-2 flex flex-wrap gap-2"></div>
            </div>
            <div class="mt-3 space-y-2">
              <label class="inline-flex items-center text-xs text-gray-700 dark:text-gray-200"><input id="manualBackupsConfirmed" type="checkbox" class="mr-2">I have created fresh manual backups of all NGN databases and stored them safely.</label>
              <button id="confirmBackups" class="inline-flex items-center rounded border border-emerald-400 text-emerald-700 dark:text-emerald-200 dark:border-emerald-500 px-3 py-1.5 text-xs bg-emerald-50 dark:bg-emerald-500/10">Mark backups as done</button>
              <p class="text-[11px] text-gray-500 dark:text-gray-400">This button does not create backups. It simply unlocks the next step once yous finished your own DB exports.</p>
            </div>
            <div id="backupsOut" class="mt-3 text-sm"></div>
          </div>

          <div id="migrate" class="rounded-lg border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-4">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-semibold">Migrate</h2>
              <button id="runMigrate" class="rounded bg-amber-600 text-white px-3 py-1.5 text-sm">Apply pending</button>
            </div>
            <div id="migrateOut" class="mt-3 text-sm"></div>
          </div>
          <div id="etl" class="rounded-lg border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-4">
            <div class="flex items-center justify-between">
              <div>
                <h2 class="text-lg font-semibold">Data migration (ETL)</h2>
                <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">This step copies your 1.0 data (artists, labels, stations, releases, posts, spins) into the new 2025 databases. You can rerun ETL safely; it is idempotent.</p>
              </div>
              <button id="runEtl" class="rounded bg-emerald-600 text-white px-3 py-1.5 text-sm">Run all ETL seeds</button>
            </div>
            <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3 text-xs" id="etlCards">
              <!-- Filled from /admin/progress etl_seeds milestone -->
            </div>
            <div id="etlOut" class="mt-3 text-sm"></div>
          </div>



          <div id="seeds" class="rounded-lg border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-4">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-semibold">Seeds</h2>
              <button id="runSeeds" class="rounded bg-emerald-600 text-white px-3 py-1.5 text-sm" disabled>Run seeds (coming soon)</button>
            </div>
            <div id="seedsOut" class="mt-3 text-sm"></div>
          </div>

          <div id="branding" class="rounded-lg border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-4">
            <h2 class="text-lg font-semibold">Branding / Meta</h2>
            <div class="mt-2 text-sm">Use Settings → upload PNG for icons (ICO generated) and update Site Metadata. Current favicon version will appear in Checks.</div>
            <div class="mt-3"><a href="/admin/ngn2.php" class="rounded border px-3 py-1.5 text-sm">Open Settings</a></div>
          </div>

          <div id="maintenance" class="rounded-lg border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-4">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-semibold">Maintenance</h2>
              <div class="flex items-center gap-2">
                <button id="maintOn" class="rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 px-3 py-1.5 text-sm">Enable</button>
                <button id="maintOff" class="rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 px-3 py-1.5 text-sm">Disable</button>
              </div>
            </div>
            <div id="maintOut" class="mt-3 text-sm"></div>
          </div>

          <div id="verify" class="rounded-lg border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-4">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-semibold">Verify</h2>
              <button id="runVerify" class="rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 px-3 py-1.5 text-sm">Run verification</button>
            </div>
            <div id="verifyOut" class="mt-3 text-sm"></div>
          </div>

          <div id="rollback" class="rounded-lg border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-4">
            <h2 class="text-lg font-semibold">Rollback</h2>
            <div class="mt-2 text-sm">Use the latest DB dump files in <code>storage/backups/db/</code> and file archives in <code>storage/backups/files/</code>. The activity log contains the exact paths generated in this run.</div>
          </div>
        </div>
      </section>
    </main>
  </div>

<?php include __DIR__.'/_token_store.php'; ?>
  <script>
    const api = (p) => `/api/v1${p}`;
    const authHeader = () => {
      const t = localStorage.getItem('ngn_admin_token') || localStorage.getItem('ngn_access_token') || '';
      return t ? { 'Authorization': 'Bearer ' + t } : {};
    };
    const log = (obj) => {
      const el = document.getElementById('activityLog');
      const line = (typeof obj === 'string') ? obj : JSON.stringify(obj);
      el.textContent += (line + '\n');
      el.scrollTop = el.scrollHeight;
    };
    const wf = {
      checksOk: false,
      backupsOk: false,
      migrateOk: false,
      etlOk: false,
      verifyOk: false,
      maintenanceOn: false,
    };

    function recomputeWorkflow(){
      const btnChecks = document.querySelector('button[data-stage="checks"]');
      const btnBackups = document.querySelector('button[data-stage="backups"]');
      const btnMigrate = document.querySelector('button[data-stage="migrate"]');
      const btnEtl = document.querySelector('button[data-stage="etl"]');
      const btnVerify = document.querySelector('button[data-stage="verify"]');
      const runChecks = document.getElementById('runChecks');
      const runBackups = document.getElementById('runBackups');
      const runMigrate = document.getElementById('runMigrate');
      const runEtl = document.getElementById('runEtl');
      const runVerify = document.getElementById('runVerify');

      const stages = [
        { key: 'checks',  ok: wf.checksOk,  btn: btnChecks },
        { key: 'backups', ok: wf.backupsOk, btn: btnBackups },
        { key: 'migrate', ok: wf.migrateOk, btn: btnMigrate },
        { key: 'etl',     ok: wf.etlOk,     btn: btnEtl },
        { key: 'verify',  ok: wf.verifyOk,  btn: btnVerify },
      ];

      let firstPending = null;
      stages.forEach(s => {
        if (!s.btn) return;
        s.btn.classList.remove('opacity-40', 'border-emerald-400', 'bg-emerald-50', 'text-emerald-700', 'border-amber-400', 'bg-amber-50', 'text-amber-700');
        if (s.ok) {
          s.btn.classList.add('border-emerald-400', 'bg-emerald-50', 'text-emerald-700');
        } else if (!firstPending) {
          firstPending = s;
          s.btn.classList.add('border-amber-400', 'bg-amber-50', 'text-amber-700');
        } else {
          s.btn.classList.add('opacity-40');
        }
      });

      if (runChecks) {
        runChecks.disabled = false;
      }
      if (runBackups) {
        const can = wf.checksOk;
        runBackups.disabled = !can;
        runBackups.classList.toggle('opacity-50', !can);
        runBackups.classList.toggle('cursor-not-allowed', !can);
      }
      if (runMigrate) {
        // Allow migrations once checks + backups are confirmed, without hard-gating on maintenance flag
        const can = wf.checksOk && wf.backupsOk;
        runMigrate.disabled = !can;
        runMigrate.classList.toggle('opacity-50', !can);
        runMigrate.classList.toggle('cursor-not-allowed', !can);
      }
      if (runEtl) {
        const can = wf.migrateOk;
        runEtl.disabled = !can;
        runEtl.classList.toggle('opacity-50', !can);
        runEtl.classList.toggle('cursor-not-allowed', !can);
      }
      if (runVerify) {
        const can = wf.etlOk || wf.migrateOk; // allow verify once ETL has run (or at least schema migrated)
        runVerify.disabled = !can;
        runVerify.classList.toggle('opacity-50', !can);
        runVerify.classList.toggle('cursor-not-allowed', !can);
      }
    }



    async function refreshChecks(){
      const checksOut = document.getElementById('checksOut');
      checksOut.textContent = 'Running…';
      try {
        const r = await fetch(api('/admin/upgrade/checks'), { headers: { ...authHeader() }});
        const j = await r.json();
        const c = j?.data?.checks || {};
        const worlds = c?.db_worlds || [];
        const pill = document.getElementById('maintPill');
        const maintOn = !!c.maintenance_mode;
        wf.maintenanceOn = maintOn;
        wf.checksOk = true;
        recomputeWorkflow();
        pill.textContent = maintOn ? 'Maintenance: ON' : 'Maintenance: OFF';
        pill.className = 'ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs border ' + (maintOn ? 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-500/20 dark:text-amber-200 dark:border-amber-500/40' : 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-500/20 dark:text-emerald-200 dark:border-emerald-500/40');
        // Worlds UI
        const wrap = document.getElementById('dbWorlds');
        wrap.innerHTML = '';
        worlds.forEach(w => {
          const id = 'w_'+(w.name||w);
          const label = document.createElement('label');
          label.className = 'inline-flex items-center gap-2 text-sm px-2 py-1 rounded border border-gray-200 dark:border-white/10';
          const cb = document.createElement('input');
          cb.type = 'checkbox';
          cb.id = id; cb.value = (w.name||w);
          cb.checked = true;
          label.appendChild(cb);
          const span = document.createElement('span');
          span.textContent = (w.name||w);
          label.appendChild(span);
          wrap.appendChild(label);
        });
        checksOut.textContent = '';
        const pre = document.createElement('pre'); pre.className = 'bg-gray-50 dark:bg-white/5 p-2 rounded text-xs overflow-auto';
        pre.textContent = JSON.stringify(c, null, 2);
        checksOut.appendChild(pre);
        log({stage:'checks', ok:true, result:c});
      } catch (e) {
        checksOut.textContent = 'Error: ' + (e?.message||'failed');
        log({stage:'checks', ok:false, error: e?.message||'failed'});
      }
    }

    async function runBackups(){
      const backupsOut = document.getElementById('backupsOut');
      backupsOut.textContent = 'Starting backups…';
      const includeUploads = !!document.getElementById('includeUploads')?.checked;
      const selected = Array.from(document.querySelectorAll('#dbWorlds input[type="checkbox"]:checked')).map(x => x.value);
      try {
        const r = await fetch(api('/admin/upgrade/backups'), { method:'POST', headers: { 'Content-Type':'application/json', ...authHeader() }, body: JSON.stringify({ connections: selected, include_uploads: includeUploads }) });
        const j = await r.json();
        const ok = r.status === 200 && (j?.data?.ok !== false);
        const out = j?.data || j || {};
        if (ok) { wf.backupsOk = true; recomputeWorkflow(); }
        const pre = document.createElement('pre'); pre.className = 'bg-gray-50 dark:bg-white/5 p-2 rounded text-xs overflow-auto mt-2';
        pre.textContent = JSON.stringify(out, null, 2);
        backupsOut.innerHTML = (ok ? 'Backups completed.' : 'Backups finished with errors.')
        backupsOut.appendChild(pre);
        log({stage:'backups', ok, result: out});
        // Refresh maintenance pill
        await refreshChecks();
      } catch (e) {
        backupsOut.textContent = 'Error: ' + (e?.message||'failed');
        log({stage:'backups', ok:false, error:e?.message||'failed'});
      }
    }

    async function runEtl(){
      const out = document.getElementById('etlOut');
      const cards = document.getElementById('etlCards');
      out.textContent = 'Running ETL seeds…';
      try {
        const r = await fetch(api('/admin/upgrade/migrate'), { method:'POST', headers: { ...authHeader() } });
        const j = await r.json();
        const ok = r.status === 200;
        const data = j?.data || j || {};
        if (ok) {
          wf.migrateOk = true;
          wf.etlOk = true;
          recomputeWorkflow();
        }
        const pre = document.createElement('pre'); pre.className = 'bg-gray-50 dark:bg-white/5 p-2 rounded text-xs overflow-auto mt-2';
        pre.textContent = JSON.stringify(data, null, 2);
        out.innerHTML = ok ? 'ETL seeds completed. You can rerun this safely if you add new data later.' : ('ETL failed: ' + (j?.errors?.[0]?.message || r.status));
        out.appendChild(pre);
        log({ stage:'etl', ok, result:data });
        // Refresh /admin progress to redraw ETL cards with latest counts
        try { await fetchProgressForEtl(cards); } catch(e){}
      } catch (e) {
        out.textContent = 'Error: ' + (e?.message || 'failed');
        log({ stage:'etl', ok:false, error:e?.message||'failed' });
      }
    }

    async function fetchProgressForEtl(cardsEl){
      const target = cardsEl || document.getElementById('etlCards');
      if (!target) return;
      try {
        const r = await fetch(api('/admin/progress'), { headers: { ...authHeader() }});
        const j = await r.json();
        const miles = j?.data?.milestones || [];
        const etl = miles.find(m => m.key === 'etl_seeds');
        target.innerHTML = '';
        if (!etl || !etl.meta || !etl.meta.etl){
          const d = document.createElement('div');
          d.className = 'text-xs text-gray-500 dark:text-gray-400';
          d.textContent = 'Run Checks once, then Migrate, to see detailed ETL status here.';
          target.appendChild(d);
          return;
        }
        const detail = etl.meta.etl;
        const prettyLabel = {
          artists: 'Artists',
          labels: 'Labels',
          stations: 'Stations',
          releases_tracks: 'Releases & Tracks',
          posts: 'posts',
          station_spins: 'Station spins',
          smr_chart: 'SMR chart',
        };
        Object.keys(detail).forEach(key => {
          const row = detail[key] || {};
          const legacy = row.legacy_rows ?? '—';
          const cdm = row.cdm_rows ?? '—';
          const applied = !!row.migration_applied;
          const status = applied && cdm && legacy ? (cdm >= legacy ? 'done' : 'in_progress') : (applied ? 'in_progress' : 'pending');
          const badge = status === 'done' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/40' : (status === 'in_progress' ? 'bg-amber-500/10 text-amber-400 border-amber-500/40' : 'bg-gray-500/5 text-gray-400 border-gray-500/30');
          const card = document.createElement('div');
          card.className = 'rounded border px-3 py-2 ' + badge;
          card.innerHTML = `<div class=\"flex items-center justify-between mb-1\"><span class=\"text-[11px] font-semibold\">${prettyLabel[key] || key}</span><span class=\"text-[10px] uppercase tracking-wide\">${status}</span></div><div class=\"text-[11px]\">Legacy rows: <span class=\"font-mono\">${legacy}</span></div><div class=\"text-[11px]\">2025 rows: <span class=\"font-mono\">${cdm}</span></div>`;
          target.appendChild(card);
        });
      } catch (e) {
        if (cardsEl) return;
      }
    }

    async function runMigrate(){
      const migrateOut = document.getElementById('migrateOut');
      migrateOut.textContent = 'Applying pending migrations…';
      try {
        const r = await fetch(api('/admin/upgrade/migrate'), { method:'POST', headers: { ...authHeader() } });
        const j = await r.json();
        const ok = r.status === 200;
        const out = j?.data || j || {};
        if (ok) { wf.migrateOk = true; recomputeWorkflow(); }
        const pre = document.createElement('pre'); pre.className = 'bg-gray-50 dark:bg-white/5 p-2 rounded text-xs overflow-auto mt-2';
        pre.textContent = JSON.stringify(out, null, 2);
        migrateOut.innerHTML = ok ? 'Migrations applied.' : ('Migration failed: '+ (j?.errors?.[0]?.message||r.status));
        migrateOut.appendChild(pre);
        log({stage:'migrate', ok, result: out});
      } catch (e) {
        migrateOut.textContent = 'Error: ' + (e?.message||'failed');
        log({stage:'migrate', ok:false, error:e?.message||'failed'});
      }
    }

    async function runVerify(){
      const verifyOut = document.getElementById('verifyOut');
      verifyOut.textContent = 'Running verification…';
      try {
        const r = await fetch(api('/admin/upgrade/verify'), { headers: { ...authHeader() } });
        const j = await r.json();
        const out = j?.data || j || {};
        wf.verifyOk = true;
        recomputeWorkflow();

        // Pretty UI summary for ETL/data checks
        const v = out?.verify || {};
        const etl = v.etl || {};
        let summary = 'Verification complete.';
        const problems = Object.keys(etl).filter(k => etl[k] && etl[k].ok === false);
        if (problems.length === 0) {
          summary = 'Verification complete. All ETL/data checks look healthy.';
        } else {
          summary = 'Verification complete with warnings. Some ETL checks need review.';
        }

        const pre = document.createElement('pre'); pre.className = 'bg-gray-50 dark:bg-white/5 p-2 rounded text-xs overflow-auto mt-2';
        pre.textContent = JSON.stringify(out, null, 2);
        verifyOut.innerHTML = summary;
        verifyOut.appendChild(pre);
        log({stage:'verify', ok:true, result: out});
      } catch (e) {
        verifyOut.textContent = 'Error: ' + (e?.message||'failed');
        log({stage:'verify', ok:false, error:e?.message||'failed'});
      }
    }

    async function maintToggle(enabled){
      const maintOut = document.getElementById('maintOut');
      maintOut.textContent = (enabled?'Enabling':'Disabling')+ ' maintenance…';
      wf.maintenanceOn = !!enabled;
      recomputeWorkflow();
      try {
        const r = await fetch(api('/admin/maintenance'), { method:'PUT', headers: { 'Content-Type':'application/json', ...authHeader() }, body: JSON.stringify({ enabled }) });
        const j = await r.json();
        maintOut.textContent = 'Maintenance: ' + ((j?.data?.enabled===true)?'ON':'OFF');
        await refreshChecks();
        log({stage:'maintenance', ok:true, result:j?.data||j||{}});
      } catch (e) {
        maintOut.textContent = 'Error: ' + (e?.message||'failed');
        log({stage:'maintenance', ok:false, error:e?.message||'failed'});
      }
    }

    // Wire UI
    document.querySelectorAll('button[data-stage]').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const id = btn.getAttribute('data-stage');
        const el = document.getElementById(id);
        if (el) el.scrollIntoView({ behavior:'smooth', block:'start' });
      });
    });
    document.getElementById('runChecks').addEventListener('click', refreshChecks);
    document.getElementById('runBackups').addEventListener('click', runBackups);
    document.getElementById('runMigrate').addEventListener('click', runMigrate);
    document.getElementById('runEtl').addEventListener('click', runEtl);
    document.getElementById('runVerify').addEventListener('click', runVerify);
    document.getElementById('confirmBackups').addEventListener('click', function(){
      var cb = document.getElementById('manualBackupsConfirmed');
      if (!cb || !cb.checked) {
        alert('Please tick the confirmation box to confirm you have manual backups before marking this step as done.');
        return;
      }
      wf.backupsOk = true;
      recomputeWorkflow();
      var el = document.getElementById('backupsOut');
      if (el) {
        el.textContent = 'Backups marked as done (manual confirmation). You are responsible for ensuring your DB exports are valid.';
      }
      log({ stage: 'backups', manualOverride: true, ok: true });
    });
    document.getElementById('maintOn').addEventListener('click', ()=>maintToggle(true));
    document.getElementById('maintOff').addEventListener('click', ()=>maintToggle(false));

    // Initial
    refreshChecks();
  </script>
</body>
</html>
