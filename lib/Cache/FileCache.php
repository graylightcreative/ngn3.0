<?php
namespace NGN\Lib\Cache;

class FileCache
{
    private string $baseDir;

    public function __construct(string $baseDir)
    {
        $this->baseDir = rtrim($baseDir, '/');
    }

    public function get(string $key): mixed
    {
        $file = $this->pathForKey($key);
        if (!is_file($file)) return null;
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        return $data['value'] ?? null;
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        $file = $this->pathForKey($key);
        $dir = dirname($file);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $payload = [
            'stored_at' => time(),
            'ttl' => max(0, (int)$ttlSeconds),
            'value' => $value,
        ];
        // JSON_PARTIAL_OUTPUT_ON_ERROR to be defensive; preserve types
        file_put_contents($file, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    public function exists(string $key): bool
    {
        $file = $this->pathForKey($key);
        return is_file($file);
    }

    public function valid(string $key): bool
    {
        $file = $this->pathForKey($key);
        if (!is_file($file)) return false;
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        if (!is_array($data)) return false;
        $stored = (int)($data['stored_at'] ?? 0);
        $ttl = (int)($data['ttl'] ?? 0);
        if ($ttl <= 0) return false;
        return (time() - $stored) < $ttl;
    }

    public function delete(string $key): void
    {
        $file = $this->pathForKey($key);
        if (is_file($file)) @unlink($file);
    }

    public function clear(): void
    {
        $dir = $this->baseDir;
        if (!is_dir($dir)) return;
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iter as $file) {
            if ($file->isFile()) @unlink($file->getPathname());
            else @rmdir($file->getPathname());
        }
    }

    public function key(string $resource, string $interval, int $page, int $perPage, string $sort, string $dir): string
    {
        $parts = [$resource, $interval, $page, $perPage, $sort, $dir];
        return implode(':', $parts);
    }

    public function filePathFor(string $resource, string $interval, string $hash): string
    {
        return $this->baseDir . '/' . $resource . '/' . $interval . '/' . $hash . '.json';
    }

    private function pathForKey(string $key): string
    {
        // Hash keys to avoid very long filenames
        $hash = hash('sha256', $key);
        // Attempt to parse resource/interval from key for directory structure if possible
        $parts = explode(':', $key);
        $resource = $parts[0] ?? 'unknown';
        $interval = $parts[1] ?? 'any';
        return $this->filePathFor($resource, $interval, $hash);
    }
}
