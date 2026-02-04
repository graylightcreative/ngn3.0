<?php
/**
 * Dashboard Bootstrap
 * Initializes session, auth, and common utilities for entity dashboards
 */

$root = dirname(__DIR__, 2);
require_once $root . '/lib/bootstrap.php';
require_once $root . '/lib/Services/DatabaseConnectionPool.php';

use NGN\Lib\Config;
use NGN\Lib\Auth\TokenService;
use NGN\Lib\Services\DatabaseConnectionPool;

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize connection pool (single-time initialization)
DatabaseConnectionPool::initialize();

/**
 * Get current authenticated user
 */
function dashboard_get_user(): ?array {
    return $_SESSION['User'] ?? null;
}

/**
 * Check if user is logged in
 */
function dashboard_is_logged_in(): bool {
    return !empty($_SESSION['User']['Id']);
}

/**
 * Get user's entity type based on RoleId
 * 3 = Artist, 7 = Label, 4/15 = Station, 5/17 = Venue
 */
function dashboard_get_entity_type(): ?string {
    $roleId = (int)($_SESSION['User']['RoleId'] ?? 0);
    return match($roleId) {
        3 => 'artist',
        7 => 'label',
        4, 15 => 'station',
        5, 17 => 'venue',
        default => null
    };
}

/**
 * Require authentication - redirect to login if not logged in
 */
