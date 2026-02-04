<?php
/**
 * Bouncer Mode - Sync Offline Scans
 * Endpoint: POST /api/v1/tickets/sync
 *
 * Syncs offline ticket scans back to server when bouncer comes back online
 *
 * Request Body:
 * {
 *   device_id: string,  // Bouncer device ID
 *   event_id: string,   // Event UUID
 *   scans: [
 *     {
 *       qr_hash: string,
 *       scanned_at: datetime,
 *       location: string,
 *       bouncer_id: string
 *     },
 *     ...
 *   ]
 * }
 *
 * Response:
 * {
 *   synced: int,      // Successfully synced
 *   failed: int,      // Failed syncs
 *   errors: string[],
 *   timestamp: datetime
 * }
 */

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Tickets\TicketVerifier;

if (!isset($_SESSION)) {
    session_start();
}

header('Content-Type: application/json');

// Authentication (bouncer session)
$bouncerId = $_SESSION['User']['Id'] ?? 'unknown';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST method required']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['scans']) || !is_array($data['scans'])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Required: scans array'
    ]);
    exit;
}

$eventId = $data['event_id'] ?? '';
$deviceId = $data['device_id'] ?? 'unknown';
$scans = $data['scans'];

try {
    $config = new Config();
    $pdo = ConnectionFactory::read($config);
    $verifier = new TicketVerifier($pdo);

    $result = [
        'synced' => 0,
        'failed' => 0,
        'errors' => [],
        'timestamp' => date('c')
    ];

    // Process each offline scan
    foreach ($scans as $scan) {
        try {
            $qrHash = $scan['qr_hash'] ?? null;
            $location = $scan['location'] ?? 'Unknown';
            $scanTime = $scan['scanned_at'] ?? null;

            if (!$qrHash) {
                $result['failed']++;
                $result['errors'][] = 'Scan missing qr_hash';
                continue;
            }

            // Redeem the ticket with offline flag
            $redemption = $verifier->redeemTicket(
                $qrHash,
                "{$bouncerId}:{$deviceId}",
                $location,
                'offline_manifest',
                true // Mark as offline-redeemed
            );

            if ($redemption['success']) {
                $result['synced']++;
            } else {
                $result['failed']++;
                $result['errors'][] = "{$qrHash}: {$redemption['message']}";
            }

        } catch (\Throwable $e) {
            $result['failed']++;
            $result['errors'][] = $e->getMessage();
        }
    }

    http_response_code(200);
    echo json_encode($result);

} catch (\Throwable $e) {
    error_log("Sync API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Sync failed',
        'message' => $e->getMessage(),
        'timestamp' => date('c')
    ]);
}
