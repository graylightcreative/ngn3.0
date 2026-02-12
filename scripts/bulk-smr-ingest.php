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

// Handle single file argument
$targetFile = null;
foreach ($argv as $arg) {
    if (str_contains($arg, '--file=')) {
        $targetFile = str_replace('--file=', '', $arg);
    }
}

if ($targetFile) {
    $files = [$targetFile];
    echo "🎯 Targeting single file: " . basename($targetFile) . "\n";
} else {
    echo "Scanning $archiveDir for SMR Archive files...\n";
    $files = glob($archiveDir . '/* Top 200.csv');
}

if (empty($files)) {
    echo "[INFO] No files found.\n";
    exit(0);
}

echo "Found " . count($files) . " files. Starting ingestion...\n";

$successCount = 0;
$failCount = 0;

foreach ($files as $filePath) {
    if (!file_exists($filePath)) {
        echo "   [SKIP] File not found: $filePath\n";
        continue;
    }

    $filename = basename($filePath);
    echo "\nProcessing: $filename\n";
    
    if (strpos($filename, 'unknown') !== false) {
        echo "   [SKIP] Non-historical fragment detected.\n";
        continue;
    }

    try {
        // Ingest and Push
        $result = $ingestService->push($filePath);
        
        $vaultId = $result['data']['vault_id'] ?? 'N/A';
        $txHash = $result['data']['transaction_hash'] ?? 'N/A';
        echo "   [OK] Anchored! Vault ID: $vaultId\n";
        echo "   [OK] Transaction: $txHash\n";
        echo "   [DATE] Report Date: " . ($result['report_date'] ?? 'N/A') . "\n";
        
        // 2. Log Locally
        $stmt = $pdo->prepare("
            INSERT INTO cdm_ingestion_logs (
                vault_id, transaction_hash, namespace, filename, report_week, report_year, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $vaultId,
            $txHash,
            'NGN_SMR_DUMP',
            $filename,
            $result['week'] ?? null,
            $result['year'] ?? null,
            'anchored'
        ]);
        
        $successCount++;
        
        // 3. Local Match (CDM Reload)
        echo "   🔄 Triggering CDM Match...\n";
        include __DIR__ . '/CDM_Match.php';
        
    } catch (\Throwable $e) {
        echo "   [FAIL] " . $e->getMessage() . "\n";
        
        // Log failure locally
        try {
            $stmt = $pdo->prepare("INSERT INTO cdm_ingestion_logs (vault_id, namespace, filename, status) VALUES (?, ?, ?, ?)");
            $stmt->execute(['FAILED', 'NGN_SMR_DUMP', $filename, 'failed_anchor']);
        } catch (\Throwable $dbE) {}
        
        $failCount++;
    }
}

echo "\n========================================\n";
echo "🏁 Ingestion Complete\n";
echo "   Success: $successCount\n";
echo "   Failed:  $failCount\n";
echo "========================================\n";
