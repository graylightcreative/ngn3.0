<?php

use PHPUnit\Framework\TestCase;
use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Legal\BlockchainAnchoringService;
use Monolog\Logger;
use Monolog\Handler\NullHandler;

class BlockchainAnchoringServiceTest extends TestCase
{
    private $pdo;
    private $config;
    private $logger;
    private $service;

    protected function setUp(): void
    {
        $this->config = new Config();
        $this->pdo = ConnectionFactory::write($this->config);
        $this->logger = new Logger('test');
        $this->logger->pushHandler(new NullHandler());
        
        // Clean up and setup test data
        $this->pdo->exec("DELETE FROM content_ledger");
        
        $this->service = new BlockchainAnchoringService($this->pdo, $this->config, $this->logger);
        
        // Enable simulation mode for testing
        putenv('APP_ENV=development');
        putenv('BLOCKCHAIN_SIMULATE=true');
    }

    protected function tearDown(): void
    {
        putenv('BLOCKCHAIN_SIMULATE'); // Reset
    }

    public function testAnchorPendingEntries(): void
    {
        // 1. Create some pending entries
        $hashes = [
            '0x' . bin2hex(random_bytes(31)),
            '0x' . bin2hex(random_bytes(31)),
            '0x' . bin2hex(random_bytes(31))
        ];

        foreach ($hashes as $hash) {
            $this->pdo->prepare("
                INSERT INTO content_ledger (content_hash, metadata_hash, owner_id, upload_source, file_size_bytes, mime_type, original_filename, certificate_id)
                VALUES (?, 'meta', 1, 'test', 1024, 'audio/mpeg', 'test.mp3', ?)
            ")->execute([$hash, 'cert_' . bin2hex(random_bytes(8))]);
        }

        // 2. Run anchoring
        $result = $this->service->anchorPendingEntries();

        // 3. Verify results
        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['count']);
        $this->assertNotEmpty($result['tx_hash']);
        $this->assertNotEmpty($result['merkle_root']);

        // 4. Verify DB was updated
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM content_ledger WHERE blockchain_tx_hash = ? AND blockchain_status = 'confirmed'");
        $stmt->execute([$result['tx_hash']]);
        $this->assertEquals(3, $stmt->fetchColumn());
    }

    public function testAnchorNoPendingEntries(): void
    {
        $result = $this->service->anchorPendingEntries();
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['count']);
        $this->assertStringContainsString('No pending entries', $result['message']);
    }
}
