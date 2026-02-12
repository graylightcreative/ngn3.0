<?php

/**
 * test-smr-push.php - Single-row ping to verify HMAC handshake and rate limiting
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Services\Graylight\GraylightServiceClient;

echo "🛰️  NGN Ingest - Graylight Handshake Test\n";
echo "========================================\n";

// 1. Timestamp Audit
$now = time();
echo "[AUDIT] Local System Time: " . date('Y-m-d H:i:s', $now) . " (Unix: $now)\n";
echo "[AUDIT] Feb 10, 2026 Expected: 1770681600 (Approx)\n";

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
    echo "Pushing single-row test payload to /v1/ingest/push...\n";
    $result = $client->call('ingest/push', $testPayload);
    
    if (isset($result['status']) && $result['status'] === 'error') {
        echo "❌ Graylight API Error: " . ($result['message'] ?? 'Unknown Error') . "\n";
        echo "   Code: " . ($result['code'] ?? 'N/A') . "\n";
        echo "   Full Response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
        exit(1);
    }

    echo "✅ Success! Handshake verified.\n";
    echo "   Vault ID: " . ($result['data']['vault_id'] ?? 'N/A') . "\n";
    echo "   Transaction: " . ($result['data']['transaction_hash'] ?? 'N/A') . "\n";
    
} catch (\Throwable $e) {
    echo "❌ Execution Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ Test run complete.\n";