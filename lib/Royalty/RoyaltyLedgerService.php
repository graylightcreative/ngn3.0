<?php

namespace NGN\Lib\Royalty;

use PDO;
use Exception;

/**
 * Royalty Ledger Service (Bible Ch. 13 & 14)
 *
 * Handles all money flow through NGN platform:
 * - Sparks (Bible Ch. 13.2.2): Fixed value 1 Spark = $0.01 USD
 * - Rights payments (Bible Ch. 14): Revenue distribution
 * - Platform fees (Bible: 90/10 Rule - 10% fee)
 * - Payouts
 * - Refunds
 */
class RoyaltyLedgerService
{
    private PDO $pdo;
    private float $platformFeePercentage;

    public function __construct(PDO $pdo, float $platformFeePercentage = 0.10)
    {
        $this->pdo = $pdo;
        $this->platformFeePercentage = $platformFeePercentage; // Bible: 90/10 Rule
    }

    /**
     * Record a spark transaction (Bible Ch. 13.2.2)
     *
     * Fixed Value: 1 Spark = $0.01 USD
     *
     * @param int $fromUserId User sending spark
     * @param string $entityType Entity being sparked
     * @param int $entityId Entity ID
     * @param int $sparkCount Number of sparks (1 Spark = $0.01 USD)
     * @param int|null $engagementId Link to engagement record
     * @param string $paymentMethod Payment method used
     * @param string|null $paymentReference External payment processor reference
     * @return array Transaction record
     * @throws Exception
     */
    public function recordSpark(
        int $fromUserId,
        string $entityType,
        int $entityId,
        int $sparkCount,
        ?int $engagementId = null,
        string $paymentMethod = 'stripe',
        ?string $paymentReference = null
    ): array {
        // Bible: 1 Spark = $0.01 USD (fixed value)
        $amountGross = round($sparkCount * 0.01, 2);

        // Determine recipient user (entity owner)
        $toUserId = $this->getEntityOwnerId($entityType, $entityId);

        // Bible: 90/10 Rule - 10% platform fee
        $platformFee = round($amountGross * $this->platformFeePercentage, 2);
        $amountNet = $amountGross - $platformFee;

        // Generate unique transaction ID
        $transactionId = $this->generateTransactionId('SPARK');

        // Insert transaction (Bible: cdm_royalty_transactions table)
        $stmt = $this->pdo->prepare("
            INSERT INTO ngn_2025.cdm_royalty_transactions (
                transaction_id, source_type,
                from_user_id, to_user_id,
                source_id, entity_type, entity_id,
                amount_gross, platform_fee, amount_net,
                payment_method, payment_reference, status,
                created_at
            ) VALUES (
                :transaction_id, 'spark_tip',
                :from_user_id, :to_user_id,
                :source_id, :entity_type, :entity_id,
                :amount_gross, :platform_fee, :amount_net,
                :payment_method, :payment_reference, 'pending',
                NOW()
            )
        ");

        $stmt->execute([
            ':transaction_id' => $transactionId,
            ':from_user_id' => $fromUserId,
            ':to_user_id' => $toUserId,
            ':source_id' => $engagementId,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':amount_gross' => $amountGross,
            ':platform_fee' => $platformFee,
            ':amount_net' => $amountNet,
            ':payment_method' => $paymentMethod,
            ':payment_reference' => $paymentReference
        ]);

        $txId = (int)$this->pdo->lastInsertId();

        // ========================================
        // Chapter 24 - SMR Bounty Settlement (Rule 5)
        // ========================================
        if ($_ENV['SMR_ENABLE_BOUNTIES'] ?? true) {
            try {
                // Determine artist ID from entity type
                if ($entityType === 'artist') {
                    $artistId = $entityId;
                } else {
                    // Try to resolve artist from entity (e.g., post -> artist)
                    $artistId = $this->resolveArtistFromEntity($entityType, $entityId);
                }

                // If we have an artist, check for active attribution window
                if ($artistId) {
                    $attributionService = new \NGN\Lib\Smr\AttributionWindowService($this->pdo);

                    if ($attributionService->hasActiveWindow($artistId)) {
                        $bountyService = new \NGN\Lib\Smr\BountySettlementService($this->pdo);

                        // Calculate bounty (with optional geofence check if engagement has venue)
                        $venueId = $engagementId ? $this->getVenueFromEngagement($engagementId) : null;
                        $bountyData = $bountyService->calculateBounty($txId, $artistId, $venueId);

                        if ($bountyData) {
                            // Record bounty transaction
                            $bountyTxId = $bountyService->recordBountyTransaction($bountyData);

                            // Link bounty transaction to royalty transaction
                            $stmt = $this->pdo->prepare("
                                UPDATE ngn_2025.cdm_royalty_transactions
                                SET bounty_eligible = 1, bounty_transaction_id = ?
                                WHERE id = ?
                            ");
                            $stmt->execute([$bountyTxId, $txId]);

                            // Settle immediately (Rule 5: Real-time settlement)
                            $bountyService->settleBounty($bountyTxId);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Log bounty settlement error but don't fail the transaction
                error_log("SMR bounty settlement error: {$e->getMessage()}");
            }
        }
        // ========================================

        // Auto-clear if using platform credit
        if ($paymentMethod === 'platform_credit') {
            $this->clearTransaction($txId);
        }

        return $this->getTransaction($txId);
    }

    /**
     * Record EQS distribution (Bible Ch. 13.2.1)
     *
     * Monthly Creator Pool distribution based on Engagement Quality Score
     *
     * @param int $toUserId User receiving payout
     * @param float $amount Amount to distribute
     * @param array $metadata EQS calculation breakdown
     * @return array Transaction record
     * @throws Exception
     */
    public function recordEQSDistribution(
        int $toUserId,
        float $amount,
        array $metadata = []
    ): array {
        // Bible: Platform-funded, no platform fee on pool distributions
        $platformFee = 0.00;
        $amountNet = $amount;

        $transactionId = $this->generateTransactionId('EQS');

        $stmt = $this->pdo->prepare("
            INSERT INTO ngn_2025.cdm_royalty_transactions (
                from_user_id, to_user_id,
                amount_gross, platform_fee, amount_net,
                payment_method, status,
                metadata, created_at
            ) VALUES (
                :transaction_id, 'eqs_distribution',
                NULL, :to_user_id,
                :amount_gross, :platform_fee, :amount_net,
                'platform_credit', 'cleared',
                :metadata, NOW()
            )
        ");

        $stmt->execute([
            ':transaction_id' => $transactionId,
            ':to_user_id' => $toUserId,
            ':amount_gross' => $amount,
            ':platform_fee' => $platformFee,
            ':amount_net' => $amountNet,
            ':metadata' => json_encode($metadata)
        ]);

        return $this->getTransaction((int)$this->pdo->lastInsertId());
    }

    /**
     * Record a rights payment (Bible Ch. 14 - Revenue distribution)
     *
     * Requires: is_royalty_eligible = TRUE in cdm_rights_ledger
     *
     * @param int $ledgerId Rights ledger ID
     * @param float $amount Total revenue to distribute
     * @param array $metadata Stream count, source platform, etc.
     * @return array Transaction records
     * @throws Exception
     */
    public function recordRightsPayment(
        int $ledgerId,
        float $amount,
        array $metadata = []
    ): array {
        // Get rights ledger and verify eligibility
        $ledger = $this->getRightsLedger($ledgerId);

        if (!$ledger['is_royalty_eligible']) {
            throw new Exception("Rights ledger {$ledgerId} is not royalty-eligible (status: {$ledger['status']})");
        }

        // Get splits
        $splits = $this->getRightsSplits($ledgerId);

        if (empty($splits)) {
            throw new Exception("No rights splits defined for ledger {$ledgerId}");
        }

        // Verify all splits are accepted (Bible: Double-opt-in handshake)
        foreach ($splits as $split) {
            if ($split['accepted_at'] === null) {
                throw new Exception("Split not accepted by user {$split['user_id']} (role: {$split['role']})");
            }
        }

        // Verify splits total 100%
        $totalPercentage = array_sum(array_column($splits, 'percentage'));
        if (abs($totalPercentage - 100.0) > 0.01) {
            throw new Exception("Rights splits must total 100%, got {$totalPercentage}%");
        }

        $transactions = [];
        $this->pdo->beginTransaction();

        try {
            foreach ($splits as $split) {
                $splitAmount = round(($amount * $split['percentage']) / 100, 2);
                $platformFee = round($splitAmount * $this->platformFeePercentage, 2);
                $amountNet = $splitAmount - $platformFee;

                $transactionId = $this->generateTransactionId('RIGHTS');

                $stmt = $this->pdo->prepare("
                    INSERT INTO ngn_2025.cdm_royalty_transactions (
                        transaction_id, source_type,
                        from_user_id, to_user_id,
                        source_id, entity_type, entity_id,
                        amount_gross, platform_fee, amount_net,
                        payment_method, status,
                        rights_split_data, metadata, created_at
                    ) VALUES (
                        :transaction_id, 'rights_payment',
                        NULL, :to_user_id,
                        :source_id, 'track', :entity_id,
                        :amount_gross, :platform_fee, :amount_net,
                        'platform_credit', 'cleared',
                        :rights_split_data, :metadata, NOW()
                    )
                ");

                $stmt->execute([
                    ':transaction_id' => $transactionId,
                    ':to_user_id' => $split['user_id'],
                    ':source_id' => $ledgerId,
                    ':entity_id' => $ledger['track_id'],
                    ':amount_gross' => $splitAmount,
                    ':platform_fee' => $platformFee,
                    ':amount_net' => $amountNet,
                    ':rights_split_data' => json_encode($splits),
                    ':metadata' => json_encode($metadata)
                ]);

                $transactions[] = $this->getTransaction((int)$this->pdo->lastInsertId());
            }

            $this->pdo->commit();

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $transactions;
    }

    /**
     * Clear a pending transaction (Bible Ch. 13.4 - Settlement)
     *
     * Moves from 'pending' to 'cleared', updating balances via triggers
     *
     * @param int $txId Transaction ID
     * @return array Updated transaction
     * @throws Exception
     */
    public function clearTransaction(int $txId): array
    {
        $stmt = $this->pdo->prepare("
            UPDATE ngn_2025.cdm_royalty_transactions
            SET status = 'cleared', cleared_at = NOW()
            WHERE id = :id AND status = 'pending'
        ");

        $stmt->execute([':id' => $txId]);

        if ($stmt->rowCount() === 0) {
            throw new Exception("Transaction {$txId} not found or already cleared");
        }

        return $this->getTransaction($txId);
    }

    /**
     * Refund a transaction
     *
     * @param int $txId Transaction ID
     * @param string $reason Refund reason
     * @return array Updated transaction
     * @throws Exception
     */
    public function refundTransaction(int $txId, string $reason): array
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                UPDATE ngn_2025.cdm_royalty_transactions
                SET status = 'refunded',
                    refunded_at = NOW(),
                    notes = CONCAT(COALESCE(notes, ''), '\nRefund: ', :reason)
                WHERE id = :id AND status = 'cleared'
            ");

            $stmt->execute([
                ':id' => $txId,
                ':reason' => $reason
            ]);

            if ($stmt->rowCount() === 0) {
                throw new Exception("Transaction {$txId} not found or cannot be refunded");
            }

            $this->pdo->commit();

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $this->getTransaction($txId);
    }

    /**
     * Get user's wallet balance (Bible Ch. 13.6 - cdm_royalty_balances)
     *
     * @param int $userId User ID
     * @return array Balance data
     */
    public function getBalance(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM ngn_2025.cdm_royalty_balances
            WHERE user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([':user_id' => $userId]);
        $balance = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$balance) {
            // Create balance record if doesn't exist
            $this->pdo->prepare("
                INSERT INTO ngn_2025.cdm_royalty_balances (
                    user_id, pending_balance, available_balance,
                    lifetime_earnings, lifetime_spent
                )
                VALUES (:user_id, 0.00, 0.00, 0.00, 0.00)
            ")->execute([':user_id' => $userId]);

            return $this->getBalance($userId);
        }

        return [
            'user_id' => (int)$balance['user_id'],
            'pending_balance' => (float)$balance['pending_balance'],
            'available_balance' => (float)$balance['available_balance'],
            'lifetime_earnings' => (float)$balance['lifetime_earnings'],
            'lifetime_spent' => (float)$balance['lifetime_spent'],
            'payout_method' => $balance['payout_method'],
            'payout_threshold' => (float)$balance['payout_threshold'],
            'auto_payout_enabled' => (bool)$balance['auto_payout_enabled'],
            'stripe_account_id' => $balance['stripe_account_id'],
            'stripe_identity_verified' => (bool)$balance['stripe_identity_verified'],
            'last_payout_at' => $balance['last_payout_at']
        ];
    }

    /**
     * Request a payout (Bible Ch. 13.4 - Stripe Connect settlement)
     *
     * @param int $userId User requesting payout
     * @param float $amount Amount to withdraw
     * @param string $payoutMethod Method (stripe, paypal, crypto)
     * @return array Payout request record
     * @throws Exception
     */
    public function requestPayout(int $userId, float $amount, string $payoutMethod): array
    {
        $balance = $this->getBalance($userId);

        // Validate Stripe Identity (Bible Ch. 13.7 - KYC requirement)
        if (!$balance['stripe_identity_verified'] && $payoutMethod === 'stripe') {
            throw new Exception("Stripe Identity verification required before first payout");
        }

        // Validate balance
        if ($balance['available_balance'] < $amount) {
            throw new Exception("Insufficient balance. Available: \${$balance['available_balance']}");
        }

        // Validate threshold
        if ($amount < $balance['payout_threshold']) {
            throw new Exception("Amount below minimum payout threshold: \${$balance['payout_threshold']}");
        }

        // Generate request ID
        $requestId = $this->generateTransactionId('PAYOUT');

        $this->pdo->beginTransaction();

        try {
            // Create payout request
            $stmt = $this->pdo->prepare("
                INSERT INTO ngn_2025.cdm_payout_requests (
                    request_id, user_id, amount, payout_method, status, requested_at
                ) VALUES (
                    :request_id, :user_id, :amount, :payout_method, 'pending', NOW()
                )
            ");

            $stmt->execute([
                ':request_id' => $requestId,
                ':user_id' => $userId,
                ':amount' => $amount,
                ':payout_method' => $payoutMethod
            ]);

            // Move from available to pending (will be deducted on payout completion)
            $this->pdo->prepare("
                UPDATE ngn_2025.cdm_royalty_balances
                SET available_balance = available_balance - :amount,
                    updated_at = NOW()
                WHERE user_id = :user_id
            ")->execute([
                ':amount' => $amount,
                ':user_id' => $userId
            ]);

            $this->pdo->commit();

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $this->getPayoutRequest((int)$this->pdo->lastInsertId());
    }

    /**
     * Get transaction by ID
     *
     * @param int $txId Transaction ID
     * @return array Transaction record
     * @throws Exception
     */
    public function getTransaction(int $txId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ngn_2025.cdm_royalty_transactions WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $txId]);
        $tx = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tx) {
            throw new Exception("Transaction {$txId} not found");
        }

        return [
            'id' => (int)$tx['id'],
            'transaction_id' => $tx['transaction_id'],
            'source_type' => $tx['source_type'],
            'from_user_id' => $tx['from_user_id'] ? (int)$tx['from_user_id'] : null,
            'to_user_id' => $tx['to_user_id'] ? (int)$tx['to_user_id'] : null,
            'source_id' => $tx['source_id'] ? (int)$tx['source_id'] : null,
            'entity_type' => $tx['entity_type'],
            'entity_id' => $tx['entity_id'] ? (int)$tx['entity_id'] : null,
            'amount_gross' => (float)$tx['amount_gross'],
            'platform_fee' => (float)$tx['platform_fee'],
            'amount_net' => (float)$tx['amount_net'],
            'payment_method' => $tx['payment_method'],
            'payment_reference' => $tx['payment_reference'],
            'status' => $tx['status'],
            'rights_split_data' => $tx['rights_split_data'] ? json_decode($tx['rights_split_data'], true) : null,
            'metadata' => $tx['metadata'] ? json_decode($tx['metadata'], true) : null,
            'notes' => $tx['notes'],
            'created_at' => $tx['created_at'],
            'cleared_at' => $tx['cleared_at'],
            'refunded_at' => $tx['refunded_at']
        ];
    }

    /**
     * Get user's transaction history
     *
     * @param int $userId User ID
     * @param int $limit Number of records to return
     * @param int $offset Pagination offset
     * @return array Transactions
     */
    public function getUserTransactions(int $userId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM ngn_2025.cdm_royalty_transactions
            WHERE from_user_id = :user_id OR to_user_id = :user_id
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset
        ");

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $transactions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $transactions[] = [
                'id' => (int)$row['id'],
                'transaction_id' => $row['transaction_id'],
                'source_type' => $row['source_type'],
                'from_user_id' => $row['from_user_id'] ? (int)$row['from_user_id'] : null,
                'to_user_id' => $row['to_user_id'] ? (int)$row['to_user_id'] : null,
                'entity_type' => $row['entity_type'],
                'entity_id' => $row['entity_id'] ? (int)$row['entity_id'] : null,
                'amount_gross' => (float)$row['amount_gross'],
                'amount_net' => (float)$row['amount_net'],
                'status' => $row['status'],
                'created_at' => $row['created_at']
            ];
        }

        return $transactions;
    }

    /**
     * Get rights ledger by ID (Bible Ch. 14)
     *
     * @param int $ledgerId Ledger ID
     * @return array Ledger record
     * @throws Exception
     */
    private function getRightsLedger(int $ledgerId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ngn_2025.cdm_rights_ledger WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $ledgerId]);
        $ledger = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ledger) {
            throw new Exception("Rights ledger {$ledgerId} not found");
        }

        return [
            'id' => (int)$ledger['id'],
            'track_id' => $ledger['track_id'] ? (int)$ledger['track_id'] : null,
            'release_id' => $ledger['release_id'] ? (int)$ledger['release_id'] : null,
            'isrc' => $ledger['isrc'],
            'iswc' => $ledger['iswc'],
            'status' => $ledger['status'],
            'is_royalty_eligible' => (bool)$ledger['is_royalty_eligible'],
            'title' => $ledger['title'],
            'artist_name' => $ledger['artist_name']
        ];
    }

    /**
     * Get rights splits for a ledger (Bible Ch. 14.5)
     *
     * @param int $ledgerId Ledger ID
     * @return array Rights splits
     */
    private function getRightsSplits(int $ledgerId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM ngn_2025.cdm_rights_splits
            WHERE ledger_id = :ledger_id
            ORDER BY percentage DESC
        ");
        $stmt->execute([':ledger_id' => $ledgerId]);

        $splits = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $splits[] = [
                'id' => (int)$row['id'],
                'user_id' => (int)$row['user_id'],
                'role' => $row['role'],
                'percentage' => (float)$row['percentage'],
                'accepted_at' => $row['accepted_at']
            ];
        }

        return $splits;
    }

    /**
     * Get payout request by ID
     */
    private function getPayoutRequest(int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ngn_2025.cdm_payout_requests WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $payout = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'id' => (int)$payout['id'],
            'request_id' => $payout['request_id'],
            'user_id' => (int)$payout['user_id'],
            'amount' => (float)$payout['amount'],
            'payout_method' => $payout['payout_method'],
            'status' => $payout['status'],
            'requested_at' => $payout['requested_at']
        ];
    }

