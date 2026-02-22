<?php
namespace NGN\Lib\Services\Royalties;

/**
 * NGN Payout Engine (NGN 3.0 Edition)
 * Orchestrates automated royalty distribution, Foundry Fixed-Rates, and Board Rakes.
 * Bible Ref: Rule 5 (Automated 75/25 Split of 10% Platform Fee)
 * Foundry Merger: Wholesale Deduction + 10% Board Rake.
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Commerce\StripeConnectService;
use NGN\Lib\Commerce\CommissionService;
use PDO;

class PayoutEngine
{
    private $config;
    private $pdo;
    private $connect;
    private $commissions;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdo = ConnectionFactory::read($config);
        $this->connect = new StripeConnectService($config);
        $this->commissions = new CommissionService($config);
    }

    /**
     * Process Foundry Merch Settlement
     * sequence: Gross -> Manufacturing Cost -> 10% Board Rake -> Creator Profit
     */
    public function processFoundrySettlement(int $orderId): array
    {
        // 1. Fetch Order Items with Foundry metadata
        $stmt = $this->pdo->prepare("
            SELECT oi.*, p.cost_cents, p.price_cents, p.fulfillment_source
            FROM `ngn_2025`.`order_items` oi
            JOIN `ngn_2025`.`products` p ON oi.product_id = p.id
            WHERE oi.order_id = ? AND p.fulfillment_source = 'foundry'
        ");
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($items as $item) {
            $gross = $item['total'] * 100; // Work in cents
            $wholesale = $item['cost_cents'] * $item['quantity'];
            
            $remainingProfit = $gross - $wholesale;
            
            // 2. Platform Split (Rule 5)
            $rule5 = $this->commissions->settleRule5($orderId, $gross);
            
            $boardRake = $remainingProfit * 0.10;
            $creatorShare = $remainingProfit - $boardRake - ($rule5['ops_cents'] + $rule5['data_cents']);

            // 3. Execute Payouts
            // Pay Wholesale to Kieran's Business (Future: Dedicated Stripe Account)
            // Pay Board Rake to Settlement Pool
            // Pay Creator Profit to their Stripe Connect account

            $results[] = [
                'item_id' => $item['id'],
                'gross_cents' => $gross,
                'wholesale_cents' => $wholesale,
                'board_rake_cents' => $boardRake,
                'creator_share_cents' => $creatorShare
            ];
        }

        return [
            'status' => 'success',
            'order_id' => $orderId,
            'settlements' => $results
        ];
    }

    /**
     * Legacy: Process a payout for a specific artist (Digital/Royalties)
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
