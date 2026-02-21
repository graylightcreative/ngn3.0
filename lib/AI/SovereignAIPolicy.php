<?php
namespace NGN\Lib\AI;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Env;
use PDO;

/**
 * Sovereign AI Policy & Killswitch
 * 
 * Rules:
 * 1. AI is OFF by default (Killswitch Active).
 * 2. Activation requires reaching a Monetary Threshold.
 * 3. Threshold = Base (Niko + Writers) + (Active Users * User Needs Multiplier).
 */
class SovereignAIPolicy
{
    private PDO $pdo;
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdo = ConnectionFactory::read($config);
    }

    /**
     * Check if AI features are allowed to run.
     */
    public function isAIEnabled(): bool
    {
        // 1. Check explicit override in ENV (Safety First)
        if (Env::get('SOVEREIGN_AI_FORCE_OFF', true)) {
            return false;
        }

        // 2. Check if we have reached the threshold
        return $this->getCurrentProgress() >= $this->getActivationGoal();
    }

    /**
     * Calculate the current monetary goal based on fleet size.
     */
    public function getActivationGoal(): float
    {
        $baseThreshold = (float)Env::get('SOVEREIGN_AI_BASE_GOAL', 50000.00); // Default $50k
        $userMultiplier = (float)Env::get('SOVEREIGN_AI_USER_MULTIPLIER', 10.00); // $10 per user x 10 needs
        
        $activeUsers = (int)$this->pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
        
        return $baseThreshold + ($activeUsers * $userMultiplier);
    }

    /**
     * Get the current accumulated "Sovereign Fund" value.
     */
    public function getCurrentProgress(): float
    {
        $baseFund = (float)Env::get('SOVEREIGN_AI_CURRENT_FUND', 0.00);
        try {
            $invStmt = $this->pdo->query("SELECT SUM(amount_cents) FROM ngn_2025.investments WHERE status = 'active'");
            $invCents = (int)$invStmt->fetchColumn();
            
            // Generate XP: 10 XP per dollar
            $liveXp = ($invCents / 100) * 10;
            
            return $baseFund + $liveXp;
        } catch (\Throwable $e) {
            return $baseFund;
        }
    }

    /**
     * Get the count of unique contributors/investors.
     */
    public function getContributorCount(): int
    {
        try {
            return (int)$this->pdo->query("SELECT COUNT(DISTINCT user_id) FROM ngn_2025.investments WHERE status = 'active'")->fetchColumn();
        } catch (\Throwable $e) {
            return (int)Env::get('SOVEREIGN_AI_CONTRIBUTOR_COUNT', 42); // Fallback mock
        }
    }

    /**
     * Get the percentage towards the goal.
     */
    public function getProgressPercentage(): float
    {
        $goal = $this->getActivationGoal();
        if ($goal <= 0) return 100;
        
        $current = $this->getCurrentProgress();
        return min(100, ($current / $goal) * 100);
    }

    /**
     * The Policy Statement for the public.
     */
    public function getPolicyStatement(): string
    {
        return "AI Activation is currently restricted. NGN Sovereign Policy mandates that NIKO and our AI Writing Staff will remain dormant until the Sovereign Fund reaches the operational threshold required to sustain the Sovereign Alliance x10. This ensures human-first value remains the primary signal until we possess the independent resources to scale.";
    }
}
