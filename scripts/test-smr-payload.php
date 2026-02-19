<?php
/**
 * scripts/test-smr-payload.php
 * 
 * Purpose: Simulate the SIR-B-03 Long-Tail payload to verify 10k readiness.
 * Generates a simulated CSV with 5,000 new artists and processes it.
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Services\Graylight\SMRIngestionService;
use NGN\Lib\Services\Graylight\GraylightServiceClient;

echo "🧪 Simulating SIR-B-03 Payload (10k Readiness Test)\n";
echo "================================================\n";

$config = new Config();
$pdo = ConnectionFactory::write($config);

// 1. Generate Simulated Long-Tail CSV
$filename = "SMR_LongTail_W12_2026.csv";
$filePath = __DIR__ . '/../storage/archives/smr/' . $filename;

echo "Generating 10,000 simulated records...\n";
$handle = fopen($filePath, 'w');
fputcsv($handle, ['ARTIST', 'TITLE', 'TW SPIN', 'LW SPIN', 'STATIONS ON', 'LABEL', 'TW POS'], ',', '"', "\\");

for ($i = 1; $i <= 10000; $i++) {
    fputcsv($handle, [
        "Simulated Artist Unique $i",
        "Simulated Track $i",
        rand(10, 100),
        rand(5, 50),
        rand(1, 20),
        "Simulated Label " . rand(1, 100),
        $i
    ], ',', '"', "\\");
}
fclose($handle);
echo "   ✓ Generated: $filename\n";

// 2. Trigger Ingestion
echo "\nTriggering Bulk Ingestion...\n";
$glClient = new class($config) extends GraylightServiceClient {
    public function call(string $endpoint, array $payload = []): array {
        return ['status' => 'success', 'message' => 'Simulated Success'];
    }
};

$service = new SMRIngestionService($pdo, $config, $glClient);

$start = microtime(true);
$result = $service->push($filePath);
$end = microtime(true);

echo "   ✓ Ingestion Time: " . round($end - $start, 2) . "s\n";

// 3. Trigger CDM Match
echo "\nTriggering Identity Alignment (CDM Match)...\n";
$start = microtime(true);
include __DIR__ . '/CDM_Match.php';
$end = microtime(true);

echo "   ✓ Alignment Time: " . round($end - $start, 2) . "s\n";

// 4. Final Count
$artistCount = $pdo->query("SELECT COUNT(*) FROM artists")->fetchColumn();
echo "\n📊 Current Artist Reach: $artistCount / 10,000\n";
echo "================================================\n";

// Cleanup test file
unlink($filePath);
