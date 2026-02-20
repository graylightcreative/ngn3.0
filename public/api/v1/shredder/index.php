<?php
/**
 * NGN Shredder API v1
 * AI Stem Isolation & Mastery Tools
 */

require_once __DIR__ . '/../../../../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Services\Shredder\ShredderNodeService;
use NGN\Lib\Http\Router;

header('Content-Type: application/json');

$config = new Config();
$shredder = new ShredderNodeService($config);

// Basic Router logic for the subdirectory
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'status';
$trackId = (int)($_GET['track_id'] ?? 0);

if ($trackId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid track_id required']);
    exit;
}

try {
    switch ($action) {
        case 'request':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $result = $shredder->requestSeparation($trackId);
            echo json_encode($result);
            break;

        case 'status':
        default:
            $result = $shredder->requestSeparation($trackId); // Logic handles cache check
            echo json_encode($result);
            break;
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
