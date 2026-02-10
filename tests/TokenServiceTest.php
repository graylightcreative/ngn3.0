<?php
use NGN\Lib\Auth\TokenService;
use NGN\Lib\Config;
use PHPUnit\Framework\TestCase;

class TokenServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $envVars = [
            'JWT_SECRET' => 'test-secret',
            'JWT_ISS' => 'ngn-test',
            'JWT_AUD' => 'ngn-clients-test',
            'JWT_TTL_SECONDS' => '60'
        ];
        
        foreach ($envVars as $key => $val) {
            putenv("$key=$val");
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }
    }

    public function testIssueAndDecodeToken(): void
    {
        $config = new Config();
        $svc = new TokenService($config);
        $issued = $svc->issueAccessToken(['sub' => 'user@example.com', 'role' => 'guest']);
        $this->assertArrayHasKey('token', $issued);
        $this->assertArrayHasKey('expires_in', $issued);
        $decoded = $svc->decode($issued['token']);
        $this->assertSame('ngn-test', $decoded['iss']);
        $this->assertSame('ngn-clients-test', $decoded['aud']);
        $this->assertSame('user@example.com', $decoded['sub']);
        $this->assertSame('guest', $decoded['role']);
        $this->assertGreaterThan(time(), $decoded['exp']);
    }
}
