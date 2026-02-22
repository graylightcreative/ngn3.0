<?php
namespace NGN\Lib\Services\Institutional;

/**
 * Liquidity Service - NGN 3.0 Exit Trajectory
 * Handles Secondary Market Equity and Liquidity Event Triggers.
 * Bible Ref: Chapter 28 - Equity & The Sovereign Exit.
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class LiquidityService
{
    private $pdo;

    public function __construct(Config $config)
    {
        $this->pdo = ConnectionFactory::write($config);
    }

    /**
     * List Equity for Secondary Market Sale
     */
    public function listEquityForSale(int $userId, int $investmentId, int $amountCents): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `ngn_2025`.`equity_market_listings` (user_id, investment_id, amount_cents, status, created_at)
            VALUES (?, ?, ?, 'open', NOW())
        ");
        $stmt->execute([$userId, $investmentId, $amountCents]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Trigger Liquidity Event Protocol
     */
    public function triggerLiquidityEvent(string $reason): bool
    {
        // 1. Lock all secondary market trading
        $this->pdo->query("UPDATE `ngn_2025`.`equity_market_listings` SET status = 'locked' WHERE status = 'open'");

        // 2. Log Exit Trigger in Content Ledger
        // 3. Notify all Board Members and Shareholders

        return true;
    }

    /**
     * Check if Exit Threshold Met
     */
    public function checkExitReadiness(): array
    {
        $totalAUM = (int)$this->pdo->query("SELECT SUM(amount_cents) FROM `ngn_2025`.`investments` WHERE status = 'active'")->fetchColumn();
        $target = 5400000000; // $54M in cents

        return [
            'current_aum_cents' => $totalAUM,
            'target_cents' => $target,
            'ready' => ($totalAUM >= $target),
            'percentage' => round(($totalAUM / $target) * 100, 2)
        ];
    }
}
