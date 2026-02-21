<?php
namespace NGN\Lib\Http;

use NGN\Lib\Config;

class RateLimiter
{
    private string $storageDir;
    private int $perMin;
    private int $burst;

    public function __construct(Config $config, ?string $storageDir = null)
    {
        $this->storageDir = $storageDir ?: sys_get_temp_dir().'/ngn_rate_limit';
        
        // 1. Check Sovereign Traffic Policy
        $policy = new SovereignTrafficPolicy($config);
        $this->perMin = $policy->getEffectiveRateLimit();
        $this->burst = $policy->getEffectiveBurstLimit();

        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0775, true);
        }
    }

    /**
     * Check whether the request identified by (ip, route) is allowed.
     * Returns [allowed(bool), remaining(int), reset(int unix epoch seconds)]
     */
    public function check(string $ip, string $route): array
    {
        $key = $this->key($ip, $route);
        $now = time();
        $windowStart = $now - 59; // rolling 60-second window
        $file = $this->filePath($key);
        $events = [];
        if (is_file($file)) {
            $json = file_get_contents($file);
            $events = json_decode($json, true) ?: [];
        }
        // Drop old events
        $events = array_values(array_filter($events, fn($ts) => (int)$ts >= $windowStart));
        $count = count($events);

        $allowed = true;
        $remaining = max(0, $this->perMin - $count);
        if ($count >= ($this->perMin + $this->burst)) {
            $allowed = false;
        }

        if ($allowed) {
            $events[] = $now;
            @file_put_contents($file, json_encode($events));
            $remaining = max(0, $this->perMin - count($events));
        }

        $reset = $events ? (min($events) + 60) : ($now + 60);
        return [$allowed, $remaining, $reset];
    }

    public function headers(int $remaining, int $reset): array
    {
        return [
            'X-RateLimit-Limit' => (string)$this->perMin,
            'X-RateLimit-Remaining' => (string)max(0, $remaining),
            'X-RateLimit-Reset' => (string)$reset,
        ];
    }

    private function key(string $ip, string $route): string
    {
        return hash('sha256', $ip.'|'.$route);
    }

    private function filePath(string $hash): string
    {
        return rtrim($this->storageDir, '/').'/'.$hash.'.json';
    }
}
