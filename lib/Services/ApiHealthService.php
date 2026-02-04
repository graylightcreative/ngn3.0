<?php

namespace NGN\Lib\Services;

use NGN\Lib\Security\EncryptionService;
use PDO;
use Exception;

/**
 * API Health Monitoring and Configuration Service
 *
 * Manages API credentials, tests connections, logs health status
 * Critical for: Stripe, Printful, Meta, Spotify, TikTok integrations
 */
class ApiHealthService
{
    private PDO $pdo;
    private EncryptionService $encryption;

    public function __construct(PDO $pdo, EncryptionService $encryption = null)
    {
        $this->pdo = $pdo;
        $this->encryption = $encryption ?? new EncryptionService();
    }

    /**
     * Save API configuration (encrypted)
     *
     * @param string $service Service name (stripe, printful, etc.)
     * @param string $environment Environment (live, sandbox, test)
     * @param array $config Configuration data
     * @return array Result with id and status
     */
    public function saveConfig(string $service, string $environment, array $config): array
    {
        // Validate required fields
        if (empty($service) || empty($environment)) {
            throw new Exception('Service and environment are required');
        }

        // Encrypt sensitive fields
        $encryptedApiKey = !empty($config['api_key']) ? $this->encryption->encrypt($config['api_key']) : null;
        $encryptedApiSecret = !empty($config['api_secret']) ? $this->encryption->encrypt($config['api_secret']) : null;
        $encryptedWebhookSecret = !empty($config['webhook_secret']) ? $this->encryption->encrypt($config['webhook_secret']) : null;

        // Additional config as JSON
        $configJson = !empty($config['config_json']) ? json_encode($config['config_json']) : null;

        // Check if config exists
        $stmt = $this->pdo->prepare("
            SELECT id FROM `ngn_2025`.`api_config`
            WHERE service = :service AND environment = :environment
            LIMIT 1
        ");
        $stmt->execute([
            ':service' => $service,
            ':environment' => $environment
        ]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update
            $stmt = $this->pdo->prepare("
                UPDATE `ngn_2025`.`api_config` SET
                    api_key = :api_key,
                    api_secret = :api_secret,
                    webhook_secret = :webhook_secret,
                    config_json = :config_json,
                    is_active = :is_active,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':api_key' => $encryptedApiKey,
                ':api_secret' => $encryptedApiSecret,
                ':webhook_secret' => $encryptedWebhookSecret,
                ':config_json' => $configJson,
                ':is_active' => $config['is_active'] ?? true,
                ':id' => $existing['id']
            ]);

            return [
                'success' => true,
                'id' => $existing['id'],
                'action' => 'updated'
            ];
        } else {
            // Insert
            $stmt = $this->pdo->prepare("
                INSERT INTO `ngn_2025`.`api_config` (
                    service, environment, api_key, api_secret, webhook_secret,
                    config_json, is_active, created_at, updated_at
                ) VALUES (
                    :service, :environment, :api_key, :api_secret, :webhook_secret,
                    :config_json, :is_active, NOW(), NOW()
                )
            ");
            $stmt->execute([
                ':service' => $service,
                ':environment' => $environment,
                ':api_key' => $encryptedApiKey,
                ':api_secret' => $encryptedApiSecret,
                ':webhook_secret' => $encryptedWebhookSecret,
                ':config_json' => $configJson,
                ':is_active' => $config['is_active'] ?? true
            ]);

            return [
                'success' => true,
                'id' => $this->pdo->lastInsertId(),
                'action' => 'created'
            ];
        }
    }

    /**
     * Get API configuration (decrypted)
     *
     * @param string $service Service name
     * @param string $environment Environment
     * @param bool $maskSecrets Mask secrets for display (default: true)
     * @return array|null Configuration or null if not found
     */
    public function getConfig(string $service, string $environment, bool $maskSecrets = true): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `ngn_2025`.`api_config`
            WHERE service = :service AND environment = :environment
            LIMIT 1
        ");
        $stmt->execute([
            ':service' => $service,
            ':environment' => $environment
        ]);

        $config = $stmt->fetch();

        if (!$config) {
            return null;
        }

        // Decrypt secrets
        if ($config['api_key']) {
            try {
                $decrypted = $this->encryption->decrypt($config['api_key']);
                $config['api_key_decrypted'] = $maskSecrets ? EncryptionService::mask($decrypted) : $decrypted;
                $config['has_api_key'] = true;
            } catch (Exception $e) {
                $config['api_key_decrypted'] = null;
                $config['has_api_key'] = false;
            }
        }

        if ($config['api_secret']) {
            try {
                $decrypted = $this->encryption->decrypt($config['api_secret']);
                $config['api_secret_decrypted'] = $maskSecrets ? EncryptionService::mask($decrypted) : $decrypted;
                $config['has_api_secret'] = true;
            } catch (Exception $e) {
                $config['api_secret_decrypted'] = null;
                $config['has_api_secret'] = false;
            }
        }

        if ($config['webhook_secret']) {
            try {
                $decrypted = $this->encryption->decrypt($config['webhook_secret']);
                $config['webhook_secret_decrypted'] = $maskSecrets ? EncryptionService::mask($decrypted) : $decrypted;
                $config['has_webhook_secret'] = true;
            } catch (Exception $e) {
                $config['webhook_secret_decrypted'] = null;
                $config['has_webhook_secret'] = false;
            }
        }

        // Parse config JSON
        if ($config['config_json']) {
            $config['config_json_parsed'] = json_decode($config['config_json'], true);
        }

        return $config;
    }

