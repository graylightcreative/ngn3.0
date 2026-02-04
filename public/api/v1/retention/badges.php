<?php

/**
 * API Endpoint: GET /api/v1/retention/badges
 * API Endpoint: POST /api/v1/retention/badges/unlock (internal)
 *
 * Returns user badges and progress
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
            // Get user's badges and progress
            $userBadges = $retentionService->getUserBadges($userId);

            // Get badge definitions for progress tracking
            $stmt = $pdo->prepare("
                SELECT badge_key, badge_name, requirement_type, requirement_value
                FROM badges
                WHERE is_hidden = 0 OR badge_key IN (
                    SELECT badge_key FROM badges
                    WHERE requirement_type != 'special'
                    LIMIT 20
                )
                ORDER BY rarity DESC, requirement_value ASC
            ");
            $stmt->execute();
            $allBadges = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate progress for each badge
            $progress = [];
            foreach ($allBadges as $badge) {
                // Check if user already has this badge
                $hasIt = array_filter($userBadges, fn($b) => $b['badge_key'] === $badge['badge_key']);

                if (!$hasIt) {
                    $progressPercent = $retentionService->checkBadgeProgress($userId, $badge['badge_key']);
                    if ($progressPercent > 0) {
                        $progress[] = [
                            'badge_key' => $badge['badge_key'],
                            'badge_name' => $badge['badge_name'],
                            'requirement' => $badge['requirement_value'],
                            'progress_percent' => round($progressPercent, 1),
                            'distance' => (int)(100 - $progressPercent)
                        ];
                    }
                }
            }

            http_response_code(200);
            echo json_encode([
                'user_badges' => $userBadges,
                'progress' => $progress,
                'total_badges' => count($userBadges)
            ]);
            break;

        case 'POST':
            // Award badge (admin/system use only)
            if (strpos($path, '/unlock') === false) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid endpoint']);
                exit;
            }

            // TODO: Verify admin privileges or system call

            $data = json_decode(file_get_contents('php://input'), true);
            $badgeKey = $data['badge_key'] ?? null;

            if (!$badgeKey) {
                http_response_code(400);
                echo json_encode(['error' => 'badge_key required']);
                exit;
            }

            $success = $retentionService->awardBadge($userId, $badgeKey);

            if ($success) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Badge unlocked',
                    'badge_key' => $badgeKey
                ]);
            } else {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'Badge already unlocked or not found'
                ]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    error_log("Error in /api/v1/retention/badges: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
