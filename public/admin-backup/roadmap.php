<?php
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);
require_once $root.'/lib/bootstrap.php';
use NGN\Lib\Env; use NGN\Lib\Config;
Env::load($root);
$cfg = new Config();
include __DIR__.'/_mint_token.php';

$pageTitle = 'Roadmap';
$currentPage = 'roadmap';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-6 space-y-6">
      <!-- Docs Links -->
      <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="flex items-center justify-between">
          <div class="text-sm text-gray-500 dark:text-gray-400">Governance & Policy</div>
          <div id="docsLinks" class="text-xs text-gray-500 dark:text-gray-400"></div>
        </div>
      </div>

      <!-- Filters -->
      <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
          <label class="flex flex-col text-sm">Status
            <select id="fStatus" class="mt-1 rounded border px-2 py-2 bg-white dark:bg-transparent dark:border-white/10">
              <option value="">All</option>
              <option value="pending">Pending</option>
              <option value="in_progress">In progress</option>
              <option value="done">Done</option>
            </select>
          </label>
          <label class="flex flex-col text-sm">Epic
            <select id="fEpic" class="mt-1 rounded border px-2 py-2 bg-white dark:bg-transparent dark:border-white/10">
              <option value="">All epics</option>
            </select>
          </label>
          <label class="flex flex-col text-sm">Search
            <input id="fQuery" type="text" placeholder="Title, task id…" class="mt-1 rounded border px-2 py-2 bg-white dark:bg-transparent dark:border-white/10" />
          </label>
          <div class="flex gap-2">
            <button id="btnApply" class="px-3 h-10 rounded bg-brand text-white text-sm">Apply</button>
            <button id="btnClear" class="px-3 h-10 rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 text-sm">Clear</button>
            <button id="btnRefresh" class="ml-auto px-3 h-10 rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 text-sm">Refresh</button>
          </div>
        </div>
      </div>

      <!-- Roadmap content -->
      <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="flex items-center justify-between mb-3">
          <div class="text-sm text-gray-500 dark:text-gray-400">Epics → Milestones → Tasks</div>
          <div id="stats" class="text-xs text-gray-500 dark:text-gray-400"></div>
        </div>
        <div id="roadmapList" class="space-y-3" role="tree" aria-label="Roadmap list">
          Loading…
        </div>
      </div>
    </main>
  </div>

