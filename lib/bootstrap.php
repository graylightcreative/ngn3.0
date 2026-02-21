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

$__imageHelpers = $__root . '/lib/helpers/image.php';
if (is_file($__imageHelpers)) {
    require_once $__imageHelpers;
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
        require $envFile;
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
                    $dbHost = NGN\Lib\Env::get('DB_HOST', '127.0.0.1');
                    $dbPort = (int)(NGN\Lib\Env::get('DB_PORT', '3306') ?? '3306');
                    $dbName = NGN\Lib\Env::get('DB_NAME', '');
                    $dbUser = NGN\Lib\Env::get('DB_USER', '');
                    $dbPass = NGN\Lib\Env::get('DB_PASS', '');

                    if (!empty($dbName) && !empty($dbUser)) {
                        // Use a short timeout for the maintenance guard DB check to prevent hanging
                        $pdoMaint = new PDO(
                            "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
                            $dbUser,
                            $dbPass,
                            [
                                PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
                                PDO::ATTR_TIMEOUT => 1 // 1 second timeout
                            ]
                        );
                        // Check if table exists before using service
                        $tableCheck = $pdoMaint->query("SHOW TABLES LIKE 'feature_flags'");
                        if ($tableCheck && $tableCheck->rowCount() > 0) {
                            $featureFlags = new NGN\Lib\Services\FeatureFlagService($pdoMaint);
                        }
                    }
                }
            } catch (\Throwable $e) {
                error_log("Bootstrap - Maintenance guard DB check failed: " . $e->getMessage());
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

// Version Banner (inject early for visibility)
if (!defined('NGN_VERSION_BANNER_RENDERED')) {
    // ... existing banner logic ...
}

/**
 * Global UI Helper: Profile Upsell Component
 * High-velocity onboarding trigger for unclaimed or empty profiles.
 */
if (!function_exists('render_profile_upsell')) {
    function render_profile_upsell(string $title, string $message, bool $isClaimed, ?string $slug = null): void {
        $ctaText = $isClaimed ? "Management_Portal" : "Claim_Institutional_Portal";
        $ctaUrl = "/register.php"; // Default to registration
        ?>
        <div class="glass-panel p-12 rounded-[2rem] text-center max-w-2xl mx-auto border-dashed border-white/10 mt-12 mb-12">
            <div class="w-16 h-16 bg-white/5 rounded-2xl flex items-center justify-center text-zinc-500 mx-auto mb-6">
                <i class="bi bi-rocket-takeoff text-3xl"></i>
            </div>
            <h3 class="text-2xl font-black text-white uppercase italic brand-gradient-text mb-4"><?= htmlspecialchars(str_replace(' ', '_', $title)) ?>_Inactive</h3>
            <p class="text-zinc-500 font-medium leading-relaxed mb-8"><?= htmlspecialchars($message) ?></p>
            <a href="<?= $ctaUrl ?>" class="inline-block px-10 py-5 bg-brand text-black font-black uppercase tracking-widest text-xs rounded-full hover:bg-white transition-all shadow-xl shadow-brand/20">
                <?= $ctaText ?>
            </a>
        </div>
        <?php
    }
}

/**
 * Global UI Helper: Audit / Prove It Section
 */
if (!function_exists('render_audit_section')) {
    function render_audit_section(array $auditData): void {
        $factors = $auditData['factors'] ?? [];
        if (empty($factors)) return;
        ?>
        <section class="sp-card border border-white/5 p-8 relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10">
                <i class="bi bi-shield-check text-6xl"></i>
            </div>
            <h2 class="text-xs font-black text-zinc-500 uppercase tracking-[0.3em] mb-8">Data_Integrity // Prove_It</h2>
            <div class="space-y-6">
                <?php foreach ($factors as $f): ?>
                <div class="flex items-center justify-between group/row">
                    <div>
                        <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mb-1"><?= htmlspecialchars($f['label']) ?></div>
                        <div class="text-sm font-bold text-white"><?= htmlspecialchars($f['status'] ?? 'Verified') ?></div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs font-black text-brand">+<?= number_format($f['value'] ?? 0) ?></div>
                        <div class="text-[8px] font-black text-zinc-600 uppercase tracking-widest">Impact</div>
                    </div>
                </div>
                <div class="h-px w-full bg-white/5"></div>
                <?php endforeach; ?>
            </div>
            <div class="mt-8 pt-6 border-t border-dashed border-white/10">
                <p class="text-[9px] text-zinc-600 font-mono italic leading-relaxed">
                    Source of truth anchored to Graylight Nexus. All signals verified for data integrity.
                </p>
            </div>
        </section>
        <?php
    }
}

/**
 * Global UI Helper: Recent Spins Section
 */
if (!function_exists('render_recent_spins')) {
    function render_recent_spins(array $spins, string $type = 'artist'): void {
        if (empty($spins)) return;
        ?>
        <section class="sp-card border border-white/5 p-8">
            <h2 class="text-xs font-black text-zinc-500 uppercase tracking-[0.3em] mb-8">Live_Signal // Radio_Rotation</h2>
            <div class="space-y-6">
                <?php foreach ($spins as $spin): ?>
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded bg-brand/10 flex items-center justify-center text-brand">
                        <i class="bi bi-broadcast"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-black text-white truncate"><?= htmlspecialchars($spin['song_title'] ?? 'Unknown Track') ?></div>
                        <div class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">
                            <?= $type === 'artist' ? htmlspecialchars($spin['station_name'] ?? 'Unknown Station') : htmlspecialchars($spin['artist_name'] ?? 'Unknown Artist') ?>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] font-black text-zinc-400"><?= date('H:i', strtotime($spin['played_at'])) ?></div>
                        <div class="text-[8px] font-black text-brand uppercase"><?= date('M j', strtotime($spin['played_at'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }
}