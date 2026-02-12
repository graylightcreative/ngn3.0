<?php

require_once __DIR__ . '/../../../../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Http\Request;
use NGN\Lib\Http\JsonResponse;
use NGN\Lib\Services\StripeConnectService;
use NGN\Lib\Logging\LoggerFactory;
use NGN\Lib\Auth\TokenService;

$config = new Config();
$logger = LoggerFactory::getLogger('stripe_callback');
$pdo = ConnectionFactory::write($config);
$tokenSvc = new TokenService($config);
$stripeSvc = new StripeConnectService($pdo, $config, $logger);

$request = new Request();

// Simple session check for security
session_start();
$userId = $_SESSION['User']['id'] ?? $_SESSION['user_id'] ?? null;

if (!$userId) {
    // Try JWT if session fails
    $authHeader = $request->header('Authorization');
    if ($authHeader) {
        try {
            $token = str_replace('Bearer ', '', $authHeader);
            $decoded = $tokenSvc->decode($token);
            $userId = $decoded['userId'];
        } catch (Throwable $e) {}
    }
}

if (!$userId) {
    header('Location: /login?redirect=/dashboard/settings/payouts&error=unauthorized');
    exit;
}

try {
    // Verify account status with Stripe
    $accountStatus = $stripeSvc->getAccountStatus((int)$userId);
    
    if ($accountStatus) {
        $stripeSvc->updateAccountStatusInDb(
            $accountStatus['id'],
            $accountStatus['details_submitted'],
            $accountStatus['charges_enabled'],
            $accountStatus['payouts_enabled']
        );
        
        $logger->info("Stripe callback processed for user $userId. Payouts enabled: " . ($accountStatus['payouts_enabled'] ? 'YES' : 'NO'));
        
        header('Location: /dashboard/settings/payouts?status=connected');
    } else {
        throw new Exception("Could not retrieve account status");
    }

} catch (Throwable $e) {
    $logger->error("Stripe callback error: " . $e->getMessage());
    header('Location: /dashboard/settings/payouts?error=connection_failed');
}
exit;
