<?php

/**
 * test-smr-push.php - Single-row ping to verify HMAC handshake and rate limiting
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Services\Graylight\GraylightServiceClient;

echo "🛰️  NGN Ingest - Graylight Handshake Test
";
echo "========================================
";

$config = new Config();
$client = new GraylightServiceClient($config);

$testPayload = [
    'namespace' => 'NGN_SMR_DUMP',
    'schema_version' => 'v1.1.0',
    'metadata' => [
        'report_week' => 1,
        'report_year' => 2026,
        'source' => 'Erik_Baker_Archive',
        'integrity_check' => 'pre_push'
    ],
    'data' => [
        [
            'raw_artist_name' => 'PING_TEST_ARTIST',
            'raw_track_title' => 'PING_TEST_TITLE',
            'spin_count' => 1,
            'rank_position' => 1,
            'reach_count' => 1,
            'raw_label_name' => 'PING_TEST_LABEL',
            'spin_at' => date('c')
        ]
    ]
];

try {
    echo "Pushing single-row test payload to /v1/ingest/push...
";
    $result = $client->call('ingest/push', $testPayload);
    
    echo "✅ Success! Handshake verified.
";
    echo "   Vault ID: " . ($result['data']['vault_id'] ?? 'N/A') . "
";
    echo "   Result: " . json_encode($result, JSON_PRETTY_PRINT) . "
";
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "
";
}

echo "
✅ Test run complete.
";
