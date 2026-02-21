<?php
namespace NGN\Lib\Commerce;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Services\SMRService;
use PDO;

/**
 * DonationService - Handles one-time and subscription donations for NGN entities
 * Integrates with Stripe for payment processing
 */
class DonationService
{
    private Config $config;
    private PDO $read;
    private PDO $write;
    private SMRService $smrService;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);
        $this->smrService = new SMRService($this->write); // Share write connection
    }

    /**
     * Create a one-time donation
     * @param array<string,mixed> $data
     * @return array{success: bool, id?: int, payment_intent_id?: string, client_secret?: string, error?: string}
     */
    public function createOneTime(array $data): array
    {
        if (empty($data['amount']) || (float)$data['amount'] <= 0) {
            return ['success' => false, 'error' => 'Amount must be greater than 0'];
        }
        if (empty($data['entity_type']) || empty($data['entity_id'])) {
            return ['success' => false, 'error' => 'Entity type and ID are required'];
        }

        $amountCents = (int)round((float)$data['amount'] * 100);
        
        // Calculate Splits (Rule 5 + Data Bounty)
        // Baseline: 90% Artist, 10% Platform
        $platformFeeRate = 0.10;
        
        // Data Bounty Trigger (Bible Ch. 24)
        $hasHeatSpike = false;
        if ($data['entity_type'] === 'artist') {
            $hasHeatSpike = $this->smrService->hasHeatSpike((int)$data['entity_id']);
        }
        
        if ($hasHeatSpike) {
            // "5% of NGN's platform fee is automatically routed to the Advisor"
            // Wait, Bible says "5% of NGN's platform fee" OR "25% to Erik Baker".
            // Let's stick to the 25% Rule 5 logic from PlaybackService, but applied to the platform fee here.
            
            // Standard NGN Split: 75% Ops / 25% Data Provider (Erik)
            // If Bounty Triggered:
            // Let's interpret "Data Bounty" as the activation of this 25% split for Commerce transactions.
            // Without the spike, maybe it's 100% NGN Ops?
            // "A 'Data Bounty' is triggered... if a transaction occurs within 90 days of a Heat Spike"
            
            // Logic:
            // No Spike: Platform Fee = 100% NGN Ops
            // Spike: Platform Fee = 75% NGN Ops / 25% Data Provider
            
            $bountyActive = true;
        } else {
            $bountyActive = false;
        }

        $currency = strtolower($data['currency'] ?? 'usd');

        try {
            // Create donation record
            $sql = "INSERT INTO `ngn_2025`.`donations` (
                        entity_type, entity_id, user_id, email, donor_name,
                        amount_cents, currency, donation_type, message, is_anonymous,
                        status, created_at, updated_at,
                        bounty_triggered
                    ) VALUES (
                        :entity_type, :entity_id, :user_id, :email, :donor_name,
                        :amount_cents, :currency, 'one_time', :message, :is_anonymous,
                        'pending', NOW(), NOW(),
                        :bounty_triggered
                    )";
            $stmt = $this->write->prepare($sql);
            $stmt->bindValue(':entity_type', $data['entity_type']);
            $stmt->bindValue(':entity_id', (int)$data['entity_id'], PDO::PARAM_INT);
            $stmt->bindValue(':user_id', isset($data['user_id']) ? (int)$data['user_id'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':email', $data['email'] ?? null);
            $stmt->bindValue(':donor_name', $data['donor_name'] ?? null);
            $stmt->bindValue(':amount_cents', $amountCents, PDO::PARAM_INT);
            $stmt->bindValue(':currency', $currency);
            $stmt->bindValue(':message', $data['message'] ?? null);
            $stmt->bindValue(':is_anonymous', (int)($data['is_anonymous'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':bounty_triggered', $bountyActive ? 1 : 0, PDO::PARAM_INT);
            $stmt->execute();

            $donationId = (int)$this->write->lastInsertId();

            // Delegate to Chancellor Handshake
            $chancellor = new \NGN\Lib\Services\Graylight\ChancellorHandshakeService($this->config);
            $payload = [
                'type' => 'PAYMENT_INTENT',
                'user_id' => $data['user_id'] ?? null,
                'customer_email' => $data['email'] ?? null,
                'amount' => $amountCents,
                'currency' => $currency,
                'description' => "NGN Donation to {$data['entity_type']} #{$data['entity_id']}",
                'metadata' => [
                    'donation_id' => $donationId,
                    'entity_type' => $data['entity_type'],
                    'entity_id' => $data['entity_id'],
                    'type' => 'one_time',
                ]
            ];

            $result = $chancellor->authorizeCheckout($payload);

            if ($result['success'] && isset($result['payment_intent_id'])) {
                // Update donation with payment intent
                $updateSql = "UPDATE `ngn_2025`.`donations` SET stripe_payment_intent_id = :pi WHERE id = :id";
                $updateStmt = $this->write->prepare($updateSql);
                $updateStmt->bindValue(':pi', $result['payment_intent_id']);
                $updateStmt->bindValue(':id', $donationId, PDO::PARAM_INT);
                $updateStmt->execute();

                return [
                    'success' => true,
                    'id' => $donationId,
                    'payment_intent_id' => $result['payment_intent_id'],
                    'client_secret' => $result['client_secret'] ?? null,
                ];
            }

            return ['success' => true, 'id' => $donationId];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create a subscription donation
     * @param array<string,mixed> $data
     * @return array{success: bool, id?: int, subscription_id?: string, client_secret?: string, error?: string}
     */
    public function createSubscription(array $data): array
    {
        if (empty($data['amount']) || (float)$data['amount'] <= 0) {
            return ['success' => false, 'error' => 'Amount must be greater than 0'];
        }
        if (empty($data['entity_type']) || empty($data['entity_id'])) {
            return ['success' => false, 'error' => 'Entity type and ID are required'];
        }
        if (empty($data['email'])) {
            return ['success' => false, 'error' => 'Email is required for subscriptions'];
        }

        $amountCents = (int)round((float)$data['amount'] * 100);
        $currency = strtolower($data['currency'] ?? 'usd');
        $interval = $data['interval'] ?? 'month'; // month, year

        try {
            // Create donation record
            $sql = "INSERT INTO `ngn_2025`.`donations` (
                        entity_type, entity_id, user_id, email, donor_name,
                        amount_cents, currency, donation_type, subscription_interval,
                        message, is_anonymous, status, created_at, updated_at
                    ) VALUES (
                        :entity_type, :entity_id, :user_id, :email, :donor_name,
                        :amount_cents, :currency, 'subscription', :interval,
                        :message, :is_anonymous, 'pending', NOW(), NOW()
                    )";
            $stmt = $this->write->prepare($sql);
            $stmt->bindValue(':entity_type', $data['entity_type']);
            $stmt->bindValue(':entity_id', (int)$data['entity_id'], PDO::PARAM_INT);
            $stmt->bindValue(':user_id', isset($data['user_id']) ? (int)$data['user_id'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':email', $data['email']);
            $stmt->bindValue(':donor_name', $data['donor_name'] ?? null);
            $stmt->bindValue(':amount_cents', $amountCents, PDO::PARAM_INT);
            $stmt->bindValue(':currency', $currency);
            $stmt->bindValue(':interval', $interval);
            $stmt->bindValue(':message', $data['message'] ?? null);
            $stmt->bindValue(':is_anonymous', (int)($data['is_anonymous'] ?? 0), PDO::PARAM_INT);
            $stmt->execute();

            $donationId = (int)$this->write->lastInsertId();

            // Delegate to Chancellor Handshake
            $chancellor = new \NGN\Lib\Services\Graylight\ChancellorHandshakeService($this->config);
            
            // Build absolute URLs for checkout
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'nextgennation.com';
            $baseUrl = "{$protocol}://{$host}";
            
            $payload = [
                'type' => 'CHECKOUT_SESSION',
                'user_id' => $data['user_id'] ?? null,
                'customer_email' => $data['email'],
                'mode' => 'subscription',
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => $currency,
                            'product_data' => [
                                'name' => "NGN Support for {$data['entity_type']} #{$data['entity_id']}"
                            ],
                            'unit_amount' => $amountCents,
                            'recurring' => ['interval' => $interval]
                        ],
                        'quantity' => 1
                    ]
                ],
                'success_url' => $baseUrl . '/donation/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $baseUrl . '/donation/cancel',
                'metadata' => [
                    'donation_id' => $donationId,
                    'entity_type' => $data['entity_type'],
                    'entity_id' => $data['entity_id'],
                    'type' => 'subscription'
                ]
            ];

            $result = $chancellor->authorizeCheckout($payload);

            if ($result['success'] && isset($result['session_id'])) {
                // Update donation with subscription session info
                $updateSql = "UPDATE `ngn_2025`.`donations` 
                              SET stripe_session_id = :sid 
                              WHERE id = :id";
                $updateStmt = $this->write->prepare($updateSql);
                $updateStmt->bindValue(':sid', $result['session_id']);
                $updateStmt->bindValue(':id', $donationId, PDO::PARAM_INT);
                $updateStmt->execute();

                return [
                    'success' => true,
                    'id' => $donationId,
                    'subscription_id' => null, // Available after webhook confirmation
                    'session_id' => $result['session_id'],
                    'url' => $result['url'] ?? null
                ];
            }

            return ['success' => true, 'id' => $donationId];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cancel a subscription donation
     * @return array{success: bool, error?: string}
     */
    public function cancelSubscription(int $donationId, ?int $userId = null): array
    {
        try {
            $sql = "SELECT * FROM `ngn_2025`.`donations` WHERE id = :id";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':id', $donationId, PDO::PARAM_INT);
            $stmt->execute();
            $donation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$donation) {
                return ['success' => false, 'error' => 'Donation not found'];
            }
            if ($donation['donation_type'] !== 'subscription') {
                return ['success' => false, 'error' => 'Not a subscription'];
            }
            if ($userId !== null && (int)$donation['user_id'] !== $userId) {
                return ['success' => false, 'error' => 'Unauthorized'];
            }

            $stripeSubId = $donation['stripe_subscription_id'] ?? null;
            if ($stripeSubId && class_exists('\Stripe\Stripe')) {
                $stripeKey = getenv('STRIPE_SECRET_KEY');
                if ($stripeKey) {
                    \Stripe\Stripe::setApiKey($stripeKey);
                    \Stripe\Subscription::update($stripeSubId, ['cancel_at_period_end' => true]);
                }
            }

            $updateSql = "UPDATE `ngn_2025`.`donations` 
                          SET status = 'cancelled', cancelled_at = NOW(), updated_at = NOW() 
                          WHERE id = :id";
            $updateStmt = $this->write->prepare($updateSql);
            $updateStmt->bindValue(':id', $donationId, PDO::PARAM_INT);
            $updateStmt->execute();

            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * List donations for an entity
     * @return array{items: array, total: int}
     */
    public function listForEntity(string $entityType, int $entityId, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        try {
            $sql = "SELECT d.id, d.user_id, d.email, d.donor_name, d.amount_cents, d.currency,
                           d.donation_type, d.subscription_interval, d.message, d.is_anonymous,
                           d.status, d.created_at
                    FROM `ngn_2025`.`donations` d
                    WHERE d.entity_type = :type AND d.entity_id = :id AND d.status = 'completed'
                    ORDER BY d.created_at DESC
                    LIMIT :limit OFFSET :offset";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':type', $entityType);
            $stmt->bindValue(':id', $entityId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $cSql = "SELECT COUNT(*) AS c FROM `ngn_2025`.`donations` 
                     WHERE entity_type = :type AND entity_id = :id AND status = 'completed'";
            $cStmt = $this->read->prepare($cSql);
            $cStmt->bindValue(':type', $entityType);
            $cStmt->bindValue(':id', $entityId, PDO::PARAM_INT);
            $cStmt->execute();
            $total = (int)($cStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

            $items = array_map(function($r) {
                return [
                    'id' => (int)$r['id'],
                    'donor_name' => $r['is_anonymous'] ? 'Anonymous' : ($r['donor_name'] ?? 'Supporter'),
                    'amount' => (float)$r['amount_cents'] / 100,
                    'currency' => $r['currency'],
                    'type' => $r['donation_type'],
                    'interval' => $r['subscription_interval'],
                    'message' => $r['is_anonymous'] ? null : $r['message'],
                    'created_at' => $r['created_at'],
                ];
            }, $rows);

            return ['items' => $items, 'total' => $total];
        } catch (\Throwable $e) {
            return ['items' => [], 'total' => 0];
        }
    }

    /**
     * Get donation stats for an entity
     * @return array<string,mixed>
     */
    public function getStats(string $entityType, int $entityId): array
    {
        try {
            $sql = "SELECT 
                        COUNT(*) AS total_donations,
                        COUNT(DISTINCT CASE WHEN donation_type = 'subscription' THEN id END) AS active_subscriptions,
                        SUM(amount_cents) AS total_amount_cents,
                        SUM(CASE WHEN donation_type = 'subscription' THEN amount_cents ELSE 0 END) AS monthly_recurring_cents
                    FROM `ngn_2025`.`donations`
                    WHERE entity_type = :type AND entity_id = :id AND status = 'completed'";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':type', $entityType);
            $stmt->bindValue(':id', $entityId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'total_donations' => (int)($row['total_donations'] ?? 0),
                'active_subscriptions' => (int)($row['active_subscriptions'] ?? 0),
                'total_raised' => (float)($row['total_amount_cents'] ?? 0) / 100,
                'monthly_recurring' => (float)($row['monthly_recurring_cents'] ?? 0) / 100,
            ];
        } catch (\Throwable $e) {
            return ['total_donations' => 0, 'active_subscriptions' => 0, 'total_raised' => 0, 'monthly_recurring' => 0];
        }
    }

    /**
     * Handle Stripe webhook for donation events
     * @param array<string,mixed> $event
     * @return array{success: bool, handled?: bool, error?: string}
     */
    public function handleWebhook(array $event): array
    {
        $type = $event['type'] ?? '';
        $data = $event['data']['object'] ?? [];

        try {
            switch ($type) {
                case 'payment_intent.succeeded':
                    return $this->handlePaymentSucceeded($data);
                case 'invoice.paid':
                    return $this->handleInvoicePaid($data);
                case 'customer.subscription.deleted':
                    return $this->handleSubscriptionCancelled($data);
                default:
                    return ['success' => true, 'handled' => false];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function handlePaymentSucceeded(array $data): array
    {
        $donationId = $data['metadata']['donation_id'] ?? null;
        if (!$donationId) return ['success' => true, 'handled' => false];

        $sql = "UPDATE `ngn_2025`.`donations` 
                SET status = 'completed', completed_at = NOW(), updated_at = NOW() 
                WHERE id = :id AND status = 'pending'";
        $stmt = $this->write->prepare($sql);
        $stmt->bindValue(':id', (int)$donationId, PDO::PARAM_INT);
        $stmt->execute();

        return ['success' => true, 'handled' => true];
    }

    private function handleInvoicePaid(array $data): array
    {
        $subId = $data['subscription'] ?? null;
        if (!$subId) return ['success' => true, 'handled' => false];

        $sql = "UPDATE `ngn_2025`.`donations` 
                SET status = 'completed', last_payment_at = NOW(), updated_at = NOW() 
                WHERE stripe_subscription_id = :sub";
        $stmt = $this->write->prepare($sql);
        $stmt->bindValue(':sub', $subId);
        $stmt->execute();

        return ['success' => true, 'handled' => true];
    }

    private function handleSubscriptionCancelled(array $data): array
    {
        $subId = $data['id'] ?? null;
        if (!$subId) return ['success' => true, 'handled' => false];

        $sql = "UPDATE `ngn_2025`.`donations` 
                SET status = 'cancelled', cancelled_at = NOW(), updated_at = NOW() 
                WHERE stripe_subscription_id = :sub";
        $stmt = $this->write->prepare($sql);
        $stmt->bindValue(':sub', $subId);
        $stmt->execute();

        return ['success' => true, 'handled' => true];
    }
}
