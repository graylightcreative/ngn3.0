<?php
/**
 * Bouncer Mode - Manifest Download
 * Endpoint: GET /api/v1/tickets/manifest?event_id=xxx&bouncer_token=yyy
 *
 * Downloads the offline manifest of valid QR hashes for an event
 * Used by bouncer PWA to enable offline ticket scanning
 *
 * Response: {
 *   event_id: string,
 *   event_title: string,
 *   ticket_hashes: string[],  // Array of valid QR hashes
 *   total_tickets: int,
 *   generated_at: datetime,
 *   expires_at: datetime
 * }
 */

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Tickets\TicketVerifier;

// Bouncer authentication (via token or session)
if (!isset($_SESSION)) {
    session_start();
}

header('Content-Type: application/json');

// Get event ID
if (!isset($_GET['event_id'])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'event_id required'
    ]);
    exit;
}

$eventId = $_GET['event_id'];

// Bouncer authentication
$bouncerToken = $_GET['bouncer_token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;
$isAuthenticated = false;

// Check if user is bouncer for this event (simplified - use Stripe Connect in production)
if (isset($_SESSION['User']['Id'])) {
    // TODO: Verify user is assigned as bouncer for this event
    // For now, allow any logged-in user
    $isAuthenticated = true;
}

if (!$isAuthenticated) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Authentication required'
    ]);
    exit;
}

try {
    $config = new Config();
    $pdo = ConnectionFactory::read($config);
    $verifier = new TicketVerifier($pdo);

    // Get event details
    $eventStmt = $pdo->prepare(
        'SELECT id, title, venue_name, starts_at, enable_ticketing
        FROM events
        WHERE id = ?'
    );
    $eventStmt->execute([$eventId]);
    $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        http_response_code(404);
        echo json_encode(['error' => 'Event not found']);
        exit;
    }

    if (!$event['enable_ticketing']) {
        http_response_code(403);
        echo json_encode(['error' => 'Ticketing not enabled for this event']);
        exit;
    }

    // Get or generate manifest
    $manifest = $verifier->getManifest($eventId);

    if (!$manifest) {
        // Generate new manifest
        $result = $verifier->generateManifest($eventId);
        $manifest = [
            'event_id' => $eventId,
            'ticket_hashes' => $result['ticket_hashes'],
            'total_tickets' => $result['total_tickets'],
            'manifest_hash' => $result['manifest_hash'],
            'generated_at' => date('c')
        ];
    }

    // Prepare response
    $response = [
        'event_id' => $event['id'],
        'event_title' => $event['title'],
        'venue' => $event['venue_name'],
        'event_starts_at' => $event['starts_at'],
        'ticket_hashes' => $manifest['ticket_hashes'] ?? json_decode($manifest['ticket_hashes'], true) ?? [],
        'total_tickets' => $manifest['total_tickets'],
        'manifest_hash' => $manifest['manifest_hash'] ?? null,
        'generated_at' => $manifest['generated_at'],
        'version' => '1.0'
    ];

    http_response_code(200);
    echo json_encode($response);

} catch (\Throwable $e) {
    error_log("Manifest API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error'
    ]);
}
