<?php

/**
 * smoke-test-graylight.php - Smoke test for Graylight Service Bridge
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Services\Graylight\GraylightServiceClient;

echo "🛰️  NGN Tenant - Graylight Bridge Smoke Test
";
echo "==========================================
";

$config = new Config();
$client = new GraylightServiceClient($config);

// 1. Check Config
$apiKey = $config->glApiKey();
if (empty($apiKey) || $apiKey === 'your_api_key_here') {
    echo "⚠️  GL_API_KEY not configured in .env
";
} else {
    echo "✅ GL_API_KEY present
";
}

// 2. Beacon Status Pull
echo "
[BEACON] Pulling System Status...
";
try {
    // Calling v1/status
    $result = $client->call('status');
    echo "✅ Result: " . json_encode($result, JSON_PRETTY_PRINT) . "
";
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "
";
    echo "   (Note: If you haven't provided real keys, a 403 or signature error is expected)
";
}

echo "
✅ Test run complete.
";
