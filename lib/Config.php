<?php
namespace NGN\Lib;

class Config
{
    private array $errors = [];
    private ?Services\FeatureFlagService $featureFlags = null;

    public function __construct(?Services\FeatureFlagService $featureFlags = null)
    {
        // Optional: inject feature flag service for database-backed flags
        // Falls back to env variables if not provided or if database unavailable
        if ($featureFlags) {
            $this->featureFlags = $featureFlags;
        }
    }

    /**
     * Get feature flag value with fallback to env
     * Prioritizes database flags, falls back to env variables
     */
    private function getFeatureFlag(string $flagName, mixed $envDefault = null): mixed
    {
        if ($this->featureFlags) {
            try {
                return $this->featureFlags->get($flagName, $envDefault);
            } catch (\Throwable $e) {
                // Silently fall back to env if service unavailable
            }
        }
        return $envDefault;
    }

    public function appEnv(): string { return Env::get('APP_ENV', 'production') ?? 'production'; }
    public function appDebug(): bool { return Env::bool('APP_DEBUG', false); }
    public function appVersion(): string { return Env::get('APP_VERSION', '0.0.0') ?? '0.0.0'; }
    public function baseUrl(): string { 
        $base = Env::get('BASEURL', Env::get('APP_URL'));
        if ($base) return rtrim($base, '/');
        
        // Fallback to current request host if available
        if (isset($_SERVER['HTTP_HOST'])) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            return $protocol . '://' . $_SERVER['HTTP_HOST'];
        }
        
