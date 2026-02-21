<?php
header('Content-Type: text/plain');
$root = dirname(__DIR__);
$logDir = $root . '/storage/logs/';
$ngnLogs = glob($logDir . '*.log');

if (!$ngnLogs) {
    echo "No logs found in $logDir";
    exit;
}

// Sort by date/mtime
usort($ngnLogs, function($a, $b) { return filemtime($b) - filemtime($a); });

foreach ($ngnLogs as $logPath) {
    $name = basename($logPath);
    $size = filesize($logPath);
    echo "--- LOG: $name ($size bytes) ---\n";
    
    // Read last 10KB of the file
    $handle = fopen($logPath, "r");
    if ($size > 10000) {
        fseek($handle, -10000, SEEK_END);
    }
    $content = fread($handle, 10000);
    fclose($handle);
    echo $content . "\n\n";
}
