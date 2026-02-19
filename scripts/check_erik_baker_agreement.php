<?php
/**
 * Check Erik Baker's Agreement Status
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Services\Legal\AgreementService;

$config = new Config();
$db = ConnectionFactory::read($config);

echo "=== ERIK BAKER AGREEMENT STATUS REPORT ===\n\n";

// Initialize AgreementService
$service = new AgreementService($db);

// Check if template exists and is active
$template = $service->getTemplate('erik-baker-advisor');

echo "1. TEMPLATE STATUS:\n";
echo "   Status: " . ($template ? "✓ FOUND" : "✗ NOT FOUND") . "\n";

if ($template) {
    echo "   Slug: {$template['slug']}\n";
    echo "   Version: {$template['version']}\n";
    echo "   Active: " . ($template['is_active'] ? 'YES' : 'NO') . "\n";
    echo "   Created: {$template['created_at']}\n";
    echo "   Body Length: " . strlen($template['body']) . " characters\n";
}

echo "\n2. ERIK BAKER SIGNATURE STATUS:\n";

$erikUserId = 4;

// Check if Erik Baker has signed
$hasSigned = $service->hasSigned($erikUserId, 'erik-baker-advisor');
echo "   Signed: " . ($hasSigned ? "✓ YES" : "✗ NO") . "\n";

// Get actual signature details if signed
if ($template && $hasSigned) {
    $stmt = $db->prepare("
        SELECT s.id, s.signed_at, s.ip_address, s.user_agent, s.agreement_hash
        FROM agreement_signatures s
        WHERE s.user_id = ? AND s.template_id = ?
    ");
    $stmt->execute([$erikUserId, $template['id']]);
    $signature = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($signature) {
        echo "   Signature ID: {$signature['id']}\n";
        echo "   Signed At: {$signature['signed_at']}\n";
        echo "   From IP: {$signature['ip_address']}\n";
        echo "   User Agent: " . ($signature['user_agent'] ? substr($signature['user_agent'], 0, 60) . "..." : "Not recorded") . "\n";
        echo "   Agreement Hash: " . substr($signature['agreement_hash'], 0, 16) . "...\n";
    }
}

// Check user exists
$stmt = $db->prepare("SELECT id, role_id FROM users WHERE id = ?");
$stmt->execute([$erikUserId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "\n3. USER DETAILS:\n";
if ($user) {
    echo "   Found: ✓ YES\n";
    echo "   User ID: {$user['id']}\n";
    echo "   Role ID: {$user['role_id']}\n";
} else {
    echo "   Found: ✗ NO (User ID 4 does not exist)\n";
}

echo "\n4. SIGNING CAPABILITY:\n";

if (!$template) {
    echo "   Status: ✗ BLOCKED - Template not found\n";
} elseif (!$template['is_active']) {
    echo "   Status: ✗ BLOCKED - Template is inactive\n";
} elseif (!$user) {
    echo "   Status: ✗ BLOCKED - User not found\n";
} elseif ($hasSigned) {
    echo "   Status: ✓ COMPLETE - Agreement already signed\n";
} else {
    echo "   Status: ✓ READY - Can sign agreement at /agreement/erik-baker-advisor\n";
}

echo "\n5. SECURITY DETAILS:\n";
if ($template) {
    echo "   Signing Method: SHA-256 hash of agreement body\n";
    $bodyHash = hash('sha256', $template['body']);
    echo "   Agreement Body Hash: " . $bodyHash . "\n";
    echo "   Audit Trail: IP address and User-Agent captured\n";
    echo "   Timestamp: Recorded at signing time with NOW()\n";
    echo "   Verification: " . ($hasSigned ? "Hash stored for integrity verification" : "Will be stored upon signing") . "\n";
}

echo "\n6. INTEGRATION DETAILS:\n";
echo "   System Location: lib/Services/Legal/AgreementService.php\n";
echo "   Redirect URL: /agreement/erik-baker-advisor\n";
echo "   Signing URL: /api/v1/legal/sign-agreement.php\n";
echo "   Check Location: public/index.php (line ~330)\n";

echo "\n=== END OF REPORT ===\n";
