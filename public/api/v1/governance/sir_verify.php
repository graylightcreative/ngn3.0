<?php

/**
 * SIR One-Tap Verification Endpoint
 * Chapter 31 - Mobile Verification
 *
 * POST /api/v1/governance/sir/{id}/verify - One-tap verification (Director only)
 *
 * Authentication: Bearer JWT token (assigned Director only)
 */

require_once __DIR__ . '/../../../../vendor/autoload.php';

header('Content-Type: application/json');

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;
use NGN\Lib\Governance\SirRegistryService;
use NGN\Lib\Governance\DirectorateRoles;
use NGN\Lib\Governance\SirAuditService;

try {
    // Get database connection
    $config = new Config();
    $pdo = ConnectionFactory::write($config);

    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }

    // Initialize services
    $roles = new DirectorateRoles();
    $sirService = new SirRegistryService($pdo, $roles);
    $auditService = new SirAuditService($pdo);

    // Verify authentication
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($authHeader) || strpos($authHeader, 'Bearer ') !== 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Missing or invalid authentication']);
        exit;
    }

    // Get user ID
    $userId = (int)($_SERVER['HTTP_X_USER_ID'] ?? 0);
    if ($userId <= 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid user']);
        exit;
    }

    // Extract SIR ID from request path
    $pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
    $sirIdIndex = array_search('sir', $pathParts);

    if ($sirIdIndex === false || !isset($pathParts[$sirIdIndex + 1])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing SIR ID']);
        exit;
    }

    $sirId = (int)$pathParts[$sirIdIndex + 1];

    // Only POST allowed
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    // Get SIR
    $sir = $sirService->getSir($sirId);
    if (!$sir) {
        http_response_code(404);
        echo json_encode(['error' => 'SIR not found']);
        exit;
    }

    // Verify only assigned director can verify
    if ($userId !== $sir['director_user_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Only assigned director can verify']);
        exit;
    }

    // Check if SIR is in a verifiable state
    if (!in_array($sir['status'], ['rant_phase', 'in_review'])) {
        http_response_code(400);
        echo json_encode([
            'error' => 'SIR cannot be verified in current state',
            'current_status' => $sir['status'],
            'allowed_statuses' => ['rant_phase', 'in_review'],
        ]);
        exit;
    }

    // Update status to VERIFIED
    $sirService->updateStatus($sirId, 'verified', $userId, 'director');

    // Log verification in audit
    $auditService->logVerified($sirId, $userId);

    // Get updated SIR
    $updatedSir = $sirService->getSir($sirId);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'SIR verified successfully',
        'data' => [
            'sir_number' => $updatedSir['sir_number'],
            'status' => $updatedSir['status'],
            'verified_at' => $updatedSir['verified_at'],
            'director' => $roles->getDirectorName($updatedSir['assigned_to_director']),
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage(),
    ]);
}
