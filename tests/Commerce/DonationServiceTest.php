<?php

namespace NGN\Tests\Commerce;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Commerce\DonationService;
use NGN\Lib\Services\SMRService;
use PHPUnit\Framework\TestCase;
use PDO;

class DonationServiceTest extends TestCase
{
    private PDO $pdo;
    private DonationService $service;

    protected function setUp(): void
    {
        $config = new Config();
        $this->pdo = ConnectionFactory::write($config);
        
        $this->service = new DonationService($config);

        // Reset donations table
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
        $this->pdo->exec("TRUNCATE TABLE donations");
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
    }

    public function testCreateOneTime_TriggersBountyWhenHeatSpikeActive()
    {
        // Mock SMRService to force a heat spike
        $smrMock = $this->createMock(SMRService::class);
        $smrMock->method('hasHeatSpike')->willReturn(true);

        // Use reflection to inject mock if possible, or refactor service.
        // For this integration test, we'll manipulate the DB state directly if mocking is hard
        // But wait, DonationService instantiates SMRService in constructor.
        // I need to refactor DonationService to accept SMRService injection for testability.
        
        // REFACTOR ON THE FLY:
        // Since I can't inject it easily without changing the class significantly, 
        // I will rely on the actual DB state.
        
        // 1. Create Artist
        $this->pdo->exec("INSERT INTO artists (id, name, slug, status) VALUES (999, 'Bounty Artist', 'bounty-artist', 'active') ON DUPLICATE KEY UPDATE name='Bounty Artist'");
        
        // 2. Create SMR Data to trigger spike
        // Need > 50 spins in last 90 days
        $this->pdo->exec("INSERT INTO cdm_chart_entries (artist_id, track_title, spin_count, week_date) VALUES (999, 'Test Track', 60, CURDATE())");

        // 3. Create Donation
        $data = [
            'amount' => 100.00,
            'entity_type' => 'artist',
            'entity_id' => 999,
            'email' => 'test@example.com'
        ];

        $result = $this->service->createOneTime($data);
        $this->assertTrue($result['success']);

        // 4. Verify Bounty Triggered
        $stmt = $this->pdo->prepare("SELECT bounty_triggered FROM donations WHERE id = ?");
        $stmt->execute([$result['id']]);
        $triggered = (int)$stmt->fetchColumn();

        $this->assertEquals(1, $triggered, "Bounty should be triggered by 60 spins");
    }

    public function testCreateOneTime_NoBountyWhenNoSpike()
    {
        // 1. Create Artist
        $this->pdo->exec("INSERT INTO artists (id, name, slug, status) VALUES (888, 'Cold Artist', 'cold-artist', 'active') ON DUPLICATE KEY UPDATE name='Cold Artist'");
        
        // 2. Create minimal SMR Data (no spike)
        $this->pdo->exec("INSERT INTO cdm_chart_entries (artist_id, track_title, spin_count, week_date) VALUES (888, 'Quiet Track', 5, CURDATE())");

        // 3. Create Donation
        $data = [
            'amount' => 100.00,
            'entity_type' => 'artist',
            'entity_id' => 888,
            'email' => 'test@example.com'
        ];

        $result = $this->service->createOneTime($data);
        $this->assertTrue($result['success']);

        // 4. Verify Bounty NOT Triggered
        $stmt = $this->pdo->prepare("SELECT bounty_triggered FROM donations WHERE id = ?");
        $stmt->execute([$result['id']]);
        $triggered = (int)$stmt->fetchColumn();

        $this->assertEquals(0, $triggered, "Bounty should NOT be triggered by 5 spins");
    }
}
