<?php

namespace NGN\Lib\Commerce;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Commerce\Exception\InvestmentException;
use PDO;

/**
 * Service for handling investment-related operations.
 */
class InvestmentService
{
    private Config $config;
    private PDO $read;
    private PDO $write;
    private string $stripeSecretKey;

    /**
     * Constructor.
     *
     * @param Config $config Application configuration.
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);

        // Load Stripe key from environment
        $this->stripeSecretKey = (string)(getenv('STRIPE_SECRET_KEY') ?: '');

        // Initialize Stripe
        if ($this->stripeSecretKey && class_exists('\Stripe\Stripe')) {
            \Stripe\Stripe::setApiKey($this->stripeSecretKey);
            \Stripe\Stripe::setApiVersion('2023-10-16');
        }
    }

    /**
     * Creates a Stripe Checkout Session for an investment.
     *
     * @param int $userId The ID of the user making the investment.
     * @param string $email User email address.
     * @param int $amountCents The investment amount in cents.
     * @param string $successUrl URL to redirect on success.
     * @param string $cancelUrl URL to redirect on cancel.
     * @return array{success: bool, url?: string, investment_id?: int, error?: string}
     * @throws \InvalidArgumentException If the investment amount is invalid.
     */
    public function createSession(
        int $userId,
        string $email,
        int $amountCents,
        string $successUrl,
        string $cancelUrl
    ): array {
        // --- Step 1: Validate Investment Amount ---
        $minInvestment = 50000; // $500 in cents
        $step = 10000; // $100 in cents

        if ($amountCents < $minInvestment) {
            return ['success' => false, 'error' => 'Minimum investment amount is $500'];
        }

        if (($amountCents % $step) !== 0) {
            return ['success' => false, 'error' => 'Investment amount must be in increments of $100'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email address'];
        }

        try {
            // --- Step 2: Insert Investment Record ---
            $stmt = $this->write->prepare(
                "INSERT INTO `ngn_2025`.`investments`
                (`user_id`, `email`, `amount_cents`, `currency`, `term_years`, `apy_percent`, `status`, `created_at`, `updated_at`)
                VALUES (:userId, :email, :amount_cents, 'usd', :termYears, :apyPercent, 'initiated', NOW(), NOW())"
            );

            $termYears = 5;
            $apyPercent = 8.0;

            $stmt->execute([
                ':userId' => $userId,
                ':email' => $email,
                ':amount_cents' => $amountCents,
                ':termYears' => $termYears,
                ':apyPercent' => $apyPercent,
            ]);

            $investmentId = (int)$this->write->lastInsertId();
            if (!$investmentId) {
                return ['success' => false, 'error' => 'Failed to create investment record'];
            }

            // --- Step 3: Create Stripe Checkout Session ---
            if (!$this->stripeSecretKey || !class_exists('\Stripe\Stripe')) {
                return ['success' => false, 'error' => 'Stripe not configured'];
            }

            $session = \Stripe\Checkout\Session::create([
                'mode' => 'payment',
                'customer_email' => $email,
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => 'usd',
                            'unit_amount' => $amountCents,
                            'product_data' => [
                                'name' => 'NGN Community Funding Investment',
                                'description' => sprintf(
                                    '%d-year note at %.2f%% APY',
                                    $termYears,
                                    $apyPercent
                                ),
                            ],
                        ],
                        'quantity' => 1,
                    ],
                ],
                'success_url' => $successUrl . (strpos($successUrl, '?') !== false ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl,
                'metadata' => [
                    'investment_id' => (string)$investmentId,
                    'user_id' => (string)$userId,
                    'type' => 'investment',
                ],
                'payment_intent_data' => [
                    'metadata' => [
                        'investment_id' => (string)$investmentId,
                        'user_id' => (string)$userId,
                        'type' => 'investment',
                    ],
                ],
            ]);

            // --- Step 4: Update investment record with session ID ---
            $stmt = $this->write->prepare(
                "UPDATE `ngn_2025`.`investments`
                SET stripe_session_id = :sessionId, status = 'pending_payment', updated_at = NOW()
                WHERE id = :id"
            );
            $stmt->execute([
                ':sessionId' => $session->id,
                ':id' => $investmentId,
            ]);

            return [
                'success' => true,
                'url' => $session->url,
                'investment_id' => $investmentId,
                'session_id' => $session->id,
            ];

        } catch (\PDOException $e) {
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return ['success' => false, 'error' => 'Stripe error: ' . $e->getMessage()];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to create checkout session: ' . $e->getMessage()];
        }
    }

    /**
     * Confirms an investment, updating its status and the user's investor flag.
     *
     * @param int $investmentId The ID of the investment to confirm.
     * @param string $stripePaymentIntentId Stripe Payment Intent ID.
     * @param string|null $stripeCustomerId Stripe Customer ID (optional).
     * @return array{success: bool, error?: string}
     */
    public function confirmInvestment(
        int $investmentId,
        string $stripePaymentIntentId,
        ?string $stripeCustomerId = null
    ): array {
        try {
            // --- Step 1: Get the investment ---
            $stmt = $this->read->prepare(
                "SELECT id, user_id, status FROM `ngn_2025`.`investments` WHERE id = :id LIMIT 1"
            );
            $stmt->execute([':id' => $investmentId]);
            $investment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$investment) {
                throw new InvestmentException("Investment #{$investmentId} not found");
            }

            if ($investment['status'] === 'active') {
                // Already confirmed, idempotent response
                return ['success' => true];
            }

            if ($investment['status'] !== 'pending_payment') {
                throw new InvestmentException(
                    "Investment #{$investmentId} is in '{$investment['status']}' status, cannot confirm"
                );
            }

            $userId = (int)$investment['user_id'];

            // --- Step 2: Generate note number ---
            $noteNumber = $this->generateNoteNumber($investmentId);

            // --- Step 3: Update investment status to active ---
            $stmt = $this->write->prepare(
                "UPDATE `ngn_2025`.`investments`
                SET status = 'active',
                    stripe_payment_intent_id = :pi,
                    stripe_customer_id = :customer,
                    note_number = :noteNumber,
                    is_elite_perk_active = 1,
                    activated_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id"
            );
            $stmt->execute([
                ':pi' => $stripePaymentIntentId,
                ':customer' => $stripeCustomerId,
                ':noteNumber' => $noteNumber,
                ':id' => $investmentId,
            ]);

            // --- Step 4: Update the user's investor flag ---
            $stmt = $this->write->prepare(
                "UPDATE `ngn_2025`.`users` SET IsInvestor = 1 WHERE Id = :userId LIMIT 1"
            );
            $stmt->execute([':userId' => $userId]);

            return ['success' => true];

        } catch (\PDOException $e) {
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        } catch (InvestmentException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to confirm investment: ' . $e->getMessage()];
        }
    }

    /**
     * Generate a unique note number for an investment.
     *
     * @param int $investmentId The investment ID.
     * @return string Note number (e.g., "NGN-2026-00001").
     */
    private function generateNoteNumber(int $investmentId): string
    {
        $year = date('Y');
        $paddedId = str_pad((string)$investmentId, 5, '0', STR_PAD_LEFT);
        return "NGN-{$year}-{$paddedId}";
    }

    /**
     * Get investment details.
     *
     * @param int $investmentId The investment ID.
     * @return array<string,mixed>|null Investment data or null if not found.
     */
    public function getInvestment(int $investmentId): ?array
    {
        try {
            $stmt = $this->read->prepare(
                "SELECT * FROM `ngn_2025`.`investments` WHERE id = :id LIMIT 1"
            );
            $stmt->execute([':id' => $investmentId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get all investments for a user.
     *
     * @param int $userId The user ID.
     * @return array<int, array<string,mixed>> Array of investments.
     */
    public function getUserInvestments(int $userId): array
    {
        try {
            $stmt = $this->read->prepare(
                "SELECT * FROM `ngn_2025`.`investments`
                WHERE user_id = :userId
                ORDER BY created_at DESC"
            );
            $stmt->execute([':userId' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
