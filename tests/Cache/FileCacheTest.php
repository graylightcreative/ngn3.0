<?php
use PHPUnit\Framework\TestCase;
use NGN\Lib\Cache\FileCache;

class FileCacheTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/ngn_cache_test_' . bin2hex(random_bytes(4));
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

    public function testSetAndGetAndValidity(): void
    {
        $cache = new FileCache($this->dir);
        $key = $cache->key('artists', 'daily', 1, 10, 'rank', 'asc');
        $this->assertFalse($cache->valid($key));
        $value = ['items' => [['id'=>1,'name'=>'Artist 1','score'=>1.0,'rank'=>1,'delta'=>0]], 'total' => 100];
        $cache->set($key, $value, 2); // 2 seconds TTL
        $this->assertTrue($cache->exists($key));
        $this->assertTrue($cache->valid($key));
        $read = $cache->get($key);
        $this->assertEquals($value, $read);
        // Wait for TTL to expire
        sleep(3);
        $this->assertFalse($cache->valid($key));
    }
}
