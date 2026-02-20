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
$logger = LoggerFactory::create($config, 'fintech_api');
$pdo = ConnectionFactory::write($config);
$tokenSvc = new TokenService($config);

// Auth Helper
if (!function_exists('getCurrentUser')) {
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
}

/**
 * Handle Fintech API Request
 */
$handleRequest = function(Request $request) use ($config, $logger, $pdo, $tokenSvc) {
    try {
        $user = getCurrentUser($tokenSvc, $request);
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $isAdmin = isset($user['role_id']) && (int)$user['role_id'] === 1;
        $userId = (int)$user['userId'];
        $royaltyService = new \NGN\Lib\Services\RoyaltyService($pdo, $config);

        $path = $request->path();
        $method = $request->method();

        if (str_contains($path, '/balance')) {
            $balance = $royaltyService->getBalance($userId);
            return new JsonResponse(['success' => true, 'data' => $balance]);
        } 
        
        if (str_contains($path, '/payout/request') && $method === 'POST') {
            $body = json_decode($request->body(), true);
            $amount = (float)($body['amount'] ?? 0);
            if ($amount < 50.00) throw new Exception("Minimum payout amount is $50.00");
            $payoutId = $royaltyService->createPayout($userId, $amount);
            return new JsonResponse(['success' => true, 'message' => 'Payout request submitted', 'data' => ['payout_id' => $payoutId]]);
        } 
        
        if (str_contains($path, '/payout/process') && $method === 'POST') {
            if (!$isAdmin) return new JsonResponse(['success' => false, 'message' => 'Forbidden: Admin Access Required'], 403);
            $body = json_decode($request->body(), true);
            $payoutId = (int)($body['payout_id'] ?? 0);
            if (!$payoutId) throw new Exception("Payout ID required");
            $result = $royaltyService->processPayoutRequest($payoutId, $userId);
            return new JsonResponse(['success' => true, 'message' => 'Payout processed', 'data' => $result]);
        } 
        
        if (str_contains($path, '/transactions')) {
            $limit = (int)($request->query('limit') ?? 20);
            $transactions = $royaltyService->getTransactions($userId, $limit);
            return new JsonResponse(['success' => true, 'data' => $transactions]);
        }

        return new JsonResponse(['success' => false, 'message' => 'Endpoint not found'], 404);
    } catch (\Throwable $e) {
        $logger->error("Fintech API Error: " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
};

// If $group router is provided (included via index.php), register routes
if (isset($group) && $group instanceof \NGN\Lib\Http\Router || (isset($group) && is_object($group) && method_exists($group, 'get'))) {
    $group->get('/balance', $handleRequest);
    $group->post('/payout/request', $handleRequest);
    $group->post('/payout/process', $handleRequest);
    $group->get('/transactions', $handleRequest);
} else {
    // Standalone execution
    $request = new Request();
    $response = $handleRequest($request);
    $response->send();
}
