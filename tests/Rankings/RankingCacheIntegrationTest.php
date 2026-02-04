<?php
use PHPUnit\Framework\TestCase;
use NGN\Lib\Cache\FileCache;

class RankingCacheIntegrationTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/ngn_rank_cache_it_' . bin2hex(random_bytes(4));
        @mkdir($this->dir, 0775, true);
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

    public function testMissThenHitThenExpire(): void
    {
        $cache = new FileCache($this->dir);
        $key = $cache->key('artists', 'daily', 1, 10, 'rank', 'asc');
        $this->assertFalse($cache->valid($key), 'Expected initial miss');

        $value = ['items' => [['id'=>1,'name'=>'Artist 1','score'=>1.23,'rank'=>1,'delta'=>0]], 'total' => 100];
        $cache->set($key, $value, 1); // 1 second TTL
        $this->assertTrue($cache->valid($key), 'Expected cache hit after set');
        $read = $cache->get($key);
        $this->assertEquals($value, $read, 'Cached value should roundtrip');

        // Wait to expire
        sleep(2);
        $this->assertFalse($cache->valid($key), 'Expected cache miss after TTL expiry');
    }
}
