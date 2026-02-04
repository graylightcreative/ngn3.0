<?php
/**
 * Bouncer Mode - Scan Ticket
 * Endpoint: POST /api/v1/tickets/scan
 *
 * Validates and redeems a ticket from QR scan
 * Works in both ONLINE and OFFLINE modes
 *
 * Request Body:
 * {
 *   qr_hash: string,          // Scanned QR hash
 *   event_id: string,         // Event UUID
 *   location: string,         // Scan location (e.g., "Main Entrance")
 *   mode: "online|offline",   // Scan mode
 *   offline_manifest: array   // Valid hashes (if offline mode)
 * }
 *
 * Response:
 * {
 *   success: bool,
 *   ticket_id: string,
 *   event_title: string,
 *   status: string,           // Valid/Already Redeemed/Invalid
 *   message: string,
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
$bouncerId = $_SESSION['User']['Id'] ?? 'anonymous';
if (empty($_SESSION['User']['Id'])) {
    // Allow some offline scanning, but require ID for redemption sync
}

// Parse request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST method required']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['qr_hash'], $data['event_id'])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Required fields: qr_hash, event_id'
    ]);
    exit;
}

$qrHash = $data['qr_hash'];
$eventId = $data['event_id'];
$location = $data['location'] ?? 'General';
$mode = $data['mode'] ?? 'online';
$offlineManifest = $data['offline_manifest'] ?? [];

try {
    $config = new Config();
    $pdo = ConnectionFactory::read($config);
    $verifier = new TicketVerifier($pdo);

    // Determine verification method
    if ($mode === 'offline') {
        // OFFLINE MODE: Check against manifest
        if (empty($offlineManifest)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'offline_manifest required for offline mode'
            ]);
            exit;
        }

        $isValid = $verifier->verifyOffline($qrHash, $offlineManifest);

        if (!$isValid) {
            http_response_code(200);
            echo json_encode([
                'success' => false,
                'status' => 'invalid',
                'message' => 'QR code not found in manifest - ticket may not exist',
                'timestamp' => date('c')
            ]);
            exit;
        }

        // Ticket is in manifest - redeem offline (will sync later)
        // Note: In offline mode, we can't fully verify the ticket in DB
        // So we return success if it's in manifest, with pending sync flag

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'status' => 'scanned_offline',
            'qr_hash' => $qrHash,
            'event_id' => $eventId,
            'message' => 'Ticket scanned offline - will sync when online',
            'timestamp' => date('c'),
            'pending_sync' => true
        ]);
        exit;
    }

    // ONLINE MODE: Verify against database
    $ticket = $verifier->verifyOnline($qrHash, $eventId);

    if (!$ticket || !$ticket['valid']) {
        http_response_code(200);
        echo json_encode([
            'success' => false,
            'status' => $ticket['status'] ?? 'invalid',
            'reason' => $ticket['reason'] ?? 'Ticket not found',
            'ticket_id' => $ticket['ticket_id'] ?? null,
            'message' => $ticket['reason'] ?? 'Invalid QR code',
            'timestamp' => date('c')
        ]);
        exit;
    }

    // Valid ticket - redeem it
    $redemption = $verifier->redeemTicket(
        $qrHash,
        (string)$bouncerId,
        $location,
        'online',
        false
    );

    if ($redemption['success']) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'ticket_id' => $redemption['ticket_id'],
            'event_title' => $ticket['event_title'],
            'venue' => $ticket['venue_name'],
            'ticket_type' => $ticket['ticket_type'],
            'status' => 'redeemed',
            'message' => $redemption['message'],
            'timestamp' => date('c')
        ]);
    } else {
        http_response_code(200);
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => $redemption['message'],
            'timestamp' => date('c')
        ]);
    }

} catch (\Throwable $e) {
    error_log("Scan API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'timestamp' => date('c')
    ]);
}
