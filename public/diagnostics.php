<?php
/**
 * Run migrations and refresh autoloader
 */
require_once __DIR__ . '/../lib/bootstrap.php';

echo "NGN DIAGNOSTICS & REPAIR\n";
echo "========================\n\n";

echo "1. Running Advanced Migrations...\n";
require_once __DIR__ . '/../scripts/apply_station_migrations.php';

echo "\n2. Refreshing Autoloader (if possible)...\n";
$root = dirname(__DIR__);
if (file_exists($root . '/vendor/bin/composer')) {
    echo "Running composer dump-autoload...\n";
    passthru("cd $root && php vendor/bin/composer dump-autoload -o 2>&1");
} else {
    echo "Composer bin not found at vendor/bin/composer. Manual sync via nexus may be required.\n";
}

echo "\n--- LATEST LOGS ---\n";
$logDir = $root . '/storage/logs/';
$ngnLogs = glob($logDir . '*.log');
if ($ngnLogs) {
    usort($ngnLogs, function($a, $b) { return filemtime($b) - filemtime($a); });
    foreach (array_slice($ngnLogs, 0, 2) as $logPath) {
        $size = filesize($logPath);
        echo "\n--- " . basename($logPath) . " ($size bytes) ---\n";
        $handle = fopen($logPath, "r");
        if ($size > 3000) fseek($handle, -3000, SEEK_END);
        echo fread($handle, 3000) . "\n";
        fclose($handle);
    }
}
