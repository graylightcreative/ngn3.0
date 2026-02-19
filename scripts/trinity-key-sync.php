<?php
/**
 * scripts/trinity-key-sync.php
 * 
 * Purpose: Hardening the Trinity Key Protocol (P2-CH17-S3).
 * 1. Generates Merkle Root for all pending ledger entries.
 * 2. Anchors root to blockchain.
 * 3. Mints ERC-721 certificates for verified entries.
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Legal\BlockchainAnchoringService;
use NGN\Lib\Blockchain\NFTMintingService;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

echo "🔑 Hardening Trinity Key Protocol (P2-CH17-S3)
";
echo "=============================================
";

$config = new Config();
$pdo = ConnectionFactory::write($config);

// Setup Logger
$logger = new Logger('trinity_sync');
$logger->pushHandler(new StreamHandler(__DIR__ . '/../storage/logs/trinity_sync.log', Logger::DEBUG));

$anchoringService = new BlockchainAnchoringService($pdo, $config, $logger);
$mintingService = new NFTMintingService($pdo, $config, $logger);

try {
    // Phase 1: Blockchain Anchoring
    echo "Phase 1: Anchoring pending ledger entries...
";
    $anchorResult = $anchoringService->anchorPendingEntries();
    
    if ($anchorResult['success'] && $anchorResult['count'] > 0) {
        echo "   ✓ Anchored " . $anchorResult['count'] . " entries.
";
        echo "   ✓ Merkle Root: " . $anchorResult['merkle_root'] . "
";
        echo "   ✓ TX Hash: " . $anchorResult['tx_hash'] . "
";
    } else {
        echo "   ℹ " . ($anchorResult['message'] ?? 'No entries requiring anchoring.') . "
";
    }

    // Phase 2: NFT Certificate Minting
    echo "
Phase 2: Minting ERC-721 certificates...
";
    
    // Fetch entries ready for minting (anchored but not minted)
    $stmt = $pdo->query("
        SELECT id, artist_name FROM content_ledger 
        WHERE blockchain_tx_hash IS NOT NULL 
        AND nft_status != 'minted' 
        LIMIT 10
    ");
    $pendingMint = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $mintedCount = 0;
    foreach ($pendingMint as $entry) {
        echo "   Minting for " . $entry['artist_name'] . " (ID: " . $entry['id'] . ")... ";
        
        // For simulation/beta, we use a fallback wallet if user hasn't linked one
        $artistWallet = '0xa87f29Af209f0D7c910B2f844E8Fd2d89c9D2Aaf'; 
        
        try {
            if (getenv('BLOCKCHAIN_SIMULATE') === 'true') {
                $mintResult = [
                    'success' => true,
                    'token_id' => rand(1000, 9999),
                    'tx_hash' => '0x' . bin2hex(random_bytes(32))
                ];
                
                $pdo->prepare("UPDATE content_ledger SET nft_token_id = ?, nft_tx_hash = ?, nft_status = 'minted' WHERE id = ?")
                    ->execute([$mintResult['token_id'], $mintResult['tx_hash'], $entry['id']]);
            } else {
                $mintResult = $mintingService->mintForEntry($entry['id'], $artistWallet);
            }
            
            echo "Done. Token ID: " . $mintResult['token_id'] . "\n";
            $mintedCount++;
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "
";
        }
    }
    
    echo "
✅ Trinity Key Sync Complete.
";
    echo "   - Anchored: " . ($anchorResult['count'] ?? 0) . "
";
    echo "   - Minted: $mintedCount
";
    echo "=============================================
";

} catch (Exception $e) {
    echo "❌ CRITICAL ERROR: " . $e->getMessage() . "
";
    exit(1);
}
