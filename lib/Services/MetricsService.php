<?php

namespace NGN\Lib\Services;

use PDO;

/**
 * Metrics Service
 *
 * Handles storage and calculation of API request metrics including
 * P50, P95, and P99 percentile latency calculations.
 *
 * Used for P95 latency monitoring per Bible Ch. 12 alert requirements.
 */
class MetricsService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Record an API request metric
     *
     * @param string $endpoint The API endpoint path (e.g., /api/v1/posts)
     * @param string $method HTTP method (GET, POST, etc.)
     * @param int $statusCode HTTP status code
     * @param float $durationMs Request duration in milliseconds
     * @param int|null $userId Authenticated user ID if applicable
     * @return bool Success status
     */
    public function recordRequest(
        string $endpoint,
        string $method,
        int $statusCode,
        float $durationMs,
        ?int $userId = null
    ): bool {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO api_request_metrics
                    (endpoint, method, status_code, duration_ms, user_id)
                VALUES
                    (:endpoint, :method, :status_code, :duration_ms, :user_id)
            ");

            return $stmt->execute([
                'endpoint' => $endpoint,
                'method' => $method,
                'status_code' => $statusCode,
                'duration_ms' => $durationMs,
                'user_id' => $userId
            ]);
        } catch (\PDOException $e) {
            error_log("MetricsService::recordRequest failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calculate percentile latency for a given time window
     *
     * @param int $percentile The percentile to calculate (50, 95, 99)
     * @param int $windowMinutes Time window in minutes (default: 5)
     * @param string|null $endpoint Filter by specific endpoint (null = all endpoints)
     * @return float|null The percentile value in milliseconds, or null if no data
     */
    public function calculatePercentile(
        int $percentile,
        int $windowMinutes = 5,
        ?string $endpoint = null
    ): ?float {
        try {
            // Build query with optional endpoint filter
            $sql = "
                SELECT duration_ms
                FROM api_request_metrics
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :window_minutes MINUTE)
            ";

            $params = ['window_minutes' => $windowMinutes];

            if ($endpoint !== null) {
                $sql .= " AND endpoint = :endpoint";
                $params['endpoint'] = $endpoint;
            }

            $sql .= " ORDER BY duration_ms ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $durations = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($durations)) {
                return null;
            }

            // Calculate percentile index
            $count = count($durations);
            $index = (int) ceil(($percentile / 100) * $count) - 1;
            $index = max(0, min($index, $count - 1)); // Clamp to valid range

            return (float) $durations[$index];
        } catch (\PDOException $e) {
            error_log("MetricsService::calculatePercentile failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get comprehensive latency statistics for a time window
     *
     * @param int $windowMinutes Time window in minutes (default: 5)
     * @param string|null $endpoint Filter by specific endpoint (null = all endpoints)
     * @return array Statistics including p50, p95, p99, min, max, avg, count
     */
    public function getLatencyStats(int $windowMinutes = 5, ?string $endpoint = null): array
    {
        try {
            $sql = "
                SELECT
                    COUNT(*) as request_count,
                    MIN(duration_ms) as min_ms,
                    MAX(duration_ms) as max_ms,
                    AVG(duration_ms) as avg_ms
                FROM api_request_metrics
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :window_minutes MINUTE)
            ";

            $params = ['window_minutes' => $windowMinutes];

            if ($endpoint !== null) {
                $sql .= " AND endpoint = :endpoint";
                $params['endpoint'] = $endpoint;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($stats['request_count'] == 0) {
                return [
                    'request_count' => 0,
                    'min_ms' => null,
                    'max_ms' => null,
                    'avg_ms' => null,
                    'p50_ms' => null,
                    'p95_ms' => null,
                    'p99_ms' => null
                ];
            }

            // Calculate percentiles
            $p50 = $this->calculatePercentile(50, $windowMinutes, $endpoint);
            $p95 = $this->calculatePercentile(95, $windowMinutes, $endpoint);
            $p99 = $this->calculatePercentile(99, $windowMinutes, $endpoint);

            return [
                'request_count' => (int) $stats['request_count'],
                'min_ms' => (float) $stats['min_ms'],
                'max_ms' => (float) $stats['max_ms'],
                'avg_ms' => (float) $stats['avg_ms'],
                'p50_ms' => $p50,
                'p95_ms' => $p95,
                'p99_ms' => $p99
            ];
        } catch (\PDOException $e) {
            error_log("MetricsService::getLatencyStats failed: " . $e->getMessage());
            return [
                'request_count' => 0,
                'min_ms' => null,
                'max_ms' => null,
                'avg_ms' => null,
                'p50_ms' => null,
                'p95_ms' => null,
                'p99_ms' => null
            ];
        }
    }

    /**
     * Get per-endpoint latency breakdown
     *
     * @param int $windowMinutes Time window in minutes (default: 5)
     * @param int $limit Maximum number of endpoints to return
     * @return array Array of endpoint statistics sorted by P95 latency (highest first)
     */
    public function getEndpointBreakdown(int $windowMinutes = 5, int $limit = 20): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT endpoint
                FROM api_request_metrics
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :window_minutes MINUTE)
                GROUP BY endpoint
                ORDER BY COUNT(*) DESC
                LIMIT :limit
            ");

            $stmt->execute([
                'window_minutes' => $windowMinutes,
                'limit' => $limit
            ]);

            $endpoints = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $breakdown = [];

            foreach ($endpoints as $endpoint) {
                $stats = $this->getLatencyStats($windowMinutes, $endpoint);
                $stats['endpoint'] = $endpoint;
                $breakdown[] = $stats;
            }

            // Sort by P95 latency (highest first)
            usort($breakdown, function ($a, $b) {
                return ($b['p95_ms'] ?? 0) <=> ($a['p95_ms'] ?? 0);
            });

            return $breakdown;
        } catch (\PDOException $e) {
            error_log("MetricsService::getEndpointBreakdown failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clean up old metrics data (retention policy)
     *
     * @param int $retentionDays Number of days to retain (default: 7)
     * @return int Number of rows deleted
     */
    public function cleanupOldMetrics(int $retentionDays = 7): int
    {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM api_request_metrics
                WHERE created_at < DATE_SUB(NOW(), INTERVAL :retention_days DAY)
            ");

            $stmt->execute(['retention_days' => $retentionDays]);
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            error_log("MetricsService::cleanupOldMetrics failed: " . $e->getMessage());
            return 0;
        }
    }
}
