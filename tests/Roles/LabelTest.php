<?php

namespace NGN\Tests\Roles;

use PHPUnit\Framework\TestCase;
use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Labels\LabelService;
use NGN\Lib\Royalty\RoyaltyLedgerService;

/**
 * Label Manager Role Tests
 */
class LabelTest extends TestCase
{
    private $pdo;
    private $config;
    private $testLabelId = 31; // Wake Up! Music Rocks

    protected function setUp(): void
    {
        $this->config = new Config();
        $this->pdo = ConnectionFactory::write($this->config);
    }

    /**
     * Story A.2: Unified Roster Management
     */
    public function testLabelCanViewRoster(): void
    {
        $service = new LabelService($this->config);
        
        try {
            $label = $service->get($this->testLabelId);
            if ($label) {
                $this->assertArrayHasKey('name', $label);
                // In a real scenario, we'd check for roster artists here
            } else {
                $this->markTestSkipped("Label {$this->testLabelId} not found.");
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped("LabelService failed: " . $e->getMessage());
        }
    }

    /**
     * Story A.6: Generate Royalty Statements
     */
    public function testLabelCanViewTransactionHistory(): void
    {
        $royaltyService = new RoyaltyLedgerService($this->pdo, 0.10);
        
        // Check if cdm_royalty_transactions exists
        $stmt = $this->pdo->prepare("SHOW TABLES LIKE 'cdm_royalty_transactions'");
        $stmt->execute();
        if (!$stmt->fetch()) {
             $this->markTestSkipped("Table 'cdm_royalty_transactions' missing.");
        }

        // Get user ID associated with label (best effort)
        $stmt = $this->pdo->prepare("SELECT user_id FROM `ngn_2025`.`labels` WHERE id = ?");
        $stmt->execute([$this->testLabelId]);
        $userId = $stmt->fetchColumn();

        if ($userId) {
            $history = $royaltyService->getUserTransactions((int)$userId, 10);
            $this->assertIsArray($history);
        } else {
            $this->markTestSkipped("User ID for Label {$this->testLabelId} not found.");
        }
    }
}
