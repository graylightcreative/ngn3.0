<?php
/**
 * Run migrations and show logs
 */
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../scripts/apply_station_migrations.php';

echo "
--- SYSTEM LOGS ---
";
$root = dirname(__DIR__);
$logDir = $root . '/storage/logs/';
$ngnLogs = glob($logDir . '*.log');
if ($ngnLogs) {
    usort($ngnLogs, function($a, $b) { return filemtime($b) - filemtime($a); });
    foreach (array_slice($ngnLogs, 0, 3) as $logPath) {
        $size = filesize($logPath);
        echo "--- " . basename($logPath) . " ($size bytes) ---
";
        $handle = fopen($logPath, "r");
        if ($size > 5000) fseek($handle, -5000, SEEK_END);
        echo fread($handle, 5000) . "

";
        fclose($handle);
    }
}
