<?php
/**
 * Fairness Receipt Verification API
 * Endpoint: GET /api/v1/receipts/verify?receipt_id=xxx
 *
 * Verifies the cryptographic signature of a receipt
 * Returns whether the receipt has been tampered with
 *
 * Response:
 * {
 *   receipt_id: string,
 *   valid: bool,
 *   signature_status: "valid|invalid|not_found",
 *   tampering_detected: bool,
 *   message: string,
 *   verified_at: datetime
 * }
 */

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Fairness\FairnessReceipt;

header('Content-Type: application/json');

// Get receipt ID from query
if (!isset($_GET['receipt_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'receipt_id parameter required']);
    exit;
}

$receiptId = $_GET['receipt_id'];

try {
    $config = new Config()
;
    $pdo = ConnectionFactory::read($config);
    $receiptService = new FairnessReceipt($pdo);

    // Look up receipt
    $stmt = $pdo->prepare(
        'SELECT * FROM fairness_receipt_log WHERE receipt_id = ?'
    );
    $stmt->execute([$receiptId]);
    $receiptLog = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$receiptLog) {
        http_response_code(404);
        echo json_encode([
            'receipt_id' => $receiptId,
            'valid' => false,
            'signature_status' => 'not_found',
            'message' => 'Receipt not found in ledger'
        ]);
        exit;
    }

    // Reconstruct receipt for verification
    // Note: This is a simplified verification - full receipt object would need to be stored
    $reconstructedReceipt = [
        'receipt_id' => $receiptLog['receipt_id'],
        'entity_type' => $receiptLog['entity_type'],
        'entity_id' => $receiptLog['entity_id'],
        'rank' => $receiptLog['rank'],
        'score' => $receiptLog['score'],
        'signature' => $receiptLog['signature']
    ];

    // Verify signature
    $isValid = $receiptService->verifyReceiptSignature($reconstructedReceipt);

    // Log verification event
    $auditStmt = $pdo->prepare(
        'INSERT INTO fairness_audit_log (receipt_id, entity_type, entity_id, event_type, details, triggered_by)
        VALUES (?, ?, ?, ?, ?, ?)'
    );
    $auditStmt->execute([
        $receiptId,
        $receiptLog['entity_type'],
        $receiptLog['entity_id'],
        'receipt_verified',
        json_encode(['result' => $isValid ? 'valid' : 'invalid']),
        'public_api'
    ]);

    http_response_code(200);
    echo json_encode([
        'receipt_id' => $receiptId,
        'valid' => $isValid,
        'signature_status' => $isValid ? 'valid' : 'invalid',
        'tampering_detected' => !$isValid,
        'message' => $isValid
            ? 'Receipt signature is valid - no tampering detected'
            : 'Receipt signature is invalid - potential tampering detected!',
        'entity_type' => $receiptLog['entity_type'],
        'entity_id' => (int)$receiptLog['entity_id'],
        'rank' => (int)$receiptLog['rank'],
        'score' => (float)$receiptLog['score'],
        'generated_at' => $receiptLog['created_at'],
        'verified_at' => date('c')
    ]);

} catch (\Throwable $e) {
    error_log("Receipt verification API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'valid' => false,
        'error' => 'Verification failed'
    ]);
}
