<?php

namespace NGN\Lib\Smr;

use PDO;
use Exception;

/**
 * Bounty Settlement Service (Chapter 24 - Rule 5)
 *
 * CRITICAL: Calculates and settles bounties in real-time.
 * Splits platform fee 75% NGN Operations, 25% Erik Baker.
 * Called immediately after Spark transaction.
 */
class BountySettlementService
{
    private PDO $pdo;
    private float $bountyPercentage;
    private float $ngn_percentage;
    private float $provider_percentage;
    private AttributionWindowService $attributionService;
    private GeofenceMatchingService $geofenceService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->bountyPercentage = (float)($_ENV['SMR_BOUNTY_PERCENTAGE'] ?? 25.00);
        // Platform fee split: 75% NGN, 25% Provider
        $this->ngn_percentage = 75.00;
        $this->provider_percentage = 25.00;

        $this->attributionService = new AttributionWindowService($pdo);
        $this->geofenceService = new GeofenceMatchingService($pdo);
    }

    /**
     * Calculate bounty for a Spark transaction
     * Returns null if no active attribution window
     *
     * @param int $royaltyTransactionId ID from cdm_royalty_transactions
     * @param int $artistId Artist ID
     * @param int|null $venueId Optional venue ID for geofence bonus
     * @return array|null Bounty calculation data, null if not eligible
     * @throws Exception
     */
    public function calculateBounty(int $royaltyTransactionId, int $artistId, ?int $venueId = null): ?array
    {
        // Check if bounties enabled
        if (!($_ENV['SMR_ENABLE_BOUNTIES'] ?? true)) {
            return null;
        }

        // Get royalty transaction
        $stmt = $this->pdo->prepare("SELECT * FROM cdm_royalty_transactions WHERE id = ?");
        $stmt->execute([$royaltyTransactionId]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction) {
            return null;
        }

        // Check for active attribution window
        $window = $this->attributionService->getActiveWindow($artistId);
        if (!$window) {
            return null;
        }

        // Platform fee from transaction
        $platformFee = (float)$transaction['platform_fee'];

        // Calculate bounty split (Rule 5: 75/25 split)
        $split = $this->splitPlatformFee($platformFee);

        // Check geofence match if venue provided (Rule 4)
        $geofenceBonus = 0.00;
        $geofenceMatched = false;
        $matchedZip = null;

        if ($venueId) {
            $geofenceCheck = $this->geofenceService->checkGeofenceMatch(
                $artistId,
                $venueId,
                (int)$window['heat_spike_id']
            );

            if ($geofenceCheck['matched']) {
                // Add 2% bonus to bounty amount
                $bonusAmount = $split['bounty'] * ($geofenceCheck['bonus_percentage'] / 100.0);
                $geofenceBonus = $bonusAmount;
                $geofenceMatched = true;
                $matchedZip = $geofenceCheck['matched_zip'];
            }
        }

        // Get provider contract for user ID
        $provider = $this->getProviderContract();

        return [
            'royalty_transaction_id' => $royaltyTransactionId,
            'attribution_window_id' => (int)$window['id'],
            'heat_spike_id' => (int)$window['heat_spike_id'],
            'artist_id' => $artistId,
            'platform_fee_gross' => $platformFee,
            'bounty_percentage' => $this->bountyPercentage,
            'bounty_amount' => $split['bounty'] + $geofenceBonus,
            'ngn_operations_amount' => $split['ngn'],
            'geofence_matched' => $geofenceMatched,
            'geofence_bonus_percentage' => $geofenceMatched ? $this->geofenceService->calculateGeofenceBonus(true) : 0.00,
            'venue_id' => $venueId,
            'matched_zip_code' => $matchedZip,
            'provider_user_id' => $provider['provider_user_id'],
        ];
    }

    /**
     * Split platform fee between NGN and Provider
     * Rule 5: 75% NGN Operations, 25% Erik Baker
     *
     * @param float $platformFee Total platform fee
     * @return array ['bounty' => float, 'ngn' => float]
     */
    private function splitPlatformFee(float $platformFee, float $bountyPercentage = 25.00): array
    {
        $bountyAmount = round($platformFee * ($this->provider_percentage / 100.0), 2);
        $ngnAmount = round($platformFee * ($this->ngn_percentage / 100.0), 2);

        return [
            'bounty' => $bountyAmount,
            'ngn' => $ngnAmount,
        ];
    }

    /**
     * Record bounty transaction
     *
     * @param array $bountyData Bounty calculation data
     * @return int Bounty transaction ID
     * @throws Exception
     */
    public function recordBountyTransaction(array $bountyData): int
    {
        try {
            // Generate unique bounty transaction ID
            $bountyTxId = $this->generateBountyTransactionId();

            $stmt = $this->pdo->prepare("
                INSERT INTO smr_bounty_transactions (
                    transaction_id,
                    royalty_transaction_id, attribution_window_id, heat_spike_id,
                    artist_id,
                    platform_fee_gross, bounty_percentage,
                    bounty_amount, ngn_operations_amount,
                    geofence_matched, geofence_bonus_percentage,
                    venue_id, matched_zip_code,
                    provider_user_id, status,
                    metadata, created_at
                ) VALUES (
                    :transaction_id,
                    :royalty_transaction_id, :attribution_window_id, :heat_spike_id,
                    :artist_id,
                    :platform_fee_gross, :bounty_percentage,
                    :bounty_amount, :ngn_operations_amount,
                    :geofence_matched, :geofence_bonus_percentage,
                    :venue_id, :matched_zip_code,
                    :provider_user_id, 'pending',
                    :metadata, NOW()
                )
            ");

            $stmt->execute([
                ':transaction_id' => $bountyTxId,
                ':royalty_transaction_id' => $bountyData['royalty_transaction_id'],
                ':attribution_window_id' => $bountyData['attribution_window_id'],
                ':heat_spike_id' => $bountyData['heat_spike_id'],
                ':artist_id' => $bountyData['artist_id'],
                ':platform_fee_gross' => $bountyData['platform_fee_gross'],
                ':bounty_percentage' => $bountyData['bounty_percentage'],
                ':bounty_amount' => $bountyData['bounty_amount'],
                ':ngn_operations_amount' => $bountyData['ngn_operations_amount'],
                ':geofence_matched' => $bountyData['geofence_matched'] ? 1 : 0,
                ':geofence_bonus_percentage' => $bountyData['geofence_bonus_percentage'],
                ':venue_id' => $bountyData['venue_id'],
                ':matched_zip_code' => $bountyData['matched_zip_code'],
                ':provider_user_id' => $bountyData['provider_user_id'],
                ':metadata' => json_encode([
                    'calculation_details' => 'Bounty calculated from platform fee split',
                    'split_ratio' => "NGN {$this->ngn_percentage}%, Provider {$this->provider_percentage}%",
                ]),
            ]);

            return (int)$this->pdo->lastInsertId();
        } catch (\Throwable $e) {
            throw new Exception("Error recording bounty transaction: {$e->getMessage()}");
        }
    }

    /**
     * Settle bounty to provider balance (real-time, Rule 5)
     *
     * @param int $bountyTransactionId Bounty transaction ID
     * @throws Exception
     */
    public function settleBounty(int $bountyTransactionId): void
    {
        try {
            // Get bounty transaction
            $stmt = $this->pdo->prepare("SELECT * FROM smr_bounty_transactions WHERE id = ?");
            $stmt->execute([$bountyTransactionId]);
            $bounty = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$bounty) {
                throw new Exception("Bounty transaction {$bountyTransactionId} not found");
            }

            // Update bounty status to settled
            $stmt = $this->pdo->prepare("
                UPDATE smr_bounty_transactions
                SET status = 'settled', settled_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$bountyTransactionId]);

            // Update provider balance (Erik Baker)
            $this->updateProviderBalance(
                (int)$bounty['provider_user_id'],
                (float)$bounty['bounty_amount']
            );

            // Update NGN operations balance
            $this->updateNGNOperationsBalance((float)$bounty['ngn_operations_amount']);

            // Record bounty triggered in attribution window
            $this->attributionService->recordBountyTriggered(
                (int)$bounty['attribution_window_id'],
                (float)$bounty['bounty_amount']
            );
        } catch (\Throwable $e) {
            throw new Exception("Error settling bounty: {$e->getMessage()}");
        }
    }

    /**
     * Update provider balance (Erik Baker's bounty earnings)
     *
     * @param int $providerUserId Provider user ID
     * @param float $amount Amount to add
     * @throws Exception
     */
    private function updateProviderBalance(int $providerUserId, float $amount): void
    {
        try {
            // Upsert provider balance
            $stmt = $this->pdo->prepare("
                INSERT INTO cdm_royalty_balances (user_id, amount, currency, created_at, updated_at)
                VALUES (:user_id, :amount, 'USD', NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    amount = amount + VALUES(amount),
                    updated_at = NOW()
            ");
            $stmt->execute([
                ':user_id' => $providerUserId,
                ':amount' => $amount,
            ]);
        } catch (\Throwable $e) {
            throw new Exception("Error updating provider balance: {$e->getMessage()}");
        }
    }

    /**
     * Update NGN operations balance
     *
     * @param float $amount Amount to add
     * @throws Exception
     */
    private function updateNGNOperationsBalance(float $amount): void
    {
        try {
            // NGN Operations user (typically user_id = 0 or special account)
            $ngnUserId = $_ENV['NGN_OPERATIONS_USER_ID'] ?? 0;

            $stmt = $this->pdo->prepare("
                INSERT INTO cdm_royalty_balances (user_id, amount, currency, created_at, updated_at)
                VALUES (:user_id, :amount, 'USD', NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    amount = amount + VALUES(amount),
                    updated_at = NOW()
            ");
            $stmt->execute([
                ':user_id' => $ngnUserId,
                ':amount' => $amount,
            ]);
        } catch (\Throwable $e) {
            throw new Exception("Error updating NGN balance: {$e->getMessage()}");
        }
    }

    /**
     * Get provider contract terms (Rule 6)
     *
     * @return array Provider contract data
     */
    private function getProviderContract(): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM smr_provider_contracts
                WHERE provider_status IN ('preferred', 'active')
                ORDER BY preferred_data_partner DESC
                LIMIT 1
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                // Fallback to default provider (Erik Baker)
                return [
                    'provider_user_id' => (int)($_ENV['SMR_PROVIDER_USER_ID'] ?? 1),
                    'provider_name' => 'Erik Baker',
                    'bounty_percentage' => 25.00,
                    'preferred_data_partner' => true,
                ];
            }

            return $result;
        } catch (\Throwable $e) {
            return [
                'provider_user_id' => (int)($_ENV['SMR_PROVIDER_USER_ID'] ?? 1),
                'provider_name' => 'Erik Baker',
                'bounty_percentage' => 25.00,
                'preferred_data_partner' => true,
            ];
        }
    }

    /**
     * Generate unique bounty transaction ID
     *
     * @return string Format: BOUNTY-20260123-ABC123
     */
    private function generateBountyTransactionId(): string
    {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        return "BOUNTY-{$date}-{$random}";
    }

    /**
     * Get bounty transaction details
     *
     * @param int $bountyTransactionId Bounty transaction ID
     * @return array|null Bounty transaction data
     */
    public function getBountyTransaction(int $bountyTransactionId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM smr_bounty_transactions WHERE id = ?");
            $stmt->execute([$bountyTransactionId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
