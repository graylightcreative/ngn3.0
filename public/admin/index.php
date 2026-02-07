<?php
/**
 * NGN Admin v2 - React SPA Entry Point
 *
 * This file:
 * 1. Validates admin JWT or session via _guard.php
 * 2. Passes the token to the React app
 * 3. Serves the SPA shell that loads React
 */

require_once __DIR__ . '/_guard.php';

// Extract JWT from Authorization header or session
$adminToken = '';
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

    if ($authHeader && stripos($authHeader, 'Bearer ') === 0) {
        $adminToken = trim(substr($authHeader, 7));
    }
} catch (\Throwable $e) {
    // Token extraction failed, will fall back to empty
}

// Security headers
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Cache-Control: no-cache, no-store, must-revalidate');
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8" />
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='75' font-size='75' fill='%231DB954'>N</text></svg>" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>NGN Admin v2</title>
    <script>
        // Pass admin token to React app
        window.NGN_ADMIN_TOKEN = <?= json_encode($adminToken) ?>;
    </script>
    <script type="module" crossorigin src="/admin/assets/index-eTQlndin.js"></script>
    <link rel="stylesheet" crossorigin href="/admin/assets/index-C4FZWdHt.css">
</head>
<body>
    <div id="root"></div>
</body>
</html>
