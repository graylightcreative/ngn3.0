<?php
/**
 * NGN EPK Export API v1
 * Generates data-backed discovery profiles for professional export.
 */

require_once __DIR__ . '/../../../../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Services\EPK\EPKService;

header('Content-Type: application/json');

$config = new Config();
$epkService = new EPKService($config);

$artistId = (int)($_GET['artist_id'] ?? 0);

if ($artistId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid artist_id required']);
    exit;
}

try {
    $data = $epkService->getEPKData($artistId);
    
    if (empty($data)) {
        http_response_code(404);
        echo json_encode(['error' => 'Artist not found or profile incomplete']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'data' => $data,
        'bible_ref' => 'Chapter 18 - Discovery Profile'
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
