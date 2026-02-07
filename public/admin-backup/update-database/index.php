<?php
$root = dirname(__DIR__, 1);
require $root . '/lib/bootstrap.php';

// Very light auth: require legacy admin session
@session_start();
$isAdmin = !empty($_SESSION['User']['RoleId']) && (string)$_SESSION['User']['RoleId'] === '1';
if (!$isAdmin) {
    http_response_code(403);
    echo 'Forbidden — admin only';
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

// Collect .sql files
function list_sql_files(string $baseDir): array {
    $files = [];
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS));
    foreach ($iter as $f) {
        if ($f->isFile() && strtolower($f->getExtension()) === 'sql') {
            // Normalize path relative to project root
            $files[] = str_replace('\\', '/', $f->getPathname());
        }
    }
    sort($files);
    return $files;
}

$projectRoot = dirname(__DIR__, 1);
$migrationsDir = $projectRoot . '/migrations';
$sqlFiles = is_dir($migrationsDir) ? list_sql_files($migrationsDir) : [];

// Env & flags for guardrails
$appEnv = strtolower((string)(getenv('APP_ENV') ?: 'development'));
$canProd = in_array(strtolower((string)(getenv('FEATURE_DB_CONSOLE_PROD') ?: 'false')), ['1','true','on','yes'], true);
$consoleEnabled = in_array(strtolower((string)(getenv('FEATURE_DB_CONSOLE') ?: 'true')), ['1','true','on','yes'], true);

if (!$consoleEnabled) {
    http_response_code(403);
    echo 'Database console disabled (FEATURE_DB_CONSOLE=false).';
    exit;
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>NGN — Update Database</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#0b1020;color:#f8fafc;margin:0;padding:24px}
    .card{max-width:1100px;margin:0 auto;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:18px}
    h1{margin:0 0 10px}
    .muted{color:#9ca3af}
    .row{display:grid;grid-template-columns:1fr;gap:12px}
    @media(min-width:900px){.row{grid-template-columns:300px 1fr}}
    .list{max-height:60vh;overflow:auto;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:8px;padding:10px}
    .file{display:flex;align-items:center;gap:8px;padding:6px 4px;border-bottom:1px solid rgba(255,255,255,.06)}
    .file:last-child{border-bottom:none}
    .controls{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
    .btn{appearance:none;border:1px solid rgba(255,255,255,.18);background:#1DB954;color:#06120a;border-radius:8px;padding:8px 12px;font-weight:700;cursor:pointer}
    .btn.secondary{background:transparent;color:#f8fafc}
    .out{white-space:pre-wrap;background:#030712;border:1px solid rgba(255,255,255,.12);border-radius:8px;padding:10px;min-height:160px}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
    label small{color:#9ca3af}
  </style>
</head>
<body>
  <div class="card">
    <h1>Update Database</h1>
    <p class="muted">Run SQL migrations, schema updates, and ETL across ngn_2025 and shard databases. Admin only.</p>
    <?php if ($appEnv === 'production'): ?>
      <div style="margin:8px 0 12px;padding:10px;border:1px solid rgba(255,0,0,.35);background:rgba(255,0,0,.08);border-radius:8px">
        <strong>Production mode</strong> —
        <?php if ($canProd): ?>
          console allowed with confirmation.
        <?php else: ?>
          console disabled (set FEATURE_DB_CONSOLE_PROD=true to allow).
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <form id="runner" class="row" method="post" action="run.php">
      <div>
        <strong>Available SQL files</strong>
        <div class="controls">
          <button class="btn secondary" type="button" onclick="toggleAll(true)">Select All</button>
          <button class="btn secondary" type="button" onclick="toggleAll(false)">Select None</button>
        </div>
        <div class="list" id="files">
          <?php foreach ($sqlFiles as $i => $file): $rel = str_replace($projectRoot . '/', '', $file); ?>
            <label class="file">
              <input type="checkbox" name="files[]" value="<?=htmlspecialchars($rel)?>" />
              <span><?=htmlspecialchars($rel)?></span>
            </label>
          <?php endforeach; ?>
          <?php if (empty($sqlFiles)): ?>
            <div class="muted">No .sql files found under /migrations</div>
          <?php endif; ?>
        </div>
      </div>
      <div>
        <div class="grid">
          <label>Target Connection
            <small class="muted"><br/>Used for execution; scripts may reference other schemas explicitly.</small>
            <select name="target" style="width:100%;padding:8px;border-radius:8px;border:1px solid rgba(255,255,255,.18);background:#020617;color:#f8fafc">
              <option value="primary">primary (DEV_DB → ngn_2025)</option>
              <option value="rankings">rankings (NGNRANKINGS/2025)</option>
              <option value="smr">smr</option>
              <option value="spins">spins</option>
            </select>
          </label>
          <label>Mode
            <select name="mode" style="width:100%;padding:8px;border-radius:8px;border:1px solid rgba(255,255,255,.18);background:#020617;color:#f8fafc">
              <option value="apply">Apply</option>
              <option value="dry">Dry‑run (print only)</option>
            </select>
          </label>
        </div>
        <?php if ($appEnv === 'production'): ?>
          <div class="grid">
            <label>Confirmation
              <small class="muted"><br/>Type <code>RUN IN PRODUCTION</code> to enable Apply.</small>
              <input type="text" name="confirm" placeholder="RUN IN PRODUCTION" style="width:100%;padding:8px;border-radius:8px;border:1px solid rgba(255,255,255,.18);background:#020617;color:#f8fafc" />
            </label>
          </div>
        <?php endif; ?>
        <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)?>" />
        <div class="controls">
          <button class="btn" type="submit" <?php if ($appEnv==='production' && !$canProd) echo 'disabled title="Disabled in production"'; ?>>Run Selected</button>
          </div>
        <div id="out" class="out" style="margin-top:10px"></div>
      </div>
    </form>
  </div>

  <script>
  function toggleAll(on){
    document.querySelectorAll('#files input[type=checkbox]').forEach(cb=>cb.checked=!!on);
  }
  const form = document.getElementById('runner');
  // Presets
  function selectByPrefix(prefixes){
    const boxes=[...document.querySelectorAll('#files input[type=checkbox]')];
    boxes.forEach(cb=>{
      const p=cb.value.toLowerCase();
      cb.checked = prefixes.some(pref=>p.includes(pref));
    });
  }
  form.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const out = document.getElementById('out');
    out.textContent = 'Running...';
    const fd = new FormData(form);
    const res = await fetch('run.php', { method:'POST', body: fd });
    const txt = await res.text();
    out.textContent = txt;
  });
  </script>
</body>
</html>
