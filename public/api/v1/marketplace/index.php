<?php
/**
 * NGN Professional Marketplace API v1
 * Handles service discovery and professional listings.
 */

require_once __DIR__ . '/../../../../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Services\Marketplace\MarketplaceService;

header('Content-Type: application/json');

$config = new Config();
$marketplace = new MarketplaceService($config);

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $filters = [
            'category' => $_GET['category'] ?? null
        ];
        $listings = $marketplace->getListings($filters);
        echo json_encode([
            'status' => 'success',
            'count' => count($listings),
            'data' => $listings
        ]);
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['user_id']) || empty($input['name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        $id = $marketplace->createListing($input);
        echo json_encode([
            'status' => 'success',
            'listing_id' => $id,
            'message' => 'Service listing created in MyIndiPro node'
        ]);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
