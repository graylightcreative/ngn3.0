<?php

/**
 * SIR Audit Trail Endpoint
 * Chapter 31 - Immutable Paper Trail
 *
 * GET /api/v1/governance/sir/{id}/audit - Get audit trail (Admin only)
 *
 * Authentication: Bearer JWT token (Admin only)
 */

require_once __DIR__ . '/../../../../vendor/autoload.php';

header('Content-Type: application/json');

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;
use NGN\Lib\Governance\DirectorateRoles;
use NGN\Lib\Governance\SirAuditService;

try {
    // Get database connection
    $config = new Config();
    $pdo = ConnectionFactory::read($config);

    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }

    // Initialize services
    $roles = new DirectorateRoles();
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

    // Check if user is admin/chairman (for audit access)
    // In production, check actual admin role
    $isAdmin = $roles->isChairman($userId);

    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }

    // Only GET allowed
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
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

    // Verify SIR exists
    $stmt = $pdo->prepare("SELECT id, sir_number FROM ngn_2025.directorate_sirs WHERE id = ?");
    $stmt->execute([$sirId]);
    $sir = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sir) {
        http_response_code(404);
        echo json_encode(['error' => 'SIR not found']);
        exit;
    }

    // Get audit trail
    $auditTrail = $auditService->getAuditTrail($sirId);

    // Enhance audit entries with actor names
    foreach ($auditTrail as &$entry) {
        // Try to get user name from database
        $userStmt = $pdo->prepare("SELECT display_name AS UserName FROM `ngn_2025`.`users` WHERE id = ? LIMIT 1");
        $userStmt->execute([$entry['actor_user_id']]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        $entry['actor_name'] = $user['UserName'] ?? 'Unknown User';
    }

    // Get audit summary
    $summary = $auditService->getAuditSummary($sirId);

    echo json_encode([
        'success' => true,
        'data' => [
            'sir_id' => $sirId,
            'sir_number' => $sir['sir_number'],
            'summary' => $summary,
            'audit_trail' => $auditTrail,
            'total_entries' => count($auditTrail),
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage(),
    ]);
}