    /**
     * Get all API configurations
     *
     * @param bool $maskSecrets Mask secrets for display
     * @return array List of configurations
     */
    public function getAllConfigs(bool $maskSecrets = true): array
    {
        $stmt = $this->pdo->query("
            SELECT * FROM `ngn_2025`.`api_config`
            ORDER BY service, environment
        ");

        $configs = [];
        while ($row = $stmt->fetch()) {
            $configs[] = $this->getConfig($row['service'], $row['environment'], $maskSecrets);
        }

        return $configs;
    }

    /**
     * Test Stripe connection
     *
     * @param string $environment Environment to test (live or sandbox)
     * @return array Result with status and details
     */
    public function testStripeConnection(string $environment): array
    {
        $config = $this->getConfig('stripe', $environment, false);

        if (!$config || !$config['api_secret_decrypted']) {
            return [
                'success' => false,
                'status' => 'failure',
                'error' => 'Stripe API key not configured'
            ];
        }

        $apiKey = $config['api_secret_decrypted'];
        $startTime = microtime(true);

        try {
            // Test Stripe API: Retrieve balance
            $ch = curl_init('https://api.stripe.com/v1/balance');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                ],
                CURLOPT_TIMEOUT => 10
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responseTime = (microtime(true) - $startTime) * 1000; // ms
            curl_close($ch);

            if ($httpCode === 200) {
                $this->logHealthCheck($config['id'], 'stripe', $environment, 'success', $responseTime);

                return [
                    'success' => true,
                    'status' => 'success',
                    'response_time_ms' => round($responseTime, 2),
                    'message' => 'Stripe API connection successful'
                ];
            } else {
                $error = json_decode($response, true);
                $errorMessage = $error['error']['message'] ?? 'Unknown error';

                $this->logHealthCheck($config['id'], 'stripe', $environment, 'auth_error', $responseTime, $errorMessage, $httpCode);

                return [
                    'success' => false,
                    'status' => 'auth_error',
                    'http_status' => $httpCode,
                    'error' => $errorMessage
                ];
            }
        } catch (Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;
            $this->logHealthCheck($config['id'], 'stripe', $environment, 'failure', $responseTime, $e->getMessage());

            return [
                'success' => false,
                'status' => 'failure',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test webhook signature validation
     *
     * @param string $service Service name
     * @param string $environment Environment
     * @param string $payload Test payload
     * @param string $signature Test signature
     * @return array Result with validation status
     */
    public function testWebhookSignature(string $service, string $environment, string $payload, string $signature): array
    {
        $config = $this->getConfig($service, $environment, false);

        if (!$config || !$config['webhook_secret_decrypted']) {
            return [
                'success' => false,
                'signature_valid' => false,
                'error' => 'Webhook secret not configured'
            ];
        }

        // For Stripe webhooks
        if ($service === 'stripe') {
            return $this->validateStripeWebhookSignature(
                $config['webhook_secret_decrypted'],
                $payload,
                $signature
            );
        }

        return [
            'success' => false,
            'error' => 'Webhook validation not implemented for ' . $service
        ];
    }

    /**
     * Validate Stripe webhook signature
     *
     * @param string $secret Webhook secret
     * @param string $payload Webhook payload
     * @param string $signature Stripe-Signature header
     * @return array Validation result
     */
    private function validateStripeWebhookSignature(string $secret, string $payload, string $signature): array
    {
        // Parse signature header: t=timestamp,v1=signature
        $elements = explode(',', $signature);
        $timestamp = null;
        $v1Signature = null;

        foreach ($elements as $element) {
            [$key, $value] = explode('=', $element, 2);
            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1') {
                $v1Signature = $value;
            }
        }

        if (!$timestamp || !$v1Signature) {
            return [
                'success' => false,
                'signature_valid' => false,
                'error' => 'Invalid signature format'
            ];
        }

        // Compute expected signature
        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        $valid = hash_equals($expectedSignature, $v1Signature);

        return [
            'success' => true,
            'signature_valid' => $valid,
            'message' => $valid ? 'Signature valid' : 'Signature mismatch'
        ];
    }

    /**
     * Log health check result
     *
     * @param int $configId Config ID
     * @param string $service Service name
     * @param string $environment Environment
     * @param string $status Status (success/failure/timeout/auth_error)
     * @param float $responseTimeMs Response time in milliseconds
     * @param string|null $errorMessage Error message if failed
     * @param int|null $httpStatus HTTP status code
     */
    private function logHealthCheck(
        int $configId,
        string $service,
        string $environment,
        string $status,
        float $responseTimeMs,
        string $errorMessage = null,
        int $httpStatus = null
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO `ngn_2025`.`api_health_log` (
                config_id, service, environment, status,
                response_time_ms, error_message, http_status, checked_at
            ) VALUES (
                :config_id, :service, :environment, :status,
                :response_time_ms, :error_message, :http_status, NOW()
            )
        ");

        $stmt->execute([
            ':config_id' => $configId,
            ':service' => $service,
            ':environment' => $environment,
            ':status' => $status,
            ':response_time_ms' => round($responseTimeMs, 2),
            ':error_message' => $errorMessage,
            ':http_status' => $httpStatus
        ]);

        // Update last_verified_at in api_config
        if ($status === 'success') {
            $this->pdo->prepare("
                UPDATE `ngn_2025`.`api_config`
                SET last_verified_at = NOW(), last_error = NULL
                WHERE id = :id
            ")->execute([':id' => $configId]);
        } else {
            $this->pdo->prepare("
                UPDATE `ngn_2025`.`api_config`
                SET last_error = :error
                WHERE id = :id
            ")->execute([
                ':id' => $configId,
                ':error' => $errorMessage
            ]);
        }
    }

    /**
     * Test Printful connection
     *
     * @param string $environment Environment to test
     * @return array Result with status and details
     */
    public function testPrintfulConnection(string $environment): array
    {
        $config = $this->getConfig('printful', $environment, false);

        if (!$config || !$config['api_key_decrypted']) {
            return [
                'success' => false,
                'status' => 'failure',
                'error' => 'Printful API key not configured'
            ];
        }

        $apiKey = $config['api_key_decrypted'];
        $startTime = microtime(true);

        try {
            // Test Printful API: Get store info
            $ch = curl_init('https://api.printful.com/store');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 10
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responseTime = (microtime(true) - $startTime) * 1000;
            curl_close($ch);

            if ($httpCode === 200) {
                $this->logHealthCheck($config['id'], 'printful', $environment, 'success', $responseTime);

                return [
                    'success' => true,
                    'status' => 'success',
                    'response_time_ms' => round($responseTime, 2),
                    'message' => 'Printful API connection successful'
                ];
            } else {
                $error = json_decode($response, true);
                $errorMessage = $error['error']['message'] ?? $error['message'] ?? 'Unknown error';

                $this->logHealthCheck($config['id'], 'printful', $environment, 'auth_error', $responseTime, $errorMessage, $httpCode);

                return [
                    'success' => false,
                    'status' => 'auth_error',
                    'http_status' => $httpCode,
                    'error' => $errorMessage
                ];
            }
        } catch (Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;
            $this->logHealthCheck($config['id'], 'printful', $environment, 'failure', $responseTime, $e->getMessage());

            return [
                'success' => false,
                'status' => 'failure',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test Meta (Instagram/Facebook) connection
     *
     * @param string $environment Environment to test
     * @return array Result with status and details
     */
    public function testMetaConnection(string $environment): array
    {
        $config = $this->getConfig('meta', $environment, false);

        if (!$config || !$config['api_key_decrypted']) {
            return [
                'success' => false,
                'status' => 'failure',
                'error' => 'Meta access token not configured'
            ];
        }

        $accessToken = $config['api_key_decrypted'];
        $startTime = microtime(true);

        try {
            // Test Meta API: Debug token to verify validity
            $ch = curl_init('https://graph.facebook.com/v18.0/debug_token?input_token=' . urlencode($accessToken) . '&access_token=' . urlencode($accessToken));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responseTime = (microtime(true) - $startTime) * 1000;
            curl_close($ch);

            $data = json_decode($response, true);

            if ($httpCode === 200 && isset($data['data']['is_valid']) && $data['data']['is_valid']) {
                $this->logHealthCheck($config['id'], 'meta', $environment, 'success', $responseTime);

                return [
                    'success' => true,
                    'status' => 'success',
                    'response_time_ms' => round($responseTime, 2),
                    'message' => 'Meta API connection successful',
                    'token_expires' => $data['data']['expires_at'] ?? null
                ];
            } else {
                $errorMessage = $data['error']['message'] ?? 'Invalid access token';

                $this->logHealthCheck($config['id'], 'meta', $environment, 'auth_error', $responseTime, $errorMessage, $httpCode);

                return [
                    'success' => false,
                    'status' => 'auth_error',
                    'http_status' => $httpCode,
                    'error' => $errorMessage
                ];
            }
        } catch (Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;
            $this->logHealthCheck($config['id'], 'meta', $environment, 'failure', $responseTime, $e->getMessage());

            return [
                'success' => false,
                'status' => 'failure',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test Spotify connection
     *
     * @param string $environment Environment to test
     * @return array Result with status and details
     */
    public function testSpotifyConnection(string $environment): array
    {
        $config = $this->getConfig('spotify', $environment, false);

        if (!$config || !$config['api_key_decrypted'] || !$config['api_secret_decrypted']) {
            return [
                'success' => false,
                'status' => 'failure',
                'error' => 'Spotify client credentials not configured'
            ];
        }

        $clientId = $config['api_key_decrypted'];
        $clientSecret = $config['api_secret_decrypted'];
        $startTime = microtime(true);

        try {
            // Test Spotify API: Get access token via client credentials flow
            $ch = curl_init('https://accounts.spotify.com/api/token');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
                CURLOPT_HTTPHEADER => [
                    'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
                    'Content-Type: application/x-www-form-urlencoded'
                ],
                CURLOPT_TIMEOUT => 10
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responseTime = (microtime(true) - $startTime) * 1000;
            curl_close($ch);

            $data = json_decode($response, true);

            if ($httpCode === 200 && isset($data['access_token'])) {
                $this->logHealthCheck($config['id'], 'spotify', $environment, 'success', $responseTime);

                return [
                    'success' => true,
                    'status' => 'success',
                    'response_time_ms' => round($responseTime, 2),
                    'message' => 'Spotify API connection successful',
                    'token_expires_in' => $data['expires_in'] ?? null
                ];
            } else {
                $errorMessage = $data['error_description'] ?? $data['error'] ?? 'Authentication failed';

                $this->logHealthCheck($config['id'], 'spotify', $environment, 'auth_error', $responseTime, $errorMessage, $httpCode);

                return [
                    'success' => false,
                    'status' => 'auth_error',
                    'http_status' => $httpCode,
                    'error' => $errorMessage
                ];
            }
        } catch (Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;
            $this->logHealthCheck($config['id'], 'spotify', $environment, 'failure', $responseTime, $e->getMessage());

            return [
                'success' => false,
                'status' => 'failure',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test TikTok connection
     *
     * @param string $environment Environment to test
     * @return array Result with status and details
     */
    public function testTikTokConnection(string $environment): array
    {
        $config = $this->getConfig('tiktok', $environment, false);

        if (!$config || !$config['api_key_decrypted'] || !$config['api_secret_decrypted']) {
            return [
                'success' => false,
                'status' => 'failure',
                'error' => 'TikTok client credentials not configured'
            ];
        }

        $clientKey = $config['api_key_decrypted'];
        $clientSecret = $config['api_secret_decrypted'];
        $startTime = microtime(true);

        try {
            // Test TikTok API: Get access token via client credentials
            $ch = curl_init('https://open-api.tiktok.com/oauth/access_token/');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'client_key' => $clientKey,
                    'client_secret' => $clientSecret,
                    'grant_type' => 'client_credentials'
                ]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 10
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responseTime = (microtime(true) - $startTime) * 1000;
            curl_close($ch);

            $data = json_decode($response, true);

            if ($httpCode === 200 && isset($data['data']['access_token'])) {
                $this->logHealthCheck($config['id'], 'tiktok', $environment, 'success', $responseTime);

                return [
                    'success' => true,
                    'status' => 'success',
                    'response_time_ms' => round($responseTime, 2),
                    'message' => 'TikTok API connection successful',
                    'token_expires_in' => $data['data']['expires_in'] ?? null
                ];
            } else {
                $errorMessage = $data['data']['description'] ?? $data['message'] ?? 'Authentication failed';

                $this->logHealthCheck($config['id'], 'tiktok', $environment, 'auth_error', $responseTime, $errorMessage, $httpCode);

                return [
                    'success' => false,
                    'status' => 'auth_error',
                    'http_status' => $httpCode,
                    'error' => $errorMessage
                ];
            }
        } catch (Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;
            $this->logHealthCheck($config['id'], 'tiktok', $environment, 'failure', $responseTime, $e->getMessage());

            return [
                'success' => false,
                'status' => 'failure',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get recent health check history
     *
     * @param int $limit Number of records to retrieve
     * @return array Health check log entries
     */
    public function getHealthCheckHistory(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `ngn_2025`.`api_health_log`
            ORDER BY checked_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Run health checks for all active services
     *
     * @return array Summary of health check results
     */
    public function runAllHealthChecks(): array
    {
        $results = [];

        // Get all active configs
        $stmt = $this->pdo->query("
            SELECT service, environment FROM `ngn_2025`.`api_config`
            WHERE is_active = 1
            ORDER BY service, environment
        ");

        $configs = $stmt->fetchAll();

        foreach ($configs as $config) {
            $service = $config['service'];
            $environment = $config['environment'];

            // Test based on service type
            $result = match($service) {
                'stripe' => $this->testStripeConnection($environment),
                'printful' => $this->testPrintfulConnection($environment),
                'meta' => $this->testMetaConnection($environment),
                'spotify' => $this->testSpotifyConnection($environment),
                'tiktok' => $this->testTikTokConnection($environment),
                default => [
                    'success' => false,
                    'status' => 'failure',
                    'error' => "Health check not implemented for service: {$service}"
                ]
            };

            $results[] = [
                'service' => $service,
                'environment' => $environment,
                'result' => $result
            ];
        }

        return $results;
    }
}
