<?php
use PHPUnit\Framework\TestCase;

class OpenApiRouteTest extends TestCase
{
    protected function setUp(): void
    {
        // Ensure dev env for openapi.json route
        putenv('APP_ENV=development');
        // Disable rate limiting for this test to avoid 429s
        putenv('FEATURE_RATE_LIMITING=false');
        // Provide minimal required env for config validation paths
        putenv('DB_NAME=testdb');
        putenv('DB_USER=testuser');
        putenv('JWT_SECRET=test-secret');
    }

    public function testOpenApiJsonReturnsSpec(): void
    {
        // Simulate a GET request to /api/v1/openapi.json
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/v1/openapi.json';
        $_GET = [];

        $index = __DIR__ . '/../../public/api/v1/index.php';
        $this->assertFileExists($index, 'API front controller not found');

        ob_start();
        include $index; // Should echo JSON and not throw
        $out = ob_get_clean();

        $this->assertNotEmpty($out, 'No output from openapi.json');
        $json = json_decode($out, true);
        $this->assertIsArray($json, 'Output is not valid JSON');
        $this->assertArrayHasKey('openapi', $json);
        $this->assertArrayHasKey('info', $json);
        $this->assertArrayHasKey('paths', $json);
        $this->assertSame('3.0.3', $json['openapi']);
        $paths = $json['paths'] ?? [];
        $this->assertArrayHasKey('/api/v1/health', $paths);
        $this->assertArrayHasKey('/api/v1/smr/uploads', $paths);
        $this->assertArrayHasKey('/api/v1/smr/ingestions', $paths);
        $this->assertArrayHasKey('/api/v1/contracts', $paths);
        $this->assertArrayHasKey('/api/v1/royalties/statements', $paths);
    }
}
