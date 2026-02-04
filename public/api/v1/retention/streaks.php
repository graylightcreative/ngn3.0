<?php

/**
 * API Endpoint: GET /api/v1/retention/streaks
 * API Endpoint: POST /api/v1/retention/streaks/checkin
 *
 * Returns user streaks and allows check-in
 * Requires authentication
 */

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../../lib/bootstrap.php';

    use NGN\Lib\Retention\RetentionService;
    use NGN\Lib\Http\ConnectionFactory;
    use NGN\Lib\Config;

    // Check authentication
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    // Initialize service
    $config = Config::getInstance();
    $pdo = ConnectionFactory::write($config);
    $retentionService = new RetentionService($pdo);

    // Handle different HTTP methods
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    switch ($method) {
        case 'GET':
            // Get user's streak data
            $streakData = $retentionService->getUserStreak($userId);

            http_response_code(200);
            echo json_encode($streakData);
            break;

        case 'POST':
            // Record check-in
            if (strpos($path, '/checkin') === false) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid endpoint']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $streakType = $data['streak_type'] ?? 'listening';

            $validTypes = ['listening', 'engagement', 'login'];
            if (!in_array($streakType, $validTypes)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid streak type']);
                exit;
            }

            // Record check-in
            $updatedStreak = $retentionService->recordCheckIn($userId, $streakType);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Check-in recorded',
                'streak' => $updatedStreak
            ]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    error_log("Error in /api/v1/retention/streaks: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
