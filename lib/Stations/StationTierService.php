<?php
/**
 * Station Tier Service
 * Manages station subscription tiers, feature gating, and usage limit checks
 *
 * Extends subscription patterns from lib/Fans/SubscriptionService.php
 * Provides feature and limit checking for BYOS/PLN features
 */

namespace NGN\Lib\Stations;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Logging\LoggerFactory;
use Monolog\Logger;
use PDO;

class StationTierService
{
    private PDO $read;
    private PDO $write;
    private Logger $logger;
    private Config $config;

    // Default tier for new stations (free tier)
    private const DEFAULT_TIER_SLUG = 'station_connect';

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);
        $this->logger = LoggerFactory::create($config, 'station_tier');
    }

    /**
     * Get station's active subscription tier
     *
     * @param int $stationId Station ID
     * @return array|null Tier data with id, slug, name, features, limits, or null if not found
     */
    public function getStationTier(int $stationId): ?array
    {
        try {
            $stmt = $this->write->prepare("
                SELECT
                    t.id, t.slug, t.name, t.description,
                    t.price_monthly_cents, t.price_annual_cents,
                    t.features, t.limits,
                    us.id as subscription_id,
                    us.status as subscription_status,
                    us.current_period_end
                FROM `ngn_2025`.`user_subscriptions` us
                JOIN `ngn_2025`.`subscription_tiers` t ON us.tier_id = t.id
                WHERE us.entity_type = 'station'
                  AND us.entity_id = :stationId
                  AND us.status IN ('active', 'trialing')
                ORDER BY us.updated_at DESC
                LIMIT 1
            ");
            $stmt->execute([':stationId' => $stationId]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($subscription) {
                // Parse JSON columns
                $subscription['features'] = json_decode($subscription['features'] ?? '{}', true) ?: [];
                $subscription['limits'] = json_decode($subscription['limits'] ?? '{}', true) ?: [];
                return $subscription;
            }

            // If no active subscription, return default free tier
            return $this->getDefaultTier();

        } catch (\Throwable $e) {
            $this->logger->error('get_station_tier_failed', ['station_id' => $stationId, 'error' => $e->getMessage()]);
            return $this->getDefaultTier();
        }
    }

    /**
     * Check if station has a specific feature enabled
     *
     * @param int $stationId Station ID
     * @param string $featureKey Feature flag key (e.g., 'byos_upload', 'live_chat')
     * @return bool True if feature is enabled
     */
    public function hasFeature(int $stationId, string $featureKey): bool
    {
        try {
            $tier = $this->getStationTier($stationId);

            if (!$tier) {
                return false;
            }

            $features = $tier['features'] ?? [];
            return isset($features[$featureKey]) && $features[$featureKey] === true;

        } catch (\Throwable $e) {
            $this->logger->warning('has_feature_check_failed', [
                'station_id' => $stationId,
                'feature' => $featureKey,
                'error' => $e->getMessage()
            ]);
            return false; // Fail closed - deny access if check fails
        }
    }

    /**
     * Check if station is within usage limit for a specific feature
     *
     * @param int $stationId Station ID
     * @param string $limitKey Limit key (e.g., 'max_byos_tracks', 'max_playlists')
     * @param int $currentUsage Current usage count
     * @return bool True if within limit (or limit is -1 for unlimited)
     */
    public function checkLimit(int $stationId, string $limitKey, int $currentUsage): bool
    {
        try {
            $tier = $this->getStationTier($stationId);

            if (!$tier) {
                return false;
            }

            $limits = $tier['limits'] ?? [];
            $limitValue = $limits[$limitKey] ?? 0;

            // -1 means unlimited
            if ($limitValue === -1) {
                return true;
            }

            return $currentUsage < $limitValue;

        } catch (\Throwable $e) {
            $this->logger->warning('check_limit_failed', [
                'station_id' => $stationId,
                'limit_key' => $limitKey,
                'error' => $e->getMessage()
            ]);
            return false; // Fail closed
        }
    }

    /**
     * Get limit value for a feature
     *
     * @param int $stationId Station ID
     * @param string $limitKey Limit key
     * @return int Limit value (-1 for unlimited, 0 for not allowed)
     */
    public function getLimitValue(int $stationId, string $limitKey): int
    {
        try {
            $tier = $this->getStationTier($stationId);
            if (!$tier) {
                return 0;
            }

            $limits = $tier['limits'] ?? [];
            return $limits[$limitKey] ?? 0;

        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Initialize default tier for new station
     *
     * @param int $stationId Station ID
     * @param int $userId Station owner user ID
     * @return bool Success
     */
    public function initializeDefaultTier(int $stationId, int $userId): bool
    {
        try {
            // Get default tier
            $defaultTier = $this->getDefaultTier();
            if (!$defaultTier) {
                throw new \RuntimeException('Default tier not found');
            }

            // Create subscription record
            $stmt = $this->write->prepare("
                INSERT INTO user_subscriptions
                (user_id, tier_id, entity_type, entity_id, billing_cycle, status, current_period_start, current_period_end)
                VALUES (:userId, :tierId, 'station', :stationId, 'lifetime', 'active', NOW(), DATE_ADD(NOW(), INTERVAL 100 YEAR))
            ");

            $success = $stmt->execute([
                ':userId' => $userId,
                ':tierId' => $defaultTier['id'],
                ':stationId' => $stationId
            ]);

            if ($success) {
                $this->logger->info('station_tier_initialized', [
                    'station_id' => $stationId,
                    'user_id' => $userId,
                    'tier_id' => $defaultTier['id']
                ]);
            }

            return $success;

        } catch (\Throwable $e) {
            $this->logger->error('initialize_default_tier_failed', [
                'station_id' => $stationId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Upgrade station to a new tier
     *
     * @param int $stationId Station ID
     * @param int $tierId Subscription tier ID
     * @param string $billingCycle Billing cycle (monthly, annual, lifetime)
     * @param string|null $stripeSubscriptionId Optional Stripe subscription ID
     * @return bool Success
     */
    public function upgradeTier(
        int $stationId,
        int $tierId,
        string $billingCycle = 'monthly',
        ?string $stripeSubscriptionId = null
    ): bool {
        try {
            // Get station user ID
            $stmt = $this->write->prepare("SELECT user_id FROM `ngn_2025`.`stations` WHERE id = :stationId");
            $stmt->execute([':stationId' => $stationId]);
            $station = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$station) {
                throw new \RuntimeException('Station not found');
            }

            // Check if subscription already exists
            $existingStmt = $this->write->prepare("
                SELECT id FROM `ngn_2025`.`user_subscriptions`
                WHERE user_id = :userId AND entity_type = 'station' AND entity_id = :stationId
            ");
            $existingStmt->execute([':userId' => $station['user_id'], ':stationId' => $stationId]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing subscription
                $updateStmt = $this->write->prepare("
                    UPDATE user_subscriptions
                    SET tier_id = :tierId,
                        billing_cycle = :billingCycle,
                        status = 'active',
                        current_period_start = NOW(),
                        current_period_end = DATE_ADD(NOW(), INTERVAL 1 MONTH),
                        stripe_subscription_id = IFNULL(:stripeSubId, stripe_subscription_id)
                    WHERE id = :id
                ");
                return $updateStmt->execute([
                    ':id' => $existing['id'],
                    ':tierId' => $tierId,
                    ':billingCycle' => $billingCycle,
                    ':stripeSubId' => $stripeSubscriptionId
                ]);
            } else {
                // Create new subscription
                $insertStmt = $this->write->prepare("
                    INSERT INTO user_subscriptions
                    (user_id, tier_id, entity_type, entity_id, billing_cycle, status, stripe_subscription_id, current_period_start, current_period_end)
                    VALUES (:userId, :tierId, 'station', :stationId, :billingCycle, 'active', :stripeSubId, NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH))
                ");
                return $insertStmt->execute([
                    ':userId' => $station['user_id'],
                    ':tierId' => $tierId,
                    ':stationId' => $stationId,
                    ':billingCycle' => $billingCycle,
                    ':stripeSubId' => $stripeSubscriptionId
                ]);
            }

        } catch (\Throwable $e) {
            $this->logger->error('upgrade_tier_failed', [
                'station_id' => $stationId,
                'tier_id' => $tierId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get all available tiers for stations
     *
     * @return array Array of tier data
     */
    public function getAvailableTiers(): array
    {
        try {
            $stmt = $this->write->prepare("
                SELECT
                    id, slug, name, description,
                    price_monthly_cents, price_annual_cents,
                    features, limits, sort_order
                FROM `ngn_2025`.`subscription_tiers`
                WHERE is_active = 1 AND slug LIKE 'station_%'
                ORDER BY sort_order ASC
            ");
            $stmt->execute();
            $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

            // Parse JSON columns
            foreach ($tiers as &$tier) {
                $tier['features'] = json_decode($tier['features'] ?? '{}', true) ?: [];
                $tier['limits'] = json_decode($tier['limits'] ?? '{}', true) ?: [];
            }

            return $tiers;

        } catch (\Throwable $e) {
            $this->logger->error('get_available_tiers_failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get tier by slug
     *
     * @param string $slug Tier slug (e.g., 'station_pro')
     * @return array|null Tier data or null
     */
    public function getTierBySlug(string $slug): ?array
    {
        try {
            $stmt = $this->write->prepare("
                SELECT id, slug, name, description,
                       price_monthly_cents, price_annual_cents,
                       features, limits
                FROM `ngn_2025`.`subscription_tiers`
                WHERE slug = :slug AND is_active = 1
            ");
            $stmt->execute([':slug' => $slug]);
            $tier = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($tier) {
                $tier['features'] = json_decode($tier['features'] ?? '{}', true) ?: [];
                $tier['limits'] = json_decode($tier['limits'] ?? '{}', true) ?: [];
            }

            return $tier;

        } catch (\Throwable $e) {
            $this->logger->error('get_tier_by_slug_failed', ['slug' => $slug, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get tier comparison data for display
     *
     * @return array Array with 'tiers' and common feature keys
     */
    public function getTierComparison(): array
    {
        try {
            $tiers = $this->getAvailableTiers();

            // Extract all unique feature keys
            $allFeatures = [];
            foreach ($tiers as $tier) {
                foreach (array_keys($tier['features']) as $key) {
                    $allFeatures[$key] = true;
                }
            }

            return [
                'tiers' => $tiers,
                'features' => array_keys($allFeatures)
            ];

        } catch (\Throwable $e) {
            $this->logger->error('get_tier_comparison_failed', ['error' => $e->getMessage()]);
            return ['tiers' => [], 'features' => []];
        }
    }

    // ========================================================================
    // PRIVATE HELPER METHODS
    // ========================================================================

    /**
     * Get default (free) tier for stations
     *
     * @return array|null Default tier data
     */
    private function getDefaultTier(): ?array
    {
        try {
            $stmt = $this->write->prepare("
                SELECT id, slug, name, features, limits, description
                FROM `ngn_2025`.`subscription_tiers`
                WHERE slug = :slug AND is_active = 1
            ");
            $stmt->execute([':slug' => self::DEFAULT_TIER_SLUG]);
            $tier = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($tier) {
                $tier['features'] = json_decode($tier['features'] ?? '{}', true) ?: [];
                $tier['limits'] = json_decode($tier['limits'] ?? '{}', true) ?: [];
            }

            return $tier;

        } catch (\Throwable $e) {
            $this->logger->error('get_default_tier_failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
