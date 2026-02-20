<?php
namespace NGN\Lib\Services\Analytics;

/**
 * Oracle Analytics Service
 * Pulls "Pressurized" GA4 & Search Console data from the Graylight Nexus.
 * Bible Ref: Chapter 5 - Data nervous system
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class OracleAnalyticsService
{
    private $config;
    private $pdo;
    private $projectId = 23; // Fixed for NextGenNoise

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdo = ConnectionFactory::nexus($config);
    }

    /**
     * Fetch weekly analytics overview (Users, Sessions, etc)
     */
    public function getWeeklyOverview(): array
    {
        return $this->getTruth('google_analytics_weekly');
    }

    /**
     * Fetch top search queries and SEO metrics
     */
    public function getSearchPerformance(): array
    {
        return $this->getTruth('search_console_top_queries');
    }

    /**
     * Internal helper to fetch truth blob
     */
    private function getTruth(string $key): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT truth_value, verified_at 
                FROM oracle_truths 
                WHERE project_id = ? 
                AND truth_key = ? 
                ORDER BY verified_at DESC LIMIT 1
            ");
            $stmt->execute([$this->projectId, $key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) return ['status' => 'empty', 'data' => []];

            $data = json_decode($row['truth_value'], true) ?: [];
            return [
                'status' => 'success',
                'verified_at' => $row['verified_at'],
                'data' => $data
            ];
        } catch (\Throwable $e) {
            error_log("OracleAnalyticsError ({$key}): " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
