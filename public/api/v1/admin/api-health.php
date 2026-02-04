<?php
/**
 * API Health & Configuration Endpoints
 *
 * POST /api/v1/admin/api-health/stripe/config - Save Stripe configuration
 * GET /api/v1/admin/api-health/stripe/config - Get Stripe configuration (masked)
 * POST /api/v1/admin/api-health/stripe/test - Test Stripe connection
 * POST /api/v1/admin/api-health/webhook/test - Test webhook signature
 * POST /api/v1/admin/api-health/run-all - Run all health checks
 * GET /api/v1/admin/api-health/history - Get health check history
 */

$root = dirname(__DIR__, 3);
require_once $root . '/lib/bootstrap.php';

use NGN\Lib\Env;
use NGN\Lib\Config;
use NGN\Lib\Services\ApiHealthService;
use NGN\Lib\Security\EncryptionService;

// Load environment
Env::load($root);
$cfg = new Config();

// Database connection
$pdo = require $root . '/lib/db.php';

// Initialize services
$encryptionService = new EncryptionService();
$apiHealthService = new ApiHealthService($pdo, $encryptionService);

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Extract endpoint (last parts after /admin/api-health/)
$endpoint = array_slice($pathParts, array_search('api-health', $pathParts) + 1);

// Helper functions
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

// TODO: Add admin auth guard here
// For now, assume authenticated

