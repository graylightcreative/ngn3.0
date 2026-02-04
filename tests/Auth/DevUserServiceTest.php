<?php
use PHPUnit\Framework\TestCase;
use NGN\Lib\Auth\DevUserService;
use NGN\Lib\Config;

class DevUserServiceTest extends TestCase
{
    private string $ledgerPath;

    protected function setUp(): void
    {
        $this->ledgerPath = sys_get_temp_dir() . '/ngn_users_ledger_' . bin2hex(random_bytes(4)) . '.json';
        $users = [
            ['email' => 'user@example.com', 'password' => 'secret'],
            ['email' => 'hash@example.com', 'password' => password_hash('p@ss', PASSWORD_BCRYPT)],
        ];
        file_put_contents($this->ledgerPath, json_encode($users));
        putenv('USERS_LEDGER_PATH=' . $this->ledgerPath);
    }

    protected function tearDown(): void
    {
        if (is_file($this->ledgerPath)) @unlink($this->ledgerPath);
    }

    public function testValidatePlainPassword(): void
    {
        $svc = new DevUserService(new Config());
        $this->assertTrue($svc->validate('user@example.com', 'secret'));
        $this->assertFalse($svc->validate('user@example.com', 'wrong'));
    }

    public function testValidateBcryptPassword(): void
    {
        $svc = new DevUserService(new Config());
        $this->assertTrue($svc->validate('hash@example.com', 'p@ss'));
        $this->assertFalse($svc->validate('hash@example.com', 'nope'));
    }
}
