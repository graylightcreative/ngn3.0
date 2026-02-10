<?php

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Env;

class Deployer
{
    private $config;
    private $pdo;
    private $errors = [];
    private $warnings = [];

    public function __construct()
    {
        $this->config = new Config();
        $this->pdo = ConnectionFactory::write($this->config);
    }

    public function run(): void
    {
        echo "\n╔════════════════════════════════════════════════════════════╗\n";
        echo "║         NGN 2.0 DEPLOYMENT & MIGRATION RUNNER             ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";

        // 1. Pre-flight checks
        echo "[1/6] Running pre-flight checks...\n";
        if (!$this->validateEnvironment()) {
            $this->fail("Environment validation failed");
        }
        echo "      ✓ Environment valid\n";

        if (!$this->checkDatabaseConnection()) {
            $this->fail("Database connection failed");
        }
        echo "      ✓ Database connection successful\n";

        // 2. Create necessary directories
        echo "\n[2/6] Creating necessary directories...\n";
        $this->ensureDirectories();
        echo "      ✓ Directories created\n";

        // 3. Run SQL migrations
        echo "\n[3/6] Running SQL migrations...\n";
        $this->runSqlMigrations();
        echo "      ✓ SQL migrations complete\n";

        // 4. Run PHP migrations
        echo "\n[4/6] Running PHP migrations...\n";
        $this->runPhpMigrations();
        echo "      ✓ PHP migrations complete\n";

        // 5. Post-migration setup
        echo "\n[5/6] Running post-migration setup...\n";
        $this->ensureAdminUser();
        echo "      ✓ Admin user ensured\n";

        $this->setFilePermissions();
        echo "      ✓ File permissions set\n";

        // 6. Clear caches & health check
        echo "\n[6/6] Clearing caches and verifying health...\n";
        try {
            $this->clearCaches();
            echo "      ✓ Caches cleared\n";
        } catch (\Throwable $e) {
            $this->warnings[] = "Cache clearing failed: " . $e->getMessage();
            echo "      ⚠ Cache clearing skipped (non-critical)\n";
        }

        $this->healthCheck();
        echo "      ✓ Health check passed\n";

        // Summary
        echo "\n╔════════════════════════════════════════════════════════════╗\n";
        echo "║                   ✓ DEPLOYMENT COMPLETE                    ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";

        if (!empty($this->warnings)) {
            echo "⚠ Warnings:\n";
            foreach ($this->warnings as $warning) {
                echo "  - {$warning}\n";
            }
            echo "\n";
        }
    }

    private function validateEnvironment(): bool
    {
        $required = [
            'APP_ENV',
            'DB_HOST',
            'DB_PORT',
            'DB_NAME',
            'DB_USER',
            'DB_PASS',
        ];

        foreach ($required as $var) {
            $value = Env::get($var);
            if (empty($value)) {
                $this->errors[] = "Missing required environment variable: {$var}";
                return false;
            }
        }

        return true;
    }

    private function checkDatabaseConnection(): bool
    {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (\Exception $e) {
            $this->errors[] = "Database connection failed: " . $e->getMessage();
            return false;
        }
    }

    private function ensureDirectories(): void
    {
        $dirs = [
            __DIR__ . '/../storage/logs',
            __DIR__ . '/../storage/cache',
            __DIR__ . '/../storage/uploads',
            __DIR__ . '/../storage/tmp',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
    }

    private function runSqlMigrations(): void
    {
        require_once __DIR__ . '/run-migrations.php';
    }

    private function runPhpMigrations(): void
    {
        require_once __DIR__ . '/run-php-migrations.php';
    }

    private function ensureAdminUser(): void
    {
        try {
            // Check if any admin users exist
            $stmt = $this->pdo->query(
                "SELECT COUNT(*) as cnt FROM ngn_2025.users WHERE role_id IN (SELECT id FROM roles WHERE name = 'admin')"
            );
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result['cnt'] == 0) {
                echo "\n      ! No admin user found. Creating default admin...\n";

                // Create default admin user if ENV variables are set
                $adminEmail = Env::get('ADMIN_EMAIL');
                $adminPassword = Env::get('ADMIN_PASSWORD');

                if (!empty($adminEmail) && !empty($adminPassword)) {
                    $hashedPassword = password_hash($adminPassword, PASSWORD_BCRYPT);

                    $stmt = $this->pdo->prepare(
                        "INSERT INTO ngn_2025.users (email, password, role_id, created_at)
                         SELECT ?, ?, id, NOW() FROM roles WHERE name = 'admin' LIMIT 1"
                    );
                    $stmt->execute([$adminEmail, $hashedPassword]);

                    echo "      ✓ Default admin created ({$adminEmail})\n";
                } else {
                    $this->warnings[] = "No admin user exists and ADMIN_EMAIL/ADMIN_PASSWORD not set. Create manually.";
                }
            }
        } catch (\Exception $e) {
            $this->warnings[] = "Could not ensure admin user: " . $e->getMessage();
        }
    }

    private function setFilePermissions(): void
    {
        $dirs = [
            __DIR__ . '/../storage/logs' => 0755,
            __DIR__ . '/../storage/cache' => 0755,
            __DIR__ . '/../storage/uploads' => 0755,
        ];

        foreach ($dirs as $dir => $perms) {
            if (is_dir($dir)) {
                @chmod($dir, $perms);
                // Recursively chmod files inside
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                foreach ($iterator as $file) {
                    @chmod($file, $perms);
                }
            }
        }
    }

    private function clearCaches(): void
    {
        // Clear OPcache if available
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        // Clear file cache directory if it exists
        $cacheDir = __DIR__ . '/../storage/cache';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*');
            if ($files !== false) {
                array_map('unlink', $files);
            }
        }

        // Try to clear Redis cache if available and extension is loaded
        if (extension_loaded('redis')) {
            try {
                $redis = new \Redis();
                if (@$redis->connect('127.0.0.1', 6379)) {
                    $redis->flushDb();
                    $redis->close();
                }
            } catch (\Throwable $e) {
                // Redis not available, skip silently
            }
        }
    }

    private function healthCheck(): void
    {
        // Verify core tables exist
        $requiredTables = [
            'ngn_2025.users',
            'ngn_2025.roles',
        ];

        foreach ($requiredTables as $table) {
            try {
                $this->pdo->query("SELECT 1 FROM {$table} LIMIT 1");
            } catch (\Exception $e) {
                throw new \Exception("Critical table missing: {$table}");
            }
        }

        // Verify Composer autoloader
        if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
            throw new \Exception("Composer autoloader not found. Run 'composer install'");
        }

        // Verify .env is readable
        if (!file_exists(__DIR__ . '/../.env')) {
            throw new \Exception(".env file not found");
        }
    }

    private function fail(string $message): void
    {
        echo "\n✗ {$message}\n";
        foreach ($this->errors as $error) {
            echo "  - {$error}\n";
        }
        exit(1);
    }
}

try {
    (new Deployer())->run();
} catch (\Exception $e) {
    echo "\n✗ Deployment failed: " . $e->getMessage() . "\n";
    exit(1);
}
