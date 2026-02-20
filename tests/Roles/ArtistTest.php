<?php

namespace NGN\Tests\Roles;

use PHPUnit\Framework\TestCase;
use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Artists\ArtistService;
use NGN\Lib\Fans\SubscriptionService;
use NGN\Lib\Royalty\RoyaltyLedgerService;
use NGN\Lib\Rankings\RankingCalculator;

/**
 * Artist Role Tests
 * Bible Ref: Chapter 07 - Product Specifications (Artist User Stories)
 */
class ArtistTest extends TestCase
{
    private $pdo;
    private $config;
    private $testArtistId = 1286; // Heroes and Villains

    protected function setUp(): void
    {
        $this->config = new Config();
        $this->pdo = ConnectionFactory::write($this->config);
        putenv('APP_ENV=testing');
    }

    /**
     * Story A.1: Real-time dashboard data
     */
    public function testArtistCanViewProfileData(): void
    {
        $service = new ArtistService($this->config);
        $data = $service->get($this->testArtistId);

        // If artist doesn't exist in test DB, we might get null. 
        // We should at least check if service handles it without crashing.
        if ($data !== null) {
            $this->assertEquals($this->testArtistId, $data['id']);
            $this->assertArrayHasKey('name', $data);
        } else {
            $this->markTestSkipped("Artist {$this->testArtistId} not found in database.");
        }
    }

    /**
     * Story A.3: NGN Score Influence Weighting status
     */
    public function testInvestorStatusCheck(): void
    {
        $calc = new RankingCalculator($this->config);
        
        // Check if is_investor column exists first
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM `ngn_2025`.`users` LIKE 'is_investor'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $this->markTestSkipped("Column 'is_investor' missing in users table.");
        }

        $reflector = new \ReflectionClass($calc);
        $method = $reflector->getMethod('isInvestor');
        $method->setAccessible(true);
        
        $isInvestor = $method->invoke($calc, 1);
        $this->assertIsBool($isInvestor);
    }

    /**
     * Story A.5: EQS Transparency
     */
    public function testArtistCanViewBalance(): void
    {
        $royaltyService = new RoyaltyLedgerService($this->pdo, 0.10); // Pass 10% fee
        
        // check if cdm_royalty_balances exists
        $stmt = $this->pdo->prepare("SHOW TABLES LIKE 'cdm_royalty_balances'");
        $stmt->execute();
        if (!$stmt->fetch()) {
             $this->markTestSkipped("Table 'cdm_royalty_balances' missing.");
        }

        $balance = $royaltyService->getBalance($this->testArtistId);
        $this->assertArrayHasKey('available_balance', $balance);
    }

    /**
     * Story A.13/A.14: Fan Subscription Gating
     */
    public function testArtistSubscriptionAccess(): void
    {
        $subService = new SubscriptionService($this->config);
        
        // check if user_fan_subscriptions exists
        $stmt = $this->pdo->prepare("SHOW TABLES LIKE 'user_fan_subscriptions'");
        $stmt->execute();
        if (!$stmt->fetch()) {
             $this->markTestSkipped("Table 'user_fan_subscriptions' missing.");
        }

        $hasAccess = $subService->checkAccess(9999, $this->testArtistId, 1);
        $this->assertFalse($hasAccess);
    }
}
