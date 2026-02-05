<?php
/**
 * Power Station SMR Auto-Ingestion Job
 *
 * Runs scheduled ingestions for high-volume terrestrial stations.
 * Frequency: Every 15 minutes recommended
 */

$root = dirname(__DIR__, 2);
require_once $root . '/lib/bootstrap.php';

use NGN\Lib\Smr\PowerStationIngestionService;
use NGN\Lib\DB\ConnectionFactory;

echo "[" . date('Y-m-d H:i:s') . "] Starting Power Station SMR Ingestion...
";

try {
    $pdo = ConnectionFactory::write($config);
    $service = new PowerStationIngestionService($pdo, $config);

    $results = $service->processScheduled();

    $totalSuccess = 0;
    $totalFailed = 0;
    
    foreach ($results as $id => $res) {
        if ($res['success']) {
            echo "  - Profile #{$id}: Success ({$res['count']} records)
";
            $totalSuccess++;
        } else {
            echo "  - Profile #{$id}: FAILED ({$res['error']})
";
            $totalFailed++;
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Ingestion complete. Success: {$totalSuccess}, Failed: {$totalFailed}
";

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] CRITICAL ERROR: " . $e->getMessage() . "
";
    exit(1);
}
