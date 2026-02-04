<?php
/**
 * Slug Availability Check API
 * Endpoint: GET /api/v1/urls/check?slug=john-doe&entity_type=artist
 *
 * Returns: {
 *   available: bool,
 *   slug: string,
 *   suggestions: string[],
 *   reason: string (if not available)
 * }
 */

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\URL\SlugGenerator;

// Authentication check (optional - can check without auth for public availability)
if (!isset($_SESSION)) {
    session_start();
}

header('Content-Type: application/json');

// Validate input
if (!isset($_GET['slug'])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'slug parameter required',
        'available' => false
    ]);
    exit;
}

$slug = trim($_GET['slug']);
$entityType = $_GET['entity_type'] ?? 'artist';

// Validate entity type
$validTypes = ['artist', 'label', 'venue', 'station', 'fan'];
if (!in_array($entityType, $validTypes)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid entity_type',
        'available' => false
    ]);
    exit;
}

try {
    $config = new Config();
    $pdo = ConnectionFactory::read($config);
    $slugGenerator = new SlugGenerator($pdo);

    // Validate the slug
    $tier = 'pro'; // Assume Pro tier by default (free tier is @username only)
    if (isset($_SESSION['User']['Tier'])) {
        $tier = strtolower($_SESSION['User']['Tier']);
    }

    $validation = $slugGenerator->validateSlug($slug, $entityType, null, $tier);

    if ($validation['valid']) {
        // Slug is available
        echo json_encode([
            'available' => true,
            'slug' => $validation['slug'],
            'suggestions' => [],
            'message' => 'Slug is available'
        ]);
    } else {
        // Slug is not available - suggest alternatives
        $suggestions = $slugGenerator->getSuggestedAlternatives(
            $validation['slug'],
            $entityType,
            5
        );

        http_response_code(200);
        echo json_encode([
            'available' => false,
            'slug' => $validation['slug'],
            'reason' => $validation['error'],
            'suggestions' => $suggestions
        ]);
    }

} catch (\Throwable $e) {
    error_log("Slug check API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'available' => false
    ]);
}
