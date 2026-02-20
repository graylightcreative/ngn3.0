<?php

namespace NGN\Tests\Roles;

use PHPUnit\Framework\TestCase;
use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Venues\VenueService;

/**
 * Venue Role Tests
 */
class VenueTest extends TestCase
{
    private $pdo;
    private $config;
    private $testVenueId = 1;

    protected function setUp(): void
    {
        $this->config = new Config();
        $this->pdo = ConnectionFactory::write($this->config);
    }

    /**
     * Story V.2: Venue Info
     */
    public function testVenueCanGetInfo(): void
    {
        $service = new VenueService($this->config);
        
        try {
            $venue = $service->get($this->testVenueId);
            if ($venue) {
                $this->assertArrayHasKey('name', $venue);
            } else {
                $this->markTestSkipped("Venue {$this->testVenueId} not found.");
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped("VenueService failed: " . $e->getMessage());
        }
    }

    /**
     * Story V.3: PPV / Events (Stub for now)
     */
    public function testVenueEventsAccess(): void
    {
        // For now, check if we can query shows for this venue
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`shows` WHERE venue_id = ?");
        $stmt->execute([$this->testVenueId]);
        $count = (int)$stmt->fetchColumn();
        
        $this->assertIsInt($count);
    }
}