        return ''; 
    }

    public function corsAllowedOrigins(): string { return Env::get('CORS_ALLOWED_ORIGINS', '*') ?? '*'; }
    public function corsAllowedMethods(): string { return Env::get('CORS_ALLOWED_METHODS', 'GET,POST,PUT,PATCH,DELETE,OPTIONS') ?? 'GET,POST,PUT,PATCH,DELETE,OPTIONS'; }
    public function corsAllowedHeaders(): string {
        $headers = Env::get('CORS_ALLOWED_HEADERS', 'Content-Type,Authorization,X-Requested-With') ?? 'Content-Type,Authorization,X-Requested-With';
        if (strpos($headers, 'Authorization') === false) {
            $headers .= ',Authorization';
        }
        return $headers;
    }
    public function adminAllowedOrigins(): ?string { return Env::get('ADMIN_ALLOWED_ORIGINS', null); }

    public function logPath(): string { 
        $path = Env::get('LOG_PATH');
        if ($path) {
            // If path is relative, make it absolute relative to project root
            if ($path[0] !== DIRECTORY_SEPARATOR && !preg_match('/^[a-zA-Z]:\\\\/', $path)) {
                return dirname(__DIR__) . '/' . $path;
            }
            return $path;
        }
        return dirname(__DIR__) . '/storage/logs';
    }
    public function logLevel(): string { return Env::get('LOG_LEVEL', 'info') ?? 'info'; }

	    // Feature flags
	    public function featureStationPortal(): bool { return Env::bool('FEATURE_STATION_PORTAL', $this->appEnv() === 'development'); }
	    public function featurePostSpins(): bool { return Env::bool('FEATURE_POST_SPINS', $this->appEnv() === 'development'); }
	    public function featureSmrUploads(): bool { return Env::bool('FEATURE_SMR_UPLOADS', $this->appEnv() === 'development'); }
	    public function featureSmrTraining(): bool { return Env::bool('FEATURE_SMR_TRAINING', false); }
	    public function featureRankings(): bool { return Env::bool('FEATURE_RANKINGS', $this->appEnv() === 'development'); }
	    public function featureRankingsCache(): bool { return Env::bool('FEATURE_RANKINGS_CACHE', $this->appEnv() === 'development'); }
	    public function featureRateLimiting(): bool { return Env::bool('FEATURE_RATE_LIMITING', $this->appEnv() === 'development'); }
	    public function featureAuthDevLedger(): bool { return Env::bool('FEATURE_AUTH_DEV_LEDGER', $this->appEnv() === 'development'); }
	    public function featureAdmin(): bool { return Env::bool('FEATURE_ADMIN', $this->appEnv() === 'development'); }
	    public function featureRoyalties(): bool { return Env::bool('FEATURE_ROYALTIES', $this->appEnv() === 'development'); }
	    public function featurePublicRollout(): bool { return Env::bool('FEATURE_PUBLIC_ROLLOUT', false); }
        public function featureAiEnabled(): bool { return false; } // AI services disabled until product is profitable

	    // Sparks / monetization
	    public function sparksMode(): string {
	        $env = strtolower($this->appEnv());
	        $m = strtolower(Env::get('SPARKS_MODE', $env) ?? $env);
	        if (in_array($m, ['development','dev'], true)) return 'development';
	        if (in_array($m, ['staging','stage'], true)) return 'staging';
	        return 'production';
	    }

	    public function sparksEnforceCharges(): bool {
	        // Default: enforce in production, relaxed in non-prod
	        $default = $this->appEnv() === 'production';
	        return Env::bool('SPARKS_ENFORCE_CHARGES', $default);
	    }

    // Maintenance mode (for NGN 1.0 landing) — supports database-backed feature flags
    public function maintenanceMode(): bool {
        $dbValue = $this->getFeatureFlag('MAINTENANCE_MODE', null);
        if ($dbValue !== null) {
            return (bool)$dbValue;
        }
        return Env::bool('MAINTENANCE_MODE', false);
    }

    // Public view mode (1.0 legacy vs 2.0 next) — supports database-backed feature flags
    public function publicViewMode(): string {
        $dbValue = $this->getFeatureFlag('FEATURE_PUBLIC_VIEW_MODE', null);
        if ($dbValue !== null) {
            $m = strtolower((string)$dbValue);
            return in_array($m, ['legacy','next'], true) ? $m : 'legacy';
        }
        $m = strtolower(Env::get('FEATURE_PUBLIC_VIEW_MODE', 'legacy') ?? 'legacy');
        return in_array($m, ['legacy','next'], true) ? $m : 'legacy';
    }

    // Rollout percentage (0-100) for gradual traffic migration — supports database-backed feature flags
    public function rolloutPercentage(): int {
        $dbValue = $this->getFeatureFlag('ROLLOUT_PERCENTAGE', null);
        if ($dbValue !== null) {
            $v = (int)$dbValue;
            return max(0, min(100, $v));
        }
        $v = (int)(Env::get('ROLLOUT_PERCENTAGE', '0') ?? '0');
        return max(0, min(100, $v));
    }

    // Check if current request should see 2.0 based on rollout percentage
    public function shouldShowNext(): bool {
        if (!$this->featurePublicRollout()) return false;
        $pct = $this->rolloutPercentage();
        if ($pct >= 100) return true;
        if ($pct <= 0) return false;
        // Use session-based sticky assignment
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        if (!isset($_SESSION['_ngn_rollout_bucket'])) {
            $_SESSION['_ngn_rollout_bucket'] = mt_rand(1, 100);
        }
        return $_SESSION['_ngn_rollout_bucket'] <= $pct;
    }

    // Uploads
    public function uploadMaxBytes(): int { return (int)((Env::get('UPLOAD_MAX_MB', '50') ?? '50') * 1024 * 1024); }
    public function uploadAllowedMime(): array { $v = Env::get('UPLOAD_ALLOWED_MIME', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv') ?? ''; return array_values(array_filter(array_map('trim', explode(',', $v)))); }
    public function uploadDir(): string { return Env::get('UPLOAD_DIR', __DIR__.'/../storage/uploads') ?? __DIR__.'/../storage/uploads'; }
    public function uploadRetentionDays(): int { return (int)(Env::get('UPLOAD_RETENTION_DAYS', '30') ?? '30'); }

    // Preview/ingestion limits
    public function previewMaxRows(): int { return (int)(Env::get('PREVIEW_MAX_ROWS', '5') ?? '5'); }
    public function previewTimeoutMs(): int { return (int)(Env::get('PREVIEW_TIMEOUT_MS', '500') ?? '500'); }

    // Rankings cache
    public function rankingsCacheTtlSeconds(): int { return (int)(Env::get('RANKINGS_CACHE_TTL_SECONDS', '300') ?? '300'); }
    public function rankingsCacheDir(): string { return Env::get('RANKINGS_CACHE_DIR', __DIR__.'/../storage/cache/rankings') ?? __DIR__.'/../storage/cache/rankings'; }

    // API guards
    public function maxJsonBodyBytes(): int { return (int)(Env::get('MAX_JSON_BODY_BYTES', '1048576') ?? '1048576'); }

    // Dev users ledger
    public function usersLedgerPath(): string { return Env::get('USERS_LEDGER_PATH', __DIR__.'/../storage/users/ledger.json') ?? __DIR__.'/../storage/users/ledger.json'; }

    public function db(): array
    {
        $dbEnv = strtolower(Env::get('DB_ENVIRONMENT', 'local') ?? 'local');
        switch ($dbEnv) {
            case 'external': return $this->dbExternal();
            case 'internal': return $this->dbInternal();
            case 'local':
            default: return $this->dbLocal();
        }
    }

    public function dbLocal(): array
    {
        return [
            'host' => Env::get('DB_LOCAL_HOST', Env::get('DB_HOST', '127.0.0.1')),
            'port' => (int)(Env::get('DB_LOCAL_PORT', Env::get('DB_PORT', '3306')) ?? '3306'),
            'name' => Env::get('DB_LOCAL_NAME', Env::get('DB_NAME', 'ngn_2025')),
            'user' => Env::get('DB_LOCAL_USER', Env::get('DB_USER', 'root')),
            'pass' => Env::get('DB_LOCAL_PASS', Env::get('DB_PASS', 'root')),
        ];
    }

    public function dbInternal(): array
    {
        return [
            'host' => Env::get('DB_INTERNAL_HOST', 'localhost'),
            'port' => (int)(Env::get('DB_INTERNAL_PORT', '3306') ?? '3306'),
            'name' => Env::get('DB_INTERNAL_NAME', 'ngn_2025'),
            'user' => Env::get('DB_INTERNAL_USER', 'ngn_2025'),
            'pass' => Env::get('DB_INTERNAL_PASS', 'NextGenNoise!1'),
        ];
    }

    public function dbExternal(): array
    {
        return [
            'host' => Env::get('DB_EXTERNAL_HOST', 'server.starrship1.com'),
            'port' => (int)(Env::get('DB_EXTERNAL_PORT', '3306') ?? '3306'),
            'name' => Env::get('DB_EXTERNAL_NAME', 'ngn_2025'),
            'user' => Env::get('DB_EXTERNAL_USER', 'ngn_2025'),
            'pass' => Env::get('DB_EXTERNAL_PASS', 'NextGenNoise!1'),
        ];
    }

    public function dbRead(): array
    {
        return [
            'host' => Env::get('DB_READ_HOST', $this->dbExternal()['host']), // Fallback to external if not set
            'port' => (int)(Env::get('DB_READ_PORT', $this->dbExternal()['port']) ?? $this->dbExternal()['port']),
            'name' => Env::get('DB_READ_NAME', $this->dbExternal()['name']) ?? $this->dbExternal()['name'],
            'user' => Env::get('DB_READ_USER', $this->dbExternal()['user']) ?? $this->dbExternal()['user'],
            'pass' => Env::get('DB_READ_PASS', $this->dbExternal()['pass']) ?? $this->dbExternal()['pass'],
        ];
    }

    public function jwt(): array
    {
        return [
            'secret' => Env::get('JWT_SECRET', ''),
            'iss' => Env::get('JWT_ISS', 'ngn'),
            'aud' => Env::get('JWT_AUD', 'ngn-clients'),
            'ttl' => (int)(Env::get('JWT_TTL_SECONDS', '900') ?? '900'),
            'refresh_ttl' => (int)(Env::get('JWT_REFRESH_TTL_SECONDS', '1209600') ?? '1209600'),
        ];
    }

    public function validateCritical(): bool
    {
        $this->errors = [];
        $db = $this->db();
        foreach (['name','user'] as $k) {
            if (empty($db[$k])) $this->errors[] = "DB_".strtoupper($k)." is required";
        }
        $jwt = $this->jwt();
        if (empty($jwt['secret']) || $jwt['secret'] === 'change-me') $this->errors[] = 'JWT_SECRET must be set to a secure value';
        return empty($this->errors);
    }

    public function errors(): array { return $this->errors; }

    // Legacy auth feature flag (use legacy users table)
    public function featureAuthLegacyUsers(): bool { return Env::bool('FEATURE_AUTH_LEGACY_USERS', false); }

    // Legacy users table/column mapping
    public function legacyUsersTable(): string { return Env::get('LEGACY_USERS_TABLE', 'users') ?? 'users'; }
    public function legacyEmailColumn(): string { return Env::get('LEGACY_EMAIL_COLUMN', 'Email') ?? 'Email'; }
    public function legacyPasswordColumn(): string { return Env::get('LEGACY_PASSWORD_COLUMN', 'PasswordHash') ?? 'PasswordHash'; }
    public function legacyRoleIdColumn(): string { return Env::get('LEGACY_ROLE_ID_COLUMN', 'RoleId') ?? 'RoleId'; }

    // Admin role ids (CSV, defaults to '1')
    public function legacyAdminRoleIds(): array {
        $v = Env::get('LEGACY_ADMIN_ROLE_IDS', '1') ?? '1';
        $parts = array_map('trim', explode(',', $v));
        $parts = array_values(array_filter($parts, fn($x) => $x !== ''));
        return $parts;
    }

    // Global API auth requirement (defaults to true — lock down API)
    public function requireAuthForApi(): bool { return Env::bool('REQUIRE_AUTH_FOR_API', true); }
}
