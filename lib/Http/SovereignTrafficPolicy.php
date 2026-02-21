<?php
namespace NGN\Lib\Http;

use NGN\Lib\Config;
use NGN\Lib\AI\SovereignAIPolicy;
use NGN\Lib\Env;

/**
 * Sovereign Traffic & Load Balancing Policy
 * 
 * Rules:
 * 1. "Badass" Load Balancing is DISABLED until the Monetary Threshold is met.
 * 2. Until then, we enforce a "Dormant Fleet" Throttle (lower limits).
 * 3. Threshold = Base (Load Balancing Infrastructure) + (Active Users * 10).
 */
class SovereignTrafficPolicy
{
    private Config $config;
    private SovereignAIPolicy $aiPolicy;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->aiPolicy = new SovereignAIPolicy($config);
    }

    /**
     * Is the advanced "Badass" Load Balancing enabled?
     */
    public function isBadassEnabled(): bool
    {
        if (Env::get('SOVEREIGN_LB_FORCE_OFF', true)) {
            return false;
        }

        return $this->aiPolicy->getCurrentProgress() >= $this->getLBGoal();
    }

    /**
     * Get the Monetary Goal for Advanced Load Balancing.
     */
    public function getLBGoal(): float
    {
        $base = (float)Env::get('SOVEREIGN_LB_BASE_GOAL', 250000.00); // $250k for massive scale
        $userMultiplier = (float)Env::get('SOVEREIGN_LB_USER_MULTIPLIER', 10.00);
        
        // We reuse the user count logic from AI Policy but with LB weights
        return $base + ($this->getActiveUserCount() * $userMultiplier);
    }

    /**
     * Get the current accumulated "Sovereign Fund" for LB.
     */
    public function getCurrentProgress(): float
    {
        $baseFund = (float)Env::get('SOVEREIGN_LB_CURRENT_FUND', 0.00);
        
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
        return "Advanced Load Balancing is restricted. NGN Sovereign Policy mandates a dormant network throttle until the Sovereign Fund reaches the threshold required for high-velocity global redundancy. This ensures our human-first value remains primary until we reach full autonomous scale.";
    }

    /**
     * Determine the appropriate Rate Limit (per minute) based on policy status.
     */
    public function getEffectiveRateLimit(): int
    {
        if ($this->isBadassEnabled()) {
            return (int)Env::get('RATE_LIMIT_PER_MIN_BADASS', 1000); // Massive throughput
        }

        // Dormant/Limited Fleet Throttle
        return (int)Env::get('RATE_LIMIT_PER_MIN_DORMANT', 60);
    }

    /**
     * Determine the appropriate Burst Limit based on policy status.
     */
    public function getEffectiveBurstLimit(): int
    {
        if ($this->isBadassEnabled()) {
            return (int)Env::get('RATE_LIMIT_BURST_BADASS', 500);
        }

        return (int)Env::get('RATE_LIMIT_BURST_DORMANT', 10);
    }

    private function getActiveUserCount(): int
    {
        // Simple fallback if DB isn't available during early boot
        try {
            $pdo = \NGN\Lib\DB\ConnectionFactory::read($this->config);
            return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
        } catch (\Throwable $e) {
            return 100; // conservative estimate
        }
    }
}
