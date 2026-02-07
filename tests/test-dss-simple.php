<?php
/**
 * Digital Safety Seal - Simple Integration Test
 *
 * Run from command line: php tests/test-dss-simple.php
 */

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  NGN 2.0.2 Digital Safety Seal - Simple Test Suite           ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$passed = 0;
$failed = 0;

function test($name, $condition, $detail = '') {
    global $passed, $failed;
    if ($condition) {
        echo "  ✓ PASS: $name";
        $passed++;
    } else {
        echo "  ✗ FAIL: $name";
        $failed++;
    }
    if ($detail) echo " - $detail";
    echo "\n";
}

// ============================================================================
// TEST 1: Bootstrap & Database
// ============================================================================
echo "\n┌─ TEST 1: Bootstrap & Database ─────────────────────────────────┐\n";

require_once __DIR__ . '/../lib/bootstrap.php';
test("Bootstrap loads", class_exists('NGN\\Lib\\Config'));

try {
    $config = new \NGN\Lib\Config();
    $pdo = \NGN\Lib\DB\ConnectionFactory::read($config);
    test("Database connects", $pdo instanceof PDO);

    // Check tables exist
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'content_ledger'");
    $stmt->execute();
    test("content_ledger table exists", $stmt->rowCount() > 0);

    $stmt = $pdo->prepare("SHOW TABLES LIKE 'content_ledger_verification_log'");
    $stmt->execute();
    test("verification_log table exists", $stmt->rowCount() > 0);

} catch (Exception $e) {
    test("Database operations", false, $e->getMessage());
}

// ============================================================================
// TEST 2: Service Classes
// ============================================================================
echo "\n┌─ TEST 2: Service Classes ──────────────────────────────────────┐\n";

try {
    $write_pdo = \NGN\Lib\DB\ConnectionFactory::write($config);
    $logger = \NGN\Lib\Logging\LoggerFactory::create($config, 'test');

    $ledger = new \NGN\Lib\Legal\ContentLedgerService($write_pdo, $config, $logger);
    test("ContentLedgerService instantiates", $ledger instanceof \NGN\Lib\Legal\ContentLedgerService);

    $cert = new \NGN\Lib\Legal\DigitalCertificateService('https://test.example.com');
    test("DigitalCertificateService instantiates", $cert instanceof \NGN\Lib\Legal\DigitalCertificateService);

} catch (Exception $e) {
    test("Service instantiation", false, $e->getMessage());
}

// ============================================================================
// TEST 3: Hash Functions
// ============================================================================
echo "\n┌─ TEST 3: Hash Functions ───────────────────────────────────────┐\n";

$test_content = "Test file content for " . time();
$test_file = sys_get_temp_dir() . '/test_' . uniqid() . '.mp3';
file_put_contents($test_file, $test_content);

$file_hash = hash_file('sha256', $test_file);
test("SHA-256 hash generation", strlen($file_hash) === 64, substr($file_hash, 0, 16) . "...");

$hash_valid = preg_match('/^[a-f0-9]{64}$/i', $file_hash) === 1;
test("Hash format validation", $hash_valid);

// Metadata hash
$metadata = [
    'title' => 'Test Track',
    'artist_name' => 'Test Artist',
    'credits' => ['producer' => 'Test'],
    'rights_split' => ['artist' => 100]
];

$metadata_hash = $ledger->generateMetadataHash($metadata);
test("Metadata hash generation", strlen($metadata_hash) === 64, substr($metadata_hash, 0, 16) . "...");

$metadata_hash2 = $ledger->generateMetadataHash($metadata);
test("Metadata hash consistency", $metadata_hash === $metadata_hash2);

// ============================================================================
// TEST 4: Certificate Generation
// ============================================================================
echo "\n┌─ TEST 4: Certificate Generation ───────────────────────────────┐\n";

$owner_info = ['name' => 'Test Artist', 'email' => 'test@example.com'];

$test_ledger_record = [
    'certificate_id' => 'CRT-20260206-12345678',
    'content_hash' => $file_hash,
    'title' => 'Test Track',
    'artist_name' => 'Test Artist',
    'file_size_bytes' => filesize($test_file),
    'mime_type' => 'audio/mpeg',
    'owner_id' => 1,
    'created_at' => date('Y-m-d H:i:s'),
    'verification_count' => 0
];

