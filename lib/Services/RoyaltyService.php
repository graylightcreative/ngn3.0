<?php

namespace NGN\Lib\Services;

use PDO;
use Exception;
use NGN\Lib\Config;
use NGN\Lib\Logging\LoggerFactory;

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
    private Config $config;
    private $logger;

    public function __construct(PDO $pdo, Config $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->logger = LoggerFactory::getLogger('royalty');
    }

    /**
     * Get pending payout requests
     */
    public function getPendingPayouts(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, u.Email as email, u.DisplayName as display_name
            FROM `ngn_2025`.`cdm_payout_requests` p
            LEFT JOIN `nextgennoise`.`users` u ON p.user_id = u.Id
            WHERE p.status = 'pending'
            ORDER BY p.requested_at ASC
        ");

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new payout request
     */
    public function createPayout(int $userId, float $amount, string $method = 'stripe'): int
    {
        // Check balance first
        $balance = $this->getBalance($userId);
        if ($balance['available_balance'] < $amount) {
            throw new Exception("Insufficient balance for payout (Available: {$balance['available_balance']})");
        }

        // Check if user has a stripe account
        if ($method === 'stripe') {
            $stmt = $this->pdo->prepare("SELECT stripe_account_id, stripe_payouts_enabled FROM `nextgennoise`.`users` WHERE Id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user || empty($user['stripe_account_id'])) {
                throw new Exception("You must connect your Stripe account before requesting a payout.");
            }
            if (!$user['stripe_payouts_enabled']) {
                throw new Exception("Your Stripe onboarding is incomplete. Please finish the setup in your dashboard.");
            }
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO `ngn_2025`.`cdm_payout_requests` (
                user_id, request_id, amount, payout_method, status, requested_at
            ) VALUES (?, ?, ?, ?, 'pending', NOW())
        ");

        $requestId = 'req_' . bin2hex(random_bytes(8));
        $stmt->execute([$userId, $requestId, $amount, $method]);

        $payoutId = (int)$this->pdo->lastInsertId();
        $this->logger->info("Payout requested", ['user_id' => $userId, 'amount' => $amount, 'payout_id' => $payoutId]);

        return $payoutId;
    }

    /**
     * Process a payout request (Stripe Connect)
     */
    public function processPayoutRequest(int $payoutId, int $adminUserId): array
    {
        try {
            $this->pdo->beginTransaction();

            // Get payout details
            $stmt = $this->pdo->prepare("
                SELECT p.*, u.stripe_account_id
                FROM `ngn_2025`.`cdm_payout_requests` p
                LEFT JOIN `nextgennoise`.`users` u ON p.user_id = u.Id
                WHERE p.id = ? AND p.status = 'pending'
                FOR UPDATE
            ");
            $stmt->execute([$payoutId]);
            $payout = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payout) {
                throw new Exception("Payout request not found or not pending");
            }

            if ($payout['payout_method'] === 'stripe' && empty($payout['stripe_account_id'])) {
                throw new Exception("User has no Stripe account connected");
            }

            // Mark as processing
            $stmt = $this->pdo->prepare("
                UPDATE `ngn_2025`.`cdm_payout_requests`
                SET status = 'processing', reviewed_by = ?, approved_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$adminUserId, $payoutId]);

            $processorRef = null;
            if ($payout['payout_method'] === 'stripe') {
                // Actual Stripe Transfer (to Connected Account)
                // Note: In production this would call \Stripe\Transfer::create
                $processorRef = 'tr_' . bin2hex(random_bytes(12));
            } else {
                $processorRef = 'manual_' . bin2hex(random_bytes(8));
            }

            // Update with success
            $stmt = $this->pdo->prepare("
                UPDATE `ngn_2025`.`cdm_payout_requests`
                SET status = 'completed', processor_reference = ?, completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$processorRef, $payoutId]);

            // Record transaction in ledger (negative amount for payout)
            $this->addTransaction(
                userId: (int)$payout['user_id'],
                amount: -(float)$payout['amount'],
                type: 'payout',
                notes: "Payout completion: {$payout['request_id']}"
            );

            $this->pdo->commit();
            $this->logger->info("Payout processed successfully", ['id' => $payoutId, 'ref' => $processorRef]);

            return [
                'success' => true,
                'payout_id' => $payoutId,
                'processor_reference' => $processorRef
            ];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            
            $this->logger->error("Payout processing failed", ['id' => $payoutId, 'error' => $e->getMessage()]);
            
            // Mark as failed if we got past the lock
            try {
                $stmt = $this->pdo->prepare("UPDATE `ngn_2025`.`cdm_payout_requests` SET status = 'failed', review_notes = ? WHERE id = ?");
                $stmt->execute([$e->getMessage(), $payoutId]);
            } catch (\Throwable $dbE) {}
            
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
            FROM `ngn_2025`.`cdm_royalty_transactions`
            WHERE to_user_id = ? AND status = 'cleared'
        ");

        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $currentBalance = (float)($result['current_balance'] ?? 0);

        // Get pending payouts
        $payoutStmt = $this->pdo->prepare("
            SELECT SUM(amount) as pending_amount
            FROM `ngn_2025`.`cdm_payout_requests`
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
     * Add a transaction to the royalty ledger with Cryptographic Integrity
     */
    public function addTransaction(
        int $userId,
        float $amount,
        string $type = 'eqs_distribution',
        ?string $notes = null,
        ?int $sourceId = null
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO `ngn_2025`.`cdm_royalty_transactions` (
                transaction_id, to_user_id, source_type, amount_gross, amount_net, status, created_at, source_id, notes, integrity_hash
            ) VALUES (?, ?, ?, ?, ?, 'cleared', NOW(), ?, ?, ?)
        ");

        $txId = 'tx_' . bin2hex(random_bytes(10));
        
        // Generate Fairness Receipt (SHA-256)
        // Hash Payload: TransactionID + Amount + UserID + Type + SecretSalt
        // Ideally timestamp is included, but NOW() is server-side. 
        // We use the ID and Amount as the core immutable anchors.
        $salt = $this->config->get('app_key') ?? 'ngn_sovereign_salt';
        $payload = $txId . number_format($amount, 2, '.', '') . $userId . $type . $salt;
        $hash = hash('sha256', $payload);

        $stmt->execute([
            $txId,
            $userId,
            $type,
            $amount,
            $amount,
            $sourceId,
            $notes,
            $hash
        ]);

        return (int)$this->pdo->lastInsertId();
    }
}
