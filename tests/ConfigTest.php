<?php
use NGN\Lib\Config;

use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        // Set minimal env
        putenv('DB_NAME=testdb');
        putenv('DB_USER=testuser');
        putenv('JWT_SECRET=super-secret');
    }

    public function testValidateCriticalPassesWithRequiredEnv(): void
    {
        $config = new Config();
        $this->assertTrue($config->validateCritical(), 'Config should validate with required env');
        $this->assertSame([], $config->errors());
    }

    public function testValidateCriticalFailsWithoutJwtSecret(): void
    {
        putenv('JWT_SECRET=');
        $config = new Config();
        $this->assertFalse($config->validateCritical());
        $this->assertNotEmpty($config->errors());
    }
}
