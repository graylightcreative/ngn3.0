<?php
/**
 * Custom Domain DNS Verification Check
 * Verifies TXT record and marks domain as verified
 *
 * Usage: php check-custom-domain.php example.com
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

if (php_sapi_name() !== 'cli') {
    die("This script is CLI only.\n");
}

// Get domain from command line
$domain = $argv[1] ?? null;
if (!$domain) {
    echo "Usage: php check-custom-domain.php example.com\n";
    exit(1);
}

$domain = trim($domain);

// Validate domain format
if (!preg_match('/^[a-z0-9]([a-z0-9-]*\.)*[a-z0-9-]*\.[a-z]{2,}$/i', $domain)) {
    echo "Error: Invalid domain format: $domain\n";
    exit(1);
}

// Connect to database
try {
    $config = new Config();
    $pdo = ConnectionFactory::write($config);
} catch (\Throwable $e) {
    echo "Error: Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Get expected token from database
try {
    $stmt = $pdo->prepare("
        SELECT custom_domain_verification_token
        FROM url_routes
        WHERE custom_domain = :domain
        LIMIT 1
    ");
    $stmt->execute([':domain' => $domain]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo "Error: Domain '$domain' not found in url_routes table.\n";
        exit(1);
    }

    $expectedToken = $row['custom_domain_verification_token'];
    if (!$expectedToken) {
        echo "Error: No verification token found for domain '$domain'.\n";
        echo "Run: php verify-custom-domain.php $domain\n";
        exit(1);
    }

} catch (\Throwable $e) {
    echo "Error: Database error: " . $e->getMessage() . "\n";
    exit(1);
}

// Check DNS TXT record
echo "Checking DNS for TXT record on _ngn-verify.$domain...\n";

$txtRecords = @dns_get_record("_ngn-verify.$domain", DNS_TXT);

if ($txtRecords === false) {
    echo "Error: Could not query DNS for $domain.\n";
    echo "This may be a temporary DNS issue. Please try again.\n";
    exit(1);
}

if (empty($txtRecords)) {
    echo "Error: No TXT records found at _ngn-verify.$domain\n";
    echo "Expected TXT value: $expectedToken\n";
    exit(1);
}

// Look for matching token
$found = false;
foreach ($txtRecords as $record) {
    if (isset($record['txt']) && $record['txt'] === $expectedToken) {
        $found = true;
        break;
    }
}

if (!$found) {
    echo "Error: TXT record found but token doesn't match.\n";
    echo "Expected: $expectedToken\n";
    echo "Found:\n";
    foreach ($txtRecords as $record) {
        echo "  - " . ($record['txt'] ?? '(empty)') . "\n";
    }
    exit(1);
}

// Mark domain as verified in database
try {
    $stmt = $pdo->prepare("
        UPDATE url_routes
        SET custom_domain_verified = TRUE,
            custom_domain_verified_at = NOW(),
            custom_domain_expires_at = DATE_ADD(NOW(), INTERVAL 1 YEAR)
        WHERE custom_domain = :domain
    ");
    $stmt->execute([':domain' => $domain]);

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✅ Domain verified successfully!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "\nDomain: $domain\n";
    echo "Status: VERIFIED\n";
    echo "Expires: " . date('Y-m-d H:i:s', strtotime('+1 year')) . "\n";
    echo "\nYou can now use this domain for your NGN profile.\n";

    exit(0);

} catch (\Throwable $e) {
    echo "Error: Failed to update database: " . $e->getMessage() . "\n";
    exit(1);
}
