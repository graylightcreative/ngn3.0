<?php
// CLI-only: Migrate legacy lib/definitions/.env values into the new .env schema.
// Usage:
//   php jobs/tools/env_migrate.php          # dry-run (no writes)
//   php jobs/tools/env_migrate.php --write  # write missing keys to ./.env (with backup)
//   php jobs/tools/env_migrate.php --write --backup=/path/to/backups

use NGN\Lib\Env;

$root = dirname(__DIR__, 2);
require_once $root . '/lib/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden"; exit(1);
}

if (!class_exists(Env::class)) { ngn_autoload_diagnostics($root, true); exit(2); }
Env::load($root);

// Parse args
$write = in_array('--write', $argv, true);
$backupDirArg = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--backup=')) {
        $backupDirArg = substr($arg, 9);
    }
}

// Helpers
$parseEnvFile = function(string $path): array {
    $out = [];
    if (!is_file($path)) return $out;
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (stripos($line, 'export ') === 0) { $line = trim(substr($line, 7)); }
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) continue;
        $k = trim($parts[0]);
        $v = trim($parts[1]);
        if ($k === '') continue;
        if ((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
            $v = substr($v, 1, -1);
        }
        $out[$k] = $v;
    }
    return $out;
};
$readCurrentEnv = function(string $root) use ($parseEnvFile): array {
    $path = $root.'/.env';
    return $parseEnvFile($path);
};
$mask = function(?string $v, string $key): ?string {
    if ($v === null || $v === '') return $v;
    $uk = strtoupper($key);
    $sensitive = ['KEY','SECRET','TOKEN','PASS','PASSWORD','API_KEY'];
    foreach ($sensitive as $frag) {
        if (str_contains($uk, $frag)) {
            $len = strlen($v);
            if ($len <= 6) return str_repeat('*', $len);
            return substr($v, 0, 3) . str_repeat('*', max(0,$len-6)) . substr($v, -3);
        }
    }
    return $v;
};

$legacyPath = $root . '/lib/definitions/.env';
$legacy = $parseEnvFile($legacyPath);
$current = $readCurrentEnv($root);

// Build mapping legacy -> new schema values
$suggest = [];
// APP_ENV from DEV
if (array_key_exists('DEV', $legacy)) {
    $suggest['APP_ENV'] = ((string)$legacy['DEV'] === '1' || strtolower((string)$legacy['DEV']) === 'true') ? 'development' : 'production';
}
// DB
if (isset($legacy['DB_SERVER'])) $suggest['DB_HOST'] = $legacy['DB_SERVER'];
$suggest['DB_PORT'] = $suggest['DB_PORT'] ?? '3306';
foreach (['DB_NAME','DB_USER','DB_PASS'] as $k) {
    if (isset($legacy[$k])) $suggest[$k] = $legacy[$k];
}
// JWT
if (isset($legacy['JWTKEY'])) $suggest['JWT_SECRET'] = $legacy['JWTKEY'];
$suggest['JWT_ISS'] = $suggest['JWT_ISS'] ?? 'ngn';
$suggest['JWT_AUD'] = $suggest['JWT_AUD'] ?? 'ngn-clients';
$suggest['JWT_TTL_SECONDS'] = $suggest['JWT_TTL_SECONDS'] ?? '900';
$suggest['JWT_REFRESH_TTL_SECONDS'] = $suggest['JWT_REFRESH_TTL_SECONDS'] ?? '1209600';
// Features
if (($suggest['APP_ENV'] ?? ($current['APP_ENV'] ?? 'production')) === 'development') {
    $suggest['FEATURE_ADMIN'] = 'true';
}
// Defaults
$suggest += [
    'LOG_PATH' => $current['LOG_PATH'] ?? 'storage/logs',
    'LOG_LEVEL' => $current['LOG_LEVEL'] ?? 'info',
    'UPLOAD_MAX_MB' => $current['UPLOAD_MAX_MB'] ?? '50',
    'UPLOAD_ALLOWED_MIME' => $current['UPLOAD_ALLOWED_MIME'] ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv',
    'UPLOAD_DIR' => $current['UPLOAD_DIR'] ?? 'storage/uploads',
    'UPLOAD_RETENTION_DAYS' => $current['UPLOAD_RETENTION_DAYS'] ?? '30',
    'RANKINGS_CACHE_TTL_SECONDS' => $current['RANKINGS_CACHE_TTL_SECONDS'] ?? '300',
    'RANKINGS_CACHE_DIR' => $current['RANKINGS_CACHE_DIR'] ?? 'storage/cache/rankings',
    'MAX_JSON_BODY_BYTES' => $current['MAX_JSON_BODY_BYTES'] ?? '1048576',
];

// Determine which keys are missing in current .env
$toWrite = [];
foreach ($suggest as $k => $v) {
    if (!array_key_exists($k, $current) || $current[$k] === '') {
        $toWrite[$k] = $v;
    }
}

// Output report
fwrite(STDOUT, "NGN .env migrator (dry-run=" . ($write ? 'false' : 'true') . ")\n");
fwrite(STDOUT, "Legacy env: $legacyPath => " . (is_file($legacyPath) ? 'found' : 'missing') . "\n");
$envPath = $root.'/.env';
fwrite(STDOUT, "Target .env: $envPath => " . (is_file($envPath) ? 'found' : 'missing (will create)') . "\n\n");

if (empty($toWrite)) {
    fwrite(STDOUT, "No missing keys detected. Your .env appears complete for the new schema.\n");
    exit(0);
}

fwrite(STDOUT, "Keys to add/update (values masked for secrets):\n");
foreach ($toWrite as $k => $v) {
    fwrite(STDOUT, sprintf("  - %s=%s\n", $k, $mask((string)$v, $k)));
}

if (!$write) {
    fwrite(STDOUT, "\nRun with --write to apply these keys to your .env (a backup will be created).\n");
    exit(0);
}

// Ensure backup directory
$backupDir = $backupDirArg ?: ($root . '/storage/backups');
if (!is_dir($backupDir)) { @mkdir($backupDir, 0775, true); }

// Backup current .env if present
if (is_file($envPath)) {
    $ts = date('Ymd_His');
    $backupPath = rtrim($backupDir, '/')."/.env.backup.$ts";
    @copy($envPath, $backupPath);
    fwrite(STDOUT, "Backup created: $backupPath\n");
}

// Append missing keys to .env (non-destructive)
$lines = [];
$lines[] = "\n# --- Added by NGN migrator on " . date('c') . " ---";
foreach ($toWrite as $k => $v) {
    // Quote values that contain spaces or special chars
    $val = (string)$v;
    $needsQuote = preg_match('/\s|[#\\n\\r]/', $val) === 1;
    if ($needsQuote) {
        // prefer single quotes; escape single quotes inside
        $val = "'" . str_replace("'", "'\''", $val) . "'";
    }
    $lines[] = $k . '=' . $val;
}
$append = implode("\n", $lines) . "\n";
file_put_contents($envPath, $append, FILE_APPEND | LOCK_EX);

fwrite(STDOUT, "\nWrote " . count($toWrite) . " keys to $envPath. Reload PHP-FPM/clear OPcache to apply.\n");
exit(0);
