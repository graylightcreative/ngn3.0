<?php
/**
 * Slug Claiming API
 * Endpoint: POST /api/v1/urls/claim
 * Body: {
 *   slug: string (desired slug),
 *   entity_type: string (artist, label, venue, station),
 *   entity_id: int
 * }
 *
 * Response: {
 *   success: bool,
 *   slug: string (if claimed),
 *   url: string (if claimed),
 *   error: string (if failed),
 *   reason: string
 * }
 */

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\URL\SlugGenerator;
use NGN\Lib\URL\ProfileRouter;

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

if (!$data || !isset($data['slug'], $data['entity_type'], $data['entity_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Required fields missing: slug, entity_type, entity_id'
    ]);
    exit;
}

$desiredSlug = trim($data['slug']);
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
    $profileRouter = new ProfileRouter($pdo);

    // Check user tier (must be Pro+)
    $userTier = strtolower($_SESSION['User']['Tier'] ?? 'free');
    if ($userTier === 'free') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Pro tier required',
            'reason' => 'Upgrade to Pro to claim custom URLs'
        ]);
        exit;
    }

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

    // Check if user already has a claimed slug for this entity
    $existingStmt = $pdo->prepare(
        "SELECT id FROM url_routes WHERE entity_type = ? AND entity_id = ?"
    );
    $existingStmt->execute([$entityType, $entityId]);
    $existingRoute = $existingStmt->fetch();

    // Validate the slug
    $validation = $slugGenerator->validateSlug(
        $desiredSlug,
        $entityType,
        $existingRoute ? null : $entityId,
        $userTier
    );

    if (!$validation['valid']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $validation['error'],
            'slug' => $validation['slug'],
            'suggestions' => $slugGenerator->getSuggestedAlternatives(
                $validation['slug'],
                $entityType,
                5
            )
        ]);
        exit;
    }

    $finalSlug = $validation['slug'];

    // Update or create the route
    if ($existingRoute) {
        // Update existing route
        $updateStmt = $pdo->prepare(
            'UPDATE url_routes SET url_slug = ?, updated_at = NOW() WHERE entity_type = ? AND entity_id = ?'
        );
        $updateStmt->execute([$finalSlug, $entityType, $entityId]);

        // Log the action
        $logStmt = $pdo->prepare(
            'INSERT INTO url_slug_history (entity_type, entity_id, old_slug, new_slug, action, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $logStmt->execute([
            $entityType,
            $entityId,
            $existingRoute['url_slug'] ?? null,
            $finalSlug,
            'updated'
        ]);
    } else {
        // Create new route
        $canonicalUrl = $entityType === 'artist' ? "/artist/{$finalSlug}" : "/{$finalSlug}";

        $insertStmt = $pdo->prepare(
            'INSERT INTO url_routes (entity_type, entity_id, url_slug, canonical_url, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())'
        );
        $insertStmt->execute([$entityType, $entityId, $finalSlug, $canonicalUrl]);

        // Log the action
        $logStmt = $pdo->prepare(
            'INSERT INTO url_slug_history (entity_type, entity_id, new_slug, action, created_at)
            VALUES (?, ?, ?, ?, NOW())'
        );
        $logStmt->execute([$entityType, $entityId, $finalSlug, 'created']);
    }

    // Also update the entity's url_slug column if not already set
    $updateEntityStmt = $pdo->prepare(
        "UPDATE {$entityType}s SET url_slug = ?, claimed = 1, updated_at = NOW() WHERE id = ?"
    );
    $updateEntityStmt->execute([$finalSlug, $entityId]);

    // Build response URL
    $responseUrl = $entityType === 'artist' ? "/artist/{$finalSlug}" : "/{$finalSlug}";

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'slug' => $finalSlug,
        'url' => $responseUrl,
        'message' => 'Slug claimed successfully'
    ]);

} catch (\Throwable $e) {
    error_log("Slug claim API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