$cert_html = $cert->generateCertificateHtml($test_ledger_record, $owner_info);

test("Certificate HTML generated", strlen($cert_html) > 100, strlen($cert_html) . " bytes");
test("HTML is valid", strpos($cert_html, '<!DOCTYPE html>') !== false);
test("QR code embedded", strpos($cert_html, 'data:image/png;base64,') !== false);
test("Certificate ID in HTML", strpos($cert_html, 'CRT-20260206-12345678') !== false);
test("Title in HTML", strpos($cert_html, 'Test Track') !== false);

// Save certificate
$cert_dir = __DIR__ . '/../storage/certificates';
@mkdir($cert_dir, 0775, true);
$cert_file = $cert_dir . '/CRT-20260206-TEST.html';
file_put_contents($cert_file, $cert_html);
test("Certificate file saved", file_exists($cert_file), "Size: " . filesize($cert_file) . " bytes");

// ============================================================================
// TEST 5: File System
// ============================================================================
echo "\n┌─ TEST 5: File System ──────────────────────────────────────────┐\n";

test("Certificate directory exists", is_dir($cert_dir));
test("Certificate directory writable", is_writable($cert_dir));
test("Test file created", file_exists($test_file));

// ============================================================================
// TEST 6: Code Integrations
// ============================================================================
echo "\n┌─ TEST 6: Code Integrations ────────────────────────────────────┐\n";

$integrations = [
    'StationContentService.php' => 'ContentLedgerService',
    'smr-ingestion.php' => 'ContentLedgerService',
    'assistant-upload.php' => 'ContentLedgerService',
    'UploadService.php' => 'hash_file'
];

foreach ($integrations as $file => $keyword) {
    $filepath = __DIR__ . '/../';
    if (strpos($file, '.php') !== false) {
        if (strpos($file, '/') === false) {
            $paths = [
                "../lib/Stations/$file",
                "../public/admin/$file",
                "../lib/Smr/$file"
            ];
            $found = false;
            foreach ($paths as $p) {
                $fullpath = __DIR__ . '/' . $p;
                if (file_exists($fullpath)) {
                    $content = file_get_contents($fullpath);
                    if (strpos($content, $keyword) !== false) {
                        test("Integration in " . basename($file), true, "Found '$keyword'");
                        $found = true;
                        break;
                    }
                }
            }
            if (!$found) {
                test("Integration in " . basename($file), false, "Could not find '$keyword'");
            }
        }
    }
}

// ============================================================================
// TEST 7: Documentation
// ============================================================================
echo "\n┌─ TEST 7: Documentation ────────────────────────────────────────┐\n";

$docs = [
    ['DIGITAL_SAFETY_SEAL_IMPLEMENTATION.md', '../docs/DIGITAL_SAFETY_SEAL_IMPLEMENTATION.md'],
    ['DEPLOYMENT_NOTES.md', '../DEPLOYMENT_NOTES.md'],
    ['PROGRESS_TRACKING_OVERVIEW.md', '../PROGRESS_TRACKING_OVERVIEW.md']
];

foreach ($docs as [$docName, $relPath]) {
    $path = __DIR__ . '/' . $relPath;
    test("Docs: $docName", file_exists($path), file_exists($path) ? filesize($path) . " bytes" : "NOT FOUND");
}

// ============================================================================
// CLEANUP
// ============================================================================
echo "\n┌─ Cleanup ──────────────────────────────────────────────────────┐\n";

if (file_exists($test_file)) {
    unlink($test_file);
    echo "  ✓ Temporary test file removed\n";
}

// ============================================================================
// SUMMARY
// ============================================================================
echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "  TEST SUMMARY\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$total = $passed + $failed;
echo "  Total Tests: $total\n";
echo "  Passed: \033[92m$passed\033[0m\n";
if ($failed > 0) {
    echo "  Failed: \033[91m$failed\033[0m\n";
}

if ($failed === 0) {
    echo "\n  \033[92m🎉 ALL TESTS PASSED! 🎉\033[0m\n\n";
    exit(0);
} else {
    echo "\n  \033[91m⚠️  SOME TESTS FAILED\033[0m\n\n";
    exit(1);
}
