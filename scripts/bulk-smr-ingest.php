<?php

/**
 * bulk-smr-ingest.php - Bulk ingestion of Erik Baker's SMR Archives
 * 
 * Scans storage/archives/smr/ for Top 200 CSV files and pushes to Graylight.
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Services\Graylight\GraylightServiceClient;
use NGN\Lib\Services\Graylight\SMRIngestionService;
use NGN\Lib\Logging\LoggerFactory;

echo "🛸 NGN Bulk Ingest - Erik Baker Archives\n";
echo "========================================\n";

$config = new Config();
$logger = LoggerFactory::create($config, 'bulk_ingest');
$pdo = ConnectionFactory::write($config);
$glClient = new GraylightServiceClient($config);
$ingestService = new SMRIngestionService($pdo, $config, $glClient);

$archiveDir = __DIR__ . '/../storage/archives/smr';

if (!is_dir($archiveDir)) {
    echo "[ERROR] Archive directory not found at $archiveDir\n";
    exit(1);
}

// 1. Scan for files
echo "Scanning $archiveDir for SMR Archive files...\n";
$files = glob($archiveDir . '/* Top 200.csv');

if (empty($files)) {
    echo "[INFO] No files matching '* Top 200.csv' found.\n";
    exit(0);
}

echo "Found " . count($files) . " files. Starting ingestion...\n";

$successCount = 0;
$failCount = 0;

foreach ($files as $filePath) {
    $filename = basename($filePath);
    echo "\nProcessing: $filename\n";
    
    try {
        // Ingest and Push
        $result = $ingestService->push($filePath);
        
        $vaultId = $result['data']['vault_id'] ?? 'N/A';
        echo "   [OK] Anchored! Vault ID: $vaultId\n";
        echo "   [DATE] Report Date: " . $result['report_date'] . "\n";
        
        // 2. Log Locally
        $stmt = $pdo->prepare("
            INSERT INTO cdm_ingestion_logs (
                vault_id, namespace, filename, report_week, report_year, status
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        // Extract metadata for logging
        preg_match('/(\d{2})-(\d{4}) Top 200/i', $filename, $m);
        $week = isset($m[1]) ? (int)$m[1] : null;
        $year = isset($m[2]) ? (int)$m[2] : null;
        
        $stmt->execute([
            $vaultId,
            'NGN_SMR_DUMP',
            $filename,
            $week,
            $year,
            'anchored'
        ]);
        
        $successCount++;
        
        // 3. Local Match (CDM Reload)
        // trigger matching script
        
    } catch (\Throwable $e) {
        echo "   [FAIL] " . $e->getMessage() . "\n";
        $failCount++;
    }
}

echo "\n========================================\n";
echo "🏁 Ingestion Complete\n";
echo "   Success: $successCount\n";
echo "   Failed:  $failCount\n";
echo "========================================\n";