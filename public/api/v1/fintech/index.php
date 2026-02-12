<?php

require_once __DIR__ . '/../../../../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Http\Request;
use NGN\Lib\Http\Response;
use NGN\Lib\Http\JsonResponse;
use NGN\Lib\Auth\TokenService;
use NGN\Lib\Services\RoyaltyService;
use NGN\Lib\Logging\LoggerFactory;

$config = new Config();
$logger = LoggerFactory::getLogger('fintech_api');
$pdo = ConnectionFactory::write($config);
$tokenSvc = new TokenService($config);

$request = new Request();

// Auth Helper
function getCurrentUser($tokenSvc, $request) {
    $authHeader = $request->header('Authorization') ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($authHeader)) return null;
    $token = str_replace('Bearer ', '', $authHeader);
    try {
        return $tokenSvc->decode($token);
    } catch (\Throwable $e) {
        return null;
    }
}

$user = getCurrentUser($tokenSvc, $request);
if (!$user) {
    (new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401))->send();
    exit;
}

// SECURITY PERIMETER: ROLE & IP CHECK
// Only Admins (Role ID 1) can access admin functions
// Standard users can only access their own data
$isAdmin = isset($user['role_id']) && (int)$user['role_id'] === 1;

$userId = (int)$user['userId'];
$royaltyService = new RoyaltyService($pdo, $config);

// Basic router for /api/v1/fintech
$path = $request->uri();
$method = $request->method();

try {
    if (str_contains($path, '/balance')) {
        $balance = $royaltyService->getBalance($userId);
        (new JsonResponse(['success' => true, 'data' => $balance]))->send();
    } elseif (str_contains($path, '/payout/request') && $method === 'POST') {
        $body = json_decode($request->body(), true);
        $amount = (float)($body['amount'] ?? 0);
        
        if ($amount < 50.00) {
            throw new Exception("Minimum payout amount is $50.00");
        }

        $payoutId = $royaltyService->createPayout($userId, $amount);
        (new JsonResponse(['success' => true, 'message' => 'Payout request submitted', 'data' => ['payout_id' => $payoutId]]))->send();
    } elseif (str_contains($path, '/payout/process') && $method === 'POST') {
        // ADMIN ONLY: Process Payout
        if (!$isAdmin) {
            (new JsonResponse(['success' => false, 'message' => 'Forbidden: Admin Access Required'], 403))->send();
            exit;
        }
        
        $body = json_decode($request->body(), true);
        $payoutId = (int)($body['payout_id'] ?? 0);
        
        if (!$payoutId) throw new Exception("Payout ID required");
        
        $result = $royaltyService->processPayoutRequest($payoutId, $userId);
        (new JsonResponse(['success' => true, 'message' => 'Payout processed', 'data' => $result]))->send();
        
    } elseif (str_contains($path, '/transactions')) {
        $limit = (int)($request->query('limit') ?? 20);
        $transactions = $royaltyService->getTransactions($userId, $limit);
        (new JsonResponse(['success' => true, 'data' => $transactions]))->send();
    } else {
        (new JsonResponse(['success' => false, 'message' => 'Endpoint not found'], 404))->send();
    }
} catch (\Throwable $e) {
    $logger->error("Fintech API Error: " . $e->getMessage());
    (new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400))->send();
}
