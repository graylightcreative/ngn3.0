<?php

namespace NGN\Tests\Security;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Services\Security\RateLimiterService;
use PHPUnit\Framework\TestCase;
use PDO;

class RateLimiterServiceTest extends TestCase
{
    private PDO $pdo;
    private RateLimiterService $service;

    protected function setUp(): void
    {
        $config = new Config();
        $this->pdo = ConnectionFactory::write($config);
        $this->service = new RateLimiterService($this->pdo);

        // Reset table for each test
        $this->pdo->exec("TRUNCATE TABLE api_rate_limits");
    }

    public function testIsLimited_AllowsRequestsUnderLimit()
    {
        $ip = '127.0.0.1';
        $endpoint = '/api/test';
        $limit = 5;
        $window = 60;

        for ($i = 0; $i < $limit; $i++) {
            $this->assertFalse(
                $this->service->isLimited($ip, $endpoint, $limit, $window),
                "Request should be allowed on attempt #$i"
            );
        }
    }

    public function testIsLimited_BlocksRequestExceedingLimit()
    {
        $ip = '127.0.0.2';
        $endpoint = '/api/test';
        $limit = 3;
        $window = 60;

        // Consume limit
        for ($i = 0; $i < $limit; $i++) {
            $this->service->isLimited($ip, $endpoint, $limit, $window);
        }

        // Next request should be blocked
        $this->assertTrue(
            $this->service->isLimited($ip, $endpoint, $limit, $window),
            "Request should be blocked after exceeding limit"
        );
    }

    public function testIsLimited_WindowResetsAfterTime()
    {
        $ip = '127.0.0.3';
        $endpoint = '/api/test';
        $limit = 2;
        $window = 1; // 1 second window

        // Consume limit
        $this->service->isLimited($ip, $endpoint, $limit, $window);
        $this->service->isLimited($ip, $endpoint, $limit, $window);

        // Assert it's blocked
        $this->assertTrue($this->service->isLimited($ip, $endpoint, $limit, $window));

        // Wait for window to expire
        sleep($window + 1);

        // Assert it's allowed again
        $this->assertFalse($this->service->isLimited($ip, $endpoint, $limit, $window));
    }
    
    public function testGetRemaining_ReturnsCorrectCount()
    {
        $ip = '127.0.0.4';
        $endpoint = '/api/test';
        $limit = 10;
        
        $this->assertEquals($limit, $this->service->getRemaining($ip, $endpoint, $limit));

        $this->service->isLimited($ip, $endpoint, $limit, 60);
        $this->assertEquals($limit - 1, $this->service->getRemaining($ip, $endpoint, $limit));

        $this->service->isLimited($ip, $endpoint, $limit, 60);
        $this->assertEquals($limit - 2, $this->service->getRemaining($ip, $endpoint, $limit));
    }
}
