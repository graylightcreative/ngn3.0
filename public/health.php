<?php
/**
 * Health Check Endpoint
 * Returns JSON with system status for monitoring and deployment verification
 * Endpoint: /health or /health.php (no authentication required)
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

$health = [
    'status' => 'ok',
    'timestamp' => date('c'),
    'version' => getenv('NGN_VERSION') ?: 'unknown',
    'environment' => getenv('APP_ENV') ?: 'unknown',
    'checks' => []
];

// Check 1: Database connectivity
try {
    require_once __DIR__ . '/../lib/bootstrap.php';
    $config = new \NGN\Lib\Config();
    $pdo = \NGN\Lib\DB\ConnectionFactory::read($config);
    $stmt = $pdo->query('SELECT 1');
    $health['checks']['database'] = [
        'status' => 'ok',
        'message' => 'Database connection successful'
    ];
} catch (\Throwable $e) {
    $health['status'] = 'error';
    $health['checks']['database'] = [
        'status' => 'failed',
        'message' => 'Database connection failed',
        'error' => $e->getMessage()
    ];
}

// Check 2: Storage writable
$testFile = __DIR__ . '/../storage/cache/.health_check_' . microtime(true);
if (@file_put_contents($testFile, 'health-check')) {
    @unlink($testFile);
    $health['checks']['storage'] = [
        'status' => 'ok',
        'message' => 'Storage is writable'
    ];
} else {
    $health['status'] = 'error';
    $health['checks']['storage'] = [
        'status' => 'failed',
        'message' => 'Storage directory not writable'
    ];
}

// Check 3: Required tables
try {
    $requiredTables = ['artists', 'users', 'entity_scores'];
    $missingTables = [];

    foreach ($requiredTables as $table) {
        try {
            $stmt = $pdo->query('SELECT 1 FROM `' . $table . '` LIMIT 1');
        } catch (\Throwable $e) {
            $missingTables[] = $table;
        }
    }

    if (empty($missingTables)) {
        $health['checks']['tables'] = [
            'status' => 'ok',
            'message' => 'All required tables exist'
        ];
    } else {
        $health['status'] = 'error';
        $health['checks']['tables'] = [
            'status' => 'partial',
            'message' => 'Some required tables missing',
            'missing' => $missingTables
        ];
    }
} catch (\Throwable $e) {
    $health['checks']['tables'] = [
        'status' => 'unknown',
        'message' => 'Could not verify tables'
    ];
}

// Check 4: PHP version
$phpVersion = phpversion();
$health['checks']['php_version'] = [
    'status' => 'ok',
    'version' => $phpVersion,
    'message' => 'PHP ' . $phpVersion
];

// HTTP response code based on overall status
http_response_code($health['status'] === 'ok' ? 200 : 503);

echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
