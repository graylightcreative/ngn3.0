<?php

/**
 * stress-test-anchoring.php - Stress test for blockchain anchoring services
 * 
 * Tests the efficiency of Merkle root generation and batch database updates.
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Legal\BlockchainAnchoringService;
use NGN\Lib\Logging\LoggerFactory;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

echo "🧪 NGN 2.0.3 - Blockchain Anchoring Stress Test
";
echo "==============================================
";

$config = new Config();
$logger = new Logger('stress_test');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
$pdo = ConnectionFactory::write($config);

// Enable simulation mode
putenv('BLOCKCHAIN_SIMULATE=true');
putenv('APP_ENV=development');

$service = new BlockchainAnchoringService($pdo, $config, $logger);

// Test Parameters
$batchSizes = [10, 100, 1000, 5000];

foreach ($batchSizes as $size) {
    echo "
📊 Testing Batch Size: $size
";
    echo "--------------------------
";

    // 1. Clean up
    $pdo->exec("DELETE FROM content_ledger");

    // 2. Prepare Data
    echo "   Generating $size ledger entries... ";
    $startPrepare = microtime(true);
    
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("
        INSERT INTO content_ledger (content_hash, metadata_hash, owner_id, upload_source, file_size_bytes, mime_type, original_filename, certificate_id)
        VALUES (?, ?, 1, 'stress_test', 1024, 'audio/mpeg', 'test.mp3', ?)
    ");

    for ($i = 0; $i < $size; $i++) {
        $hash = '0x' . bin2hex(random_bytes(31));
        $meta = hash('sha256', $hash);
        $cert = 'CERT-' . $size . '-' . $i . '-' . bin2hex(random_bytes(4));
        $stmt->execute([$hash, $meta, $cert]);
    }
    $pdo->commit();
    
    $endPrepare = microtime(true);
    echo sprintf("%.2f seconds
", $endPrepare - $startPrepare);

    // 3. Run Anchoring
    echo "   Running batch anchoring... ";
    $startAnchor = microtime(true);
    
    try {
        $result = $service->anchorPendingEntries();
        $endAnchor = microtime(true);
        
        $duration = $endAnchor - $startAnchor;
        echo sprintf("%.2f seconds
", $duration);
        
        echo "   Result: success=" . ($result['success'] ? 'true' : 'false') . ", count=" . $result['count'] . "
";
        echo "   Merkle Root: " . substr($result['merkle_root'], 0, 20) . "...
";
        echo "   Memory Peak: " . sprintf("%.2f MB
", memory_get_peak_usage() / 1024 / 1024);
        
    } catch (\Throwable $e) {
        echo "   ❌ FAILED: " . $e->getMessage() . "
";
    }
}

echo "
✅ Stress test complete.
";
