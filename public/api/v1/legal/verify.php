<?php
/**
 * Digital Safety Seal Verification API
 * Endpoint: GET /api/v1/legal/verify
 *
 * Public verification endpoint for content ownership ledger entries.
 * No authentication required - allows third-party verification.
 *
 * Query Parameters:
 *   - hash: SHA-256 content hash (e.g., abc123def...)
 *   - certificate_id: Certificate ID (e.g., CRT-20260206-A3F8D91E)
 *
 * Example requests:
 *   GET /api/v1/legal/verify?hash=abc123...
 *   GET /api/v1/legal/verify?certificate_id=CRT-20260206-A3F8D91E
 *
 * Response (200 - verified):
 * {
 *   "verified": true,
 *   "certificate_id": "CRT-20260206-A3F8D91E",
 *   "content_hash": "abc123...",
 *   "owner": { "user_id": 42, "name": "Artist Name" },
 *   "content": {
 *     "title": "Track Title",
 *     "artist_name": "Artist Name",
 *     "file_size_bytes": 5242880,
 *     "mime_type": "audio/mpeg"
 *   },
 *   "registered_at": "2026-02-06T12:00:00+00:00",
 *   "verification_count": 15,
 *   "last_verified_at": "2026-02-06T14:30:00+00:00",
 *   "status": "active",
 *   "message": "Content verified - Registered in NGN ledger"
 * }
 */

require_once dirname(__DIR__, 4) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Legal\ContentLedgerService;
use NGN\Lib\Logging\LoggerFactory;
use NGN\Lib\Http\Request;
use NGN\Lib\Middleware\RateLimiter;

// Initialize config and request
$config = new Config();
$request = new Request();

// Set response headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 2.0.3 Rate Limiting
$limiter = new RateLimiter(__DIR__ . '/../../../../storage/logs/ratelimit', 100, 3600);
$limitResponse = $limiter->check($request);
if ($limitResponse) {
    $limitResponse->send();
    exit;
}

header('Cache-Control: public, max-age=300'); // Cache verification for 5 minutes

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $pdo = ConnectionFactory::read($config);
    $logger = LoggerFactory::create($config, 'content_verification_api');

    // Get query parameters
    $contentHash = isset($_GET['hash']) ? trim($_GET['hash']) : null;
    $certificateId = isset($_GET['certificate_id']) ? trim($_GET['certificate_id']) : null;

    // Validate inputs
    if (!$contentHash && !$certificateId) {
        http_response_code(400);
        echo json_encode([
            'verified' => false,
            'status' => 'invalid_request',
            'message' => 'Either "hash" or "certificate_id" parameter is required'
        ]);
        exit;
    }

    // Validate hash format if provided
    if ($contentHash && !preg_match('/^[a-f0-9]{64}$/i', $contentHash)) {
        http_response_code(400);
        echo json_encode([
            'verified' => false,
            'status' => 'invalid_hash_format',
            'message' => 'Hash must be a 64-character hexadecimal SHA-256'
        ]);
        exit;
    }

    // Normalize hash to lowercase for comparison
    if ($contentHash) {
        $contentHash = strtolower($contentHash);
    }

    // Initialize ledger service
    $ledgerService = new ContentLedgerService($pdo, $config, $logger);

    // Lookup in ledger
    $ledgerRecord = null;
    $lookupType = null;

    if ($certificateId) {
        $ledgerRecord = $ledgerService->lookupByCertificateId($certificateId);
        $lookupType = 'certificate';
    } elseif ($contentHash) {
        $ledgerRecord = $ledgerService->lookupByHash($contentHash);
        $lookupType = 'hash';
    }

    // Not found
    if (!$ledgerRecord) {
        http_response_code(404);

        // Log failed lookup attempt
        $logger->notice('verification_not_found', [
            'lookup_type' => $lookupType,
            'search_value' => $lookupType === 'hash' ? substr($contentHash, 0, 8) : $certificateId,
            'request_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);

        echo json_encode([
            'verified' => false,
            'status' => 'not_found',
            'message' => 'No ledger entry found for the provided hash or certificate ID'
        ]);
        exit;
    }

    // Get owner information
    $ownerStmt = $pdo->prepare("
        SELECT id, name, email FROM users WHERE id = :owner_id LIMIT 1
    ");
    $ownerStmt->execute([':owner_id' => $ledgerRecord['owner_id']]);
    $ownerInfo = $ownerStmt->fetch(PDO::FETCH_ASSOC);

    // Prepare verification response
    $response = [
        'verified' => true,
        'certificate_id' => $ledgerRecord['certificate_id'],
        'content_hash' => $ledgerRecord['content_hash'],
        'owner' => [
            'user_id' => (int)$ledgerRecord['owner_id'],
            'name' => $ownerInfo['name'] ?? 'Unknown'
            // Email intentionally omitted for privacy
        ],
        'content' => [
            'title' => $ledgerRecord['title'] ?? 'Untitled',
            'artist_name' => $ledgerRecord['artist_name'] ?? '',
            'file_size_bytes' => (int)$ledgerRecord['file_size_bytes'],
            'mime_type' => $ledgerRecord['mime_type'] ?? 'unknown',
            'original_filename' => $ledgerRecord['original_filename'] ?? 'unknown'
        ],
        'registered_at' => gmdate('c', strtotime($ledgerRecord['created_at'])),
        'verification_count' => (int)$ledgerRecord['verification_count'],
        'last_verified_at' => $ledgerRecord['last_verified_at']
            ? gmdate('c', strtotime($ledgerRecord['last_verified_at']))
            : null,
        'status' => $ledgerRecord['status'] ?? 'active',
        'message' => 'Content verified - Registered in NGN ledger'
    ];

    // Increment verification counter and log the verification
    $requestInfo = [
        'request_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'request_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'request_referer' => $_SERVER['HTTP_REFERER'] ?? null,
        'request_metadata' => [
            'lookup_type' => $lookupType,
            'verification_type' => 'public_api'
        ]
    ];

    $ledgerService->incrementVerificationCount(
        (int)$ledgerRecord['id'],
        'public_api',
        'match',
        $requestInfo
    );

    // Update verification count in response (increment already happened)
    $response['verification_count'] = (int)$ledgerRecord['verification_count'] + 1;

    // Log successful verification
    $logger->info('verification_successful', [
        'certificate_id' => $ledgerRecord['certificate_id'],
        'owner_id' => $ledgerRecord['owner_id'],
        'lookup_type' => $lookupType,
        'request_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'verification_count' => $response['verification_count']
    ]);

    // Return response
    http_response_code(200);
    echo json_encode($response);

} catch (\Exception $e) {
    // Log error
    error_log('Content verification API error: ' . $e->getMessage());

    // Return generic error (don't expose internal details)
    http_response_code(500);
    echo json_encode([
        'verified' => false,
        'status' => 'server_error',
        'message' => 'An error occurred while verifying the content'
    ]);
    exit;
}
