<?php

/**
 * Cron Job: Detect Rivalries
 *
 * Detects genre rival overtakes and sends alerts
 * Schedule: 0 8 * * * (daily at 8 AM UTC)
 *
 * Checks for rank movements and triggers competitive engagement hooks
 */

require_once __DIR__ . '/../../lib/bootstrap.php';

use NGN\Lib\Retention\RivalryDetectionService;
use NGN\Lib\Retention\PushNotificationService;
use NGN\Lib\Http\ConnectionFactory;
use NGN\Lib\Config;

try {
    // Initialize services
    $config = Config::getInstance();
    $pdo = ConnectionFactory::write($config);

    $pushService = new PushNotificationService($pdo);
    $rivalryService = new RivalryDetectionService($pdo, $pushService);

    error_log("=== Detect Rivalries Job Started ===");

    // Get all users who should have rivalry detection
    $users = $rivalryService->getUsersForRivalryDetection(1000);

    $overtakesDetected = 0;
    $alertsSent = 0;

    foreach ($users as $user) {
        try {
            $userId = (int)$user['id'];

            // Check for overtakes in all genres
            $overtakes = $rivalryService->checkForOvertakes($userId);

            if (!empty($overtakes)) {
                $overtakesDetected += count($overtakes);

                foreach ($overtakes as $overtake) {
                    // Alert already sent in checkForOvertakes if needed
                    $alertsSent++;
                }
            }
        } catch (Exception $e) {
            error_log("Error processing rivalries for user {$user['id']}: " . $e->getMessage());
        }
    }

    // Detect new rivalries for active artists
    $detectNewRivalries = true;

    if ($detectNewRivalries) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.display_name
            FROM `ngn_2025`.`users` u
            WHERE u.status = 'active'
              AND (u.role_id = 3 OR u.role_id = 7) -- Assuming RoleId 3 = Artist, 7 = Label
            LIMIT 500
        ");
        $stmt->execute();
        $artistsForDetection = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $newRivalriesDetected = 0;

        foreach ($artistsForDetection as $artist) {
            try {
                $userId = (int)$artist['id'];

                // TODO: Get artist's genres from profile
                $genres = ['electronic', 'hip-hop', 'pop']; // Placeholder

                foreach ($genres as $genre) {
                    try {
                        $rivals = $rivalryService->detectGenreRivals($userId, $genre, 5);
                        if (!empty($rivals)) {
                            $newRivalriesDetected += count($rivals);
                        }
                    } catch (Exception $e) {
                        error_log("Error detecting rivals for user {$userId} in genre {$genre}: " . $e->getMessage());
                    }
                }
            } catch (Exception $e) {
                error_log("Error processing artist {$artist['id']}: " . $e->getMessage());
            }
        }

        error_log("New rivalries detected: {$newRivalriesDetected}");
    }

    error_log("=== Detect Rivalries Job Completed ===");
    error_log("Overtakes detected: {$overtakesDetected}");
    error_log("Alerts sent: {$alertsSent}");

} catch (Exception $e) {
    error_log("Critical error in detect_rivalries job: " . $e->getMessage());
    exit(1);
}
