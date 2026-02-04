<?php
/**
 * Public Fairness Receipt API
 * Endpoint: GET /api/v1/receipts/public/{receipt_id}
 *
 * Returns a public fairness receipt with:
 * - Artist/label name and rank
 * - Score breakdown by factors
 * - Ranking window info
 * - Cryptographic signature for verification
 *
 * Response:
 * {
 *   receipt_id: string,
 *   entity_type: string,
 *   entity_name: string,
 *   rank: int,
 *   score: float,
 *   factors: { factor_name => { weight, description, formula } },
 *   ranking_window: { interval, start, end },
 *   generated_at: datetime,
 *   signature: string (HMAC-SHA256),
 *   verification_status: "valid|invalid"
 * }
 */

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Fairness\FairnessReceipt;

header('Content-Type: application/json');

// Extract receipt ID from URL
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!preg_match('/\/api\/v1\/receipts\/public\/([A-Z0-9\-]+)/', $path, $m)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$receiptId = $m[1];

try {
    $config = new Config();
    $pdo = ConnectionFactory::read($config);
    $receiptService = new FairnessReceipt($pdo);

    // Look up receipt in log
    $stmt = $pdo->prepare(
        'SELECT * FROM fairness_receipt_log WHERE receipt_id = ?'
    );
    $stmt->execute([$receiptId]);
    $receiptLog = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$receiptLog) {
        http_response_code(404);
        echo json_encode(['error' => 'Receipt not found']);
        exit;
    }

    // Get entity details
    $entityType = $receiptLog['entity_type'];
    $entityId = $receiptLog['entity_id'];

    $entityStmt = $pdo->prepare(
        "SELECT Name FROM {$entityType}s WHERE id = ?"
    );
    $entityStmt->execute([$entityId]);
    $entity = $entityStmt->fetch(PDO::FETCH_ASSOC);

    if (!$entity) {
        http_response_code(404);
        echo json_encode(['error' => 'Entity not found']);
        exit;
    }

    // Get factor weights for context
    $factorStmt = $pdo->prepare(
        'SELECT factor_name, description, base_weight, methodology_url
        FROM fairness_factor_weights
        WHERE applies_to_artist = 1 AND is_public = 1
        ORDER BY factor_name'
    );
    $factorStmt->execute();
    $factors = [];
    while ($row = $factorStmt->fetch(PDO::FETCH_ASSOC)) {
        $factors[$row['factor_name']] = [
            'description' => $row['description'],
            'weight' => (float)$row['base_weight'],
            'methodology' => $row['methodology_url']
        ];
    }

    // Build public receipt response
    $response = [
        'receipt_id' => $receiptLog['receipt_id'],
        'entity_type' => $entityType,
        'entity_id' => (int)$entityId,
        'entity_name' => $entity['Name'],
        'rank' => (int)$receiptLog['rank'],
        'score' => (float)$receiptLog['score'],
        'factors' => $factors,
        'signature' => $receiptLog['signature'],
        'generated_at' => $receiptLog['created_at'],
        'verified_at' => $receiptLog['verified_at'],
        'verification_url' => "/api/v1/receipts/verify?receipt_id={$receiptId}",
        'transparency_page' => "https://ngn.io/fairness"
    ];

    // Optional: Include decoded factors if available
    if (!empty($receiptLog['factors_json'])) {
        $response['factor_breakdown'] = json_decode($receiptLog['factors_json'], true);
    }

    http_response_code(200);
    echo json_encode($response);

} catch (\Throwable $e) {
    error_log("Public receipt API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
