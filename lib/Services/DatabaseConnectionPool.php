<?php
/**
 * Database Connection Pool Service
 * Centralized connection management to eliminate N+1 connection overhead
 *
 * Features:
 * - Single instance per connection type (reuse connections)
 * - Automatic reconnect on connection lost
 * - Consistent credential handling
 * - Query result caching support
 */

namespace NGN\Lib\Services;

use PDO;
use PDOException;
use NGN\Lib\Env;

class DatabaseConnectionPool {
    /**
     * Connection instances (static cache)
     */
    private static array $connections = [];

    /**
     * Connection parameters
     */
    private static array $config = [];

    /**
     * Query cache for analytics/heavy queries
     */
    private static array $queryCache = [];

    /**
     * Initialize connection pool with environment config
     */
    public static function initialize(array $envConfig = []): void {
        self::$config = $envConfig ?: [
            'primary' => [
                'host' => Env::get('DB_HOST', 'localhost'),
                'port' => Env::get('DB_PORT', '3306'),
                'user' => Env::get('DB_USER', 'root'),
                'pass' => Env::get('DB_PASS', ''),
                'database' => Env::get('DB_NAME', 'ngn_2025')
            ],
            'spins' => [
                'host' => Env::get('DB_HOST', 'localhost'),
                'port' => Env::get('DB_PORT', '3306'),
                'user' => Env::get('DB_USER', 'root'),
                'pass' => Env::get('DB_PASS', ''),
                'database' => 'ngn_spins_2025'
            ],
            'rankings' => [
                'host' => Env::get('DB_HOST', 'localhost'),
                'port' => Env::get('DB_PORT', '3306'),
                'user' => Env::get('DB_USER', 'root'),
                'pass' => Env::get('DB_PASS', ''),
                'database' => 'ngn_rankings_2025'
            ]
        ];
    }

    /**
     * Get connection to primary database (ngn_2025)
     */
    public static function primary(): PDO {
        return self::getConnection('primary');
    }

    /**
     * Get connection to spins database
     */
    public static function spins(): PDO {
        return self::getConnection('spins');
    }

    /**
     * Get connection to rankings database
     */
    public static function rankings(): PDO {
        return self::getConnection('rankings');
    }

    /**
     * Get connection by name with pooling
     *
     * @throws PDOException If connection fails
     */
    private static function getConnection(string $name): PDO {
        // Return cached connection if healthy
        if (isset(self::$connections[$name])) {
            try {
                // Test connection is still alive
                self::$connections[$name]->query('SELECT 1');
                return self::$connections[$name];
            } catch (PDOException $e) {
                // Connection dead, will reconnect below
                unset(self::$connections[$name]);
            }
        }

        // Ensure config is initialized
        if (empty(self::$config)) {
            self::initialize();
        }

        if (!isset(self::$config[$name])) {
            throw new PDOException("Unknown connection pool: $name");
        }

        $cfg = self::$config[$name];

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $cfg['host'],
                $cfg['port'],
                $cfg['database']
            );

            $pdo = new PDO(
                $dsn,
                $cfg['user'],
                $cfg['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 5,
                    // Enable connection attribute to prevent "server has gone away" errors
                    PDO::ATTR_PERSISTENT => false
                ]
            );

            // Cache the connection
            self::$connections[$name] = $pdo;

            return $pdo;

        } catch (PDOException $e) {
            error_log("Failed to connect to database pool '$name': " . $e->getMessage());
            throw new PDOException(
                "Database connection failed for pool '$name': " . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Cache query result for analytics/expensive queries
     * Cache invalidates after TTL or on explicit clear
     *
     * @param string $key Unique cache key
     * @param callable $queryFn Function that returns query result
     * @param int $ttl Time-to-live in seconds (default 300 = 5 minutes)
     * @return mixed Query result
     */
    public static function cachedQuery(string $key, callable $queryFn, int $ttl = 300): mixed {
        // Check cache
        if (isset(self::$queryCache[$key])) {
            $cached = self::$queryCache[$key];
            if ($cached['expires_at'] > time()) {
                return $cached['data'];
            }
            // Cache expired
            unset(self::$queryCache[$key]);
        }

        // Execute query and cache result
        try {
            $result = $queryFn();
            self::$queryCache[$key] = [
                'data' => $result,
                'expires_at' => time() + $ttl
            ];
            return $result;
        } catch (PDOException $e) {
            error_log("Cache query failed for key '$key': " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Clear specific cache entry
     */
    public static function clearCache(string $key = null): void {
        if ($key === null) {
            self::$queryCache = [];
        } else {
            unset(self::$queryCache[$key]);
        }
    }

    /**
     * Clear all connections and caches (for testing/reset)
     */
    public static function reset(): void {
        self::$connections = [];
        self::$queryCache = [];
    }

    /**
     * Get connection statistics for monitoring
     */
    public static function getStats(): array {
        return [
            'active_connections' => count(self::$connections),
            'cached_queries' => count(self::$queryCache),
            'pools' => array_keys(self::$config)
        ];
    }
}
