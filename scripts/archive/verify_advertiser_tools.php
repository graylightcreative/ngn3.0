<?php
/**
 * VERIFY ADVERTISER TOOLS
 *
 * Tests AdvertiserService and API endpoints (internal calls).
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Services\Advertiser\AdvertiserService;

$config = new Config();
$pdo = ConnectionFactory::write($config);

echo "--- VERIFYING ADVERTISER TOOLS ---
";

$svc = new AdvertiserService($pdo);

// 1. Test Suggestion Generator
echo "Testing AI Suggestions...
";
$suggestions = $svc->generateSuggestions("Promote new metal album", "audio");
if (isset($suggestions['targeting']) && count($suggestions['copy']) > 0) {
    echo "✓ AI Suggestions working.
";
} else {
    echo "✗ AI Suggestions failed.
";
}

// 2. Test Submit Request
echo "Testing Submit Request...
";
$requestId = $svc->submitRequest(1, [ // Using admin user ID 1
    'campaign_type' => 'audio',
    'title' => 'Test Audio Campaign',
    'objective' => 'Increase streams for new single',
    'budget_cents' => 50000,
    'target_audience' => ['genres' => ['Metal']],
    'ai_suggestions' => $suggestions
]);

if ($requestId > 0) {
    echo "✓ Request submitted (ID: $requestId).
";
} else {
    echo "✗ Request submission failed.
";
}

// 3. Test Get Requests
echo "Testing Get Requests...
";
$requests = $svc->getRequests(1);
if (count($requests) > 0) {
    echo "✓ Retrieved " . count($requests) . " requests.
";
} else {
    echo "✗ Retrieval failed.
";
}

// 4. Test Admin Update Status
echo "Testing Admin Status Update...
";
$success = $svc->updateStatus($requestId, 'reviewing', 'Looking good, needs higher res assets.');
if ($success) {
    $updated = $svc->getRequest($requestId);
    if ($updated['status'] === 'reviewing') {
        echo "✓ Status update verified.
";
    } else {
        echo "✗ Status mismatch.
";
    }
} else {
    echo "✗ Status update failed.
";
}

echo "--- VERIFICATION COMPLETE ---
";
