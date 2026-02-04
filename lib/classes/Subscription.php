<?php

namespace App\Services;

use Stripe\StripeClient;

class Subscription
{
    private Stripe $stripe;

    public function __construct(string $apiKey)
    {
        // Initialize the Stripe wrapper class with the provided API key
        $this->stripe = new Stripe($apiKey);
    }

    /**
     * Create a new subscription for a customer.
     *
     * @param string $customerId
     * @param string $priceId
     * @param array $options
     * @return array
     */
    public function createSubscription(string $customerId, string $priceId, array $options = []): array
    {
        return $this->stripe->createSubscription(array_merge([
            'customer' => $customerId,
            'items' => [
                ['price' => $priceId]
            ]
        ], $options));
    }

    /**
     * Retrieve a subscription by its ID.
     *
     * @param string $subscriptionId
     * @return array
     */
    public function getSubscription(string $subscriptionId): array
    {
        return $this->stripe->retrieveSubscription($subscriptionId);
    }

    /**
     * Cancel a subscription.
     *
     * @param string $subscriptionId
     * @param bool $atPeriodEnd
     * @return array
     */
    public function cancelSubscription(string $subscriptionId, bool $atPeriodEnd = true): array
    {
        $data = ['cancel_at_period_end' => $atPeriodEnd];
        return $this->stripe->cancelSubscription($subscriptionId, $data);
    }

    /**
     * Verify if a customer has an active subscription.
     *
     * @param string $customerId
     * @return bool
     */
    public function hasActiveSubscription(string $customerId): bool
    {
        $subscriptions = $this->stripe->listSubscriptions([
            'customer' => $customerId,
            'status' => 'active'
        ]);

        return count($subscriptions['data']) > 0;
    }

    /**
     * Gatekeeping - check if a customer can access a feature based on a subscription.
     *
     * @param string $customerId
     * @param string|null $requiredPlanId
     * @return bool
     */
    public function canAccessFeature(string $customerId, ?string $requiredPlanId = null): bool
    {
        $subscriptions = $this->stripe->listSubscriptions([
            'customer' => $customerId,
            'status' => 'active'
        ]);

        foreach ($subscriptions['data'] as $subscription) {
            foreach ($subscription['items']['data'] as $item) {
                if ($requiredPlanId === null || $item['price']['id'] === $requiredPlanId) {
                    return true;
                }
            }
        }

        return false;
    }
}