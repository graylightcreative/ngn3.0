<?php
/**
 * Digital Safety Seal - Comprehensive Test Suite
 *
 * This script tests all aspects of the NGN 2.0.2 Digital Safety Seal system:
 * - Database connectivity
 * - Ledger table structure
 * - Service functionality
 * - API endpoints
 * - Certificate generation
 *
 * Run from command line: php tests/test-digital-safety-seal.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Color codes for terminal output
define('COLOR_GREEN', "\033[92m");
define('COLOR_RED', "\033[91m");
define('COLOR_YELLOW', "\033[93m");
define('COLOR_BLUE', "\033[94m");
define('COLOR_RESET', "\033[0m");
define('CHECK', "✓");
define('CROSS', "✗");
define('ARROW', "→");

// Track test results
$tests_run = 0;
$tests_passed = 0;
$tests_failed = 0;

function print_header($title) {
    echo "\n" . COLOR_BLUE . str_repeat("═", 80) . COLOR_RESET . "\n";
    echo COLOR_BLUE . "  " . $title . COLOR_RESET . "\n";
    echo COLOR_BLUE . str_repeat("═", 80) . COLOR_RESET . "\n\n";
}

function assert_test($name, $condition, $details = '') {
    global $tests_run, $tests_passed, $tests_failed;
    $tests_run++;

    if ($condition) {
        $tests_passed++;
        echo COLOR_GREEN . "  $CHECK PASS" . COLOR_RESET . ": $name\n";
        if ($details) echo "       $ARROW $details\n";
    } else {
        $tests_failed++;
        echo COLOR_RED . "  $CROSS FAIL" . COLOR_RESET . ": $name\n";
        if ($details) echo "       $ARROW $details\n";
    }
    echo "\n";
}

function print_summary() {
    global $tests_run, $tests_passed, $tests_failed;

    echo "\n" . COLOR_BLUE . str_repeat("═", 80) . COLOR_RESET . "\n";
    echo COLOR_BLUE . "  TEST SUMMARY" . COLOR_RESET . "\n";
    echo COLOR_BLUE . str_repeat("═", 80) . COLOR_RESET . "\n\n";

    echo "  Total Tests: $tests_run\n";
    echo COLOR_GREEN . "  Passed: $tests_passed" . COLOR_RESET . "\n";
    if ($tests_failed > 0) {
        echo COLOR_RED . "  Failed: $tests_failed" . COLOR_RESET . "\n";
    }

    if ($tests_failed === 0) {
        echo "\n" . COLOR_GREEN . "  🎉 ALL TESTS PASSED! 🎉" . COLOR_RESET . "\n\n";
    } else {
        echo "\n" . COLOR_RED . "  ⚠️  SOME TESTS FAILED" . COLOR_RED . "\n\n";
    }
}

// ============================================================================
// TESTS START HERE
// ============================================================================

echo "\n" . COLOR_BLUE . str_repeat("╔", 80) . COLOR_RESET . "\n";
echo COLOR_BLUE . "║" . str_repeat(" ", 78) . "║" . COLOR_RESET . "\n";
echo COLOR_BLUE . "║  NGN 2.0.2 DIGITAL SAFETY SEAL - COMPREHENSIVE TEST SUITE" . str_repeat(" ", 18) . "║" . COLOR_RESET . "\n";
echo COLOR_BLUE . "║" . str_repeat(" ", 78) . "║" . COLOR_RESET . "\n";
echo COLOR_BLUE . str_repeat("╚", 80) . COLOR_RESET . "\n\n";

// ============================================================================
// TEST 1: Environment & Bootstrap
// ============================================================================
print_header("TEST 1: Environment & Bootstrap");

$env_file = __DIR__ . '/../.env';
assert_test("Environment file exists", file_exists($env_file), $env_file);

require_once __DIR__ . '/../lib/bootstrap.php';
assert_test("Bootstrap loaded successfully", class_exists('NGN\\Lib\\Config'), "Config class available");

// ============================================================================
// TEST 2: Database Connectivity
// ============================================================================
print_header("TEST 2: Database Connectivity");

try {
    $config = new \NGN\Lib\Config();
    $pdo = \NGN\Lib\DB\ConnectionFactory::read($config);
    assert_test("Database connection established", $pdo instanceof PDO, "Read connection active");
} catch (Exception $e) {
    assert_test("Database connection established", false, $e->getMessage());
    exit(1);
}

// ============================================================================
// TEST 3: Database Schema
// ============================================================================
print_header("TEST 3: Database Schema Verification");

try {
    // Check for content_ledger table
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'content_ledger'");
    $stmt->execute();
    $table_exists = $stmt->rowCount() > 0;
    assert_test("content_ledger table exists", $table_exists, "Table found in database");

    if ($table_exists) {
        // Check columns in content_ledger
        $stmt = $pdo->prepare("DESCRIBE content_ledger");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        $required_columns = [
            'id', 'content_hash', 'metadata_hash', 'owner_id', 'upload_source',
            'certificate_id', 'verification_count', 'created_at'
        ];

        foreach ($required_columns as $col) {
            $has_col = in_array($col, $columns);
            assert_test("  Column: $col", $has_col, "Found in schema");
        }

        echo "  Total columns: " . count($columns) . "\n\n";
    }

    // Check for verification_log table
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'content_ledger_verification_log'");
    $stmt->execute();
    $log_table_exists = $stmt->rowCount() > 0;
    assert_test("content_ledger_verification_log table exists", $log_table_exists, "Audit table found");

} catch (Exception $e) {
    assert_test("Schema verification", false, $e->getMessage());
}

// ============================================================================
// TEST 4: Service Classes
// ============================================================================
print_header("TEST 4: Service Classes");

try {
    $write_pdo = \NGN\Lib\DB\ConnectionFactory::write($config);
    $logger = \NGN\Lib\Logging\LoggerFactory::create($config, 'test');

    $ledger_service = new \NGN\Lib\Legal\ContentLedgerService($write_pdo, $config, $logger);
    assert_test("ContentLedgerService instantiated", $ledger_service !== null, "Service ready");

    $cert_service = new \NGN\Lib\Legal\DigitalCertificateService('https://beta.nextgennoise.com');
    assert_test("DigitalCertificateService instantiated", $cert_service !== null, "Service ready");

} catch (Exception $e) {
    assert_test("Service instantiation", false, $e->getMessage());
}

// ============================================================================
// TEST 5: Content Hash Generation & Validation
// ============================================================================
print_header("TEST 5: Content Hash Generation & Validation");

// Generate test file and hash
$test_content = "Test audio file content for Digital Safety Seal " . time();
$test_file = sys_get_temp_dir() . '/ngn_test_' . uniqid() . '.mp3';
file_put_contents($test_file, $test_content);

$test_hash = hash_file('sha256', $test_file);
assert_test("SHA-256 hash calculated", strlen($test_hash) === 64, "Hash: " . substr($test_hash, 0, 16) . "...");

// Validate hash format
$hash_valid = preg_match('/^[a-f0-9]{64}$/i', $test_hash) === 1;
assert_test("Hash format validation", $hash_valid, "Valid hexadecimal format");

// ============================================================================
// TEST 6: Metadata Hash Generation
// ============================================================================
print_header("TEST 6: Metadata Hash Generation");

try {
    $metadata = [
        'title' => 'Test Track Title',
        'artist_name' => 'Test Artist Name',
        'credits' => ['Producer' => 'Test Producer'],
        'rights_split' => ['Artist' => 100]
    ];

    $metadata_hash = $ledger_service->generateMetadataHash($metadata);
    assert_test("Metadata hash generated", strlen($metadata_hash) === 64, "Hash: " . substr($metadata_hash, 0, 16) . "...");

    // Test consistency
    $metadata_hash2 = $ledger_service->generateMetadataHash($metadata);
    assert_test("Metadata hash consistency", $metadata_hash === $metadata_hash2, "Same metadata produces same hash");

} catch (Exception $e) {
    assert_test("Metadata hashing", false, $e->getMessage());
}

// ============================================================================
// TEST 7: Ledger Registration
// ============================================================================
print_header("TEST 7: Ledger Registration");

try {
    $ledger_result = $ledger_service->registerContent(
        ownerId: 1, // User ID 1 (should exist in system)
        contentHash: $test_hash,
        uploadSource: 'test_suite',
        metadata: $metadata,
        fileInfo: [
            'size_bytes' => filesize($test_file),
            'mime_type' => 'audio/mpeg',
            'filename' => basename($test_file)
        ]
    );

    assert_test("Content registered in ledger", isset($ledger_result['id']), "Ledger ID: " . ($ledger_result['id'] ?? 'N/A'));
    assert_test("Certificate ID generated", isset($ledger_result['certificate_id']), "Cert: " . ($ledger_result['certificate_id'] ?? 'N/A'));

    $cert_id = $ledger_result['certificate_id'] ?? null;
    $ledger_id = $ledger_result['id'] ?? null;

    // Verify certificate ID format
    if ($cert_id) {
        $cert_format_valid = preg_match('/^CRT-\d{8}-[A-F0-9]{8}$/i', $cert_id) === 1;
        assert_test("Certificate ID format", $cert_format_valid, "Format: CRT-YYYYMMDD-XXXXXXXX");
    }

} catch (Exception $e) {
    assert_test("Ledger registration", false, $e->getMessage());
    $cert_id = null;
    $ledger_id = null;
}

// ============================================================================
// TEST 8: Ledger Lookup
// ============================================================================
print_header("TEST 8: Ledger Lookup");

if ($ledger_id) {
    try {
        // Lookup by hash
        $lookup = $ledger_service->lookupByHash($test_hash);
        assert_test("Lookup by content hash", $lookup !== null, "Entry found in ledger");

        // Lookup by certificate ID
        if ($cert_id) {
            $cert_lookup = $ledger_service->lookupByCertificateId($cert_id);
            assert_test("Lookup by certificate ID", $cert_lookup !== null, "Entry found by certificate");
        }

    } catch (Exception $e) {
        assert_test("Ledger lookup", false, $e->getMessage());
    }
}

// ============================================================================
// TEST 9: Duplicate Detection
// ============================================================================
print_header("TEST 9: Duplicate Detection");

try {
    $is_duplicate = $ledger_service->isDuplicate($test_hash);
    assert_test("Duplicate detection working", $is_duplicate === true, "Hash correctly identified as registered");

} catch (Exception $e) {
    assert_test("Duplicate detection", false, $e->getMessage());
}

// ============================================================================
// TEST 10: Certificate HTML Generation
// ============================================================================
print_header("TEST 10: Certificate HTML Generation");

if ($ledger_id && $cert_id) {
    try {
        $ledger_record = $ledger_service->lookupByHash($test_hash);

        $owner_info = [
            'name' => 'Test Artist',
            'email' => 'test@example.com'
        ];

        $cert_html = $cert_service->generateCertificateHtml($ledger_record, $owner_info);

        $has_html = strpos($cert_html, '<!DOCTYPE html>') !== false;
        assert_test("HTML certificate generated", $has_html, "Valid HTML5 document");

        $has_qr = strpos($cert_html, 'data:image/png;base64,') !== false;
        assert_test("QR code embedded", $has_qr, "Base64 encoded PNG");

        $has_cert_id = strpos($cert_html, $cert_id) !== false;
        assert_test("Certificate ID in HTML", $has_cert_id, "Certificate ID displayed");

        // Save test certificate
        $cert_dir = __DIR__ . '/../storage/certificates';
        @mkdir($cert_dir, 0775, true);
        $cert_file = $cert_dir . '/' . $cert_id . '_test.html';
        file_put_contents($cert_file, $cert_html);

        assert_test("Certificate file saved", file_exists($cert_file), "File: $cert_file");
        echo "       $ARROW Open in browser: file://$cert_file\n\n";

    } catch (Exception $e) {
        assert_test("Certificate generation", false, $e->getMessage());
    }
}

// ============================================================================
// TEST 11: Verification Counter
// ============================================================================
print_header("TEST 11: Verification Counter Increment");

if ($ledger_id) {
    try {
        $ledger_service->incrementVerificationCount(
            $ledger_id,
            'test_suite',
            'match',
            [
                'request_ip' => '127.0.0.1',
                'request_user_agent' => 'Test Suite/1.0'
            ]
        );

        $updated = $ledger_service->lookupByHash($test_hash);
        $count = $updated['verification_count'] ?? 0;

        assert_test("Verification counter incremented", $count > 0, "Counter: $count");

        // Check verification log
        $log_stmt = $pdo->prepare("SELECT COUNT(*) FROM content_ledger_verification_log WHERE ledger_id = ?");
        $log_stmt->execute([$ledger_id]);
        $log_count = $log_stmt->fetchColumn();

        assert_test("Verification logged", $log_count > 0, "Log entries: $log_count");

    } catch (Exception $e) {
        assert_test("Verification tracking", false, $e->getMessage());
    }
}

// ============================================================================
// TEST 12: API Endpoint Test (Simulated)
// ============================================================================
print_header("TEST 12: API Response Format");

if ($cert_id) {
    try {
        // Simulate API response format
        $ledger_record = $ledger_service->lookupByCertificateId($cert_id);

        if ($ledger_record) {
            $api_response = [
                'verified' => true,
                'certificate_id' => $ledger_record['certificate_id'],
                'content_hash' => $ledger_record['content_hash'],
                'owner' => [
                    'user_id' => $ledger_record['owner_id'],
                    'name' => 'Test Artist'
                ],
                'content' => [
                    'title' => $ledger_record['title'],
                    'artist_name' => $ledger_record['artist_name'],
                    'file_size_bytes' => $ledger_record['file_size_bytes'],
                    'mime_type' => $ledger_record['mime_type']
                ],
                'registered_at' => $ledger_record['created_at'],
                'verification_count' => $ledger_record['verification_count']
            ];

            $json = json_encode($api_response);
            $decoded = json_decode($json, true);

            assert_test("API response structure valid", is_array($decoded), "Valid JSON structure");
            assert_test("Response contains certificate_id", isset($decoded['certificate_id']), "Field present");
            assert_test("Response contains content_hash", isset($decoded['content_hash']), "Field present");
            assert_test("Response contains owner info", isset($decoded['owner']), "Field present");
            assert_test("Response contains content info", isset($decoded['content']), "Field present");
        }

    } catch (Exception $e) {
        assert_test("API response format", false, $e->getMessage());
    }
}

// ============================================================================
// TEST 13: File System & Permissions
// ============================================================================
print_header("TEST 13: File System & Permissions");

$cert_dir = __DIR__ . '/../storage/certificates';
$dir_exists = is_dir($cert_dir);
assert_test("Certificate directory exists", $dir_exists, "Path: $cert_dir");

if ($dir_exists) {
    $is_writable = is_writable($cert_dir);
    assert_test("Certificate directory writable", $is_writable, "Web server can write files");

    $perms = substr(sprintf('%o', fileperms($cert_dir)), -4);
    echo "       $ARROW Directory permissions: $perms\n\n";
}

// ============================================================================
// TEST 14: Integration Points Check
// ============================================================================
print_header("TEST 14: Code Integration Points");

$integrations = [
    ['path' => '/lib/Stations/StationContentService.php', 'search' => 'ContentLedgerService'],
    ['path' => '/public/admin/smr-ingestion.php', 'search' => 'ContentLedgerService'],
    ['path' => '/public/admin/assistant-upload.php', 'search' => 'ContentLedgerService'],
    ['path' => '/lib/Smr/UploadService.php', 'search' => 'hash_file']
];

foreach ($integrations as $check) {
    $file = __DIR__ . '/..' . $check['path'];
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $has_integration = strpos($content, $check['search']) !== false;
        assert_test("Integration in " . basename($file), $has_integration, "Code: " . $check['search']);
    }
}

// ============================================================================
// TEST 15: Documentation Check
// ============================================================================
print_header("TEST 15: Documentation Files");

$docs = [
    '/docs/DIGITAL_SAFETY_SEAL_IMPLEMENTATION.md',
    '/DEPLOYMENT_NOTES.md',
    '/docs/bible/42 - Digital Safety Seal and Content Ledger.md',
    '/PROGRESS_TRACKING_OVERVIEW.md'
];

foreach ($docs as $doc) {
    $file = __DIR__ . '/..' . $doc;
    $exists = file_exists($file);
    assert_test("Documentation: " . basename($doc), $exists, $file);
}

// ============================================================================
// CLEANUP
// ============================================================================
print_header("Cleanup");

if (file_exists($test_file)) {
    unlink($test_file);
    echo COLOR_GREEN . "  $CHECK" . COLOR_RESET . " Temporary test file removed\n\n";
}

// ============================================================================
// FINAL SUMMARY
// ============================================================================
print_summary();

echo COLOR_YELLOW . "\n📋 NEXT STEPS:\n" . COLOR_RESET;
echo "  1. Test actual file uploads via the web interface\n";
echo "  2. Verify certificate generation in /storage/certificates/\n";
echo "  3. Test QR code scanning with a smartphone\n";
echo "  4. Monitor logs for any errors: storage/logs/content_ledger.log\n";
echo "  5. Check database: SELECT COUNT(*) FROM content_ledger;\n\n";

exit($tests_failed > 0 ? 1 : 0);
