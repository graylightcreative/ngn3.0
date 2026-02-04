<?php
/**
 * Unified Profile Router
 *
 * Entry point for all clean URL patterns:
 * - /john-doe (vanity slug)
 * - /@john_smith (username)
 * - Subdomains: john.ngn.io (custom subdomain)
 * - Custom domains: johnny.com (custom domain)
 *
 * Routes to appropriate profile view based on entity type
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\URL\ProfileRouter;

$config = new Config();
$pdo = ConnectionFactory::read($config);
$router = new ProfileRouter($pdo);

// Determine the identifier from the request
$identifier = null;
$redirectUrl = null;

// 1. Check for custom domain (if different from main domain)
$host = $_SERVER['HTTP_HOST'] ?? 'ngn.io';
$host = str_replace(['https://', 'http://'], '', $host);
if (stripos($host, 'ngn.io') === false) {
    // This is a custom domain request (e.g., johnny.com)
    $identifier = $router->resolveByCustomDomain($host);
} else {
    // 2. Check for subdomain on ngn.io (e.g., john.ngn.io)
    if (preg_match('/^([a-z0-9\-]+)\.ngn\.io$/', $host, $m)) {
        $subdomain = $m[1];
        if ($subdomain !== 'www') {
            $identifier = $router->resolveBySubdomain($subdomain);
        }
    }
}

// 3. Check for @username pattern: /@john_smith
if (!$identifier && preg_match('/^\/\@([a-zA-Z0-9_\-]+)\/?$/', $_SERVER['REQUEST_URI'] ?? '', $m)) {
    $username = $m[1];
    $identifier = $router->resolveByUsername($username);
    if ($identifier) {
        // Cache in session for quick access
        $_SESSION['_resolved_profile'] = $identifier;
    }
}

// 4. Check for vanity slug: /john-doe
if (!$identifier) {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    // Extract the slug from the path (first segment after /)
    if (preg_match('/^\/([a-zA-Z0-9\-]+)\/?$/', $path, $m)) {
        $slug = $m[1];

        // Don't match reserved routes
        $reserved = ['dashboard', 'admin', 'api', 'auth', 'login', 'register', 'logout',
                     'profile', 'settings', 'pricing', 'help', 'terms', 'privacy', 'contact', 'about'];

        if (!in_array($slug, $reserved)) {
            $identifier = $router->resolveBySlug($slug);
            if ($identifier) {
                $_SESSION['_resolved_profile'] = $identifier;
            }
        }
    }
}

// 5. Check for legacy query string ID (for backwards compatibility with old URLs)
if (!$identifier && isset($_GET['id'])) {
    $entityId = (int)$_GET['id'];

    // Try to determine entity type from context
    $entityType = $_GET['type'] ?? 'artist'; // Default to artist

    if ($entityId > 0) {
        // Resolve by ID and redirect to clean URL
        $identifier = $router->resolveById($entityType, $entityId);

        if ($identifier) {
            // Redirect to clean URL with 301
            $redirectUrl = $identifier['canonical_url'] ?? "/{$identifier['url_slug']}";
        }
    }
}

// Handle redirects (e.g., old ID-based URLs → clean URLs)
if ($redirectUrl) {
    header("Location: {$redirectUrl}", true, 301);
    exit;
}

// If not found, return 404
if (!$identifier) {
    http_response_code(404);

    // Include 404 template
    $pageTitle = 'Profile Not Found';
    include __DIR__ . '/lib/404.php';
    exit;
}

// ============================================================================
// Profile Found - Route to appropriate view
// ============================================================================

extract($identifier); // $entity_type, $entity_id, $name, $image, $bio, etc.

$entityType = $entity_type;
$entityId = $entity_id;

try {
    // Store in SESSION for the profile page to access
    $_SESSION['_profile_resolved'] = [
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'identifier' => $identifier
    ];

    // Determine which profile page to include
    switch ($entityType) {
        case 'artist':
            // Pass slug to existing artist-profile.php
            $_GET['slug'] = $url_slug ?? null;
            $_GET['id'] = $entityId;
            include __DIR__ . '/artist-profile.php';
            break;

        case 'label':
            $_GET['slug'] = $url_slug ?? null;
            $_GET['id'] = $entityId;
            include __DIR__ . '/label-profile.php';
            break;

        case 'venue':
            $_GET['slug'] = $url_slug ?? null;
            $_GET['id'] = $entityId;
            include __DIR__ . '/venue-profile.php';
            break;

        case 'station':
            $_GET['slug'] = $url_slug ?? null;
            $_GET['id'] = $entityId;
            include __DIR__ . '/station-profile.php';
            break;

        case 'fan':
            // For fan profiles, show a simple profile view or redirect to a fan profile page
            // For now, show a 404 until fan profiles are implemented
            http_response_code(404);
            include __DIR__ . '/lib/404.php';
            exit;

        default:
            http_response_code(404);
            include __DIR__ . '/lib/404.php';
            exit;
    }

} catch (Exception $e) {
    error_log("Profile router error: " . $e->getMessage());
    http_response_code(500);
    include __DIR__ . '/lib/500.php';
}
