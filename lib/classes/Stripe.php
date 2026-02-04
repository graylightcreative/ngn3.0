<?php

use Stripe\StripeClient;

class Stripe
{
    private StripeClient $client;

    public function __construct(string $apiKey)
    {
        $this->client = new StripeClient($apiKey);
    }

    /**
     * Create a Payment Intent for one-time payments (e.g., tickets, merch, etc.)
     */
    public function createPaymentIntent(array $params): array
    {
        if (!isset($params['amount'], $params['currency'])) {
            throw new InvalidArgumentException('Amount and currency are required.');
        }

        $paymentIntent = $this->client->paymentIntents->create([
            'amount' => $params['amount'],
            'currency' => $params['currency'],
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => $params['metadata'] ?? [],
        ]);

        return $paymentIntent->toArray();
    }

    /**
     * Retrieve an existing Payment Intent by ID
     */
    public function retrievePaymentIntent(string $paymentIntentId): array
    {
        return $this->client->paymentIntents->retrieve($paymentIntentId);
    }

    /**
     * Create a Subscription
     * This requires the customer ID and a price ID (from your Stripe Dashboard)
     */
    public function createSubscription(array $params): array
    {
        if (!isset($params['customer'], $params['items'])) {
            throw new InvalidArgumentException('Customer ID and items are required.');
        }

        // Build the subscription payload
        $subscriptionData = [
            'customer' => $params['customer'],
            'items' => array_map(function ($item) {
                if (isset($item['price'])) {
                    // Use predefined price ID
                    return ['price' => $item['price']];
                }

                if (isset($item['price_data'])) {
                    // Ensure required fields in price_data
                    if (empty($item['price_data']['currency']) || empty($item['price_data']['unit_amount'])) {
                        throw new InvalidArgumentException('Currency and unit_amount are required in price_data.');
                    }

                    // Ensure price_data is properly nested
                    return [
                        'price_data' => [
                            'currency' => $item['price_data']['currency'],
                            'product_data' => [
                                'name' => $item['price_data']['product_data']['name'],
                                'description' => $item['price_data']['product_data']['description'] ?? null,
                            ],
                            'unit_amount' => $item['price_data']['unit_amount'], // Amount in cents
                        ],
                    ];
                }

                throw new InvalidArgumentException('Either price or price_data must be provided for subscription items.');
            }, $params['items']),
            'metadata' => $params['metadata'] ?? [],
            'billing_cycle_anchor' => $params['billing_cycle_anchor'] ?? time(),
            'proration_behavior' => $params['proration_behavior'] ?? 'none',
        ];

        // Log and debug the payload before sending it to Stripe
        error_log(json_encode($subscriptionData, JSON_PRETTY_PRINT));

        // Create the subscription
        return $this->client->subscriptions->create($subscriptionData)->toArray();
    }
    /**
     * Retrieve a Subscription
     */
    public function retrieveSubscription(string $subscriptionId): array
    {
        return $this->client->subscriptions->retrieve($subscriptionId);
    }

    /**
     * Cancel a Subscription
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        return $this->client->subscriptions->cancel($subscriptionId);
    }

    /**
     * Create a Setup Intent to save a card for future use
     */
    public function createSetupIntent(array $params = []): array
    {
        return $this->client->setupIntents->create([
            'customer' => $params['customer'] ?? null,
            'metadata' => $params['metadata'] ?? [],
        ]);
    }

    /**
     * Create a new customer
     */
    public function createCustomer(array $params): array
    {
        if (!isset($params['email'])) {
            throw new InvalidArgumentException('Email is required to create a customer.');
        }

        $customer = $this->client->customers->create([
            'email' => $params['email'],
            'name' => $params['name'] ?? null,
            'metadata' => $params['metadata'] ?? [],
        ]);

        return $customer->toArray();
    }

    /**
     * Retrieve a Stripe customer by customer ID
     */
    public function retrieveCustomer(string $customerId): array
    {
        return $this->client->customers->retrieve($customerId);
    }

    /**
     * Refund a Payment Intent or Charge
     */
    public function createRefund(array $params): array
    {
        if (!isset($params['payment_intent']) && !isset($params['charge'])) {
            throw new InvalidArgumentException('Payment intent or charge is required for a refund.');
        }

        return $this->client->refunds->create($params);
    }

    /**
     * Handle Webhooks (To be implemented in a webhook handler)
     * Example: Handle events like 'payment_intent.succeeded', 'invoice.payment_failed'
     */
    public function handleWebhook(string $payload, string $sigHeader, string $secret): array
    {
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                $secret
            );

            return $event;
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            throw new RuntimeException('Webhook signature verification failed.');
        }
    }

    // NEXT STEPS
    //1. Use the **frontend** with Stripe.js and Stripe Elements for card input.
    //2. Ensure you have APIs to:
    //    - Create Payment Intents (e.g., for donations, merchandise).
    //    - Create and manage Subscriptions with different price IDs.
    //
    //3. Add webhook handling in your backend (e.g., in a controller that listens to Stripe events).
    //4. Define metadata for each transaction for better tracking and integration with your project.
}