    /**
     * Generate unique transaction ID
     */
    private function generateTransactionId(string $prefix): string
    {
        $date = date('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Get entity owner's user ID
     */
    private function getEntityOwnerId(string $entityType, int $entityId): ?int
    {
        $tables = [
            'artist' => 'ngn_2025.cdm_artists',
            'label' => 'ngn_2025.cdm_labels',
            'venue' => 'ngn_2025.cdm_venues',
            'station' => 'ngn_2025.cdm_stations'
        ];

        if (!isset($tables[$entityType])) {
            return null;
        }

        $table = $tables[$entityType];
        $stmt = $this->pdo->prepare("SELECT user_id FROM {$table} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $entityId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (int)$result['user_id'] : null;
    }

    /**
     * Chapter 24 Helper: Resolve artist ID from various entity types
     */
    private function resolveArtistFromEntity(string $entityType, int $entityId): ?int
    {
        try {
            switch ($entityType) {
                case 'post':
                    $stmt = $this->pdo->prepare("SELECT artist_id FROM ngn_2025.cdm_posts WHERE id = ? LIMIT 1");
                    break;
                case 'video':
                    $stmt = $this->pdo->prepare("SELECT artist_id FROM ngn_2025.cdm_videos WHERE id = ? LIMIT 1");
                    break;
                case 'release':
                    $stmt = $this->pdo->prepare("SELECT artist_id FROM ngn_2025.cdm_releases WHERE id = ? LIMIT 1");
                    break;
                case 'song':
                    $stmt = $this->pdo->prepare("SELECT artist_id FROM ngn_2025.cdm_songs WHERE id = ? LIMIT 1");
                    break;
                default:
                    return null;
            }

            $stmt->execute([$entityId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)$result['artist_id'] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Chapter 24 Helper: Get venue ID from engagement record
     */
    private function getVenueFromEngagement(?int $engagementId): ?int
    {
        if (!$engagementId) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT venue_id FROM ngn_2025.cdm_engagements WHERE id = ? LIMIT 1");
            $stmt->execute([$engagementId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result && $result['venue_id'] ? (int)$result['venue_id'] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
