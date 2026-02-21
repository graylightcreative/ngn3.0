<?php
/**
 * Final repair: Trigger sync_forge.sh
 */
require_once __DIR__ . '/../lib/bootstrap.php';

echo "NGN FINAL REPAIR\n";
echo "================\n\n";

$root = dirname(__DIR__);
$syncScript = $root . '/bin/sync_forge.sh';

if (file_exists($syncScript)) {
    echo "Triggering sync_forge.sh...\n";
    // We try passthru to run the bash script
    passthru("bash $syncScript 2>&1");
    echo "\nSync script execution attempt finished.\n";
} else {
    echo "Sync script not found at $syncScript\n";
}

echo "\n--- SYSTEM LOGS ---\n";
$logDir = $root . '/storage/logs/';
$ngnLogs = glob($logDir . '*.log');
if ($ngnLogs) {
    usort($ngnLogs, function($a, $b) { return filemtime($b) - filemtime($a); });
    foreach (array_slice($ngnLogs, 0, 1) as $logPath) {
        echo "\n--- " . basename($logPath) . " ---\n";
        $handle = fopen($logPath, "r");
        fseek($handle, -2000, SEEK_END);
        echo fread($handle, 2000) . "\n";
        fclose($handle);
    }
}
