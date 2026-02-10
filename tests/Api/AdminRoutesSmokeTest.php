<?php
use PHPUnit\Framework\TestCase;
use NGN\Lib\Config;
use NGN\Lib\Auth\TokenService;

class AdminRoutesSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('APP_ENV=development');
        putenv('FEATURE_ADMIN=true');
        putenv('JWT_SECRET=test-secret');
        putenv('DB_NAME=testdb');
        putenv('DB_USER=testuser');
    }

    private function withAdminAuthHeader(): void
    {
        $config = new Config();
        $svc = new TokenService($config);
        $issued = $svc->issueAccessToken(['sub' => 'admin@example.com', 'role' => 'admin']);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $issued['token'];
    }

    public function testAdminHealthReturnsEnvelope(): void
    {
        $this->withAdminAuthHeader();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/v1/admin/health';
        $_GET = [];

        $index = __DIR__ . '/../../public/api/v1/index.php';
        ob_start();
        include $index;
        $out = ob_get_clean();
        $json = json_decode($out, true);
        $this->assertArrayHasKey('data', $json, "Response missing 'data' key. Output: " . $out);
        $this->assertArrayHasKey('meta', $json);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('services', $json['data']);
    }
}
