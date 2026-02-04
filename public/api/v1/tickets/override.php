<?php
/**
 * Bouncer Mode - Manual Override
 * Endpoint: POST /api/v1/tickets/override
 *
 * Manually redeems a ticket without QR scan
 * Used for lost/damaged tickets or access issues
 *
 * Request Body:
 * {
 *   identifier: string,    // Ticket number, email, or user ID
 *   event_id: string,
 *   location: string,
 *   reason: string         // "lost", "damaged", "id_verification", etc.
 * }
 *
 * Response:
 * {
 *   success: bool,
 *   ticket_id: string,
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

// IMPORTANT: Require admin/bouncer authentication for overrides
// (Prevent unauthorized manual redemptions)

if (!isset($_SESSION['User']) || empty($_SESSION['User']['Id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// TODO: Verify user has bouncer privileges for this event
// For now, require admin role as safety measure
if (($_SESSION['User']['RoleId'] ?? null) !== 1) { // Assuming RoleId 1 is admin
    http_response_code(403);
    echo json_encode([
        'error' => 'Insufficient privileges',
        'message' => 'Only admins/event managers can use manual override'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST method required']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['identifier'], $data['event_id'])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Required fields: identifier, event_id'
    ]);
    exit;
}

$identifier = $data['identifier'];
$eventId = $data['event_id'];
$location = $data['location'] ?? 'Manual Override';
$reason = $data['reason'] ?? 'Lost/damaged ticket';
$bouncerId = (string)$_SESSION['User']['Id'];

try {
    $config = new Config();
    $pdo = ConnectionFactory::read($config);
    $verifier = new TicketVerifier($pdo);

    // Perform manual override
    $result = $verifier->manualOverride(
        $identifier,
        $bouncerId,
        $location,
        $reason
    );

    if ($result['success']) {
        // Log security event
        error_log("AUDIT: Manual override by {$bouncerId}: {$identifier} at {$location} ({$reason})");
    }

    http_response_code(200);
    echo json_encode([
        'success' => $result['success'],
        'ticket_id' => $result['ticket_id'] ?? null,
        'message' => $result['message'],
        'timestamp' => date('c')
    ]);

} catch (\Throwable $e) {
    error_log("Override API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Override failed',
        'timestamp' => date('c')
    ]);
}
