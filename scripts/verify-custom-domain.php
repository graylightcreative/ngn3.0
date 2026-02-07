<?php
/**
 * Custom Domain Verification Script
 * Initiates verification for custom domain ownership
 *
 * Usage: php verify-custom-domain.php example.com
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
    echo "Usage: php verify-custom-domain.php example.com\n";
    echo "       php verify-custom-domain.php example.com artist\n";
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

// Generate verification token
$token = 'ngn-verify-' . bin2hex(random_bytes(16));

// Prepare and execute update
try {
    $stmt = $pdo->prepare("
        UPDATE url_routes
        SET custom_domain_verified = FALSE,
            custom_domain_verification_token = :token,
            custom_domain_requested_at = NOW()
        WHERE custom_domain = :domain
    ");

    $result = $stmt->execute([
        ':token' => $token,
        ':domain' => $domain
    ]);

    if ($stmt->rowCount() === 0) {
        echo "Error: Domain '$domain' not found in url_routes table.\n";
        echo "You must first add the domain to a url_routes record.\n";
        exit(1);
    }

    echo "Domain verification initiated for: $domain\n";
    echo "\n";
    echo "Add this TXT record to your domain's DNS:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Name:  _ngn-verify\n";
    echo "Type:  TXT\n";
    echo "Value: $token\n";
    echo "TTL:   3600 (1 hour)\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "\n";
    echo "After adding the TXT record (allow 10-30 minutes for propagation),\n";
    echo "run: php check-custom-domain.php $domain\n";
    echo "\n";

} catch (\PDOException $e) {
    echo "Error: Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
