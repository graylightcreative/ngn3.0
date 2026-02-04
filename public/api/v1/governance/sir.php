<?php

/**
 * Directorate SIR Registry API
 * Chapter 31 - Governance Endpoints
 *
 * POST /api/v1/governance/sir - Create new SIR (Chairman only)
 * GET  /api/v1/governance/sir - List SIRs (with filters)
 *
 * Authentication: Bearer JWT token + specific role requirements
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

    // Extract token (basic validation - real auth would be JWT verification)
    $token = substr($authHeader, 7);

    $method = $_SERVER['REQUEST_METHOD'];

    // ========================================
    // POST - Create new SIR
    // ========================================
    if ($method === 'POST') {
        // Only Chairman can create SIRs
        $chairmanUserId = $roles->getChairmanUserId();

        // In production, verify JWT token and get actual user ID
        // For now, require X-User-ID header for testing
        $userId = (int)($_SERVER['HTTP_X_USER_ID'] ?? 1);

        if ($userId !== $chairmanUserId) {
            http_response_code(403);
            echo json_encode(['error' => 'Only Chairman can create SIRs']);
            exit;
        }

        // Parse request body
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON body']);
            exit;
        }

        // Validate required fields
        $required = ['objective', 'context', 'deliverable', 'assigned_to_director'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Missing required field: {$field}"]);
                exit;
            }
        }

        // Create SIR
        $sirData = [
            'objective' => trim($input['objective']),
            'context' => trim($input['context']),
            'deliverable' => trim($input['deliverable']),
            'threshold' => $input['threshold'] ?? null,
            'threshold_date' => $input['threshold_date'] ?? null,
            'assigned_to_director' => strtolower($input['assigned_to_director']),
            'priority' => $input['priority'] ?? 'medium',
            'issued_by_user_id' => $userId,
            'notes' => $input['notes'] ?? null,
            'metadata' => $input['metadata'] ?? null,
        ];

        $sirId = $sirService->createSir($sirData);

        // Get created SIR
        $sir = $sirService->getSir($sirId);
        $directorName = $roles->getDirectorName($sir['assigned_to_director']);

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'data' => [
                'sir_id' => $sir['id'],
                'sir_number' => $sir['sir_number'],
                'status' => $sir['status'],
                'assigned_to' => $directorName,
                'notification_sent' => $sir['notification_sent'],
            ],
        ]);
        exit;
    }

    // ========================================
    // GET - List SIRs with filters
    // ========================================
    if ($method === 'GET') {
        // Parse query parameters
        $filters = [
            'status' => $_GET['status'] ?? null,
            'director' => $_GET['director'] ?? null,
            'priority' => $_GET['priority'] ?? null,
            'registry' => $_GET['registry'] ?? null,
            'overdue' => isset($_GET['overdue']) ? (bool)$_GET['overdue'] : false,
            'limit' => min((int)($_GET['limit'] ?? 50), 100),
            'offset' => (int)($_GET['offset'] ?? 0),
        ];

        // Remove null filters
        $filters = array_filter($filters, fn($v) => $v !== null);

        // Get SIRs
        $result = $sirService->listSirs($filters);

        // Enhance with director names
        foreach ($result['sirs'] as &$sir) {
            $sir['director_name'] = $roles->getDirectorName($sir['assigned_to_director']);
            $sir['registry_name'] = ucwords(str_replace('_', ' ', $sir['registry_division']));
        }

        echo json_encode([
            'success' => true,
            'data' => $result,
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