function dashboard_require_auth(): void {
    if (!dashboard_is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Require specific entity type
 */
function dashboard_require_entity_type(string $type): void {
    $current = dashboard_get_entity_type();
    if ($current !== $type) {
        header('Location: /');
        exit;
    }
}

/**
 * Get entity data from ngn_2025 based on user's slug
 */
function dashboard_get_entity(string $type): ?array {
    $user = dashboard_get_user();
    if (!$user || empty($user['Slug'])) return null;
    
    $table = match($type) {
        'artist' => 'artists',
        'label' => 'labels',
        'venue' => 'venues',
        'station' => 'stations',
        default => null
    };
    if (!$table) return null;
    
    try {
        $pdo = dashboard_pdo();
        // Try different column name variations for compatibility
        $slubCol = match($type) {
            'station' => 'Slug',  // stations table uses Slug
            'artist' => 'slug',   // artists table likely uses slug
            'label' => 'slug',    // labels table likely uses slug
            'venue' => 'slug',    // venues table likely uses slug
            default => 'slug'
        };
        $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE `{$slubCol}` = ? LIMIT 1");
        $stmt->execute([$user['Slug']]);
        $entity = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entity) {
            return null;
        }

        // Normalize entity keys to lowercase for consistency
        // Handles both 'Id' (stations) and 'id' (other entities)
        if (isset($entity['Id']) && !isset($entity['id'])) {
            $entity['id'] = $entity['Id'];
        }
        if (isset($entity['Slug']) && !isset($entity['slug'])) {
            $entity['slug'] = $entity['Slug'];
        }
        if (isset($entity['Name']) && !isset($entity['name'])) {
            $entity['name'] = $entity['Name'];
        }

        return $entity;
    } catch (PDOException $e) {
        error_log('Dashboard entity fetch error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get PDO connection to ngn_2025 (primary database)
 * Uses centralized connection pool to prevent multiple connections
 */
function dashboard_pdo(): PDO {
    return DatabaseConnectionPool::primary();
}

/**
 * Get PDO connection to ngn_spins_2025 (spins database)
 * Uses centralized connection pool
 */
function dashboard_pdo_spins(): PDO {
    return DatabaseConnectionPool::spins();
}

/**
 * Get PDO connection to ngn_rankings_2025 (rankings database)
 * Uses centralized connection pool
 */
function dashboard_pdo_rankings(): PDO {
    return DatabaseConnectionPool::rankings();
}

/**
 * Execute cached query for analytics/expensive queries
 * Reduces redundant database calls
 *
 * @param string $key Unique cache key for query
 * @param callable $queryFn Function that executes and returns query result
 * @param int $ttl Time-to-live in seconds (default 300 = 5 minutes)
 * @return mixed Query result
 */
function dashboard_cached_query(string $key, callable $queryFn, int $ttl = 300): mixed {
    return DatabaseConnectionPool::cachedQuery($key, $queryFn, $ttl);
}

/**
 * Clear analytics cache (call after data mutations)
 */
function dashboard_clear_cache(string $key = null): void {
    DatabaseConnectionPool::clearCache($key);
}

/**
 * CSRF token generation
 */
function dashboard_csrf_token(): string {
    if (empty($_SESSION['dashboard_csrf'])) {
        $_SESSION['dashboard_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['dashboard_csrf'];
}

/**
 * Validate CSRF token
 */
function dashboard_validate_csrf(string $token): bool {
    return hash_equals($_SESSION['dashboard_csrf'] ?? '', $token);
}

/**
 * Get a Config instance for service initialization
 * Used by service classes that require Config for database/API setup
 */
function dashboard_get_config(): Config {
    return new Config();
}

/**
 * Generate a JWT for the current user for API calls
 */
function dashboard_get_jwt(): ?string {
    $user = dashboard_get_user();
    if (!$user) return null;

    try {
        $config = dashboard_get_config();
        $tokenSvc = new TokenService($config);

        $payload = [
            'sub' => $user['Id'],
            'role' => dashboard_get_entity_type(), // artist, label, station, etc
            'email' => $user['Email'],
        ];

        return $tokenSvc->encode($payload);
    } catch (\Throwable $e) {
        error_log('JWT generation failed for dashboard: ' . $e->getMessage());
        return null;
    }
}

/**
 * Generate unique slug with collision prevention
 * Appends microseconds + random string for guaranteed uniqueness
 *
 * @param string $title Title to convert to slug
 * @param string $entityType Entity type (artist, release, post, etc)
 * @param string $entityId Optional entity ID for additional uniqueness
 * @return string Unique slug
 */
function dashboard_generate_slug(string $title, string $entityType = '', string $entityId = ''): string {
    // Base slug from title
    $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($title)));
    $base = trim($base, '-'); // Remove leading/trailing hyphens

    if (empty($base)) {
        $base = 'item'; // Fallback for titles with no alphanumeric chars
    }

    // Add microsecond timestamp + 4-char random for collision prevention
    $microtime = str_replace('.', '', microtime(true));
    $random = substr(bin2hex(random_bytes(2)), 0, 4); // 4 random hex chars

    return "{$base}-{$microtime}-{$random}";
}

/**
 * Safe null-check access to array elements
 * Returns value or default without PHP warnings
 *
 * @param array $array Array to access
 * @param string|int $key Key to access
 * @param mixed $default Default value if key doesn't exist or is null
 * @return mixed Value or default
 */
function dashboard_safe_get(array $array, $key, $default = null) {
    return isset($array[$key]) && $array[$key] !== null ? $array[$key] : $default;
}

/**
 * HTML-escape and safely display entity data
 * Combines htmlspecialchars + null-check
 *
 * @param array $array Array to access
 * @param string $key Key to access
 * @param string $default Default display value
 * @return string Escaped value or default
 */
function dashboard_safe_display(array $array, string $key, string $default = '—'): string {
    $value = dashboard_safe_get($array, $key);
    return !empty($value) ? htmlspecialchars((string)$value) : $default;
}

/**
 * Require CSRF validation for POST requests
 * Use this before processing any form submission
 *
 * @param array $postData $_POST data
 * @return bool True if CSRF is valid
 */
function dashboard_require_csrf(array $postData): bool {
    return dashboard_validate_csrf($postData['csrf'] ?? '');
}

/**
 * Validate and sanitize URL input
 *
 * @param string $url URL to validate
 * @return string|null Valid URL or null
 */
function dashboard_validate_url(?string $url): ?string {
    if (empty($url)) return null;

    $url = trim($url);
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }

    // Only allow http/https
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return null;
    }

    return $url;
}

/**
 * Validate and sanitize integer input
 *
 * @param mixed $value Value to validate
 * @param int $min Minimum value
 * @param int $max Maximum value
 * @return int|null Valid integer or null
 */
function dashboard_validate_int($value, int $min = 0, int $max = PHP_INT_MAX): ?int {
    if (!is_numeric($value)) {
        return null;
    }

    $int = (int)$value;

    if ($int < $min || $int > $max) {
        return null;
    }

    return $int;
}

