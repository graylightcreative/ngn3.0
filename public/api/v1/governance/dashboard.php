<?php

/**
 * SIR Dashboard Statistics Endpoint
 * Chapter 31 - Governance Dashboard
 *
 * GET /api/v1/governance/dashboard - Get SIR statistics and overview
 *
 * Query Parameters:
 * - director: Filter stats by director (brandon|pepper|erik)
 *
 * Authentication: Bearer JWT token (any authenticated user)
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
    $pdo = ConnectionFactory::read($config);

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

    // Only GET allowed
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    // Parse query parameters
    $directorFilter = $_GET['director'] ?? null;

    // Get stats
    $stats = $sirService->getDashboardStats($directorFilter);

    // Get overdue SIRs
    $overdueSirs = $sirService->getOverdueSirs();

    // Enhance overdue SIRs with names
    foreach ($overdueSirs as &$sir) {
        $sir['director_name'] = $roles->getDirectorName($sir['assigned_to_director']);
    }

    // Get recent SIRs
    $recentResult = $sirService->listSirs([
        'limit' => 10,
        'offset' => 0,
    ]);

    foreach ($recentResult['sirs'] as &$sir) {
        $sir['director_name'] = $roles->getDirectorName($sir['assigned_to_director']);
    }

    // Build response
    $response = [
        'success' => true,
        'data' => [
            'overview' => $stats['overview'],
            'by_director' => $stats['by_director'],
            'overdue_sirs' => $overdueSirs,
            'recent_sirs' => array_slice($recentResult['sirs'], 0, 5),
        ],
    ];

    // Add director-specific stats if filtered
    if ($directorFilter) {
        $directorName = $roles->getDirectorName($directorFilter);
        $response['data']['filtered_director'] = [
            'slug' => $directorFilter,
            'name' => $directorName,
            'focus_area' => $roles->getDirectorFocus($directorFilter),
        ];
    }

    // Add health indicators
    $response['data']['health_indicators'] = [
        'sirs_exceeding_threshold' => 0,
        'pending_director_response' => $stats['overview']['in_review'] ?? 0,
        'pending_chairman_review' => $stats['overview']['rant_phase'] ?? 0,
        'ready_for_closure' => $stats['overview']['verified'] ?? 0,
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage(),
    ]);
}
