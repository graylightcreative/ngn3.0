<?php

namespace NGN\Lib\Services;

use PDO;
use Exception;

/**
 * RoyaltyService - Handle royalty calculations and payouts
 *
 * Implements royalty workflows from Bible Ch. 13:
 * - EQS (Engagement Quality Score) calculations
 * - Transaction ledger tracking
 * - Payout request processing (Stripe Connect)
 * - Balance management
 */
class RoyaltyService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get pending payout requests
     */
    public function getPendingPayouts(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, u.email, u.display_name
            FROM cdm_payout_requests p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.status = 'pending'
            ORDER BY p.requested_at ASC
        ");

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new payout request
     */
    public function createPayout(int $userId, float $amount): int
    {
        // Check balance first
        $balance = $this->getBalance($userId);
        if ($balance['available_balance'] < $amount) {
            throw new Exception("Insufficient balance for payout (Available: {$balance['available_balance']})");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO cdm_payout_requests (
                user_id, request_id, amount, status, requested_at
            ) VALUES (?, ?, ?, 'pending', NOW())
        ");

        $requestId = 'req_' . uniqid();
        $stmt->execute([$userId, $requestId, $amount]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Process a payout request (Stripe integration stub)
     */
    public function processPayoutRequest(int $payoutId): array
    {
        try {
            $this->pdo->beginTransaction();

            // Get payout details
            $stmt = $this->pdo->prepare("
                SELECT p.*, u.stripe_account_id
                FROM cdm_payout_requests p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE p.id = ? AND p.status = 'pending'
                FOR UPDATE
            ");
            $stmt->execute([$payoutId]);
            $payout = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payout) {
                throw new Exception("Payout request not found or not pending");
            }

            if (empty($payout['stripe_account_id'])) {
                throw new Exception("User has no Stripe account connected");
            }

            // Mark as processing
            $stmt = $this->pdo->prepare("
                UPDATE cdm_payout_requests
                SET status = 'processing'
                WHERE id = ?
            ");
            $stmt->execute([$payoutId]);

            // TODO: Call Stripe API here
            $stripeTransferId = 'tr_simulated_' . uniqid();

            // Update with success
            $stmt = $this->pdo->prepare("
                UPDATE cdm_payout_requests
                SET status = 'completed', processor_reference = ?, completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$stripeTransferId, $payoutId]);

            // Record transaction in ledger (negative amount for payout)
            $this->addTransaction(
                userId: $payout['user_id'],
                amount: -$payout['amount'],
                type: 'payout',
                periodStart: null,
                periodEnd: null,
                ingestionId: null
            );

            $this->pdo->commit();

            return [
                'success' => true,
                'payout_id' => $payoutId,
                'stripe_transfer_id' => $stripeTransferId
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            
            // Mark as failed
            $stmt = $this->pdo->prepare("
                UPDATE cdm_payout_requests
                SET status = 'failed'
                WHERE id = ?
            ");
            $stmt->execute([$payoutId]);
            
            throw $e;
        }
    }

    /**
     * Get user balance
     */
    public function getBalance(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT SUM(amount_net) as current_balance
            FROM cdm_royalty_transactions
            WHERE to_user_id = ? AND status = 'cleared'
        ");

        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $currentBalance = (float)($result['current_balance'] ?? 0);

        // Get pending payouts
        $payoutStmt = $this->pdo->prepare("
            SELECT SUM(amount) as pending_amount
            FROM cdm_payout_requests
            WHERE user_id = ? AND status IN ('pending', 'approved', 'processing')
        ");
        $payoutStmt->execute([$userId]);
        $payoutResult = $payoutStmt->fetch(PDO::FETCH_ASSOC);

        $pendingAmount = (float)($payoutResult['pending_amount'] ?? 0);

        return [
            'user_id' => $userId,
            'current_balance' => $currentBalance,
            'pending_payout' => $pendingAmount,
            'available_balance' => $currentBalance - $pendingAmount
        ];
    }

    /**
     * Get transactions for a user
     */
    public function getTransactions(int $userId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM cdm_royalty_transactions
            WHERE to_user_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");

        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add a transaction to the royalty ledger
     */
    public function addTransaction(
        int $userId,
        float $amount,
        string $type = 'eqs_distribution',
        ?string $periodStart = null,
        ?string $periodEnd = null,
        ?int $ingestionId = null
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO cdm_royalty_transactions (
                transaction_id, to_user_id, source_type, amount_gross, amount_net, status, created_at, source_id
            ) VALUES (?, ?, ?, ?, ?, 'cleared', NOW(), ?)
        ");

        $txId = 'tx_' . uniqid();
        $stmt->execute([
            $txId,
            $userId,
            $type,
            $amount,
            $amount,
            $ingestionId
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Calculate EQS for a period (Stub - see Bible Ch. 13 for full formula)
     */
    public function calculateEQS(string $periodStart, string $periodEnd): array
    {
        // In a real implementation, this would:
        // 1. Sum all engagements (likes, shares, plays) for the period
        // 2. Apply quality weights (Ch. 13)
        // 3. Divide by total pool
        // 4. Multiply by revenue share
        
        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'total_pool' => 10000.00,
            'status' => 'simulated'
        ];
    }
}
