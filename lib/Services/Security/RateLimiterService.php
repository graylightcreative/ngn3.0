<?php

namespace NGN\Lib\Services\Security;

use PDO;
use Exception;

/**
 * RateLimiterService
 * 
 * Implements sliding window rate limiting using the database.
 * Optimized for production hardening in Version 2.1.
 */
class RateLimiterService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Check if a request should be rate limited
     * 
     * @param string $ip Client IP
     * @param string $endpoint Endpoint identifier
     * @param int $limit Max requests allowed
     * @param int $windowSeconds Time window in seconds
     * @return bool True if limited, false if allowed
     */
    public function isLimited(string $ip, string $endpoint, int $limit, int $windowSeconds): bool
    {
        try {
            // 1. Clean up expired windows (simple cleanup)
            if (mt_rand(1, 100) === 1) { // 1% chance cleanup to reduce DB load
                $this->pdo->prepare("DELETE FROM api_rate_limits WHERE window_start < NOW() - INTERVAL ? SECOND")
                    ->execute([$windowSeconds]);
            }

            // 2. Increment or create window
            $stmt = $this->pdo->prepare("
                INSERT INTO api_rate_limits (ip_address, endpoint, request_count, window_start)
                VALUES (:ip, :endpoint, 1, NOW())
                ON DUPLICATE KEY UPDATE
                    request_count = IF(window_start < NOW() - INTERVAL :window SECOND, 1, request_count + 1),
                    window_start = IF(window_start < NOW() - INTERVAL :window SECOND, NOW(), window_start)
            ");

            $stmt->execute([
                ':ip' => $ip,
                ':endpoint' => $endpoint,
                ':window' => $windowSeconds
            ]);

            // 3. Check current count
            $checkStmt = $this->pdo->prepare("
                SELECT request_count FROM api_rate_limits 
                WHERE ip_address = ? AND endpoint = ?
            ");
            $checkStmt->execute([$ip, $endpoint]);
            $count = (int)$checkStmt->fetchColumn();

            return $count > $limit;

        } catch (Exception $e) {
            error_log("RATE_LIMIT_ERROR: " . $e->getMessage());
            return false; // Fail open to avoid blocking users on DB error
        }
    }

    /**
     * Get remaining requests for an IP/endpoint
     */
    public function getRemaining(string $ip, string $endpoint, int $limit): int
    {
        $stmt = $this->pdo->prepare("SELECT request_count FROM api_rate_limits WHERE ip_address = ? AND endpoint = ?");
        $stmt->execute([$ip, $endpoint]);
        $count = (int)$stmt->fetchColumn();
        
        return max(0, $limit - $count);
    }
}
