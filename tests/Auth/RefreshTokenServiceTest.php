<?php
use PHPUnit\Framework\TestCase;
use NGN\Lib\Auth\RefreshTokenService;
use NGN\Lib\Config;

class RefreshTokenServiceTest extends TestCase
{
    private string $ledger;

    protected function setUp(): void
    {
        $this->ledger = sys_get_temp_dir() . '/ngn_refresh_ledger_' . bin2hex(random_bytes(4)) . '.json';
        putenv('REFRESH_TOKENS_LEDGER_PATH=' . $this->ledger);
        // Ensure JWT refresh TTL is small for expiry test
        putenv('JWT_REFRESH_TTL_SECONDS=2');
    }

    protected function tearDown(): void
    {
        if (is_file($this->ledger)) @unlink($this->ledger);
    }

    public function testIssueValidateAndRevoke(): void
    {
        $svc = new RefreshTokenService(new Config());
        $iss = $svc->issue('user@example.com', 'guest');
        $this->assertArrayHasKey('token', $iss);
        [$ok, $sub, $role, $err] = $svc->validate($iss['token']);
        $this->assertTrue($ok);
        $this->assertSame('user@example.com', $sub);
        $this->assertSame('guest', $role);
        $rev = $svc->revoke($iss['token']);
        $this->assertTrue($rev);
        [$ok2, , , $err2] = $svc->validate($iss['token']);
        $this->assertFalse($ok2);
        $this->assertSame('revoked', $err2);
    }

    public function testExpiry(): void
    {
        $svc = new RefreshTokenService(new Config());
        $iss = $svc->issue('u@e.com', 'guest');
        sleep(3);
        [$ok, , , $err] = $svc->validate($iss['token']);
        $this->assertFalse($ok);
        $this->assertSame('expired', $err);
    }
}
