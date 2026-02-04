<?php
/**
 * Private Fairness Receipt API (Owner Only)
 * Endpoint: GET /api/v1/receipts/private?entity_type=artist&entity_id=123
 *
 * Returns detailed private receipts (owner-only access)
 * Includes:
 * - Detailed factor values
 * - Calculation steps
 * - Raw data snapshots
 * - Comparative analysis
 *
 * Authentication: Required - User must own the entity
 */

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Fairness\FairnessReceipt;

if (!isset($_SESSION)) {
    session_start();
}

header('Content-Type: application/json');

// Authentication check
if (!isset($_SESSION['User'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$userId = (int)$_SESSION['User']['Id'];
$entityType = $_GET['entity_type'] ?? null;
$entityId = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : null;

// Validate parameters
if (!$entityType || !$entityId) {
    http_response_code(400);
    echo json_encode(['error' => 'entity_type and entity_id required']);
    exit;
}

try {
    $config = new Config();
    $pdo = ConnectionFactory::read($config);

    // Verify ownership
    $ownershipStmt = $pdo->prepare(
        "SELECT user_id FROM {$entityType}s WHERE id = ?"
    );
    $ownershipStmt->execute([$entityId]);
    $entity = $ownershipStmt->fetch(PDO::FETCH_ASSOC);

    if (!$entity || (int)$entity['user_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['error' => 'Not authorized to view this receipt']);
        exit;
    }

    $receiptService = new FairnessReceipt($pdo);

    // Get receipt history
    $history = $receiptService->getEntityReceiptHistory($entityType, $entityId);

    if (empty($history)) {
        http_response_code(404);
        echo json_encode([
            'message' => 'No receipts found for this entity'
        ]);
        exit;
    }

    // Prepare detailed response
    $receipts = [];
    foreach ($history as $receipt) {
        $receipts[] = [
            'receipt_id' => $receipt['receipt_id'],
            'generated_at' => $receipt['created_at'],
            'rank' => (int)$receipt['rank'],
            'score' => (float)$receipt['score'],
            'factors' => $receipt['factors_json'] ? json_decode($receipt['factors_json'], true) : [],
            'signature' => $receipt['signature'],
            'verification_link' => "/api/v1/receipts/verify?receipt_id={$receipt['receipt_id']}"
        ];
    }

    http_response_code(200);
    echo json_encode([
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'total_receipts' => count($receipts),
        'receipts' => $receipts,
        'access_level' => 'private',
        'note' => 'Detailed receipts show exact calculation breakdown. Keep private - contains sensitive data.'
    ]);

} catch (\Throwable $e) {
    error_log("Private receipt API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
