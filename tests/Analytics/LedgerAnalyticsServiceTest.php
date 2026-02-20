<?php

namespace NGN\Tests\Analytics;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Analytics\LedgerAnalyticsService;
use PHPUnit\Framework\TestCase;
use PDO;

class LedgerAnalyticsServiceTest extends TestCase
{
    private PDO $pdo;
    private LedgerAnalyticsService $service;

    protected function setUp(): void
    {
        $config = new Config();
        $this->pdo = ConnectionFactory::write($config);
        $this->service = new LedgerAnalyticsService($this->pdo);

        // Reset tables
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
        $this->pdo->exec("TRUNCATE TABLE cdm_chart_entries");
        $this->pdo->exec("TRUNCATE TABLE artists");
        $this->pdo->exec("TRUNCATE TABLE cdm_rights_ledger");
        $this->pdo->exec("TRUNCATE TABLE playback_events");
        $this->pdo->exec("TRUNCATE TABLE tracks");
        $this->pdo->exec("TRUNCATE TABLE releases");
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
    }

    public function testGetArtistVelocity_CalculatesGrowth()
    {
        // 1. Create Artist
        $this->pdo->exec("INSERT INTO artists (id, name, slug) VALUES (1, 'Growth Artist', 'growth-artist')");

        // 2. Insert Chart Data (3 weeks)
        // Week 1: 100 spins
        // Week 2: 120 spins (+20%)
        // Week 3: 150 spins (+25%)
        // Average Growth: (20 + 25) / 2 = 22.5%
        
        $this->pdo->exec("INSERT INTO cdm_chart_entries (artist_id, week_date, spin_count, track_title) VALUES (1, '2026-01-01', 100, 'Track A')");
        $this->pdo->exec("INSERT INTO cdm_chart_entries (artist_id, week_date, spin_count, track_title) VALUES (1, '2026-01-08', 120, 'Track A')");
        $this->pdo->exec("INSERT INTO cdm_chart_entries (artist_id, week_date, spin_count, track_title) VALUES (1, '2026-01-15', 150, 'Track A')");

        $result = $this->service->getArtistVelocity(1, 12);

        $this->assertEquals(1, $result['artist_id']);
        $this->assertEquals(22.5, $result['avg_weekly_growth_pct']);
        $this->assertCount(2, $result['trend_data']); // 2 growth periods from 3 data points
    }

    public function testGetEcosystemReach_AggregatesCounts()
    {
        // 1. Create Artists
        $this->pdo->exec("INSERT INTO artists (id, name, slug) VALUES (10, 'A1', 'a1'), (11, 'A2', 'a2')");

        // 2. Create Rights
        $this->pdo->exec("INSERT INTO cdm_rights_ledger (id, artist_id, status, is_royalty_eligible, owner_id) VALUES (1, 10, 'verified', 1, 99)");
        
        // 2b. Create Release & Track (FK requirements)
        $this->pdo->exec("INSERT INTO releases (id, artist_id, title, slug) VALUES (1, 10, 'Test Release', 'test-release')");
        $this->pdo->exec("INSERT INTO tracks (id, release_id, artist_id, title, slug) VALUES (1, 1, 10, 'Test Track', 'test-track')");

        // 3. Create Playbacks
        $this->pdo->exec("INSERT INTO playback_events (track_id, session_id, is_qualified_listen, started_at) VALUES (1, 's1', 1, NOW()), (1, 's2', 1, NOW()), (1, 's3', 0, NOW())");

        $stats = $this->service->getEcosystemReach();

        $this->assertEquals(2, $stats['total_artists']);
        $this->assertEquals(1, $stats['verified_rights']);
        $this->assertEquals(2, $stats['total_qualified_listens']);
    }
}
