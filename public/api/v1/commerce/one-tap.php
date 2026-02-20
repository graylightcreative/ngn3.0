<?php
/**
 * NGN One-Tap Commerce API v1
 * Frictionless Apple/Google Pay integration.
 */

require_once __DIR__ . '/../../../../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Commerce\StripeCheckoutService;

header('Content-Type: application/json');

$config = new Config();
$stripe = new StripeCheckoutService($config);

$orderId = (int)($_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid order_id required']);
    exit;
}

try {
    $result = $stripe->createPaymentIntent($orderId);
    
    if (!$result['success']) {
        http_response_code(500);
        echo json_encode(['error' => $result['error'] ?? 'Failed to initialize payment']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'client_secret' => $result['client_secret'],
        'publishable_key' => $stripe->getPublishableKey(),
        'amount' => $result['amount'],
        'currency' => $result['currency']
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
