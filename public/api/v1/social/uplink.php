<?php
/**
 * NGN Uplink SMM API v1
 * Automated social media management across the fleet.
 */

require_once __DIR__ . '/../../../../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Services\Social\UplinkService;

header('Content-Type: application/json');

$config = new Config();
$uplink = new UplinkService($config);

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $queue = $uplink->getQueueStatus();
        echo json_encode([
            'status' => 'success',
            'count' => count($queue),
            'queue' => $queue
        ]);
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['content'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required content field']);
            exit;
        }

        $id = $uplink->queuePost($input);
        echo json_encode([
            'status' => 'success',
            'post_id' => $id,
            'message' => 'Post queued via Uplink node'
        ]);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
