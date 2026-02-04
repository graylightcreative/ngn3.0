<?php

namespace NGN\Lib\Retention;

use PDO;
use Exception;

/**
 * Rivalry Detection Service
 *
 * Detects genre rivals and tracks rank overtakes
 * Triggers competitive engagement hooks (Bible Ch. 23.2)
 */
class RivalryDetectionService
{
    private PDO $pdo;
    private PushNotificationService $pushService;

    // Rivalry detection configuration
    private const RIVAL_SEARCH_RANGE = 5;  // Artists within ±5 ranks are rivals
    private const MIN_RIVALS_TO_TRACK = 3;
    private const MAX_RIVALS_TO_TRACK = 10;

    public function __construct(PDO $pdo, PushNotificationService $pushService)
    {
        $this->pdo = $pdo;
        $this->pushService = $pushService;
    }

    /**
     * Detect genre rivals for an artist
     *
     * Finds artists in same genre within ±5 rank range
     *
     * @param int $userId Artist user ID
     * @param string $genreSlug Genre slug
     * @param int $limit Maximum rivals to return
     * @return array Detected rivals
     * @throws Exception
     */
    public function detectGenreRivals(int $userId, string $genreSlug, int $limit = 5): array
    {
        try {
            // Get user's current rank in genre
            $userRankStmt = $this->pdo->prepare("
                SELECT nri.rank
                FROM `ngn_rankings_2025`.`ranking_items` nri
                JOIN `ngn_rankings_2025`.`ranking_windows` nrh ON nri.window_id = nrh.id
                WHERE nri.entity_id = :user_id AND nri.entity_type = 'artist'
                  AND nrh.interval = 'weekly' AND nri.genre_slug = :genre_slug
                ORDER BY nrh.window_end DESC LIMIT 1
            ");
            $userRankStmt->execute([':user_id' => $userId, ':genre_slug' => $genreSlug]);
            $userCurrentRank = (int)($userRankStmt->fetchColumn() ?: 0);

            if ($userCurrentRank === 0) {
                return []; // User not ranked in this genre
            }

            // Find artists within rank range
            $minRank = max(1, $userCurrentRank - self::RIVAL_SEARCH_RANGE);
            $maxRank = $userCurrentRank + self::RIVAL_SEARCH_RANGE;

            $rivalsStmt = $this->pdo->prepare("
                SELECT nri.entity_id AS id, nri.rank, a.name AS display_name
                FROM `ngn_rankings_2025`.`ranking_items` nri
                JOIN `ngn_2025`.`artists` a ON nri.entity_id = a.id
                JOIN `ngn_rankings_2025`.`ranking_windows` nrh ON nri.window_id = nrh.id
                WHERE nri.entity_type = 'artist' AND nri.genre_slug = :genre_slug
                  AND nrh.interval = 'weekly' AND nri.rank BETWEEN :min_rank AND :max_rank
                  AND nri.entity_id != :user_id
                ORDER BY ABS(nri.rank - :user_current_rank) ASC, nri.rank ASC
                LIMIT :limit
            ");
            $rivalsStmt->bindValue(':genre_slug', $genreSlug, PDO::PARAM_STR);
            $rivalsStmt->bindValue(':min_rank', $minRank, PDO::PARAM_INT);
            $rivalsStmt->bindValue(':max_rank', $maxRank, PDO::PARAM_INT);
            $rivalsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $rivalsStmt->bindValue(':user_current_rank', $userCurrentRank, PDO::PARAM_INT);
            $rivalsStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $rivalsStmt->execute();
            $rivals = $rivalsStmt->fetchAll(PDO::FETCH_ASSOC);

            // For each rival, create/update user_rivalries record
            foreach ($rivals as $rival) {
                $this->createOrUpdateRivalry($userId, (int)$rival['id'], $genreSlug, $userCurrentRank, (int)$rival['rank']);
            }

            return $rivals;
        } catch (Exception $e) {
            LoggerFactory::getLogger('retention')->error("Error detecting genre rivals: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check for overtakes (rank changes)
     *
     * Compares current rankings with historical data
     * Triggers alerts when rival passes user
     *
     * @param int $userId User ID
     * @return array Array of overtakes
     * @throws Exception
     */
    public function checkForOvertakes(int $userId): array
    {
        $overtakes = [];

        try {
            // Get user's current rivalries
            $stmt = $this->pdo->prepare("
                SELECT id, user_id, rival_user_id, genre_slug,
                       user_current_rank, rival_current_rank,
                       alert_frequency, last_alert_sent_at
                FROM `ngn_2025`.`user_rivalries`
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $userId]);
            $rivalries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rivalries as $rivalry) {
                // TODO: Get current ranks from LeaderboardCalculator
                $currentUserRank = (int)$rivalry['user_current_rank'];
                $currentRivalRank = (int)$rivalry['rival_current_rank'];

                // Check if rival overtook user
                if ($currentRivalRank < $currentUserRank) {
                    // Rival passed user!
                    $overtakes[] = [
                        'rivalry_id' => $rivalry['id'],
                        'rival_user_id' => (int)$rivalry['rival_user_id'],
                        'genre_slug' => $rivalry['genre_slug'],
                        'user_rank' => $currentUserRank,
                        'rival_rank' => $currentRivalRank
                    ];

                    // Update overtake count and trigger alert if needed
                    if ($this->shouldSendAlert($rivalry, $currentUserRank, $currentRivalRank)) {
                        $this->pushService->sendRivalryAlert(
                            (int)$rivalry['user_id'],
                            (int)$rivalry['rival_user_id'],
                            $rivalry['genre_slug']
                        );

                        // Update rivalry record
                        $stmt = $this->pdo->prepare("
                            UPDATE `ngn_2025`.`user_rivalries`
                            SET overtake_count = overtake_count + 1,
                                last_overtake_at = NOW(),
                                last_alert_sent_at = NOW(),
                                rival_current_rank = :rank,
                                updated_at = NOW()
                            WHERE id = :id
                        ");
                        $stmt->execute([
                            ':rank' => $currentRivalRank,
                            ':id' => $rivalry['id']
                        ]);
                    }
                }
            }

            return $overtakes;
        } catch (Exception $e) {
            error_log("Error checking for overtakes: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create or update a rivalry record
     *
     * @param int $userId User ID (artist being tracked)
     * @param int $rivalUserId Rival user ID
     * @param string $genreSlug Genre they compete in
     * @return bool Success
     * @throws Exception
     */
    private function createOrUpdateRivalry(int $userId, int $rivalUserId, string $genreSlug, int $userCurrentRank = 0, int $rivalCurrentRank = 0): bool
    {
        try {
            // Check if rivalry already exists
            $stmt = $this->pdo->prepare("
                SELECT id FROM `ngn_2025`.`user_rivalries`
                WHERE user_id = :user_id
                  AND rival_user_id = :rival_id
                  AND genre_slug = :genre
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':rival_id' => $rivalUserId,
                ':genre' => $genreSlug
            ]);

            if ($stmt->fetch()) {
                // Rivalry already exists
                return false;
            }

            // Create new rivalry record
            $stmt = $this->pdo->prepare("
                INSERT INTO `ngn_2025`.`user_rivalries` (
                    user_id, rival_user_id, genre_slug,
                    rivalry_type, alert_frequency,
                    user_current_rank, rival_current_rank,
                    created_at, updated_at
                ) VALUES (
                    :user_id, :rival_id, :genre,
                    'genre_rank', 'daily',
                    :user_current_rank, :rival_current_rank,
                    NOW(), NOW()
                )
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':rival_id' => $rivalUserId,
                ':genre' => $genreSlug,
                ':user_current_rank' => $userCurrentRank,
                ':rival_current_rank' => $rivalCurrentRank
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Error creating rivalry: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Determine if alert should be sent based on frequency
     *
     * @param array $rivalry Rivalry record
     * @param int $userRank Current user rank
     * @param int $rivalRank Current rival rank
     * @return bool Should send alert
     */
    private function shouldSendAlert(array $rivalry, int $userRank, int $rivalRank): bool
    {
        $alertFrequency = $rivalry['alert_frequency'];
        $lastAlertSentAt = $rivalry['last_alert_sent_at'];

        switch ($alertFrequency) {
            case 'always':
                return true;

            case 'daily':
                // Only send once per day
                if (!$lastAlertSentAt) {
                    return true;
                }
                $lastAlertTime = strtotime($lastAlertSentAt);
                $timeSinceLastAlert = time() - $lastAlertTime;
                return $timeSinceLastAlert > (24 * 3600);

            case 'weekly':
                // Only send once per week
                if (!$lastAlertSentAt) {
                    return true;
                }
                $lastAlertTime = strtotime($lastAlertSentAt);
                $timeSinceLastAlert = time() - $lastAlertTime;
                return $timeSinceLastAlert > (7 * 24 * 3600);

            case 'never':
                return false;

            default:
                return false;
        }
    }

    /**
     * Get user's rivalries in a genre
     *
     * @param int $userId User ID
     * @param string $genreSlug Genre slug
     * @return array Rivalries for genre
     * @throws Exception
     */
    public function getUserRivalries(int $userId, string $genreSlug): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT ur.id, ur.rival_user_id, ur.user_current_rank,
                       ur.rival_current_rank, ur.overtake_count,
                       ur.last_overtake_at, ur.alert_frequency,
                       u.display_name, u.avatar_url
                FROM `ngn_2025`.`user_rivalries` ur
                JOIN `ngn_2025`.`users` u ON ur.rival_user_id = u.id
                WHERE ur.user_id = :user_id
                  AND ur.genre_slug = :genre
                ORDER BY ur.rival_current_rank ASC
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':genre' => $genreSlug
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Error getting user rivalries: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate rivalry alert message
     *
     * @param int $userId User ID
     * @param int $rivalUserId Rival user ID
     * @param string $genreSlug Genre
     * @return string Alert message
     * @throws Exception
     */
    public function generateRivalryAlert(int $userId, int $rivalUserId, string $genreSlug): string
    {
        try {
            // Get rival's name
            $stmt = $this->pdo->prepare("
                SELECT display_name FROM `ngn_2025`.`users` WHERE id = :user_id
            ");
            $stmt->execute([':user_id' => $rivalUserId]);
            $rival = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rival) {
                return "A rival just passed you in the {$genreSlug} chart";
            }

            return "{$rival['display_name']} just jumped you in the {$genreSlug} chart. Want to drop a post to take back your spot?";
        } catch (Exception $e) {
            error_log("Error generating rivalry alert: " . $e->getMessage());
            return "A rival just passed you in the {$genreSlug} chart";
        }
    }

    /**
     * Get top rivals across all genres for a user
     *
     * @param int $userId User ID
     * @param int $limit Max rivals to return
     * @return array Top rivals
     * @throws Exception
     */
    public function getTopRivals(int $userId, int $limit = 5): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT ur.id, ur.rival_user_id, ur.genre_slug,
                       ur.user_current_rank, ur.rival_current_rank,
                       ur.overtake_count, u.display_name,
                       u.avatar_url
                FROM `ngn_2025`.`user_rivalries` ur
                JOIN `ngn_2025`.`users` u ON ur.rival_user_id = u.id
                WHERE ur.user_id = :user_id
                ORDER BY ur.overtake_count DESC, ur.last_overtake_at DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Error getting top rivals: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update alert frequency for rivalry
     *
     * @param int $rivalryId Rivalry ID
     * @param string $frequency Frequency (always, daily, weekly, never)
     * @return bool Success
     * @throws Exception
     */
    public function updateAlertFrequency(int $rivalryId, string $frequency): bool
    {
        try {
            $validFrequencies = ['always', 'daily', 'weekly', 'never'];
            if (!in_array($frequency, $validFrequencies)) {
                throw new Exception("Invalid alert frequency: {$frequency}");
            }

            $stmt = $this->pdo->prepare("
                UPDATE `ngn_2025`.`user_rivalries`
                SET alert_frequency = :frequency,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':frequency' => $frequency,
                ':id' => $rivalryId
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Error updating alert frequency: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all users who should have rivals detected
     *
     * Returns active artists/labels with genre affiliations
     *
     * @param int $limit Max users to return
     * @return array Users for rivalry detection
     * @throws Exception
     */
    public function getUsersForRivalryDetection(int $limit = 1000): array
    {
        try {
            // Get all active artists and labels
            $stmt = $this->pdo->prepare("
                SELECT id, display_name
                FROM `ngn_2025`.`users`
                WHERE status = 'active'
                  AND (role_id = 3 OR role_id = 7)
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Error getting users for rivalry detection: " . $e->getMessage());
            throw $e;
        }
    }
}
