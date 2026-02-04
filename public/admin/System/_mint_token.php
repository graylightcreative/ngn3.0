<?php
/**
 * Auto-mint admin token for authenticated admin users.
 * Include this file AFTER _guard.php and bootstrap.php.
 *
 * Requires: $cfg (Config instance)
 * Sets: $mintedToken, $mintedExpires
 */

use NGN\Lib\Auth\TokenService;

$mintedToken = null;
$mintedExpires = 0;

// Ensure session is started
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// If we got past _guard.php, we're authorized to mint a token
try {
    $svc = new TokenService($cfg);
    $sub = !empty($_SESSION['User']['Email']) ? (string)$_SESSION['User']['Email'] : 'admin@session';
    $issued = $svc->issueAccessToken(['sub' => $sub, 'role' => 'admin']);
    $mintedToken = $issued['token'] ?? null;
    $mintedExpires = (int)($issued['expires_in'] ?? 0);
} catch (\Throwable $e) {
    error_log('Token mint failed: ' . $e->getMessage());
}

