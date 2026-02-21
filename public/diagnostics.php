<?php
/**
 * Temporary diagnostic script to read error log
 */
header('Content-Type: text/plain');

$logFile = __DIR__ . '/error_log';
if (!file_exists($logFile)) {
    $logFile = dirname(__DIR__) . '/error_log';
}

if (file_exists($logFile)) {
    echo "Log found at: $logFile
";
    $lines = 100;
    $data = shell_exec("tail -n $lines " . escapeshellarg($logFile));
    echo $data;
} else {
    echo "Error log not found in common locations.
";
    // Try to find it via phpinfo or ini
    echo "ini_get('error_log'): " . ini_get('error_log') . "
";
    $phpLog = ini_get('error_log');
    if ($phpLog && file_exists($phpLog)) {
        echo shell_exec("tail -n 100 " . escapeshellarg($phpLog));
    }
}
