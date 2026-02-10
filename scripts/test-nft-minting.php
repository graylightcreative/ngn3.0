<?php

/**
 * test-nft-minting.php - Manual test for NFT minting
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Blockchain\NFTMintingService;
use NGN\Lib\Logging\LoggerFactory;

echo "🎨 NGN 2.0.3 - NFT Minting Test\n";
echo "=============================\n";

$config = new Config();
$logger = LoggerFactory::create($config, 'nft_test');
$pdo = ConnectionFactory::write($config);

// Use the deployer address as the "artist" for testing
$artistWallet = '0xa87f29Af209f0D7c910B2f844E8Fd2d89c9D2Aaf';

try {
    $service = new NFTMintingService($pdo, $config, $logger);
    
    // 1. Create a dummy ledger entry
    echo "Creating dummy ledger entry...\n";
    // 32 bytes = 64 hex characters
    $hash = '0x' . bin2hex(random_bytes(32));
    $certId = 'CERT-NFT-TEST-' . bin2hex(random_bytes(4));
    
    $pdo->prepare("
        INSERT INTO content_ledger (content_hash, metadata_hash, owner_id, upload_source, file_size_bytes, mime_type, original_filename, certificate_id, artist_name, title)
        VALUES (?, 'meta', 1, 'nft_test', 1024, 'audio/mpeg', 'test.mp3', ?, 'Test Artist', 'Test Song')
    ")->execute([$hash, $certId]);
    
    $ledgerId = $pdo->lastInsertId();
    echo "Created ledger ID: $ledgerId\n";

    // 2. Mint NFT
    echo "Minting NFT for $artistWallet...\n";
    $result = $service->mintForEntry($ledgerId, $artistWallet);
    
    echo "✅ Success! NFT Minted.\n";
    echo "   Token ID: " . $result['token_id'] . "\n";
    echo "   TX Hash:  " . $result['tx_hash'] . "\n";
    echo "   View on Explorer: https://amoy.polygonscan.com/tx/" . $result['tx_hash'] . "\n";
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}