<?php
// NGN bootstrap: require Composer autoloader and provide a defensive fallback

if (defined('NGN_BOOTSTRAP_LOADED')) {
    return;
}
define('NGN_BOOTSTRAP_LOADED', true);

$__root = dirname(realpath(__DIR__));

// 1) Composer autoloader
$__autoload = $__root . '/vendor/autoload.php';
if (is_file($__autoload)) {
    require_once $__autoload;
}

// 1.5) Load utility functions for common operations
$__imageUtils = $__root . '/lib/utils/image.php';
if (is_file($__imageUtils)) {
    require_once $__imageUtils;
}

// 2) Defensive autoload guard for NGN\Lib\ classes when Composer is stale or classmap is wrong
if (!class_exists('NGN\\Lib\\Env')) {
//    spl_autoload_register(function ($class) use ($__root) {
//        $prefix = 'NGN\\Lib\\';
//        if (strpos($class, $prefix) !== 0) return;
//        $rel = substr($class, strlen($prefix));
//        $rel = str_replace('\\', '/', $rel);
//        $path = $__root . '/lib/' . $rel . '.php';
//        if (is_file($path) && !class_exists($class)) {
//            require $path;
//        }
//    });
    // Proactively load Env to ensure autoload is functional even if class_exists checks happen early
    $envFile = $__root . '/lib/Env.php';
    if (!class_exists('NGN\\Lib\\Env') && is_file($envFile)) {
// require $envFile;
    }
}

// 2b) Ensure environment variables are loaded from .env early
if (class_exists('NGN\\Lib\\Env')) {
    try {
        NGN\Lib\Env::load($__root);
    } catch (\Throwable $e) {
        // Swallow to avoid breaking production; diagnostics will reflect missing env
        error_log("Bootstrap - Env::load failed: " . $e->getMessage());
    }
}
// Add logging here to check APP_ENV immediately after Env::load() is called
if (class_exists('NGN\\Lib\\Env')) {
    error_log("Bootstrap - APP_ENV after Env::load(): " . (NGN\Lib\Env::get('APP_ENV') === null ? 'null' : NGN\Lib\Env::get('APP_ENV')));
}

// 2c) Set Stripe API Key based on environment
if (class_exists('Stripe\\Stripe') && class_exists('NGN\\Lib\\Env')) {
    $appEnv = NGN\Lib\Env::get('APP_ENV', 'production'); // Default to production

    if (in_array($appEnv, ['local', 'development'])) {
        $stripeSecretKey = NGN\Lib\Env::get('STRIPE_SANDBOX_SECRET_KEY');
    } else {
        $stripeSecretKey = NGN\Lib\Env::get('STRIPE_SECRET_KEY');
    }

    if ($stripeSecretKey) {
        \Stripe\Stripe::setApiKey($stripeSecretKey);
        // Optional: Set API version if needed
        // \Stripe\Stripe::setApiVersion('2022-11-15');
    } else {
        error_log("Bootstrap - Stripe API key not set for environment: " . $appEnv);
    }
}

// 3) Helper to emit diagnostics if still missing (web-safe or CLI)
if (!function_exists('ngn_autoload_diagnostics')) {
    function ngn_autoload_diagnostics(string $root, bool $isCli = false): void {
        $autoloadPath = $root . '/vendor/autoload.php';
        if ($isCli) {
            fwrite(STDERR, "Autoload failed: NGN\\Lib\\Env class not found\n");
            $status = is_file($autoloadPath) ? 'found' : 'missing';
            fwrite(STDERR, 'Checked autoload file: ' . $autoloadPath . ' => ' . $status . "\n");
            fwrite(STDERR, 'PHP version: ' . PHP_VERSION . "\n\n");
            fwrite(STDERR, "Resolution steps (SSH):\n");
            fwrite(STDERR, 'cd ' . $root . "\ncomposer install --no-dev --prefer-dist\ncomposer dump-autoload -o\n# Then reload php-fpm or clear OPcache if enabled\n");
            fwrite(STDERR, "If Composer is unavailable, ensure the repository includes the vendor/ directory built from this branch.\n");
            return;
        }
        $enc = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
        $status = is_file($autoloadPath) ? 'found' : 'missing';
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>NGN Autoload Error</title>' . 
            '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f8fafc;color:#111827;padding:24px}' . 
            '.card{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;max-width:800px;margin:0 auto;}' . 
            '.error{color:#b91c1c;font-weight:600} code{background:#f3f4f6;padding:2px 4px;border-radius:4px}</style></head><body>' . 
            '<div class="card">' . 
            '<div class="error">Autoload failed: NGN\\Lib\\Env class not found</div>' . 
            '<p>The Composer autoloader may be missing or out of date on this server.</p>' . 
            '<ul>' . 
            '<li>Checked autoload file: <code>' . $enc($autoloadPath) . '</code> => ' . $status . '</li>' . 
            '<li>PHP version: <code>' . $enc(PHP_VERSION) . '</code></li>' . 
            '</ul>' . 
            '<p>Resolution steps (SSH):</p>' . 
            '<pre>cd ' . $enc($root) . "\ncomposer install --no-dev --prefer-dist\ncomposer dump-autoload -o\n# Then reload php-fpm or clear OPcache if enabled</pre>" . 
            '<p>If Composer is unavailable, ensure the repository includes the <code>vendor/</code> directory built from this branch.</p>' . 
            '</div></body></html>';
        echo $html;
    }
}

