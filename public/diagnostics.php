<?php
header('Content-Type: text/plain');
echo "Current Dir: " . __DIR__ . "\n";
echo "Files in public/:\n";
print_r(scandir(__DIR__));
echo "\nFiles in root/:\n";
print_r(scandir(dirname(__DIR__)));

$root = dirname(__DIR__);
$possibleLogs = [
    $root . '/error_log',
    $root . '/php_errors.log',
    $root . '/storage/logs/error.log',
    '/tmp/php_errors.log'
];

foreach ($possibleLogs as $log) {
    if (file_exists($log)) {
        echo "\nFound log at $log:\n";
        echo shell_exec("tail -n 50 " . escapeshellarg($log));
    }
}

// Try reading recent NGN custom logs
$ngnLogs = glob($root . '/storage/logs/*.log');
if ($ngnLogs) {
    echo "\nNGN custom logs found:\n";
    foreach ($ngnLogs as $l) {
        echo " - " . basename($l) . " (" . filesize($l) . " bytes)\n";
    }
    // Read the newest one
    usort($ngnLogs, function($a, $b) { return filemtime($b) - filemtime($a); });
    echo "\nContent of " . basename($ngnLogs[0]) . ":\n";
    echo shell_exec("tail -n 100 " . escapeshellarg($ngnLogs[0]));
}
