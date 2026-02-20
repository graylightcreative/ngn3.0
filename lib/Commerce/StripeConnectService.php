<?php
namespace NGN\Lib\Commerce;

/**
 * Stripe Connect Service
 * Handles artist/label account onboarding and automated payouts.
 * Bible Ref: Rule 5 Split & BFL 2.4
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Env;
use PDO;

class StripeConnectService
{
    private $config;
    private $secretKey;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->secretKey = (string)Env::get('STRIPE_SECRET_KEY', '');
        
        if ($this->secretKey && class_exists('\Stripe\Stripe')) {
            \Stripe\Stripe::setApiKey($this->secretKey);
        }
    }

    /**
     * Create an onboarding link for an artist/label
     */
    public function createOnboardingLink(int $userId, string $returnUrl): string
    {
        $accountId = $this->getOrCreateConnectedAccount($userId);
        
        $link = \Stripe\AccountLink::create([
            'account' => $accountId,
            'refresh_url' => $returnUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);

        return $link->url;
    }

    /**
     * Get or create a Stripe Express account for a user
     */
    private function getOrCreateConnectedAccount(int $userId): string
    {
        // 1. Check local DB for existing account ID
        $pdo = ConnectionFactory::read($this->config);
        $stmt = $pdo->prepare("SELECT stripe_account_id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $accountId = $stmt->fetchColumn();

        if ($accountId) return $accountId;

        // 2. Create new Express account
        $account = \Stripe\Account::create([
            'type' => 'express',
            'metadata' => ['user_id' => $userId]
        ]);

        // 3. Save to DB
        $write = ConnectionFactory::write($this->config);
        $write->prepare("UPDATE users SET stripe_account_id = ? WHERE id = ?")->execute([$account->id, $userId]);

        return $account->id;
    }

    /**
     * Execute a transfer to a connected account
     */
    public function transfer(string $accountId, int $amountCents, string $description, array $metadata = []): string
    {
        $transfer = \Stripe\Transfer::create([
            'amount' => $amountCents,
            'currency' => 'usd',
            'destination' => $accountId,
            'description' => $description,
            'metadata' => $metadata
        ]);

        return $transfer->id;
    }
}
