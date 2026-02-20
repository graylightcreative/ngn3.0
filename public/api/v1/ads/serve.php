<?php
/**
 * NGN Ad Serving API v1
 * Programmatically delivers ad creative based on placement context.
 */

require_once __DIR__ . '/../../../../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Services\Advertiser\SelfServeAdService;

header('Content-Type: application/json');

$config = new Config();
$adService = new SelfServeAdService($config);

$placement = $_GET['placement'] ?? 'sidebar';

try {
    $ad = $adService->getAdForPlacement($placement);
    
    if (!$ad) {
        echo json_encode(['status' => 'no_inventory']);
        exit;
    }

    // Log the impression
    $adService->logAdEvent($ad['id'], 'impression', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);

    echo json_encode([
        'status' => 'success',
        'data' => [
            'id' => $ad['id'],
            'title' => $ad['title'],
            'image_url' => $ad['image_url'],
            'link_url' => $ad['link_url'],
            'placement' => $ad['placement']
        ]
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
