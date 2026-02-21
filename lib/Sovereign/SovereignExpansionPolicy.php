<?php
namespace NGN\Lib\Sovereign;

use NGN\Lib\Config;
use NGN\Lib\AI\SovereignAIPolicy;
use NGN\Lib\Env;

/**
 * Sovereign Expansion & Foundry Policy
 * 
 * Rules:
 * 1. "Sovereign Foundry" (Global Expansion) is dormant until threshold met.
 * 2. Threshold = Base (Industrial Equipment + Staff) + (Active Users * 10).
 */
class SovereignExpansionPolicy
{
    private Config $config;
    private SovereignAIPolicy $aiPolicy;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->aiPolicy = new SovereignAIPolicy($config);
    }

    /**
     * Get the Monetary Goal for Global Foundry Expansion.
     */
    public function getExpansionGoal(): float
    {
        $base = (float)Env::get('SOVEREIGN_EX_BASE_GOAL', 500000.00); // $500k for global physical ops
        $userMultiplier = (float)Env::get('SOVEREIGN_EX_USER_MULTIPLIER', 10.00);
        
        return $base + ($this->getActiveUserCount() * $userMultiplier);
    }

    /**
     * Get the current accumulated "Sovereign Fund" for Expansion.
     */
    public function getCurrentProgress(): float
    {
        $baseFund = (float)Env::get('SOVEREIGN_EX_CURRENT_FUND', 0.00);
        
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
        return "Global Expansion is restricted. Sovereign Policy mandates that physical expansion into owned manufacturing (Foundry) and international logistics remains dormant until the Sovereign Fund reaches the threshold required to sustain industrial scale.";
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
