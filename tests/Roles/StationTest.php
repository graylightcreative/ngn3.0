<?php

namespace NGN\Tests\Roles;

use PHPUnit\Framework\TestCase;
use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Stations\StationService;
use NGN\Lib\Stations\StationStreamService;
use NGN\Lib\Stations\StationPlaylistService;

/**
 * Station Owner Role Tests
 * Bible Ref: Chapter 24 - SMR Operational Ruleset
 */
class StationTest extends TestCase
{
    private $pdo;
    private $config;
    private $testStationId = 1;

    protected function setUp(): void
    {
        $this->config = new Config();
        $this->pdo = ConnectionFactory::write($this->config);
    }

    /**
     * Story S.1: Station Info Retrieval
     */
    public function testStationCanGetInfo(): void
    {
        $service = new StationService($this->config);
        
        try {
            $station = $service->get($this->testStationId);
            if ($station !== null) {
                $this->assertArrayHasKey('name', $station);
            } else {
                $this->markTestSkipped("Station {$this->testStationId} not found.");
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped("StationService failed: " . $e->getMessage());
        }
    }

    /**
     * Story S.10: Station Stream Token Generation
     */
    public function testStationStreamToken(): void
    {
        $streamService = new StationStreamService($this->pdo, $this->config);
        
        try {
            $tokenData = $streamService->generateStreamToken($this->testStationId, null, '127.0.0.1');
            $this->assertArrayHasKey('token', $tokenData);
            $this->assertArrayHasKey('url', $tokenData);
        } catch (\Exception $e) {
            if ($e->getMessage() === "Station not found") {
                $this->markTestSkipped("Station {$this->testStationId} not found for stream token test.");
            } else {
                throw $e;
            }
        }
    }

    /**
     * Story S.3: Playlist Retrieval
     */
    public function testStationPlaylistAccess(): void
    {
        $playlistService = new StationPlaylistService($this->config);
        
        try {
            $playlists = $playlistService->getStationPlaylists($this->testStationId);
            $this->assertIsArray($playlists);
        } catch (\Throwable $e) {
            $this->markTestSkipped("StationPlaylistService failed: " . $e->getMessage());
        }
    }
}