// 4) Universal maintenance guard (defense-in-depth)
// Apply early for all web requests to ensure public pages are locked when MAINTENANCE_MODE=true,
// even if a page forgets to include lib/partials/head.php.
// Allowlist: /api/*, /admin/*, /maintenance*, /login.php
// Admin bypass: legacy admin session or admin JWT with role=admin
try {
    if (php_sapi_name() !== 'cli') {
        $reqUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = strtolower(parse_url($reqUri, PHP_URL_PATH) ?? '/');

        // Avoid re-entering when we're already serving the maintenance page
        $servingMaintenance = (str_starts_with($path, '/maintenance'));

        // Attempt to load Env/Config if available (already done above, but guard it)
        if (class_exists('NGN\\Lib\\Env')) { NGN\Lib\Env::load($__root); }
        $cfg = null;
        $featureFlags = null;

        // Try to instantiate Config with FeatureFlagService for database-backed feature flags
        if (class_exists('NGN\\Lib\\Config')) {
            try {
                // Attempt to create PDO and FeatureFlagService for hot-reload support
                if (class_exists('NGN\\Lib\\Services\\FeatureFlagService') && class_exists('NGN\\Lib\\Env')) {
                    $dbCfg = [
                        'host' => NGN\Lib\Env::get('DB_HOST', '127.0.0.1'),
                        'port' => (int)(NGN\Lib\Env::get('DB_PORT', '3306') ?? '3306'),
                        'name' => NGN\Lib\Env::get('DB_NAME', ''),
                        'user' => NGN\Lib\Env::get('DB_USER', ''),
                        'pass' => NGN\Lib\Env::get('DB_PASS', ''),
                    ];
                    if (!empty($dbCfg['name']) && !empty($dbCfg['user'])) {
                        $pdo = new PDO(
                            "mysql:host={$dbCfg['host']}:{$dbCfg['port']};dbname={$dbCfg['name']};charset=utf8mb4",
                            $dbCfg['user'],
                            $dbCfg['pass'],
                            [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]
                        );
                        $featureFlags = new NGN\Lib\Services\FeatureFlagService($pdo);
                    }
                }
            } catch (\Throwable $e) {
                // Silently ignore PDO/service errors; will fall back to Env
            }
            $cfg = new NGN\Lib\Config($featureFlags);
        }

        $isMaint = $cfg ? (bool)$cfg->maintenanceMode() : false;

        if ($isMaint && !$servingMaintenance) {
            // Allowlisted paths during maintenance
            $allowed = (
                // API always allowed
                str_starts_with($path, '/api/') ||
                // Admin area (any subpath) allowed during maintenance
                str_starts_with($path, '/admin/') ||
                // Allow bare /admin landing too
                $path === '/admin' ||
                $path === '/admin.php' ||
                // Dashboard area (artist/label/station/venue dashboards)
                str_starts_with($path, '/dashboard/') ||
                str_starts_with($path, '/dashboard') ||
                // Maintenance pages themselves
                str_starts_with($path, '/maintenance') ||
                // NGN 2.0 preview app (allow during maintenance)
                str_starts_with($path, '/frontend') ||
                // Legacy short routes that redirect to new app views
                $path === '/charts' ||
                $path === '/charts/' ||
                $path === '/smr-charts' ||
                $path === '/smr-charts/' ||
                // Login/logout routes (support both pretty and .php)
                $path === '/login' ||
                $path === '/login.php' ||
                $path === '/logout' ||
                $path === '/logout.php' ||
                $path === '/admin/login' ||
                $path === '/admin/login.php' ||
                $path === '/admin/logout' ||
                $path === '/admin/logout.php' ||
                // Allow legacy login handler to function during maintenance
                $path === '/lib/handlers/login.php'
            );

            // Admin bypass (legacy session or admin JWT)
            $isAdmin = false;
            try {
                if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
                $adminRoleIds = ['1'];
                if (class_exists('NGN\\Lib\\Config')) {
                    $adminRoleIds = array_map('strval', (new NGN\Lib\Config())->legacyAdminRoleIds());
                }
                if (!empty($_SESSION['User']['RoleId'])) {
                    $rid = (string)$_SESSION['User']['RoleId'];
                    $isAdmin = in_array($rid, $adminRoleIds, true);
                }
                if (!$isAdmin && isset($_SERVER['HTTP_AUTHORIZATION']) && stripos($_SERVER['HTTP_AUTHORIZATION'], 'Bearer ') === 0) {
                    $hdr = trim(substr($_SERVER['HTTP_AUTHORIZATION'], 7));
                    if ($hdr !== '' && class_exists('NGN\\Lib\\Auth\\TokenService')) {
                        $claims = (new NGN\Lib\Auth\TokenService($cfg))->decode($hdr);
                        $role = strtolower((string)($claims['role'] ?? ''));
                        if ($role === 'admin') { $isAdmin = true; }
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }

            if (!$allowed && !$isAdmin) {
                // Serve maintenance landing and stop execution
                include $__root . '/maintenance/index.php';
                exit;
            }
        }
    }
} catch (\Throwable $e) {
    // Never break the site due to guard errors
}