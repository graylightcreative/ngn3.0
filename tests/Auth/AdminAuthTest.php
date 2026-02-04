<?php
use PHPUnit\Framework\TestCase;
use NGN\Lib\Auth\AdminAuth;
use NGN\Lib\Auth\TokenService;
use NGN\Lib\Config;

class AdminAuthTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('APP_ENV=development');
        putenv('FEATURE_ADMIN=true');
        putenv('JWT_SECRET=test-secret');
        putenv('JWT_ISS=ngn-test');
        putenv('JWT_AUD=ngn-clients-test');
        putenv('JWT_TTL_SECONDS=60');
    }

    public function testAllowsAdminRole(): void
    {
        $config = new Config();
        $tokens = new TokenService($config);
        $issued = $tokens->issueAccessToken(['sub' => 'admin@example.com', 'role' => 'admin']);
        $auth = new AdminAuth($config);
        [$ok, $claims, $err] = $auth->check('Bearer ' . $issued['token']);
        $this->assertTrue($ok);
        $this->assertIsArray($claims);
        $this->assertNull($err);
    }

    public function testRejectsMissingHeader(): void
    {
        $config = new Config();
        $auth = new AdminAuth($config);
        [$ok, $claims, $err] = $auth->check(null);
        $this->assertFalse($ok);
        $this->assertNull($claims);
        $this->assertNotNull($err);
    }
}