try {
    // Route: POST /stripe/config - Save Stripe configuration
    if ($method === 'POST' && $endpoint[0] === 'stripe' && $endpoint[1] === 'config') {
        $input = getJsonInput();
        $environment = $input['environment'] ?? 'sandbox';

        // Validate environment
        if (!in_array($environment, ['sandbox', 'live'])) {
            jsonResponse(['error' => 'Invalid environment'], 400);
        }

        // Validate Stripe key formats
        if (!empty($input['publishable_key'])) {
            if (!EncryptionService::validateStripeKeyFormat($input['publishable_key'], 'publishable', $environment)) {
                jsonResponse(['error' => 'Invalid publishable key format for ' . $environment], 400);
            }
        }

        if (!empty($input['secret_key'])) {
            if (!EncryptionService::validateStripeKeyFormat($input['secret_key'], 'secret', $environment)) {
                jsonResponse(['error' => 'Invalid secret key format for ' . $environment], 400);
            }
        }

        if (!empty($input['webhook_secret'])) {
            if (!EncryptionService::validateStripeKeyFormat($input['webhook_secret'], 'webhook', $environment)) {
                jsonResponse(['error' => 'Invalid webhook secret format'], 400);
            }
        }

        // Save configuration
        $result = $apiHealthService->saveConfig('stripe', $environment, [
            'api_key' => $input['publishable_key'] ?? null,
            'api_secret' => $input['secret_key'] ?? null,
            'webhook_secret' => $input['webhook_secret'] ?? null,
            'is_active' => $input['is_active'] ?? false
        ]);

        jsonResponse([
            'success' => true,
            'message' => 'Stripe configuration saved',
            'data' => $result
        ]);
    }

    // Route: GET /stripe/config - Get Stripe configuration
    if ($method === 'GET' && $endpoint[0] === 'stripe' && $endpoint[1] === 'config') {
        $environment = $_GET['environment'] ?? 'sandbox';

        $config = $apiHealthService->getConfig('stripe', $environment, true);

        if (!$config) {
            jsonResponse([
                'success' => true,
                'data' => null,
                'message' => 'No configuration found for ' . $environment
            ]);
        }

        jsonResponse([
            'success' => true,
            'data' => [
                'id' => $config['id'],
                'environment' => $config['environment'],
                'is_active' => (bool)$config['is_active'],
                'has_publishable_key' => $config['has_api_key'] ?? false,
                'has_secret_key' => $config['has_api_secret'] ?? false,
                'has_webhook_secret' => $config['has_webhook_secret'] ?? false,
                'publishable_key_masked' => $config['api_key_decrypted'] ?? null,
                'secret_key_masked' => $config['api_secret_decrypted'] ?? null,
                'webhook_secret_masked' => $config['webhook_secret_decrypted'] ?? null,
                'last_verified_at' => $config['last_verified_at'],
                'last_error' => $config['last_error']
            ]
        ]);
    }

    // Route: POST /stripe/test - Test Stripe connection
    if ($method === 'POST' && $endpoint[0] === 'stripe' && $endpoint[1] === 'test') {
        $input = getJsonInput();
        $environment = $input['environment'] ?? 'sandbox';

        $result = $apiHealthService->testStripeConnection($environment);

        jsonResponse([
            'success' => $result['success'],
            'data' => $result
        ], $result['success'] ? 200 : 500);
    }

    // Route: POST /printful/test - Test Printful connection
    if ($method === 'POST' && $endpoint[0] === 'printful' && $endpoint[1] === 'test') {
        $input = getJsonInput();
        $environment = $input['environment'] ?? 'live';

        $result = $apiHealthService->testPrintfulConnection($environment);

        jsonResponse([
            'success' => $result['success'],
            'data' => $result
        ], $result['success'] ? 200 : 500);
    }

    // Route: POST /meta/test - Test Meta connection
    if ($method === 'POST' && $endpoint[0] === 'meta' && $endpoint[1] === 'test') {
        $input = getJsonInput();
        $environment = $input['environment'] ?? 'live';

        $result = $apiHealthService->testMetaConnection($environment);

        jsonResponse([
            'success' => $result['success'],
            'data' => $result
        ], $result['success'] ? 200 : 500);
    }

    // Route: POST /spotify/test - Test Spotify connection
    if ($method === 'POST' && $endpoint[0] === 'spotify' && $endpoint[1] === 'test') {
        $input = getJsonInput();
        $environment = $input['environment'] ?? 'live';

        $result = $apiHealthService->testSpotifyConnection($environment);

        jsonResponse([
            'success' => $result['success'],
            'data' => $result
        ], $result['success'] ? 200 : 500);
    }

    // Route: POST /tiktok/test - Test TikTok connection
    if ($method === 'POST' && $endpoint[0] === 'tiktok' && $endpoint[1] === 'test') {
        $input = getJsonInput();
        $environment = $input['environment'] ?? 'live';

        $result = $apiHealthService->testTikTokConnection($environment);

        jsonResponse([
            'success' => $result['success'],
            'data' => $result
        ], $result['success'] ? 200 : 500);
    }

    // Route: POST /webhook/test - Test webhook signature
    if ($method === 'POST' && $endpoint[0] === 'webhook' && $endpoint[1] === 'test') {
        $input = getJsonInput();

        $service = $input['service'] ?? 'stripe';
        $environment = $input['environment'] ?? 'sandbox';
        $payload = $input['payload'] ?? '{"test": true}';
        $signature = $input['signature'] ?? '';

        $result = $apiHealthService->testWebhookSignature($service, $environment, $payload, $signature);

        jsonResponse([
            'success' => $result['success'],
            'data' => $result
        ]);
    }

    // Route: POST /run-all - Run all health checks
    if ($method === 'POST' && $endpoint[0] === 'run-all') {
        $results = $apiHealthService->runAllHealthChecks();

        jsonResponse([
            'success' => true,
            'message' => 'Health checks completed',
            'data' => $results
        ]);
    }

    // Route: GET /history - Get health check history
    if ($method === 'GET' && $endpoint[0] === 'history') {
        $limit = (int)($_GET['limit'] ?? 50);
        $history = $apiHealthService->getHealthCheckHistory($limit);

        jsonResponse([
            'success' => true,
            'data' => $history
        ]);
    }

    // Route: GET /all-configs - Get all API configurations
    if ($method === 'GET' && $endpoint[0] === 'all-configs') {
        $configs = $apiHealthService->getAllConfigs(true);

        jsonResponse([
            'success' => true,
            'data' => $configs
        ]);
    }

    // No matching route
    jsonResponse(['error' => 'Not found', 'path' => $endpoint], 404);

} catch (Exception $e) {
    jsonResponse([
        'error' => $e->getMessage(),
        'trace' => $cfg->debug() ? $e->getTraceAsString() : null
    ], 500);
}
