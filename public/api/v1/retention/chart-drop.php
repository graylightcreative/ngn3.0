<?php

/**
 * API Endpoint: GET /api/v1/retention/chart-drop/current
 * API Endpoint: GET /api/v1/retention/chart-drop/live (Server-Sent Events)
 *
 * Returns Chart Drop event status and live reveal updates
 * Requires authentication
 */

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../../lib/bootstrap.php';

    use NGN\Lib\Retention\ChartDropService;
    use NGN\Lib\Retention\PushNotificationService;
    use NGN\Lib\Http\ConnectionFactory;
    use NGN\Lib\Config;

    // Check authentication
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    // Initialize services
    $config = Config::getInstance();
    $pdo = ConnectionFactory::write($config);
    $pushService = new PushNotificationService($pdo);
    $chartDropService = new ChartDropService($pdo, $pushService);

    // Handle different HTTP methods
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    switch ($method) {
        case 'GET':
            // Get current Chart Drop event status
            if (strpos($path, '/live') !== false) {
                // SSE endpoint for live updates
                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                header('Connection: keep-alive');

                // Get latest chart drop
                $latestChart = $chartDropService->getLatestChartDrop();

                if (!$latestChart) {
                    echo "data: " . json_encode(['error' => 'No active chart drop event']) . "\n\n";
                    exit;
                }

                $dateStr = $latestChart['event_date'];

                // Track viewer
                try {
                    $chartDropService->trackLiveViewer($userId, $dateStr);
                } catch (Exception $e) {
                    // Ignore tracking errors
                }

                // Send initial status
                $status = $chartDropService->getChartDropStatus($dateStr);
                echo "data: " . json_encode($status) . "\n\n";
                flush();

                // Keep connection alive and send updates
                $lastReveal = $status['current_rank_revealed'];
                for ($i = 0; $i < 30; $i++) {  // Max 30 seconds
                    sleep(1);

                    $status = $chartDropService->getChartDropStatus($dateStr);

                    // Send update if rank changed
                    if ($status['current_rank_revealed'] != $lastReveal) {
                        echo "data: " . json_encode($status) . "\n\n";
                        flush();
                        $lastReveal = $status['current_rank_revealed'];
                    }

                    if ($status['status'] === 'completed') {
                        break;
                    }
                }

                exit;
            } else {
                // Regular JSON endpoint for current status
                $latestChart = $chartDropService->getLatestChartDrop();

                if (!$latestChart) {
                    http_response_code(404);
                    echo json_encode(['error' => 'No chart drop event found']);
                    exit;
                }

                $status = $chartDropService->getChartDropStatus($latestChart['event_date']);

                http_response_code(200);
                echo json_encode($status);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    error_log("Error in /api/v1/retention/chart-drop: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
