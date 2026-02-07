<?php
$root = dirname(__DIR__, 1);
require $root . '/lib/bootstrap.php';

@session_start();
$isAdmin = !empty($_SESSION['User']['RoleId']) && (string)$_SESSION['User']['RoleId'] === '1';
if (!$isAdmin) { http_response_code(403); echo "Forbidden"; exit; }
if (empty($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf_token'] ?? '')) { http_response_code(400); echo "Bad CSRF"; exit; }

header('Content-Type: text/plain');

// Helpers to read env (via loaded Env in bootstrap)


function pdo_for_target(string $target): PDO {
    $config = new \NGN\Lib\Config();
    try {
        switch ($target) {
            case 'rankings':
                return \NGN\Lib\DB\ConnectionFactory::named($config, 'NGN_RANKINGS_2025');
            case 'smr':
                return \NGN\Lib\DB\ConnectionFactory::named($config, 'NGN_SMR_2025');
            case 'spins':
                return \NGN\Lib\DB\ConnectionFactory::named($config, 'SPINS2025');
            case 'primary':
            default:
                return \NGN\Lib\DB\ConnectionFactory::write($config); // Assuming 'primary' maps to the default write connection
        }
    } catch (\Throwable $e) {
        throw new PDOException("Failed to connect to target DB '{$target}': " . $e->getMessage(), 0, $e);
    }
}

function record_migration(PDO $pdo, string $name): void {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (\n  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n  `name` VARCHAR(255) NOT NULL,\n  `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  PRIMARY KEY (`id`), UNIQUE KEY `uniq_name` (`name`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        $st = $pdo->prepare('INSERT IGNORE INTO `schema_migrations` (`name`) VALUES (?)');
        $st->execute([$name]);
    } catch (Throwable $e) {
        // Ignore recording errors
    }
}

$files = isset($_POST['files']) && is_array($_POST['files']) ? $_POST['files'] : [];
$mode = isset($_POST['mode']) ? strtolower((string)$_POST['mode']) : 'apply';
$target = isset($_POST['target']) ? (string)$_POST['target'] : 'primary';
$appEnv = strtolower((string)envv('APP_ENV','development'));
$canProd = in_array(strtolower((string)envv('FEATURE_DB_CONSOLE_PROD','false')), ['1','true','on','yes'], true);

// Production guardrails
if ($appEnv === 'production') {
    if (!$canProd) {
        http_response_code(403);
        echo "Disabled in production. Set FEATURE_DB_CONSOLE_PROD=true to allow.\n";
        exit;
    }
    if ($mode !== 'dry') {
        $confirm = isset($_POST['confirm']) ? trim((string)$_POST['confirm']) : '';
        if ($confirm !== 'RUN IN PRODUCTION') {
            http_response_code(400);
            echo "Confirmation required. Type RUN IN PRODUCTION.\n";
            exit;
        }
    }
}

if (empty($files)) { echo "No files selected.\n"; exit; }

$projectRoot = dirname(__DIR__, 1);

echo "Target: {$target}\nMode: {$mode}\n\n";

// Open PDO once per target
try { $pdo = pdo_for_target($target); }
catch (Throwable $e) { http_response_code(500); echo "Connection error: ".$e->getMessage()."\n"; exit; }

// Optional: logging connection (ngn_2025) for admin_migration_runs
$logPdo = null;
try { $logPdo = pdo_for_target('primary'); } catch (Throwable $e) { $logPdo = null; }

function log_run_start(?PDO $logPdo, string $file, string $target, string $mode): ?int {
    if (!$logPdo) return null;
    try {
        $logPdo->exec("CREATE TABLE IF NOT EXISTS `admin_migration_runs` (\n  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n  `file` VARCHAR(512) NOT NULL,\n  `target` VARCHAR(32) NOT NULL,\n  `mode` VARCHAR(16) NOT NULL,\n  `started_at` DATETIME NOT NULL,\n  `ended_at` DATETIME NULL,\n  `status` ENUM('ok','error','skip') NOT NULL,\n  `error` TEXT NULL,\n  `rows_affected` BIGINT NULL,\n  PRIMARY KEY (`id`),\n  KEY `idx_target_time` (`target`,`started_at`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n");
        $st = $logPdo->prepare('INSERT INTO `admin_migration_runs` (`file`,`target`,`mode`,`started_at`,`status`) VALUES (?,?,?,?,?)');
        $st->execute([$file, $target, $mode, date('Y-m-d H:i:s'), 'skip']);
        return (int)$logPdo->lastInsertId();
    } catch (Throwable $e) { return null; }
}

function log_run_end(?PDO $logPdo, ?int $id, string $status, ?string $err = null, ?int $rows = null): void {
    if (!$logPdo || !$id) return;
    try {
        $st = $logPdo->prepare('UPDATE `admin_migration_runs` SET `ended_at`=?, `status`=?, `error`=?, `rows_affected`=? WHERE `id`=?');
        $st->execute([date('Y-m-d H:i:s'), $status, $err, $rows, $id]);
    } catch (Throwable $e) { /* ignore */ }
}

foreach ($files as $rel) {
    $rel = str_replace(['..','\\'], ['','/'], $rel);
    $path = $projectRoot . '/' . ltrim($rel, '/');
    if (!is_file($path)) { echo "[skip] Not found: {$rel}\n"; continue; }
    $sql = file_get_contents($path);
    if (!is_string($sql) || trim($sql) === '') { echo "[skip] Empty: {$rel}\n"; continue; }
    echo "==> ".$rel."\n";
    $runId = log_run_start($logPdo, $rel, $target, $mode);
    if ($mode === 'dry') {
        echo $sql."\n\n";
        log_run_end($logPdo, $runId, 'ok', null, null);
        continue;
    }
    try {
        $pdo->beginTransaction();
        $rows = $pdo->exec($sql);
        $pdo->commit();
        record_migration($pdo, basename($rel));
        echo "[ok] applied\n\n";
        log_run_end($logPdo, $runId, 'ok', null, is_int($rows) ? $rows : null);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        echo "[error] ".$e->getMessage()."\n\n";
        log_run_end($logPdo, $runId, 'error', $e->getMessage(), null);
    }
}

echo "Done.\n";
