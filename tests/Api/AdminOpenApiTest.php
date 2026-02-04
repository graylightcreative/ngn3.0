<?php
use PHPUnit\Framework\TestCase;

class AdminOpenApiTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('APP_ENV=development');
        putenv('FEATURE_ADMIN=true');
        putenv('DB_NAME=testdb');
        putenv('DB_USER=testuser');
        putenv('JWT_SECRET=test-secret');
    }

    public function testOpenApiHasAdminPaths(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/v1/openapi.json';
        $_GET = [];

        $index = __DIR__ . '/../../api/v1/index.php';
        ob_start();
        include $index;
        $out = ob_get_clean();
        $json = json_decode($out, true);
        $this->assertArrayHasKey('/api/v1/admin/health', $json['paths']);
        $this->assertArrayHasKey('/api/v1/admin/users', $json['paths']);
        $this->assertArrayHasKey('/api/v1/admin/smr/ingestions', $json['paths']);
        $this->assertArrayHasKey('/api/v1/admin/flags', $json['paths']);
    }
}
