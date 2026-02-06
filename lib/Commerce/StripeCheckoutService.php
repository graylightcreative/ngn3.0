<?php
namespace NGN\Lib\Commerce;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Env;
use PDO;

/**
 * StripeCheckoutService - Handles Stripe payment processing for NGN shops
 */
class StripeCheckoutService
{
    private Config $config;
    private PDO $read;
    private PDO $write;
    private string $secretKey;
    private string $publishableKey;
    private string $webhookSecret;
    private float $platformFeePercent;
    private string $currency;
    
    // Stripe API version
    private const API_VERSION = '2023-10-16';

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);
        
        // Load Stripe keys from environment via NGN Env service
        $this->secretKey = (string)Env::get('STRIPE_SECRET_KEY', '');
        $this->publishableKey = (string)Env::get('STRIPE_PUBLISHABLE_KEY', '');
        $this->webhookSecret = (string)Env::get('STRIPE_WEBHOOK_SECRET', '');
        $this->platformFeePercent = (float)Env::get('STRIPE_PLATFORM_FEE_PERCENT', '5.0');
        $this->currency = strtolower((string)Env::get('STRIPE_CURRENCY', 'usd'));
        
        // Initialize Stripe
        if ($this->secretKey && class_exists('\Stripe\Stripe')) {
            \Stripe\Stripe::setApiKey($this->secretKey);
            \Stripe\Stripe::setApiVersion(self::API_VERSION);
        }
    }

    /**
     * Check if Stripe is properly configured
     */
    public function isConfigured(): bool
    {
        return $this->secretKey !== '' && class_exists('\Stripe\Stripe');
    }

    /**
     * Get publishable key for frontend
     */
    public function getPublishableKey(): string
    {
        return $this->publishableKey;
    }

    /**
     * Create a Payment Intent for an order
     * 
     * @param int $orderId NGN order ID
     * @param array<string,mixed> $options Additional options
     * @return array{success: bool, payment_intent_id?: string, client_secret?: string, error?: string}
     */
    public function createPaymentIntent(int $orderId, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Stripe not configured'];
        }

        // Fetch order details
        $order = $this->getOrder($orderId);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found'];
        }

        if ($order['status'] !== 'pending') {
            return ['success' => false, 'error' => 'Order is not in pending status'];
        }

        try {
            $amountCents = (int)round($order['total'] * 100);
            
            $params = [
                'amount' => $amountCents,
                'currency' => $this->currency,
                'metadata' => [
                    'order_id' => $orderId,
                    'order_number' => $order['order_number'],
                    'email' => $order['email'],
                ],
                'description' => "NGN Order #{$order['order_number']}",
                'receipt_email' => $order['email'],
            ];

            // Add customer if we have one
            if (!empty($options['customer_id'])) {
                $params['customer'] = $options['customer_id'];
            }

            // Automatic payment methods
            $params['automatic_payment_methods'] = [
                'enabled' => true,
            ];

            // Add statement descriptor (max 22 chars)
            $params['statement_descriptor_suffix'] = 'NGN ' . substr($order['order_number'], -8);

            // Create the Payment Intent
            $intent = \Stripe\PaymentIntent::create($params);

            // Update order with Payment Intent ID
            $this->updateOrderPaymentIntent($orderId, $intent->id);

            return [
                'success' => true,
                'payment_intent_id' => $intent->id,
                'client_secret' => $intent->client_secret,
                'amount' => $amountCents,
                'currency' => $this->currency,
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Payment processing error: ' . $e->getMessage()];
        }
    }

    /**
     * Create a Checkout Session for hosted checkout
     * 
     * @param int $orderId NGN order ID
     * @param string $successUrl URL to redirect on success
     * @param string $cancelUrl URL to redirect on cancel
     * @param array<string,mixed> $options Additional options
     * @return array{success: bool, session_id?: string, url?: string, error?: string}
     */
    public function createCheckoutSession(
        int $orderId,
        string $successUrl,
        string $cancelUrl,
        array $options = []
    ): array {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Stripe not configured'];
        }

        // Fetch order with items
        $order = $this->getOrder($orderId);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found'];
        }

        $items = $this->getOrderItems($orderId);
        if (empty($items)) {
            return ['success' => false, 'error' => 'Order has no items'];
        }

        try {
            // Build line items for Stripe
            $lineItems = [];
            foreach ($items as $item) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => $this->currency,
                        'unit_amount' => (int)round($item['price'] * 100),
                        'product_data' => [
                            'name' => $item['name'],
                            'metadata' => [
                                'product_id' => $item['product_id'] ?? '',
                                'variant_id' => $item['variant_id'] ?? '',
                                'sku' => $item['sku'] ?? '',
                            ],
                        ],
                    ],
                    'quantity' => (int)$item['quantity'],
                ];
            }

            // Add shipping as a line item if applicable
            if ($order['shipping_amount'] > 0) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => $this->currency,
                        'unit_amount' => (int)round($order['shipping_amount'] * 100),
                        'product_data' => [
                            'name' => 'Shipping',
                        ],
                    ],
                    'quantity' => 1,
                ];
            }

            // Build session params
            $sessionParams = [
                'mode' => 'payment',
                'line_items' => $lineItems,
                'success_url' => $successUrl . (strpos($successUrl, '?') !== false ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl,
                'customer_email' => $order['email'],
                'metadata' => [
                    'order_id' => $orderId,
                    'order_number' => $order['order_number'],
                ],
                'payment_intent_data' => [
                    'metadata' => [
                        'order_id' => $orderId,
                        'order_number' => $order['order_number'],
                    ],
                ],
            ];

            // Add shipping address collection if physical items
            if ($this->hasPhysicalItems($items)) {
                $sessionParams['shipping_address_collection'] = [
                    'allowed_countries' => ['US', 'CA', 'GB', 'AU', 'DE', 'FR', 'NL', 'BE', 'AT', 'CH'],
                ];
            }

            // Allow promotion codes
            if (!empty($options['allow_promotion_codes'])) {
                $sessionParams['allow_promotion_codes'] = true;
            }

            // Existing customer
            if (!empty($options['customer_id'])) {
                unset($sessionParams['customer_email']);
                $sessionParams['customer'] = $options['customer_id'];
            }

            // Create session
            $session = \Stripe\Checkout\Session::create($sessionParams);

            // Update order with session ID
            $this->updateOrderCheckoutSession($orderId, $session->id);

            return [
                'success' => true,
                'session_id' => $session->id,
                'url' => $session->url,
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Checkout error: ' . $e->getMessage()];
        }
    }

    /**
     * Retrieve a Checkout Session
     * 
     * @param string $sessionId Stripe session ID
     * @return array{success: bool, session?: array<string,mixed>, error?: string}
     */
    public function getCheckoutSession(string $sessionId): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Stripe not configured'];
        }

        try {
            $session = \Stripe\Checkout\Session::retrieve([
                'id' => $sessionId,
                'expand' => ['payment_intent', 'customer'],
            ]);

            return [
                'success' => true,
                'session' => [
                    'id' => $session->id,
                    'payment_status' => $session->payment_status,
                    'status' => $session->status,
                    'amount_total' => $session->amount_total,
                    'currency' => $session->currency,
                    'customer_email' => $session->customer_email ?? $session->customer_details->email ?? null,
                    'payment_intent_id' => $session->payment_intent->id ?? $session->payment_intent ?? null,
                    'metadata' => (array)$session->metadata,
                ],
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process a refund for an order
     * 
     * @param int $orderId NGN order ID
     * @param float|null $amount Amount to refund (null for full refund)
     * @param string $reason Refund reason
     * @return array{success: bool, refund_id?: string, error?: string}
     */
    public function refund(int $orderId, ?float $amount = null, string $reason = ''): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Stripe not configured'];
        }

        $order = $this->getOrder($orderId);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found'];
        }

        $paymentIntentId = $order['stripe_payment_intent_id'] ?? null;
        $chargeId = $order['stripe_charge_id'] ?? null;

        if (!$paymentIntentId && !$chargeId) {
            return ['success' => false, 'error' => 'No payment found for this order'];
        }

        try {
            $refundParams = [];

            // Use charge if available, otherwise payment intent
            if ($chargeId) {
                $refundParams['charge'] = $chargeId;
            } else {
                $refundParams['payment_intent'] = $paymentIntentId;
            }

            // Partial refund
            if ($amount !== null) {
                $refundParams['amount'] = (int)round($amount * 100);
            }

            // Reason
            if ($reason) {
                $refundParams['reason'] = 'requested_by_customer';
                $refundParams['metadata'] = ['reason_detail' => $reason];
            }

            $refund = \Stripe\Refund::create($refundParams);

            // Update order with refund info
            $this->updateOrderRefund($orderId, $refund->id, $refund->amount / 100);

            return [
                'success' => true,
                'refund_id' => $refund->id,
                'amount_refunded' => $refund->amount / 100,
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create or retrieve a Stripe customer
     * 
     * @param string $email Customer email
     * @param array<string,mixed> $metadata Additional metadata
     * @return array{success: bool, customer_id?: string, error?: string}
     */
    public function getOrCreateCustomer(string $email, array $metadata = []): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Stripe not configured'];
        }

        try {
            // Search for existing customer
            $customers = \Stripe\Customer::search([
                'query' => "email:'{$email}'",
                'limit' => 1,
            ]);

            if (!empty($customers->data)) {
                return [
                    'success' => true,
                    'customer_id' => $customers->data[0]->id,
                    'is_new' => false,
                ];
            }

            // Create new customer
            $customer = \Stripe\Customer::create([
                'email' => $email,
                'metadata' => array_merge(['source' => 'ngn_shop'], $metadata),
            ]);

            return [
                'success' => true,
                'customer_id' => $customer->id,
                'is_new' => true,
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Retrieve Payment Intent status
     * 
     * @param string $paymentIntentId
     * @return array{success: bool, status?: string, error?: string}
     */
    public function getPaymentIntentStatus(string $paymentIntentId): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Stripe not configured'];
        }

        try {
            $intent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
            
            return [
                'success' => true,
                'status' => $intent->status,
                'amount' => $intent->amount,
                'amount_received' => $intent->amount_received,
                'currency' => $intent->currency,
                'charges' => $intent->latest_charge,
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process webhook event from Stripe
     * 
     * @param string $payload Raw request body
     * @param string $signature Stripe-Signature header
     * @return array{success: bool, event_type?: string, handled?: bool, error?: string}
     */
    public function handleWebhook(string $payload, string $signature): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Stripe not configured'];
        }

        if (!$this->webhookSecret) {
            return ['success' => false, 'error' => 'Webhook secret not configured'];
        }

        try {
            // Verify webhook signature
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $this->webhookSecret);

            $handled = false;

            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $handled = $this->handlePaymentIntentSucceeded($event->data->object);
                    break;

                case 'payment_intent.payment_failed':
                    $handled = $this->handlePaymentIntentFailed($event->data->object);
                    break;

                case 'checkout.session.completed':
                    $handled = $this->handleCheckoutSessionCompleted($event->data->object);
                    break;

                case 'checkout.session.expired':
                    $handled = $this->handleCheckoutSessionExpired($event->data->object);
                    break;

                case 'charge.refunded':
                    $handled = $this->handleChargeRefunded($event->data->object);
                    break;

                case 'charge.dispute.created':
                    $handled = $this->handleDisputeCreated($event->data->object);
                    break;

                default:
                    // Event type not handled
                    $handled = false;
            }

            return [
                'success' => true,
                'event_type' => $event->type,
                'event_id' => $event->id,
                'handled' => $handled,
            ];
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return ['success' => false, 'error' => 'Invalid signature'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Webhook error: ' . $e->getMessage()];
        }
    }

    /**
     * Calculate platform fee for an order
     * 
     * @param float $orderTotal Order total in currency units
     * @return float Platform fee amount
     */
    public function calculatePlatformFee(float $orderTotal): float
    {
        return round($orderTotal * ($this->platformFeePercent / 100), 2);
    }

    /**
     * Get configuration for frontend
     * 
     * @return array<string,mixed>
     */
    public function getFrontendConfig(): array
    {
        return [
            'publishable_key' => $this->publishableKey,
            'currency' => $this->currency,
            'is_configured' => $this->isConfigured(),
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // WEBHOOK HANDLERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Handle successful payment
     */
    private function handlePaymentIntentSucceeded(\Stripe\PaymentIntent $intent): bool
    {
        $orderId = $intent->metadata['order_id'] ?? null;
        if (!$orderId) {
            return false;
        }

        try {
            $chargeId = $intent->latest_charge;

            $sql = "UPDATE `ngn_2025`.`orders` 
                    SET status = 'paid', 
                        stripe_charge_id = :charge,
                        paid_at = NOW(),
                        updated_at = NOW()
                    WHERE id = :id AND status = 'pending'";
            $stmt = $this->write->prepare($sql);
            $stmt->bindValue(':charge', $chargeId);
            $stmt->bindValue(':id', (int)$orderId, PDO::PARAM_INT);
            $stmt->execute();

            // Log event
            $this->logOrderEvent((int)$orderId, 'payment_succeeded', "Payment received via Stripe ($intent->id)");

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Handle failed payment
     */
    private function handlePaymentIntentFailed(\Stripe\PaymentIntent $intent): bool
    {
        $orderId = $intent->metadata['order_id'] ?? null;
        if (!$orderId) {
            return false;
        }

        try {
            $error = $intent->last_payment_error->message ?? 'Payment failed';

            $this->logOrderEvent((int)$orderId, 'payment_failed', "Payment failed: $error");

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Handle completed checkout session
     */
    private function handleCheckoutSessionCompleted(\Stripe\Checkout\Session $session): bool
    {
        $orderId = $session->metadata['order_id'] ?? null;
        if (!$orderId) {
            return false;
        }

        try {
            $paymentIntentId = $session->payment_intent;

            $sql = "UPDATE `ngn_2025`.`orders` 
                    SET status = 'paid',
                        stripe_payment_intent_id = COALESCE(stripe_payment_intent_id, :pi),
                        paid_at = NOW(),
                        updated_at = NOW()
                    WHERE id = :id";
            $stmt = $this->write->prepare($sql);
            $stmt->bindValue(':pi', $paymentIntentId);
            $stmt->bindValue(':id', (int)$orderId, PDO::PARAM_INT);
            $stmt->execute();

            // Update shipping address if collected
            if ($session->shipping_details) {
                $this->updateOrderShippingFromCheckout((int)$orderId, $session->shipping_details);
            }

            $this->logOrderEvent((int)$orderId, 'checkout_completed', "Checkout completed ($session->id)");

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Handle expired checkout session
     */
    private function handleCheckoutSessionExpired(\Stripe\Checkout\Session $session): bool
    {
        $orderId = $session->metadata['order_id'] ?? null;
        if (!$orderId) {
            return false;
        }

        try {
            $this->logOrderEvent((int)$orderId, 'checkout_expired', "Checkout session expired ($session->id)");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Handle refund
     */
    private function handleChargeRefunded(\Stripe\Charge $charge): bool
    {
        $paymentIntentId = $charge->payment_intent;
        if (!$paymentIntentId) {
            return false;
        }

        try {
            // Find order by payment intent
            $sql = "SELECT id FROM `ngn_2025`.`orders` WHERE stripe_payment_intent_id = :pi LIMIT 1";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':pi', $paymentIntentId);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return false;
            }

            $orderId = (int)$row['id'];
            $refundedAmount = $charge->amount_refunded / 100;

            $sql = "UPDATE `ngn_2025`.`orders` 
                    SET status = CASE WHEN :refunded >= total THEN 'refunded' ELSE status END,
                        refund_amount = :refunded,
                        refunded_at = NOW(),
                        updated_at = NOW()
                    WHERE id = :id";
            $stmt = $this->write->prepare($sql);
            $stmt->bindValue(':refunded', $refundedAmount);
            $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
            $stmt->execute();

            $this->logOrderEvent($orderId, 'refund_processed', "Refund of \$$refundedAmount processed");

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Handle dispute created
     */
    private function handleDisputeCreated(\Stripe\Dispute $dispute): bool
    {
        $chargeId = $dispute->charge;

        try {
            // Find order by charge
            $sql = "SELECT id FROM `ngn_2025`.`orders` WHERE stripe_charge_id = :charge LIMIT 1";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':charge', $chargeId);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return false;
            }

            $orderId = (int)$row['id'];
            $this->logOrderEvent($orderId, 'dispute_created', "Dispute created: {$dispute->reason} (\${$dispute->amount})");

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // DATABASE HELPERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get order details
     * @return array<string,mixed>|null
     */
    private function getOrder(int $orderId): ?array
    {
        try {
            $sql = "SELECT * FROM `ngn_2025`.`orders` WHERE id = :id";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get order items
     * @return array<int, array<string,mixed>>
     */
    private function getOrderItems(int $orderId): array
    {
        try {
            $sql = "SELECT * FROM `ngn_2025`.`order_items` WHERE order_id = :id";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Check if order has physical items
     * @param array<int, array<string,mixed>> $items
     */
    private function hasPhysicalItems(array $items): bool
    {
        foreach ($items as $item) {
            if (empty($item['is_digital'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Update order with Payment Intent ID
     */
    private function updateOrderPaymentIntent(int $orderId, string $paymentIntentId): void
    {
        try {
            $sql = "UPDATE `ngn_2025`.`orders` SET stripe_payment_intent_id = :pi, updated_at = NOW() WHERE id = :id";
            $stmt = $this->write->prepare($sql);
            $stmt->bindValue(':pi', $paymentIntentId);
            $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\Throwable $e) {
            // Silently fail
        }
    }

    /**
     * Update order with Checkout Session ID
     */
    private function updateOrderCheckoutSession(int $orderId, string $sessionId): void
    {
        try {
            $sql = "UPDATE `ngn_2025`.`orders` SET stripe_checkout_session_id = :sid, updated_at = NOW() WHERE id = :id";
            $stmt = $this->write->prepare($sql);
            $stmt->bindValue(':sid', $sessionId);
            $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\Throwable $e) {
            // Silently fail
        }
    }

    /**
     * Update order with refund info
     */
    private function updateOrderRefund(int $orderId, string $refundId, float $amount): void
    {
        try {
            $sql = "UPDATE `ngn_2025`.`orders` 
                    SET stripe_refund_id = :rid, 
                        refund_amount = COALESCE(refund_amount, 0) + :amt,
                        refunded_at = NOW(),
                        updated_at = NOW() 
                    WHERE id = :id";
            $stmt = $this->write->prepare($sql);
            $stmt->bindValue(':rid', $refundId);
            $stmt->bindValue(':amt', $amount);
            $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\Throwable $e) {
            // Silently fail
        }
    }

    /**
     * Update order shipping from checkout session
     * @param object $shippingDetails Stripe shipping details
     */
    private function updateOrderShippingFromCheckout(int $orderId, $shippingDetails): void
    {
        try {
            $address = $shippingDetails->address ?? null;
            if (!$address) return;

            $sql = "UPDATE `ngn_2025`.`orders` 
                    SET shipping_name = :name,
                        shipping_address1 = :addr1,
                        shipping_address2 = :addr2,
                        shipping_city = :city,
                        shipping_state = :state,
                        shipping_zip = :zip,
                        shipping_country = :country,
                        updated_at = NOW()
                    WHERE id = :id";
            $stmt = $this->write->prepare($sql);
            $stmt->bindValue(':name', $shippingDetails->name ?? null);
            $stmt->bindValue(':addr1', $address->line1 ?? null);
            $stmt->bindValue(':addr2', $address->line2 ?? null);
            $stmt->bindValue(':city', $address->city ?? null);
            $stmt->bindValue(':state', $address->state ?? null);
            $stmt->bindValue(':zip', $address->postal_code ?? null);
            $stmt->bindValue(':country', $address->country ?? null);
            $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\Throwable $e) {
            // Silently fail
        }
    }

    /**
     * Log order event
     */
    private function logOrderEvent(int $orderId, string $type, string $description): void
    {
        try {
            $sql = "INSERT INTO `ngn_2025`.`order_events` (order_id, event_type, description, created_at)
                    VALUES (:order_id, :type, :desc, NOW())";
            $stmt = $this->write->prepare($sql);
            $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
            $stmt->bindValue(':type', $type);
            $stmt->bindValue(':desc', $description);
            $stmt->execute();
        } catch (\Throwable $e) {
            // Silently fail
        }
    }
}
