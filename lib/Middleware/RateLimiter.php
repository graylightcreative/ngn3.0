<?php

namespace NGN\Lib\Middleware;

use PDO;
use NGN\Lib\Http\Request;
use NGN\Lib\Http\JsonResponse;

/**
 * RateLimiter - Simple file-based rate limiter for API endpoints
 * 
 * Target: 100 requests / hour per IP
 */
class RateLimiter
{
    private string $storagePath;
    private int $limit;
    private int $window;

    public function __construct(string $storagePath, int $limit = 100, int $window = 3600)
    {
        $this->storagePath = rtrim($storagePath, '/');
        $this->limit = $limit;
        $this->window = $window;

        if (!is_dir($this->storagePath)) {
            @mkdir($this->storagePath, 0775, true);
        }
    }

    /**
     * Check if request should be limited
     */
    public function check(Request $request): ?JsonResponse
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ipHash = md5($ip);
        $file = $this->storagePath . "/ratelimit_{$ipHash}.json";

        $now = time();
        $data = ['requests' => []];

        if (is_file($file)) {
            $data = json_decode(file_get_contents($file), true) ?: ['requests' => []];
        }

        // Filter out old requests
        $data['requests'] = array_filter($data['requests'], function($timestamp) use ($now) {
            return $timestamp > ($now - $this->window);
        });

        if (count($data['requests']) >= $this->limit) {
            $oldest = min($data['requests']);
            $resetTime = $oldest + $this->window;
            
            return new JsonResponse([
                'success' => false,
                'error' => 'Too many requests',
                'message' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $resetTime - $now
            ], 429, [
                'X-RateLimit-Limit' => $this->limit,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => $resetTime
            ]);
        }

        // Add current request
        $data['requests'][] = $now;
        file_put_contents($file, json_encode($data));

        return null;
    }
}
