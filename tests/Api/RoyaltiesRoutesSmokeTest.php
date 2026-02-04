<?php
use PHPUnit\Framework\TestCase;

class RoyaltiesRoutesSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('APP_ENV=development');
        putenv('FEATURE_ROYALTIES=true');
        // Minimal required env
        putenv('DB_NAME=testdb');
        putenv('DB_USER=testuser');
        putenv('JWT_SECRET=test-secret');
    }

    public function testContractsRouteReturnsEnvelope(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/v1/contracts?labelId=2';
        $_GET = ['labelId' => '2'];
        $index = __DIR__ . '/../../api/v1/index.php';
        ob_start();
        include $index;
        $out = ob_get_clean();
        $json = json_decode($out, true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('items', $json['data']);
    }

    public function testStatementsRouteReturnsEnvelope(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/v1/royalties/statements?period=2025-Q2';
        $_GET = ['period' => '2025-Q2'];
        $index = __DIR__ . '/../../api/v1/index.php';
        ob_start();
        include $index;
        $out = ob_get_clean();
        $json = json_decode($out, true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('items', $json['data']);
    }
}
