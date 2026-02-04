<?php
/**
 * Geo-Blocking Service
 * Manages territory-based access control for licensing compliance
 * Uses IP detection to determine territory and enforce allow/block lists
 */

namespace NGN\Lib\Stations;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Logging\LoggerFactory;
use Monolog\Logger;
use PDO;

class GeoBlockingService
{
    private PDO $read;
    private PDO $write;
    private Logger $logger;
    private Config $config;

    // Cache for territory detections (IP => territory)
    private array $territoryCache = [];

    // Valid ISO 3166-1 alpha-2 country codes (subset for common licensing territories)
    private const VALID_TERRITORIES = [
        'US', 'CA', 'GB', 'AU', 'NZ', 'DE', 'FR', 'IT', 'ES', 'SE',
        'NO', 'DK', 'NL', 'BE', 'CH', 'AT', 'JP', 'KR', 'SG', 'MX'
    ];

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);
        $this->logger = LoggerFactory::create($config, 'geoblocking');
    }

    /**
     * Check if territory is allowed for entity
     *
     * @param int $stationId Station ID
     * @param string $entityType Type of entity: playlist, station_content, station
     * @param int $entityId ID of the entity
     * @param string $territory ISO 3166-1 alpha-2 territory code (or null to auto-detect)
     * @param string|null $ipAddress IP address for auto-detection (if territory is null)
     * @return bool True if allowed
     */
    public function isAllowed(
        int $stationId,
        string $entityType,
        int $entityId,
        string $territory = '',
        ?string $ipAddress = null
    ): bool {
        try {
            // Auto-detect territory from IP if not provided
            if (empty($territory) && $ipAddress) {
                $territory = $this->detectTerritory($ipAddress);
                if (empty($territory)) {
                    // If detection fails, deny by default (fail closed)
                    $this->logger->warning('territory_detection_failed', [
                        'ip_address' => substr($ipAddress, 0, 15),
                        'entity_type' => $entityType
                    ]);
                    return false;
                }
            }

            // If still no territory, allow (no restrictions)
            if (empty($territory)) {
                return true;
            }

            // Check for geo restrictions
            $stmt = $this->write->prepare("
                SELECT rule_type, territories
                FROM `ngn_2025`.`geoblocking_rules`
                WHERE entity_type = :entityType AND entity_id = :entityId
                LIMIT 1
            ");
            $stmt->execute([
                ':entityType' => $entityType,
                ':entityId' => $entityId
            ]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);

            // No restriction found - allow by default
            if (!$rule) {
                return true;
            }

            $territories = json_decode($rule['territories'] ?? '[]', true) ?? [];

            // Apply rule
            if ($rule['rule_type'] === 'allow') {
                // Allow list: territory must be in list
                return in_array(strtoupper($territory), $territories);
            } else {
                // Block list: territory must NOT be in list
                return !in_array(strtoupper($territory), $territories);
            }

        } catch (\Throwable $e) {
            $this->logger->error('is_allowed_check_failed', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage()
            ]);
            // Fail closed - deny if check fails
            return false;
        }
    }

    /**
     * Detect territory from IP address
     *
     * MVP: Uses free ip-api.com service
     * Production: Upgrade to MaxMind GeoIP2 database
     *
     * @param string $ipAddress IP address (IPv4 or IPv6)
     * @return string|null ISO 3166-1 alpha-2 territory code or null on failure
     */
    public function detectTerritory(string $ipAddress): ?string
    {
        // Check cache first
        if (isset($this->territoryCache[$ipAddress])) {
            return $this->territoryCache[$ipAddress];
        }

        try {
            // Try CloudFlare headers first (if behind CDN)
            if (isset($_SERVER['CF_IPCOUNTRY']) && strlen($_SERVER['CF_IPCOUNTRY']) === 2) {
                $territory = strtoupper($_SERVER['CF_IPCOUNTRY']);
                $this->territoryCache[$ipAddress] = $territory;
                return $territory;
            }

            // Validate IP format
            if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
                $this->logger->warning('invalid_ip_format', ['ip' => substr($ipAddress, 0, 15)]);
                return null;
            }

            // Call ip-api.com (free tier: 45 requests/minute)
            $url = "http://ip-api.com/json/" . urlencode($ipAddress) . "?fields=countryCode";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 2, // 2 second timeout
                    'user_agent' => 'NGN/2.0'
                ]
            ]);

            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                $this->logger->warning('territory_detection_api_error', [
                    'ip' => substr($ipAddress, 0, 15),
                    'api' => 'ip-api.com'
                ]);
                return null;
            }

            $data = json_decode($response, true);
            if (!isset($data['countryCode'])) {
                return null;
            }

            $territory = strtoupper($data['countryCode']);

            // Validate territory code
            if (!$this->isValidTerritory($territory)) {
                return null;
            }

            // Cache result
            $this->territoryCache[$ipAddress] = $territory;
            return $territory;

        } catch (\Throwable $e) {
            $this->logger->error('detect_territory_failed', [
                'ip' => substr($ipAddress, 0, 15),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Set geo-blocking restrictions for entity
     *
     * @param int $stationId Station ID
     * @param string $entityType Type: playlist, station_content, station
     * @param int $entityId Entity ID
     * @param string $ruleType Rule type: allow (whitelist) or block (blacklist)
     * @param array $territories Array of ISO 3166-1 alpha-2 codes
     * @return bool Success
     * @throws \InvalidArgumentException on invalid input
     */
    public function setRestrictions(
        int $stationId,
        string $entityType,
        int $entityId,
        string $ruleType,
        array $territories
    ): bool {
        try {
            // Validate inputs
            if (!in_array($ruleType, ['allow', 'block'])) {
                throw new \InvalidArgumentException("Invalid rule type: $ruleType");
            }

            // Validate territories
            $validTerritories = [];
            foreach ($territories as $territory) {
                $territory = strtoupper(trim($territory));
                if ($this->isValidTerritory($territory)) {
                    $validTerritories[] = $territory;
                }
            }

            if (empty($validTerritories)) {
                throw new \InvalidArgumentException("No valid territories provided");
            }

            // Delete existing rule
            $deleteStmt = $this->write->prepare("
                DELETE FROM geoblocking_rules
                WHERE entity_type = :entityType AND entity_id = :entityId
            ");
            $deleteStmt->execute([
                ':entityType' => $entityType,
                ':entityId' => $entityId
            ]);

            // Insert new rule
            $insertStmt = $this->write->prepare("
                INSERT INTO geoblocking_rules
                (entity_type, entity_id, rule_type, territories)
                VALUES (:entityType, :entityId, :ruleType, :territories)
            ");

            $success = $insertStmt->execute([
                ':entityType' => $entityType,
                ':entityId' => $entityId,
                ':ruleType' => $ruleType,
                ':territories' => json_encode($validTerritories)
            ]);

            if ($success) {
                $this->logger->info('geoblocking_rule_set', [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'rule_type' => $ruleType,
                    'territory_count' => count($validTerritories)
                ]);
            }

            return $success;

        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('set_restrictions_validation_failed', [
                'entity_type' => $entityType,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('set_restrictions_failed', [
                'entity_type' => $entityType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get restrictions for entity
     *
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @return array|null Restriction rule or null if none exist
     */
    public function getRestrictions(string $entityType, int $entityId): ?array
    {
        try {
            $stmt = $this->write->prepare("
                SELECT rule_type, territories
                FROM `ngn_2025`.`geoblocking_rules`
                WHERE entity_type = :entityType AND entity_id = :entityId
            ");
            $stmt->execute([
                ':entityType' => $entityType,
                ':entityId' => $entityId
            ]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rule) {
                return null;
            }

            $rule['territories'] = json_decode($rule['territories'] ?? '[]', true) ?? [];
            return $rule;

        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Remove geo-blocking restrictions
     *
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @return bool Success
     */
    public function removeRestrictions(string $entityType, int $entityId): bool
    {
        try {
            $stmt = $this->write->prepare("
                DELETE FROM geoblocking_rules
                WHERE entity_type = :entityType AND entity_id = :entityId
            ");
            return $stmt->execute([
                ':entityType' => $entityType,
                ':entityId' => $entityId
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('remove_restrictions_failed', [
                'entity_type' => $entityType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get all available territories for selection
     *
     * @return array Array of territory codes
     */
    public function getAvailableTerritories(): array
    {
        return self::VALID_TERRITORIES;
    }

    /**
     * Get territory name from code
     *
     * @param string $code ISO 3166-1 alpha-2 code
     * @return string|null Territory name or null
     */
    public function getTerritoryName(string $code): ?string
    {
        $names = [
            'US' => 'United States',
            'CA' => 'Canada',
            'GB' => 'United Kingdom',
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'CH' => 'Switzerland',
            'AT' => 'Austria',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'SG' => 'Singapore',
            'MX' => 'Mexico'
        ];

        return $names[strtoupper($code)] ?? null;
    }

    // ========================================================================
    // PRIVATE HELPER METHODS
    // ========================================================================

    /**
     * Check if territory code is valid
     *
     * @param string $territory ISO 3166-1 alpha-2 code
     * @return bool Valid
     */
    private function isValidTerritory(string $territory): bool
    {
        return strlen($territory) === 2 && in_array(strtoupper($territory), self::VALID_TERRITORIES);
    }
}
