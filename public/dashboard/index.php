<?php
/**
 * NGN Unified Dashboard Terminal
 * Routes dashboard.nextgennoise.com to appropriate entity dashboard.
 */
require_once __DIR__ . '/../lib/bootstrap.php';

dashboard_require_auth();

$user = dashboard_get_user();
$entityType = dashboard_get_entity_type();

if ($entityType) {
    header("Location: /dashboard/{$entityType}/index.php");
    exit;
}

// Fallback: If no entity type, show a selector or generic home
header("Location: /profile.php");
exit;
