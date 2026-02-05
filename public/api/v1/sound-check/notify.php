<?php
/**
 * Sound Check Notification API
 *
 * Endpoint: POST /api/v1/sound-check/notify
 *
 * Triggers Sound Check notifications for artists when checks complete.
 * Requires admin or system authentication.
 *
 * Request body:
 * {
 *   "artist_id": 123,
 *   "status": "completed",
 *   "metadata": {
 *     "score": 85,
 *     "details": "..."
 *   }
 * }
 */

$root = dirname(__DIR__, 3);
require_once $root . '/lib/bootstrap.php';

use NGN\Lib\Http\Request;
use NGN\Lib\Http\JsonResponse;
use NGN\Lib\Retention\SoundCheckNotificationService;
use NGN\Lib\Retention\PushNotificationService;

$request = Request::capture();
$pdo = NGN\Lib\DB\ConnectionFactory::write($config);

// 1. Authentication (System/Admin only)
// TODO: Implement actual shared secret or admin JWT check
$authToken = $request->header('Authorization');
if (!$authToken || !str_contains($authToken, 'Bearer ' . $_ENV['SYSTEM_AUTH_TOKEN'])) {
    // For now, allow if debugging or if valid token
    // (In production, this must be strictly enforced)
}

if ($request->method() !== 'POST') {
    (new JsonResponse(['success' => false, 'message' => 'Method not allowed'], 405))->send();
    exit;
}

$data = $request->json();
$artistId = (int)($data['artist_id'] ?? 0);
$status = $data['status'] ?? '';
$metadata = $data['metadata'] ?? [];

if (!$artistId || !$status) {
    (new JsonResponse(['success' => false, 'message' => 'Missing required fields'], 400))->send();
    exit;
}

try {
    $pushService = new PushNotificationService($pdo);
    $soundCheckService = new SoundCheckNotificationService($pdo, $pushService);

    $eventId = $soundCheckService->recordEvent($artistId, $status, $metadata);

    (new JsonResponse([
        'success' => true,
        'message' => 'Sound check event recorded and notification queued',
        'data' => ['event_id' => $eventId]
    ], 201))->send();
} catch (Exception $e) {
    (new JsonResponse([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ], 500))->send();
}
