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

$pageTitle = 'Charts';
$currentPage = 'charts';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

      <section class="max-w-7xl mx-auto px-4 py-6 space-y-4">
        <div class="flex items-center justify-between gap-4 mb-4 flex-wrap">
          <div>
            <h1 class="text-xl font-semibold">2025 Charts</h1>
            <p class="text-sm text-slate-400">Artist & label rankings from ngn_rankings_2025, joined to ngn_2025.</p>
          </div>
          <div class="inline-flex rounded-md bg-slate-900/60 border border-slate-700 p-1 text-xs">
            <button type="button" data-tab="artists" class="tab-btn px-3 py-1 rounded font-medium bg-slate-700 text-white">Artists</button>
            <button type="button" data-tab="labels" class="tab-btn px-3 py-1 rounded font-medium text-slate-200">Labels</button>
          </div>
        </div>

        <div class="flex items-center gap-3 mb-3 flex-wrap text-xs">
          <label class="flex items-center gap-2">
            <span class="text-slate-400">Interval</span>
            <select id="interval" class="bg-slate-900 border border-slate-700 rounded px-2 py-1 text-xs">
              <option value="daily">Daily</option>
              <option value="weekly" selected>Weekly</option>
              <option value="monthly">Monthly</option>
            </select>
          </label>
          <div id="window-meta" class="text-slate-400"></div>
        </div>

        <div id="table-wrapper" class="overflow-x-auto border border-slate-800 rounded-lg bg-slate-900/70">
          <table class="min-w-full text-xs">
            <thead class="bg-slate-900/90">
              <tr id="table-head-row"></tr>
            </thead>
            <tbody id="table-body"></tbody>
          </table>
        </div>

        <div id="pagination" class="flex items-center justify-between mt-3 text-xs text-slate-400">
          <div id="page-info"></div>
          <div class="inline-flex gap-1">
            <button type="button" id="prev-page" class="px-2 py-1 rounded border border-slate-700 bg-slate-900 disabled:opacity-40">Prev</button>
            <button type="button" id="next-page" class="px-2 py-1 rounded border border-slate-700 bg-slate-900 disabled:opacity-40">Next</button>
          </div>
        </div>

        <div id="state" class="mt-3 text-xs text-gray-500 dark:text-gray-400"></div>
      </section>
    </main>
  </div>

<?php include __DIR__.'/_token_store.php'; ?>
  <script>
    (function(){
      const state = {
        tab: 'artists',
        interval: 'weekly',
        page: 1,
        perPage: 25,
        total: 0,
        loading: false,
      };

      const apiBase = '/api/v1/admin/2025/charts';

      function authHeaders(){
        try {
          const token = window.localStorage.getItem('ngn_admin_token') || '';
          return token ? { 'Authorization': 'Bearer '+token } : {};
        } catch(e) { return {}; }
      }

      function setLoading(on){
        state.loading = !!on;
        const s = document.getElementById('state');
        if (s) s.textContent = on ? 'Loading…' : '';
      }

      function updateTabs(){
        document.querySelectorAll('.tab-btn').forEach(btn => {
          const t = btn.getAttribute('data-tab');
          if (t === state.tab) {
            btn.classList.add('bg-slate-700','text-white');
            btn.classList.remove('text-slate-200');
          } else {
            btn.classList.remove('bg-slate-700','text-white');
            btn.classList.add('text-slate-200');
          }
        });
      }

      function renderTableHead(){
        const head = document.getElementById('table-head-row');
        if (!head) return;
        head.innerHTML = '';
        const cols = [
          { key: 'rank', label: 'Rank', width: '60px' },
          { key: 'name', label: state.tab === 'artists' ? 'Artist' : 'Label' },
          { key: 'score', label: 'Score', width: '120px' },
          { key: 'prev_rank', label: 'Prev', width: '80px' },
        ];
        cols.forEach(c => {
          const th = document.createElement('th');
          th.textContent = c.label;
          th.className = 'px-3 py-2 text-left font-semibold text-slate-300';
          if (c.width) th.style.width = c.width;
          head.appendChild(th);
        });
      }

      function renderTableBody(items){
        const body = document.getElementById('table-body');
        if (!body) return;
        body.innerHTML = '';
        if (!Array.isArray(items) || items.length === 0) {
          const tr = document.createElement('tr');
          const td = document.createElement('td');
          td.colSpan = 4;
          td.textContent = 'No chart data yet.';
          td.className = 'px-3 py-4 text-slate-500';
          tr.appendChild(td);
          body.appendChild(tr);
          return;
        }
        items.forEach(row => {
          const tr = document.createElement('tr');
          tr.className = 'border-t border-slate-800';
          const name = row.artist_name || row.label_name || '';
          const cells = [
            '#'+String(row.rank ?? ''),
            name || 'Unknown',
            String(row.score ?? ''),
            row.prev_rank != null ? String(row.prev_rank) : '-',
          ];
          cells.forEach(text => {
            const td = document.createElement('td');
            td.textContent = text;
            td.className = 'px-3 py-2';
            body.appendChild(tr).appendChild(td);
          });
        });
      }

      function updatePagination(){
        const info = document.getElementById('page-info');
        const prev = document.getElementById('prev-page');
        const next = document.getElementById('next-page');
        const totalPages = state.perPage > 0 ? Math.max(1, Math.ceil(state.total / state.perPage)) : 1;
        if (info) info.textContent = 'Page '+state.page+' of '+totalPages+' — '+state.total+' items';
        if (prev) prev.disabled = state.page <= 1;
        if (next) next.disabled = state.page >= totalPages;
      }

      async function loadData(){
        setLoading(true);
        renderTableHead();
        const url = new URL(apiBase + '/' + state.tab, window.location.origin);
        url.searchParams.set('interval', state.interval);
        url.searchParams.set('page', String(state.page));
        url.searchParams.set('per_page', String(state.perPage));
        let payload = null;
        try {
          const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json', ...authHeaders() } });
          payload = await res.json();
        } catch (e) {
          const s = document.getElementById('state');
          if (s) s.textContent = 'Error loading data.';
        }
        setLoading(false);
        const data = payload && payload.data ? payload.data : payload;
        const items = data && Array.isArray(data.items) ? data.items : [];
        state.total = typeof data.total === 'number' ? data.total : items.length;
        renderTableBody(items);
        updatePagination();
        const metaEl = document.getElementById('window-meta');
        if (metaEl) {
          const w = data && data.window ? data.window : null;
          if (w) {
            metaEl.textContent = 'Window '+(w.window_start || '')+' → '+(w.window_end || '');
          } else {
            metaEl.textContent = '';
          }
        }
      }

      document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('.tab-btn').forEach(btn => {
          btn.addEventListener('click', () => {
            const t = btn.getAttribute('data-tab');
            if (!t || t === state.tab) return;
            state.tab = t;
            state.page = 1;
            updateTabs();
            loadData();
          });
        });
        const interval = document.getElementById('interval');
        if (interval) {
          interval.addEventListener('change', () => {
            state.interval = interval.value || 'weekly';
            state.page = 1;
            loadData();
          });
        }
        const prev = document.getElementById('prev-page');
        const next = document.getElementById('next-page');
        if (prev) prev.addEventListener('click', () => {
          if (state.page > 1) { state.page--; loadData(); }
        });
        if (next) next.addEventListener('click', () => {
          const totalPages = state.perPage > 0 ? Math.max(1, Math.ceil(state.total / state.perPage)) : 1;
          if (state.page < totalPages) { state.page++; loadData(); }
        });
        updateTabs();
        loadData();
      });
    })();
  </script>
</body>
</html>

