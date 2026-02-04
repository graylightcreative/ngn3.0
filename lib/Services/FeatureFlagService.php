<?php
/**
 * Feature Flag Service
 * Manages persistent feature flags for GA cutover and rollback
 * Stores in database with Redis cache for performance
 * Bible: GA Cutover & Hyper-Care Protocol
 */

namespace NGN\Lib\Services;

use PDO;
use Exception;

class FeatureFlagService
{
    private PDO $pdo;
    private array $cache = [];
    private bool $useRedis = false;
    private ?object $redis = null;

    public function __construct(PDO $pdo, ?object $redis = null)
    {
        $this->pdo = $pdo;
        if ($redis) {
            $this->redis = $redis;
            $this->useRedis = true;
        }
    }

    /**
     * Get feature flag value
     * Checks cache first, then database, falls back to .env
     *
     * @param string $flagName Flag name (e.g., 'FEATURE_PUBLIC_VIEW_MODE')
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function get(string $flagName, $default = null): mixed
    {
        // Check in-memory cache
        if (isset($this->cache[$flagName])) {
            return $this->cache[$flagName];
        }

        // Check Redis cache
        if ($this->useRedis) {
            $cached = $this->redis->get("feature_flag:{$flagName}");
            if ($cached !== false) {
                $value = json_decode($cached, true);
                $this->cache[$flagName] = $value;
                return $value;
            }
        }

        // Query database
        try {
            $stmt = $this->pdo->prepare("
                SELECT flag_value FROM feature_flags
                WHERE flag_name = ? AND active = 1
                LIMIT 1
            ");
            $stmt->execute([$flagName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $value = $this->deserializeValue($result['flag_value']);
                $this->cache[$flagName] = $value;

                // Cache in Redis for 1 hour
                if ($this->useRedis) {
                    $this->redis->setex("feature_flag:{$flagName}", 3600, json_encode($value));
                }

                return $value;
            }
        } catch (Exception $e) {
            // Fall back to default if database unavailable
            error_log("Feature flag database error: " . $e->getMessage());
        }

        // Fall back to default
        return $default;
    }

    /**
     * Set feature flag value (persistent)
     *
     * @param string $flagName Flag name
     * @param mixed $value Value to set
     * @param string|null $reason Reason for change (for audit log)
     * @param int|null $updatedBy User ID making the change
     * @return bool
     */
    public function set(string $flagName, mixed $value, ?string $reason = null, ?int $updatedBy = null): bool
    {
        try {
            $serialized = $this->serializeValue($value);

            // Use UPSERT pattern
            $stmt = $this->pdo->prepare("
                INSERT INTO feature_flags (flag_name, flag_value, active, reason, updated_by, updated_at)
                VALUES (?, ?, 1, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    flag_value = VALUES(flag_value),
                    reason = VALUES(reason),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()
            ");

            $success = $stmt->execute([$flagName, $serialized, $reason, $updatedBy]);

            if ($success) {
                // Clear cache
                $this->clearCache($flagName);
                $this->cache[$flagName] = $value;

                // Log change to audit trail
                $this->logFlagChange($flagName, $value, $reason, $updatedBy);
            }

            return $success;
        } catch (Exception $e) {
            error_log("Feature flag set error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Increment numeric flag (e.g., ROLLOUT_PERCENTAGE)
     * Useful for gradual rollouts
     *
     * @param string $flagName Flag name
     * @param int $increment Amount to increment (default: 1)
     * @param int $maxValue Maximum value (capped at this)
     * @return bool|int Returns new value on success, false on failure
     */
    public function increment(string $flagName, int $increment = 1, int $maxValue = 100): bool|int
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE feature_flags
                SET flag_value = LEAST(flag_value + ?, ?),
                    updated_at = NOW()
                WHERE flag_name = ? AND active = 1
            ");

            $stmt->execute([$increment, $maxValue, $flagName]);

            if ($stmt->rowCount() > 0) {
                // Clear cache
                $this->clearCache($flagName);

                // Get new value
                $newValue = $this->get($flagName);
                return $newValue;
            }

            return false;
        } catch (Exception $e) {
            error_log("Feature flag increment error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all active feature flags
     *
     * @return array
     */
    public function getAll(): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT flag_name, flag_value, reason, updated_by, updated_at
                FROM feature_flags
                WHERE active = 1
                ORDER BY flag_name
            ");
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $flags = [];
            foreach ($results as $row) {
                $flags[$row['flag_name']] = [
                    'value' => $this->deserializeValue($row['flag_value']),
                    'reason' => $row['reason'],
                    'updated_by' => $row['updated_by'],
                    'updated_at' => $row['updated_at']
                ];
            }

            return $flags;
        } catch (Exception $e) {
            error_log("Feature flags get all error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get flag change history (audit trail)
     *
     * @param string|null $flagName Filter by flag name (optional)
     * @param int $limit Number of recent changes to return
     * @return array
     */
    public function getHistory(?string $flagName = null, int $limit = 100): array
    {
        try {
            $query = "
                SELECT flag_name, old_value, new_value, reason, changed_by, changed_at
                FROM feature_flag_history
            ";

            $params = [];
            if ($flagName) {
                $query .= " WHERE flag_name = ?";
                $params[] = $flagName;
            }

            $query .= " ORDER BY changed_at DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Feature flag history error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if flag is enabled (for boolean flags)
     *
     * @param string $flagName Flag name
     * @return bool
     */
    public function isEnabled(string $flagName): bool
    {
        $value = $this->get($flagName, false);
        return (bool)$value;
    }

    /**
     * Serialize value for storage (handle types)
     */
    private function serializeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_numeric($value)) {
            return (string)$value;
        } elseif (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        return (string)$value;
    }

    /**
     * Deserialize value from storage (handle types)
     */
    private function deserializeValue(string $value): mixed
    {
        if ($value === 'true') {
            return true;
        } elseif ($value === 'false') {
            return false;
        } elseif (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float)$value : (int)$value;
        } elseif (str_starts_with($value, '{') || str_starts_with($value, '[')) {
            return json_decode($value, true);
        }

        return $value;
    }

    /**
     * Clear cache for flag (Redis + in-memory)
     */
    private function clearCache(string $flagName): void
    {
        unset($this->cache[$flagName]);

        if ($this->useRedis) {
            $this->redis->del("feature_flag:{$flagName}");
        }
    }

    /**
     * Log flag change to audit trail
     */
    private function logFlagChange(string $flagName, mixed $newValue, ?string $reason, ?int $updatedBy): void
    {
        try {
            // Get old value for comparison
            $oldValue = null;
            $stmt = $this->pdo->prepare("
                SELECT flag_value FROM feature_flags
                WHERE flag_name = ?
                ORDER BY updated_at DESC
                LIMIT 2
            ");
            $stmt->execute([$flagName]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($results) > 1) {
                $oldValue = $results[1]['flag_value'];
            }

            // Insert audit log
            $stmt = $this->pdo->prepare("
                INSERT INTO feature_flag_history (flag_name, old_value, new_value, reason, changed_by, changed_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $flagName,
                $oldValue,
                $this->serializeValue($newValue),
                $reason,
                $updatedBy
            ]);
        } catch (Exception $e) {
            error_log("Feature flag history log error: " . $e->getMessage());
        }
    }
}
