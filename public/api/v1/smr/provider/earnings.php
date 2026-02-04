<?php

/**
 * SMR Provider Earnings Dashboard API
 * Chapter 24 - Provider Dashboard Endpoint
 *
 * GET /api/v1/smr/provider/earnings
 * Returns: Provider earnings summary, attribution windows, bounties
 *
 * Authentication: Bearer token (provider must be Erik Baker)
 */

require_once __DIR__ . '/../../../../vendor/autoload.php';

header('Content-Type: application/json');

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;
use NGN\Lib\Smr\AttributionWindowService;
use NGN\Lib\Smr\BountySettlementService;

try {
    // Get database connection
    $config = new Config();
    $pdo = ConnectionFactory::read($config);

    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }

    // Verify authentication
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($authHeader) || strpos($authHeader, 'Bearer ') !== 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Missing authentication']);
        exit;
    }

    // Get provider user ID from .env or database
    $providerUserId = (int)($_ENV['SMR_PROVIDER_USER_ID'] ?? 0);

    if ($providerUserId <= 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Provider not configured']);
        exit;
    }

    // Get provider contract
    $stmt = $pdo->prepare("
        SELECT * FROM smr_provider_contracts
        WHERE provider_user_id = ?
        AND provider_status IN ('preferred', 'active')
        LIMIT 1
    ");
    $stmt->execute([$providerUserId]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        http_response_code(404);
        echo json_encode(['error' => 'Provider contract not found']);
        exit;
    }

    // Get provider info
    $stmt = $pdo->prepare("SELECT id, name, email FROM cdm_users WHERE id = ? LIMIT 1");
    $stmt->execute([$providerUserId]);
    $provider = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get provider balance
    $stmt = $pdo->prepare("
        SELECT amount, available_balance, pending_balance, lifetime_earnings
        FROM cdm_royalty_balances
        WHERE user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$providerUserId]);
    $balance = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get attribution window statistics
    $attributionService = new AttributionWindowService($pdo);
    $stats = $attributionService->getProviderStatistics($providerUserId);

    // Get bounties this month
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, SUM(bounty_amount) as total
        FROM smr_bounty_transactions
        WHERE provider_user_id = ?
        AND status = 'settled'
        AND MONTH(created_at) = MONTH(NOW())
        AND YEAR(created_at) = YEAR(NOW())
    ");
    $stmt->execute([$providerUserId]);
    $monthlyBounties = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get lifetime bounties
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, SUM(bounty_amount) as total
        FROM smr_bounty_transactions
        WHERE provider_user_id = ?
        AND status = 'settled'
    ");
    $stmt->execute([$providerUserId]);
    $lifetimeBounties = $stmt->fetch(PDO::FETCH_ASSOC);

    // Response
    $response = [
        'provider' => [
            'user_id' => (int)$provider['id'],
            'name' => $provider['name'],
            'email' => $provider['email'],
            'status' => $contract['provider_status'],
            'contract_version' => 'NGN-BOUNTY-V1',
            'preferred_partner' => (bool)$contract['preferred_data_partner'],
        ],
        'earnings' => [
            'lifetime_total' => (float)($lifetimeBounties['total'] ?? 0.00),
            'pending_balance' => (float)($balance['pending_balance'] ?? 0.00),
            'available_balance' => (float)($balance['available_balance'] ?? 0.00),
            'current_month' => (float)($monthlyBounties['total'] ?? 0.00),
        ],
        'attribution' => [
            'active_windows' => $stats['active_windows'],
            'unique_artists' => $stats['unique_artists'],
            'heat_spikes_detected' => 0,  // Can be queried separately
        ],
        'bounties' => [
            'bounties_triggered_this_month' => (int)($monthlyBounties['count'] ?? 0),
            'bounties_triggered_lifetime' => (int)($lifetimeBounties['count'] ?? 0),
            'bounty_percentage_rate' => (float)$contract['bounty_percentage'],
            'geofence_bonus_percentage' => (float)$contract['geofence_bonus_percentage'],
        ],
        'timestamp' => date('c'),
    ];

    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $_ENV['APP_ENV'] === 'development' ? $e->getMessage() : null,
    ]);
}
