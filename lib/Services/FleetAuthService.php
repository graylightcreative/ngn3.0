<?php
namespace NGN\Lib\Services;

/**
 * Fleet Identity Service - Beacon SSO Integration
 *
 * Provides centralized identity validation via Beacon (sovereign fleet node).
 * Uses HMAC-based request signing for inter-node security.
 *
 * Reference: FLEET.md Section 1 (Identity Handshake)
 */
class FleetAuthService {

    private static $beaconUrl = null;
    private static $apiKey = null;
    private static $secretKey = null;
    private static $sessionCache = [];

    /**
     * Initialize Fleet credentials from environment variables
     * (Set by nexus tenant-register during onboarding)
     */
    private static function init(): void {
        if (self::$beaconUrl === null) {
            self::$beaconUrl = $_ENV['BEACON_URL'] ?? '';
            self::$apiKey = $_ENV['BEACON_API_KEY'] ?? '';
            self::$secretKey = $_ENV['BEACON_SECRET_KEY'] ?? '';

            if (!self::$beaconUrl || !self::$apiKey || !self::$secretKey) {
                error_log('FLEET_AUTH_INIT_ERROR: Missing Fleet credentials in .env');
                error_log('Required: BEACON_URL, BEACON_API_KEY, BEACON_SECRET_KEY');
            }
        }
    }

    /**
     * Validate fleet_token via Beacon SSO endpoint
     *
     * Flow:
     * 1. Extract token from cookie
     * 2. Create HMAC-signed request to Beacon
     * 3. Beacon validates token and returns session data
     * 4. Cache result in memory
     *
     * @return array|null Session data if valid, null if invalid/missing
     */
    public static function checkHandshake(): ?array {
        self::init();

        $token = $_COOKIE['fleet_token'] ?? null;
        if (!$token) return null;

        // Check in-memory cache first (avoids repeated Beacon calls)
        if (isset(self::$sessionCache[$token])) {
            return self::$sessionCache[$token];
        }

        if (!self::$beaconUrl || !self::$apiKey || !self::$secretKey) {
            error_log('FLEET_AUTH: Credentials not configured');
            return null;
        }

        try {
            // Call Beacon /v1/auth/verify endpoint with HMAC signature
            $response = self::callBeaconWithHmac('/v1/auth/verify', [
                'token' => $token
            ]);

            if ($response && isset($response['valid']) && $response['valid']) {
                // Cache successful validation
                self::$sessionCache[$token] = $response;
                return $response;
            }

            return null;
        } catch (\Throwable $e) {
            error_log('FLEET_AUTH_ERROR: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Call Beacon API with HMAC request signing
     *
     * Signs request with X-GL-API-KEY, X-GL-TIMESTAMP, X-GL-SIGNATURE headers
     *
     * @param string $endpoint API path (e.g., '/v1/auth/verify')
     * @param array $payload Request body
     * @return array|null Parsed JSON response
     */
    private static function callBeaconWithHmac(string $endpoint, array $payload): ?array {
        $timestamp = time();
        $url = self::$beaconUrl . $endpoint;

        // Build canonical request for HMAC signing
        $jsonPayload = json_encode($payload);
        $canonical = implode("\n", [
            'POST',
            $endpoint,
            $timestamp,
            hash('sha256', $jsonPayload)
        ]);

        // Generate HMAC-SHA256 signature
        $signature = hash_hmac('sha256', $canonical, self::$secretKey);

        // Make request with HMAC headers
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'X-GL-API-KEY: ' . self::$apiKey,
                    'X-GL-TIMESTAMP: ' . $timestamp,
                    'X-GL-SIGNATURE: ' . $signature
                ],
                'content' => $jsonPayload,
                'timeout' => 5
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Clear session cache (useful for testing/logout)
     */
    public static function clearCache(): void {
        self::$sessionCache = [];
    }
}
