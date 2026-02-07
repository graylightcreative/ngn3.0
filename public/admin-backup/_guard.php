<?php
// Admin directory guard — strict mode: credentials required at all times
// This file must be required at the top of ALL scripts under /admin.

$__admin_dir = __DIR__;
$__root = dirname($__admin_dir);
$__bootstrap = $__root . '/lib/bootstrap.php';
if (is_file($__bootstrap)) { require_once $__bootstrap; }

// Start session with proper cookie config (for legacy web logins)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    @session_start();
}

// Helpers
$toBool = function ($v): bool { $v = strtolower((string)$v); return in_array($v, ['1','true','yes','on'], true); };

// Determine admin role IDs (default: 1)
$adminRoleIds = ['1'];
try {
    if (class_exists('NGN\\Lib\\Config')) {
        $cfg = new NGN\Lib\Config();
        if (method_exists($cfg, 'legacyAdminRoleIds')) {
            $adminRoleIds = array_map('strval', $cfg->legacyAdminRoleIds());
        }
    } elseif (class_exists('NGN\\Lib\\Env')) {
        $raw = NGN\Lib\Env::get('LEGACY_ADMIN_ROLE_IDS', '1') ?? '1';
        $adminRoleIds = array_values(array_filter(array_map('trim', explode(',', (string)$raw)), fn($x)=>$x!==''));
    } else {
        $raw = getenv('LEGACY_ADMIN_ROLE_IDS') ?: '1';
        $adminRoleIds = array_values(array_filter(array_map('trim', explode(',', (string)$raw)), fn($x)=>$x!==''));
    }
} catch (\Throwable $e) { /* keep defaults */ }

// Check for a valid admin JWT in Authorization header (cookie not accepted)
$hasValidAdminToken = false;
try {
    $authHeader = null;
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (function_exists('getallheaders')) {
        $all = @getallheaders();
        if (is_array($all)) {
            foreach ($all as $k => $v) {
                if (strtolower($k) === 'authorization') { $authHeader = $v; break; }
            }
        }
    }
    if ($authHeader && stripos($authHeader, 'Bearer ') === 0 && class_exists('NGN\\Lib\\Auth\\TokenService') && isset($cfg)) {
        $token = trim(substr($authHeader, 7));
        if ($token !== '') {
            $svc = new NGN\Lib\Auth\TokenService($cfg);
            $claims = $svc->decode($token);
            $role = strtolower((string)($claims['role'] ?? ''));
            if ($role === 'admin') { $hasValidAdminToken = true; }
        }
    }
} catch (\Throwable $e) { $hasValidAdminToken = false; }

// Check for an authenticated admin session (legacy web login)
$hasAdminSession = false;
try {
    if (!empty($_SESSION['User']['RoleId'])) {
        $rid = (string)$_SESSION['User']['RoleId'];
        if (in_array($rid, $adminRoleIds, true)) { $hasAdminSession = true; }
    }
} catch (\Throwable $e) { $hasAdminSession = false; }

// Strict allow policy: require either admin bearer token or admin session
$allow = $hasValidAdminToken || $hasAdminSession;

// Allowlist: let Settings page (ngn2.php) render its inline API login when unauthenticated (GET + HTML only)
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/admin/', PHP_URL_PATH) ?? '/admin/';
$pathLc = strtolower($path);
$allowInlineApiLogin = ($pathLc === '/admin/ngn2.php');
$isGet = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET';
$accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
$wantsHtml = strpos($accept, 'text/html') !== false || empty($accept);

if (!$allow) {
    if ($allowInlineApiLogin && $isGet && $wantsHtml) {
        // Do not redirect; allow page to render. Client JS shows API Login and disables dangerous actions.
        // Set strict headers and continue.
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: no-referrer');
            header('X-Frame-Options: DENY');
            header('Permissions-Policy: interest-cohort=()');
        }
        // Fall through (no exit) so ngn2.php can render its inline API login
    } else {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: no-referrer');
            header('X-Frame-Options: DENY');
            header('Permissions-Policy: interest-cohort=()');
        }
        // Redirect browsers to login; otherwise return 401
        $next = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/admin/';
        if ($isGet && $wantsHtml) {
            header('Location: /login.php?next=' . rawurlencode($next), true, 302);
        } else {
            if (!headers_sent()) {
                $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
                header($protocol . ' 401 Unauthorized');
                header('Content-Type: text/plain; charset=UTF-8');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            }
            echo "Unauthorized\n";
        }
        exit;
    }
}

// Allowed — set security headers for admin UI
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: SAMEORIGIN');
}
