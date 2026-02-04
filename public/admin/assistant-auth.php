<?php
/**
 * Assistant Authentication Middleware
 *
 * Include this file at the top of any page that requires assistant authentication.
 * Redirects to login page if not authenticated or role is not 'assistant'.
 *
 * Usage:
 * require_once __DIR__ . '/assistant-auth.php';
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: assistant-login.php');
    exit;
}

// Check if user has assistant role
if ($_SESSION['role'] !== 'assistant') {
    session_destroy();
    header('Location: assistant-login.php?error=unauthorized');
    exit;
}

// Check session timeout (4 hours)
$sessionTimeout = 4 * 60 * 60; // 4 hours in seconds
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > $sessionTimeout)) {
    session_destroy();
    header('Location: assistant-login.php?error=timeout');
    exit;
}

// Refresh session login time on activity
$_SESSION['last_activity'] = time();
