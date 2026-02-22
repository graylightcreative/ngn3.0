<?php
namespace NGN\Lib\Commerce;

/**
 * Commission Service - Centralized logic for Rule 5 and Platform Rakes.
 * Bible Ref: Rule 5 (Automated 75/25 Split of Platform Fee)
 * NGN 3.0: Multi-Tenant Marketplace Settlement.
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class CommissionService
{
    private $pdo;
    private $config;

    // Rule 5 Constants
    private const PLATFORM_FEE_PERCENT = 0.10; // 10%
    private const OPS_SHARE_PERCENT = 0.75;    // 75% of Platform Fee
    private const DATA_SHARE_PERCENT = 0.25;   // 25% of Platform Fee

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdo = ConnectionFactory::write($config);
    }

    /**
     * Calculate and Settle Rule 5 Platform Fee
     * @return array{ops_cents: int, data_cents: int}
     */
    public function settleRule5(int $orderId, int $grossCents): array
    {
        $platformFee = (int)round($grossCents * self::PLATFORM_FEE_PERCENT);
        
        $opsCents = (int)round($platformFee * self::OPS_SHARE_PERCENT);
        $dataCents = $platformFee - $opsCents; // Ensure total match

        $this->recordSettlement($orderId, 'RULE_5_OPS', $opsCents);
        $this->recordSettlement($orderId, 'RULE_5_DATA', $dataCents);

        return [
            'ops_cents' => $opsCents,
            'data_cents' => $dataCents
        ];
    }

    /**
     * Record settlement in the audit ledger
     */
    private function recordSettlement(int $orderId, string $ruleKey, int $amountCents): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `ngn_2025`.`board_settlements` (order_id, rule_key, amount_cents, status, created_at)
            VALUES (?, ?, ?, 'completed', NOW())
        ");
        $stmt->execute([$orderId, $ruleKey, $amountCents]);
    }
}
