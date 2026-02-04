<?php
/**
 * 🎵 Toggle Spotify Killer Theme - Admin Only
 * POST endpoint to toggle the SK theme on/off
 */

require_once __DIR__ . '/_guard.php';

use NGN\Lib\Theme\SpotifyKillerTheme;

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/');
    exit;
}

// Check admin access
if (!SpotifyKillerTheme::isAdmin()) {
    http_response_code(403);
    echo '⛔ Admin access required';
    exit;
}

// Get toggle value
$enabled = isset($_POST['sk_theme']) && $_POST['sk_theme'] === '1';

// Set the cookie
SpotifyKillerTheme::toggle($enabled);

// Redirect back to referrer or admin
$redirect = $_SERVER['HTTP_REFERER'] ?? '/admin/';
header('Location: ' . $redirect);
exit;
