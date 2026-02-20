<?php
/**
 * NGN Hardware Beacon API v1 (Node 48)
 * Handles secure handshakes and pulse logging for physical venue hardware.
 */

require_once __DIR__ . '/../../../../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Services\Hardware\BeaconHandshakeService;

header('Content-Type: application/json');

$config = new Config();
$beaconService = new BeaconHandshakeService($config);

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $beaconId = $input['beacon_id'] ?? '';
        $signature = $input['signature'] ?? '';

        if (!$beaconService->verifyHandshake($beaconId, $signature)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid beacon handshake signature']);
            exit;
        }

        $beaconService->logPulse($beaconId, $input['data'] ?? []);
        echo json_encode([
            'status' => 'success',
            'message' => 'Beacon pulse recorded',
            'node' => 48
        ]);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