<?php include __DIR__.'/_token_store.php'; ?>
  <script>
    // Heroicons (inline helpers)
    const icons = {
      flag: (cls='w-4 h-4')=>`<svg class="${cls}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3.75 3A.75.75 0 0 0 3 3.75v16.5a.75.75 0 1 0 1.5 0V14.5c1.239-.826 2.73-1.25 4.25-1.25 1.52 0 3.011.424 4.25 1.25 1.239-.826 2.73-1.25 4.25-1.25.837 0 1.665.118 2.45.35a.75.75 0 0 0 .95-.72V4.75a.75.75 0 0 0-.95-.72A10.52 10.52 0 0 1 17.25 4c-1.52 0-3.011.424-4.25 1.25C11.761 4.424 10.27 4 8.75 4c-1.52 0-3.011.424-4.25 1.25V3.75A.75.75 0 0 0 3.75 3z"/></svg>`,
      check: (cls='w-4 h-4')=>`<svg class="${cls}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9 12.75 6.75 10.5a.75.75 0 1 0-1.06 1.06l3 3a.75.75 0 0 0 1.06 0l7.5-7.5a.75.75 0 0 0-1.06-1.06L9 12.75z"/></svg>`,
      dot: (cls='w-2.5 h-2.5')=>`<span class="inline-block ${cls} rounded-full bg-brand"></span>`
    };

    const api = p => `/api/v1${p}`;
    const token = () => localStorage.getItem('ngn_admin_token') || localStorage.getItem('admin_token') || '';
    async function call(path){
      const res = await fetch(api(path), { headers: { 'Authorization': token()?('Bearer '+token()):'' } });
      const ct = res.headers.get('content-type')||''; const json = ct.includes('application/json') ? await res.json() : null;
      return { status: res.status, json };
    }

    function statusBadge(status){
      const base = 'inline-flex items-center px-2 py-1 rounded-full text-[11px] border ';
      if (status==='done') return base + 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:border-emerald-500/30';
      if (status==='in_progress') return base + 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:border-amber-500/30';
      return base + 'bg-gray-200 text-gray-700 border-gray-300 dark:bg-white/10 dark:text-gray-300 dark:border-white/10';
    }

    function renderDocs(docs){
      const el = document.getElementById('docsLinks');
      const items = [];
      if (docs?.fairness) items.push(`<a href="${docs.fairness}" class="underline" target="_blank">Fairness</a>`);
      if (docs?.scoring) items.push(`<a href="${docs.scoring}" class="underline" target="_blank">Scoring</a>`);
      if (docs?.acceptance) items.push(`<a href="${docs.acceptance}" class="underline" target="_blank">Acceptance</a>`);
      if (docs?.factors) items.push(`<a href="${docs.factors}" class="underline" target="_blank">Factors.json</a>`);
      if (docs?.cron) items.push(`<a href="${docs.cron}" class="underline" target="_blank">Cron</a>`);
      el.innerHTML = items.length ? items.join(' · ') : 'Docs not found';
    }

    function populateEpicFilter(epics){
      const sel = document.getElementById('fEpic');
      const seen = new Set();
      (epics||[]).forEach(e=>{
        const id = (e.id||e.title||'').toString();
        if (!id || seen.has(id)) return; seen.add(id);
        const opt = document.createElement('option');
        opt.value = id; opt.textContent = e.title || e.id || id; sel.appendChild(opt);
      });
    }

    function matchFilters(epic, status, q){
      const epicVal = (document.getElementById('fEpic').value||'').trim();
      const statusVal = (document.getElementById('fStatus').value||'').trim();
      const query = (document.getElementById('fQuery').value||'').trim().toLowerCase();
      if (epicVal && epic!==epicVal) return false;
      if (statusVal && status!==statusVal) return false;
      if (query) {
        return (q||'').toLowerCase().includes(query);
      }
      return true;
    }

    function renderRoadmap(data){
      const list = document.getElementById('roadmapList');
      list.innerHTML = '';
      const epics = data?.roadmap?.epics || [];

      // Basic stats
      let mCount = 0, tCount = 0, doneT = 0;
      epics.forEach(e=>{
        (e.milestones||[]).forEach(m=>{
          mCount++;
          (m.tasks||[]).forEach(t=>{ tCount++; if ((t.status||'')==='done') doneT++; });
        })
      });
      document.getElementById('stats').textContent = `${epics.length} epics · ${mCount} milestones · ${tCount} tasks`;

      // Render epics
      epics.forEach(e=>{
        const eId = e.id || e.title || 'Epic';
        const eWrap = document.createElement('div');
        eWrap.setAttribute('role','treeitem');
        eWrap.setAttribute('aria-expanded','true');
        eWrap.className = 'rounded border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5';
        const eHeader = document.createElement('div');
        eHeader.className = 'px-3 py-2 flex items-center justify-between border-b border-gray-200 dark:border-white/10';
        eHeader.innerHTML = `
          <div class="flex items-center gap-2">
            <span class="text-brand">${icons.flag('w-4 h-4')}</span>
            <div>
              <div class="text-sm font-semibold">${e.title || e.id}</div>
              <div class="text-[11px] text-gray-500 dark:text-gray-400">Owner: ${e.owner||'n/a'} · Weight: ${(e.weight??1)}</div>
            </div>
          </div>
          <span class="${statusBadge(e.status||'pending')}">${(e.status||'pending').replace('_',' ')}</span>
        `;
        eWrap.appendChild(eHeader);

        const mList = document.createElement('div');
        mList.className = 'divide-y divide-gray-200 dark:divide-white/10';
        (e.milestones||[]).forEach(m=>{
          const title = m.title || m.id || 'Milestone';
          const status = m.status || 'pending';
          const hay = `${e.title||''} ${title} ${(m.tasks||[]).map(t=>t.title||t.id).join(' ')}`;
          if (!matchFilters(eId.toString(), status, hay)) return;
          const row = document.createElement('div');
          row.className = 'p-3';
          const tasks = (m.tasks||[]);
          const present = tasks.filter(t=>t.status==='done').length;
          const total = tasks.length;
          const subt = total? `<div class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">${present}/${total} tasks</div>`: '';
          row.innerHTML = `
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="text-sm font-medium">${title}</div>
                ${subt}
                ${tasks.length? `<ul class="mt-2 space-y-1">${tasks.map(t=>`
                  <li class="flex items-center gap-2 text-sm">
                    <span class="${statusBadge(t.status||'pending')}">${(t.status||'pending').replace('_',' ')}</span>
                    <span>${t.title || t.id}</span>
                  </li>`).join('')}</ul>`: ''}
              </div>
              <span class="${statusBadge(status)}">${status.replace('_',' ')}</span>
            </div>
          `;
          mList.appendChild(row);
        });
        if (!mList.innerHTML) {
          const empty = document.createElement('div');
          empty.className = 'p-3 text-sm text-gray-500 dark:text-gray-400';
          empty.textContent = 'No milestones match the current filters.';
          mList.appendChild(empty);
        }
        eWrap.appendChild(mList);
        list.appendChild(eWrap);
      });

      if (!epics.length) {
        list.textContent = 'No roadmap data found. Ensure storage/plan/progress.json exists.';
      }
    }

    async function refresh(){
      document.getElementById('roadmapList').textContent = 'Loading…';
      const r = await call('/admin/roadmap');
      if (r.status===200) {
        renderDocs(r.json?.data?.docs || {});
        window.__roadmapData = r.json?.data || {};
        renderRoadmap(window.__roadmapData);
      } else if (r.status===401 || r.status===403) {
        document.getElementById('roadmapList').textContent = 'Authorization required. Open Settings once to mint an admin token.';
      } else {
        document.getElementById('roadmapList').textContent = 'Error '+r.status;
      }
      document.getElementById('ts').textContent = new Date().toLocaleString();
    }

    document.getElementById('btnRefresh')?.addEventListener('click', refresh);
    document.getElementById('refreshNow')?.addEventListener('click', refresh);
    document.getElementById('btnApply')?.addEventListener('click', ()=>{
      if (!window.__roadmapData) return; renderRoadmap(window.__roadmapData);
    });
    document.getElementById('btnClear')?.addEventListener('click', ()=>{
      document.getElementById('fStatus').value='';
      document.getElementById('fEpic').value='';
      document.getElementById('fQuery').value='';
      if (!window.__roadmapData) return; renderRoadmap(window.__roadmapData);
    });

    // Initial load: fetch once, populate epic filter
    (async function(){
      const r = await call('/admin/roadmap');
      if (r.status===200) {
        window.__roadmapData = r.json?.data || {};
        renderDocs(window.__roadmapData.docs || {});
        const epics = window.__roadmapData.roadmap?.epics || [];
        populateEpicFilter(epics);
        renderRoadmap(window.__roadmapData);
      } else {
        document.getElementById('roadmapList').textContent = (r.status===401||r.status===403)
          ? 'Authorization required. Open Settings once to mint an admin token.' : ('Error '+r.status);
      }
      document.getElementById('ts').textContent = new Date().toLocaleString();
    })();
  </script>
</body>
</html>
