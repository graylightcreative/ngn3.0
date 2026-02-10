<?php

/**
 * anchor_batch.php - Periodic worker to anchor pending content ledger entries
 * 
 * Should be run daily via cron.
 */

require_once __DIR__ . '/../../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Legal\BlockchainAnchoringService;
use NGN\Lib\Logging\LoggerFactory;

echo "🚀 NGN 2.0.3 - Blockchain Anchoring Worker
";
echo "========================================
";

$config = new Config();
$logger = LoggerFactory::create($config, 'blockchain_worker');
$pdo = ConnectionFactory::write($config);

try {
    $service = new BlockchainAnchoringService($pdo, $config, $logger);
    
    echo "Checking for pending entries...
";
    $result = $service->anchorPendingEntries();
    
    if ($result['count'] > 0) {
        echo "✅ Success: Anchored {$result['count']} entries.
";
        echo "   Merkle Root: {$result['merkle_root']}
";
        echo "   TX Hash:     {$result['tx_hash']}
";
    } else {
        echo "ℹ️  No pending entries found to anchor.
";
    }
    
    exit(0);
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "
";
    $logger->error('blockchain_worker_fatal', ['error' => $e->getMessage()]);
    exit(1);
}
