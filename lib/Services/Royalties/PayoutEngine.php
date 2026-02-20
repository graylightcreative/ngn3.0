<?php
namespace NGN\Lib\Services\Royalties;

/**
 * NGN Payout Engine (BFL 2.4)
 * Orchestrates automated royalty distribution and Rule 5 split execution.
 * Bible Ref: Rule 5 (Automated 75/25 Split of 10% Platform Fee)
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Commerce\StripeConnectService;
use PDO;

class PayoutEngine
{
    private $config;
    private $pdo;
    private $connect;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdo = ConnectionFactory::read($config);
        $this->connect = new StripeConnectService($config);
    }

    /**
     * Process a payout for a specific artist
     */
    public function processArtistPayout(int $artistId, float $amountTotal): array
    {
        // 1. Calculate Rule 5 Splits
        $platformFee = $amountTotal * 0.10;
        $artistShare = $amountTotal - $platformFee;

        // Platform Fee Sub-split (Bible Ch. 43)
        $ngnOpsShare = $platformFee * 0.75;
        $dataProviderShare = $platformFee * 0.25;

        // 2. Fetch Stripe Account for Artist
        $stmt = $this->pdo->prepare("SELECT user_id FROM artists WHERE id = ?");
        $stmt->execute([$artistId]);
        $userId = $stmt->fetchColumn();

        if (!$userId) throw new \Exception("User ID not found for Artist {$artistId}");

        $stmt = $this->pdo->prepare("SELECT stripe_account_id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $stripeAccountId = $stmt->fetchColumn();

        if (!$stripeAccountId) {
            return ['status' => 'error', 'message' => 'Artist not onboarded to Stripe Connect'];
        }

        // 3. Execute Transfer
        $transferId = $this->connect->transfer(
            $stripeAccountId, 
            (int)round($artistShare * 100),
            "NGN Royalty Payout - Artist {$artistId}"
        );

        // 4. Log Settlement (SIR Audit Compliance)
        $this->logSettlement($artistId, $amountTotal, $artistShare, $platformFee, $transferId);

        return [
            'status' => 'success',
            'transfer_id' => $transferId,
            'artist_share' => $artistShare,
            'platform_fee' => $platformFee,
            'rule_5_ops' => $ngnOpsShare,
            'rule_5_data' => $dataProviderShare
        ];
    }

    private function logSettlement(int $artistId, float $gross, float $net, float $fee, string $transferId): void
    {
        $write = ConnectionFactory::write($this->config);
        $stmt = $write->prepare("
            INSERT INTO cdm_payout_settlements (
                artist_id, amount_gross, amount_net, amount_fee, 
                stripe_transfer_id, status, created_at
            ) VALUES (?, ?, ?, ?, ?, 'completed', NOW())
        ");
        $stmt->execute([$artistId, $gross, $net, $fee, $transferId]);
    }
}
