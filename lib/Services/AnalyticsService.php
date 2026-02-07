<?php

namespace NGN\Lib\Services;

use PDO;
use Exception;

/**
 * AnalyticsService - Platform metrics and dashboard data
 */
class AnalyticsService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get high-level platform summary metrics
     */
    public function getSummary(): array
    {
        return [
            'users' => $this->getQuickCount('users'),
            'artists' => $this->getQuickCount('artists'),
            'revenue_30d' => $this->getRevenueTotal(30),
            'engagements_30d' => $this->getEngagementTotal(30),
            'active_stations' => $this->getQuickCount('stations')
        ];
    }

    /**
     * Get revenue trends for charts
     */
    public function getRevenueTrends(int $days = 30): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DATE(created_at) as date, SUM(amount_net) as total
            FROM cdm_royalty_transactions
            WHERE source_type != 'payout' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get engagement trends for charts
     */
    public function getEngagementTrends(int $days = 30): array
    {
        // Fallback to a count of engagements if specific trend table is empty
        $stmt = $this->pdo->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as count
            FROM cdm_engagements
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getQuickCount(string $table): int
    {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    }

    private function getRevenueTotal(int $days): float
    {
        $stmt = $this->pdo->prepare("
            SELECT SUM(amount_net) FROM cdm_royalty_transactions 
            WHERE status = 'cleared' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        return (float)$stmt->fetchColumn();
    }

    private function getEngagementTotal(int $days): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM cdm_engagements 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        return (int)$stmt->fetchColumn();
    }
}
