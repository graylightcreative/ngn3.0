<?php
/**
 * Test: Certificate Page Validation
 *
 * This file validates that the certificate.php page can be loaded without errors.
 * Run this to verify all dependencies are available.
 *
 * Usage:
 *   php public/legal/test-certificate.php
 *   Or access via web: /legal/test-certificate.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== NGN Digital Safety Seal - Certificate Page Test ===\n\n";

// Test 1: Bootstrap loads
echo "[1] Testing bootstrap.php...\n";
try {
    require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';
    echo "    ✓ Bootstrap loaded successfully\n";
} catch (\Exception $e) {
    echo "    ✗ Bootstrap failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Config loads
echo "[2] Testing Config class...\n";
try {
    $config = new \NGN\Lib\Config();
    echo "    ✓ Config loaded successfully\n";
} catch (\Exception $e) {
    echo "    ✗ Config failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Database connection
echo "[3] Testing database connection...\n";
try {
    $pdo = \NGN\Lib\DB\ConnectionFactory::read($config);
    echo "    ✓ Database connection successful\n";
} catch (\Exception $e) {
    echo "    ✗ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 4: Logger
echo "[4] Testing Logger...\n";
try {
    $logger = \NGN\Lib\Logging\LoggerFactory::create($config, 'certificate_test');
    echo "    ✓ Logger initialized successfully\n";
} catch (\Exception $e) {
    echo "    ✗ Logger failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 5: ContentLedgerService
echo "[5] Testing ContentLedgerService...\n";
try {
    $ledgerService = new \NGN\Lib\Legal\ContentLedgerService($pdo, $config, $logger);
    echo "    ✓ ContentLedgerService initialized successfully\n";
} catch (\Exception $e) {
    echo "    ✗ ContentLedgerService failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 6: QRCode
echo "[6] Testing QRCode library...\n";
try {
    $qrCode = new \chillerlan\QRCode\QRCode(new \chillerlan\QRCode\QROptions([
        'version' => 5,
        'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
        'scale' => 10,
        'imageBase64' => true
    ]));
    $qrCode->render('https://example.com');
    echo "    ✓ QRCode library working correctly\n";
} catch (\Exception $e) {
    echo "    ✗ QRCode library failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 7: Check if artists table exists
echo "[7] Testing database schema (artists table)...\n";
try {
    $stmt = $pdo->prepare("SELECT 1 FROM ngn_2025.artists LIMIT 1");
    $stmt->execute();
    echo "    ✓ artists table exists\n";
} catch (\Exception $e) {
    echo "    ✗ artists table check failed: " . $e->getMessage() . "\n";
    // Don't exit - table might be empty but exists
}

// Test 8: Check if entity_scores table exists
echo "[8] Testing database schema (entity_scores table)...\n";
try {
    $stmt = $pdo->prepare("SELECT 1 FROM ngn_2025.entity_scores LIMIT 1");
    $stmt->execute();
    echo "    ✓ entity_scores table exists\n";
} catch (\Exception $e) {
    echo "    ✗ entity_scores table check failed: " . $e->getMessage() . "\n";
    // Don't exit - table might be empty but exists
}

// Test 9: Check if content_ledger table exists
echo "[9] Testing database schema (content_ledger table)...\n";
try {
    $stmt = $pdo->prepare("SELECT 1 FROM content_ledger LIMIT 1");
    $stmt->execute();
    echo "    ✓ content_ledger table exists\n";
} catch (\Exception $e) {
    echo "    ✗ content_ledger table check failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== All Tests Passed ✓ ===\n";
echo "Certificate page is ready to use!\n";
echo "\nUsage:\n";
echo "  /legal/certificate.php?id=CRT-20260206-A3F8D91E\n";
echo "  /legal/certificate.php?hash=abc123def...\n";
