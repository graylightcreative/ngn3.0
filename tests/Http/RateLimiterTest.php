<?php
use PHPUnit\Framework\TestCase;
use NGN\Lib\Http\RateLimiter;
use NGN\Lib\Config;

class RateLimiterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        // Use a temp dir isolated per test run
        $this->dir = sys_get_temp_dir() . '/ngn_rl_test_' . bin2hex(random_bytes(4));
        @mkdir($this->dir, 0775, true);
        // Make limits small for test speed
        putenv('RATE_LIMIT_PER_MIN=3');
        putenv('RATE_LIMIT_BURST=0');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iter as $file) {
                if ($file->isFile()) @unlink($file->getPathname());
                else @rmdir($file->getPathname());
            }
            @rmdir($this->dir);
        }
    }

    public function testAllowsWithinLimitAndBlocksAfter(): void
    {
        $config = new Config();
        $rl = new RateLimiter($config, $this->dir);
        $ip = '127.0.0.1';
        $route = 'GET /api/v1/health';

        // First 3 should pass
        for ($i = 0; $i < 3; $i++) {
            [$allowed, $remaining, $reset] = $rl->check($ip, $route);
            $this->assertTrue($allowed, 'Request should be allowed');
            $this->assertGreaterThanOrEqual(0, $remaining);
            $this->assertIsInt($reset);
        }
        // Fourth should fail
        [$allowed4, $remaining4, $reset4] = $rl->check($ip, $route);
        $this->assertFalse($allowed4, 'Request should be rate limited after exceeding');
        $headers = $rl->headers($remaining4, $reset4);
        $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
        $this->assertArrayHasKey('X-RateLimit-Reset', $headers);
    }
}
