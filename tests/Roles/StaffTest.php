<?php

namespace NGN\Tests\Roles;

use PHPUnit\Framework\TestCase;
use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Services\Legal\TakedownService;
use NGN\Lib\Services\MetricsService;

/**
 * Staff (Admin) Role Tests
 * Bible Ref: Chapter 25 - Institutional Governance
 */
class StaffTest extends TestCase
{
    private $pdo;
    private $config;

    protected function setUp(): void
    {
        $this->config = new Config();
        $this->pdo = ConnectionFactory::write($this->config);
    }

    /**
     * Story I.1: DMCA Takedown Audit
     */
    public function testDmcTakedownAuditLogging(): void
    {
        $legalService = new TakedownService($this->config);
        
        $logData = [
            'content_id' => 101,
            'content_type' => 'track',
            'reason' => 'Copyright infringement'
        ];
        
        $logId = $legalService->create($logData);
        $this->assertGreaterThan(0, $logId);
        
        $log = $legalService->get($logId);
        $this->assertEquals('track', $log['content_type']);
        $this->assertEquals('pending', $log['status']);
    }

    /**
     * Story I.12: Critical Health Monitoring (P95 Latency)
     */
    public function testLatencyMetricsRecording(): void
    {
        $metrics = new MetricsService($this->pdo);
        
        $success = $metrics->recordRequest('/api/v1/posts', 'GET', 200, 150.5, 1);
        $this->assertTrue($success);
        
        $stats = $metrics->getLatencyStats(60); // 60 min window
        $this->assertArrayHasKey('p95_ms', $stats);
    }
}
