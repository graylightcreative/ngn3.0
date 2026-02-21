<?php
namespace NGN\Lib\Sovereign;

use NGN\Lib\Config;
use NGN\Lib\AI\SovereignAIPolicy;
use NGN\Lib\Env;

/**
 * Sovereign Operations & Payroll Policy
 * 
 * Rules:
 * 1. "Sovereign Operations" (Payroll/Payouts) is dormant until threshold met.
 * 2. Threshold = Base (Payout Reserve) + (Monthly Burn * 12).
 */
class SovereignOperationsPolicy
{
    private Config $config;
    private SovereignAIPolicy $aiPolicy;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->aiPolicy = new SovereignAIPolicy($config);
    }

    /**
     * Get the Monetary Goal for Global Operations & Payroll.
     */
    public function getOperationsGoal(): float
    {
        $base = (float)Env::get('SOVEREIGN_OP_BASE_GOAL', 1000000.00); // $1M for total network payout security
        $userMultiplier = (float)Env::get('SOVEREIGN_OP_USER_MULTIPLIER', 100.00);
        
        return $base + ($this->getActiveUserCount() * $userMultiplier);
    }

    /**
     * Get the current accumulated "Sovereign Fund" for Operations.
     */
    public function getCurrentProgress(): float
    {
        $baseFund = (float)Env::get('SOVEREIGN_OP_CURRENT_FUND', 0.00);
        
        try {
            $pdo = \NGN\Lib\DB\ConnectionFactory::read($this->config);
            $invStmt = $pdo->query("SELECT SUM(amount_cents) FROM ngn_2025.investments WHERE status = 'active'");
            $invCents = (int)$invStmt->fetchColumn();
            
            // Generate XP: 10 XP per dollar
            $liveXp = ($invCents / 100) * 10;
            
            return $baseFund + $liveXp;
        } catch (\Throwable $e) {
            return $baseFund;
        }
    }

    public function getPolicyStatement(): string
    {
        return "Sovereign Payouts and Human Operations are restricted. Policy mandates a $1M Liquid Reserve to ensure Artists, Venues, and Staff are paid independently, and to maintain an IP Fortress that protects our alliance from legacy industry interference.";
    }

    private function getActiveUserCount(): int
    {
        try {
            $pdo = \NGN\Lib\DB\ConnectionFactory::read($this->config);
            return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
        } catch (\Throwable $e) {
            return 100;
        }
    }
}
