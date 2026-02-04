<?php

/**
 * SIR Feedback Endpoint
 * Chapter 31 - Rant Phase Comments
 *
 * POST /api/v1/governance/sir/{id}/feedback - Add feedback
 * GET  /api/v1/governance/sir/{id}/feedback - Get feedback thread
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

    // Get SIR
    $sir = $sirService->getSir($sirId);
    if (!$sir) {
        http_response_code(404);
        echo json_encode(['error' => 'SIR not found']);
        exit;
    }

    // Verify access control
    $isChairman = $roles->isChairman($userId);
    $isAssignedDirector = ($userId === $sir['director_user_id']);

    if (!$isChairman && !$isAssignedDirector) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];

    // ========================================
    // POST - Add feedback
    // ========================================
    if ($method === 'POST') {
        // Parse request body
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || empty($input['feedback_text'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing feedback_text field']);
            exit;
        }

        // Determine author role
        $authorRole = $isChairman ? 'chairman' : 'director';
        $feedbackType = $input['feedback_type'] ?? 'director_comment';

        // Add feedback
        $feedbackId = $sirService->addFeedback(
            $sirId,
            trim($input['feedback_text']),
            $userId,
            $authorRole,
            $feedbackType
        );

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'data' => [
                'feedback_id' => $feedbackId,
                'sir_id' => $sirId,
                'author_role' => $authorRole,
                'created_at' => date('Y-m-d H:i:s'),
            ],
        ]);
        exit;
    }

    // ========================================
    // GET - Get feedback thread
    // ========================================
    if ($method === 'GET') {
        $feedback = $sirService->getFeedback($sirId);

        // Enhance with author names and readable format
        foreach ($feedback as &$item) {
            if ($item['author_role'] === 'director') {
                $item['author_name'] = $roles->getDirectorName($sir['assigned_to_director']);
            } else {
                $item['author_name'] = 'Chairman';
            }
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'sir_id' => $sirId,
                'sir_number' => $sir['sir_number'],
                'feedback_count' => count($feedback),
                'feedback' => $feedback,
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
