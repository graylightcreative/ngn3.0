<?php

namespace NGN\Lib\Helpers;

/**
 * Tier-based Feature Gating Helper
 *
 * Manages tier checking and feature access control
 */
class TierHelper {

    /**
     * Feature map by tier
     */
    private static $featureMap = [
        'free' => [
            'basic_profile',
            'upload_content',
            'view_charts'
        ],
        'pro' => [
            'basic_profile',
            'upload_content',
            'view_charts',
            'basic_analytics',
            'priority_support',
            'remove_ads'
        ],
        'premium' => [
            'basic_profile',
            'upload_content',
            'view_charts',
            'basic_analytics',
            'priority_support',
            'custom_domain',
            'api_access',
            'remove_ads'
        ],
        'enterprise' => [
            'all' // Enterprise has all features
        ]
    ];

    /**
     * Get user's current tier from Stripe subscription
     *
     * @param \PDO $pdo Database connection
     * @param int $userId User ID
     * @return string Tier: 'free', 'pro', 'premium', or 'enterprise'
     */
    public static function getUserTier(\PDO $pdo, int $userId): string {
        try {
            $stmt = $pdo->prepare("
                SELECT tier FROM `nextgennoise`.`users`
                WHERE Id = ? LIMIT 1
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result && !empty($result['tier'])) {
                return $result['tier'];
            }

            // TODO: Check Stripe subscription for live tier determination
            // For now, return free tier as default

            return 'free';
        } catch (\Throwable $e) {
            error_log("Error getting user tier: " . $e->getMessage());
            return 'free'; // Default to free tier on error
        }
    }

    /**
     * Check if user has access to a specific feature
     *
     * @param \PDO $pdo Database connection
     * @param int $userId User ID
     * @param string $feature Feature name
     * @return bool True if user has access to feature
     */
    public static function hasFeature(\PDO $pdo, int $userId, string $feature): bool {
        $tier = self::getUserTier($pdo, $userId);

        // Enterprise has all features
        if ($tier === 'enterprise') {
            return true;
        }

        // Check if feature is in tier's feature list
        $tierFeatures = self::$featureMap[$tier] ?? [];
        return in_array($feature, $tierFeatures);
    }

    /**
     * Check if user has access to a feature, with optional redirect
     * Throws exception if feature not available
     *
     * @param \PDO $pdo Database connection
     * @param int $userId User ID
     * @param string $feature Feature name
     * @param string $redirectUrl URL to redirect to if feature unavailable
     * @throws \RuntimeException If feature not available
     */
    public static function requireFeature(\PDO $pdo, int $userId, string $feature, string $redirectUrl = '/pricing'): void {
        if (!self::hasFeature($pdo, $userId, $feature)) {
            header("Location: $redirectUrl");
            exit;
        }
    }

    /**
     * Get all features available to user
     *
     * @param \PDO $pdo Database connection
     * @param int $userId User ID
     * @return array List of available features
     */
    public static function getAvailableFeatures(\PDO $pdo, int $userId): array {
        $tier = self::getUserTier($pdo, $userId);

        if ($tier === 'enterprise') {
            return array_merge(
                self::$featureMap['free'],
                self::$featureMap['pro'],
                self::$featureMap['premium']
            );
        }

        return self::$featureMap[$tier] ?? self::$featureMap['free'];
    }

    /**
     * Get tier info including features and pricing
     *
     * @return array Tier information
     */
    public static function getTierInfo(): array {
        return [
            'free' => [
                'name' => 'Free',
                'price' => 0,
                'interval' => 'forever',
                'description' => 'Get started with the essentials',
                'features' => [
                    'Basic Profile',
                    'Upload Content',
                    'View Charts'
                ],
                'stripe_product_id' => null
            ],
            'pro' => [
                'name' => 'Pro',
                'price' => 9,
                'interval' => 'month',
                'description' => 'For growing creators',
                'features' => [
                    'All Free features',
                    'Advanced Analytics',
                    'Priority Support',
                    'Remove Ads'
                ],
                'stripe_product_id' => 'prod_pro_ngn' // TODO: Get from config
            ],
            'premium' => [
                'name' => 'Premium',
                'price' => 29,
                'interval' => 'month',
                'description' => 'For professionals',
                'features' => [
                    'All Pro features',
                    'Custom Domain',
                    'API Access',
                    'Advanced Branding'
                ],
                'stripe_product_id' => 'prod_premium_ngn' // TODO: Get from config
            ],
            'enterprise' => [
                'name' => 'Enterprise',
                'price' => null,
                'interval' => 'custom',
                'description' => 'For large organizations',
                'features' => [
                    'All Premium features',
                    'Dedicated Support',
                    'Custom Integrations',
                    'SLA Guarantee'
                ],
                'stripe_product_id' => null
            ]
        ];
    }

    /**
     * Get next tier (for upgrade prompts)
     *
     * @param string $currentTier Current tier
     * @return string|null Next tier, or null if already enterprise
     */
    public static function getNextTier(string $currentTier): ?string {
        $tiers = ['free' => 'pro', 'pro' => 'premium', 'premium' => 'enterprise'];
        return $tiers[$currentTier] ?? null;
    }

    /**
     * Get upgrade prompt message
     *
     * @param string $feature Feature name
     * @param string $currentTier Current tier
     * @return string Upgrade message
     */
    public static function getUpgradePrompt(string $feature, string $currentTier): string {
        $messages = [
            'basic_analytics' => 'Analytics are available on Pro tier and above.',
            'priority_support' => 'Priority support is available on Pro tier and above.',
            'custom_domain' => 'Custom domains are available on Premium tier and above.',
            'api_access' => 'API access is available on Premium tier and above.',
            'remove_ads' => 'Ad-free experience is available on Pro tier and above.'
        ];

        $message = $messages[$feature] ?? "This feature requires an upgrade.";

        $nextTier = self::getNextTier($currentTier);
        if ($nextTier) {
            $tierInfo = self::getTierInfo();
            $nextTierName = $tierInfo[$nextTier]['name'] ?? ucfirst($nextTier);
            $message .= " Upgrade to $nextTierName to unlock it.";
        }

        return $message;
    }

    /**
     * Check if feature is available in multiple tiers
     *
     * @param string $feature Feature name
     * @return array Tiers that have this feature
     */
    public static function getFeatureTiers(string $feature): array {
        $tiers = [];

        foreach (self::$featureMap as $tier => $features) {
            if ($tier === 'enterprise' || in_array($feature, $features)) {
                $tiers[] = $tier;
            }
        }

        return $tiers;
    }
}
