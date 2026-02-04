<?php

/**
 * SIR Detail Endpoint
 * Chapter 31 - Individual SIR Management
 *
 * GET  /api/v1/governance/sir/{id}  - Get SIR details
 * PATCH /api/v1/governance/sir/{id}/status - Update SIR status
 *
 * Authentication: Bearer JWT token (Chairman or assigned Director only)
 */

require_once __DIR__ . '/../../../../vendor/autoload.php';

header('Content-Type: application/json');

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;
use NGN\Lib\Governance\SirRegistryService;
use NGN\Lib\Governance\DirectorateRoles;

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

    // Verify authentication
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($authHeader) || strpos($authHeader, 'Bearer ') !== 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Missing or invalid authentication']);
        exit;
    }

    // Get user ID (from X-User-ID header for testing)
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
    $actionPart = $pathParts[$sirIdIndex + 2] ?? null;

    // Get SIR
    $sir = $sirService->getSir($sirId);
    if (!$sir) {
        http_response_code(404);
        echo json_encode(['error' => 'SIR not found']);
        exit;
    }

    // Verify access control: Only Chairman or assigned Director can view/modify
    $isChairman = $roles->isChairman($userId);
    $isAssignedDirector = ($userId === $sir['director_user_id']);

    if (!$isChairman && !$isAssignedDirector) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];

    // ========================================
    // GET - Get SIR details
    // ========================================
    if ($method === 'GET') {
        echo json_encode([
            'success' => true,
            'data' => [
                'sir_id' => $sir['id'],
                'sir_number' => $sir['sir_number'],
                'objective' => $sir['objective'],
                'context' => $sir['context'],
                'deliverable' => $sir['deliverable'],
                'threshold' => $sir['threshold'],
                'threshold_date' => $sir['threshold_date'],
                'assigned_to_director' => $sir['assigned_to_director'],
                'director_name' => $roles->getDirectorName($sir['assigned_to_director']),
                'registry_division' => $sir['registry_division'],
                'registry_name' => ucwords(str_replace('_', ' ', $sir['registry_division'])),
                'status' => $sir['status'],
                'priority' => $sir['priority'],
                'issued_at' => $sir['issued_at'],
                'claimed_at' => $sir['claimed_at'],
                'rant_started_at' => $sir['rant_started_at'],
                'verified_at' => $sir['verified_at'],
                'closed_at' => $sir['closed_at'],
                'days_open' => $sir['days_open'],
                'days_until_threshold' => $sir['days_until_threshold'] ?? null,
                'is_overdue' => $sir['days_open'] > 14 && in_array($sir['status'], ['open', 'in_review']),
                'notes' => $sir['notes'],
                'metadata' => $sir['metadata'],
            ],
        ]);
        exit;
    }

    // ========================================
    // PATCH - Update status
    // ========================================
    if ($method === 'PATCH' && $actionPart === 'status') {
        // Parse request body
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || empty($input['new_status'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing new_status field']);
            exit;
        }

        $newStatus = strtolower($input['new_status']);
        $oldStatus = $sir['status'];

        // Determine actor role
        $actorRole = $isChairman ? 'chairman' : 'director';

        // Update status
        $sirService->updateStatus($sirId, $newStatus, $userId, $actorRole);

        // Get updated SIR
        $updatedSir = $sirService->getSir($sirId);

        echo json_encode([
            'success' => true,
            'message' => "SIR status updated to {$newStatus}",
            'data' => [
                'sir_id' => $updatedSir['id'],
                'old_status' => $oldStatus,
                'new_status' => $updatedSir['status'],
                'verified_at' => $updatedSir['verified_at'],
                'closed_at' => $updatedSir['closed_at'],
            ],
        ]);
        exit;
    }

    // Method not allowed
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage(),
    ]);
}
