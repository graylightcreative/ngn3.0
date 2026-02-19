<?php

namespace NGN\Lib\Middleware;

use NGN\Lib\Http\Request;
use NGN\Lib\Http\JsonResponse;
use NGN\Lib\Services\Security\RateLimiterService;
use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

/**
 * RateLimiter - Database-backed rate limiter for API endpoints
 * 
 * Target: 100 requests / hour per IP
 */
class RateLimiter
{
    private RateLimiterService $service;
    private int $limit;
    private int $window;

    public function __construct(string $storagePath = '', int $limit = 100, int $window = 3600)
    {
        $config = new Config();
        $pdo = ConnectionFactory::read($config);
        $this->service = new RateLimiterService($pdo);
        $this->limit = $limit;
        $this->window = $window;
    }

    /**
     * Check if request should be limited
     */
    public function check(Request $request): ?JsonResponse
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $endpoint = $request->path();

        if ($this->service->isLimited($ip, $endpoint, $this->limit, $this->window)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Too many requests',
                'message' => 'Rate limit exceeded. Please try again later.'
            ], 429, [
                'X-RateLimit-Limit' => $this->limit,
                'X-RateLimit-Remaining' => 0
            ]);
        }

        $remaining = $this->service->getRemaining($ip, $endpoint, $this->limit);

        // Success - no response means allowed
        header("X-RateLimit-Limit: {$this->limit}");
        header("X-RateLimit-Remaining: {$remaining}");

        return null;
    }
}
