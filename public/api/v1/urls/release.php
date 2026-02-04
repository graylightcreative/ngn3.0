<?php
/**
 * Slug Release API
 * Endpoint: POST /api/v1/urls/release
 * Body: {
 *   entity_type: string,
 *   entity_id: int
 * }
 *
 * Releases a claimed slug back to availability
 * Reverts entity to auto-generated slug
 */

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\URL\SlugGenerator;

// Start session
if (!isset($_SESSION)) {
    session_start();
}

header('Content-Type: application/json');

// Authentication check
if (!isset($_SESSION['User'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required'
    ]);
    exit;
}

// POST method required
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'POST method required'
    ]);
    exit;
}

// Parse JSON body
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['entity_type'], $data['entity_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Required fields missing: entity_type, entity_id'
    ]);
    exit;
}

$entityType = $data['entity_type'];
$entityId = (int)$data['entity_id'];
$userId = (int)$_SESSION['User']['Id'];

// Validate entity type
$validTypes = ['artist', 'label', 'venue', 'station'];
if (!in_array($entityType, $validTypes)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid entity_type'
    ]);
    exit;
}

try {
    $config = new Config();
    $pdo = ConnectionFactory::read($config);
    $slugGenerator = new SlugGenerator($pdo);

    // Verify user owns this entity
    $ownershipStmt = $pdo->prepare(
        "SELECT id FROM {$entityType}s WHERE id = ? AND user_id = ?"
    );
    $ownershipStmt->execute([$entityId, $userId]);
    if (!$ownershipStmt->fetch()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Not authorized',
            'reason' => 'You do not own this profile'
        ]);
        exit;
    }

    // Get current route info
    $routeStmt = $pdo->prepare(
        'SELECT * FROM url_routes WHERE entity_type = ? AND entity_id = ?'
    );
    $routeStmt->execute([$entityType, $entityId]);
    $currentRoute = $routeStmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentRoute) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'No claimed slug found for this entity'
        ]);
        exit;
    }

    $oldSlug = $currentRoute['url_slug'];

    // Get entity name for auto-generation
    $entityStmt = $pdo->prepare(
        "SELECT Name FROM {$entityType}s WHERE id = ?"
    );
    $entityStmt->execute([$entityId]);
    $entity = $entityStmt->fetch(PDO::FETCH_ASSOC);

    if (!$entity) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Entity not found'
        ]);
        exit;
    }

    // Generate a new auto-slug (without manual claim)
    $newSlug = $slugGenerator->generateSlug($entity['Name'], $entityType, $entityId);

    // Update the route
    $deleteStmt = $pdo->prepare(
        'DELETE FROM url_routes WHERE entity_type = ? AND entity_id = ?'
    );
    $deleteStmt->execute([$entityType, $entityId]);

    // Update entity to revert to auto-generated slug
    $updateEntityStmt = $pdo->prepare(
        "UPDATE {$entityType}s SET url_slug = ?, claimed = 0, updated_at = NOW() WHERE id = ?"
    );
    $updateEntityStmt->execute([$newSlug, $entityId]);

    // Log the release
    $logStmt = $pdo->prepare(
        'INSERT INTO url_slug_history (entity_type, entity_id, old_slug, new_slug, action, reason, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    $logStmt->execute([
        $entityType,
        $entityId,
        $oldSlug,
        $newSlug,
        'released',
        'User released claimed slug'
    ]);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Slug released successfully',
        'oldSlug' => $oldSlug,
        'newSlug' => $newSlug,
        'newUrl' => "/{$newSlug}"
    ]);

} catch (\Throwable $e) {
    error_log("Slug release API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
