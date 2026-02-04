<?php

namespace NGN\Lib\Retention;

use PDO;
use Exception;
use DateTime;
use DateTimeZone;

/**
 * Chart Drop Service
 *
 * Manages Monday 06:00 UTC "Chart Drop" weekly reveal events
 * Progressive rank reveal (100→1) with live viewer tracking
 */
class ChartDropService
{
    private PDO $pdo;
    private PushNotificationService $pushService;

    // Reveal configuration
    private const REVEAL_START_HOUR = 6;      // 06:00 UTC
    private const REVEAL_START_MINUTE = 0;
    private const REVEAL_DURATION_MINUTES = 100;  // ~1 rank per minute
    private const RANKS_TO_REVEAL = 100;

    public function __construct(PDO $pdo, PushNotificationService $pushService)
    {
        $this->pdo = $pdo;
        $this->pushService = $pushService;
    }

    /**
     * Schedule Chart Drop event for next Monday
     *
     * @param string $dateStr Date string (YYYY-MM-DD) for Monday
     * @return bool Success
     * @throws Exception
     */
    public function scheduleChartDrop(string $dateStr): bool
    {
        try {
            // Validate date is a Monday
            $date = new DateTime($dateStr, new DateTimeZone('UTC'));
            if ($date->format('N') != 1) {
                throw new Exception("Date must be a Monday (ISO 8601 format)");
            }

            // Set exact reveal time (Monday 06:00 UTC)
            $eventDateTime = $date->format('Y-m-d') . ' ' . sprintf('%02d:%02d:00', self::REVEAL_START_HOUR, self::REVEAL_START_MINUTE);

            $stmt = $this->pdo->prepare("
                SELECT id FROM `ngn_2025`.`chart_drop_events`
                WHERE event_date = :date
            ");
            $stmt->execute([':date' => $dateStr]);

            if ($stmt->fetch()) {
                // Event already exists
                return false;
            }

            // Create new Chart Drop event
            $stmt = $this->pdo->prepare("
                INSERT INTO `ngn_2025`.`chart_drop_events` (
                    event_date, event_datetime, status,
                    created_at, updated_at
                ) VALUES (
                    :date, :datetime, 'scheduled',
                    NOW(), NOW()
                )
            ");

            $stmt->execute([
                ':date' => $dateStr,
                ':datetime' => $eventDateTime
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Error scheduling chart drop: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send Sunday teaser notification
     *
     * Teases the Chart Drop happening Monday morning
     *
     * @param string $dateStr Monday date (YYYY-MM-DD)
     * @return int Number of notifications sent
     * @throws Exception
     */
    public function sendSundayTease(string $dateStr): int
    {
        try {
            // Validate date is a Monday
            $date = new DateTime($dateStr, new DateTimeZone('UTC'));
            if ($date->format('N') != 1) {
                throw new Exception("Date must be a Monday");
            }

            // Get chart drop event
            $stmt = $this->pdo->prepare("
                SELECT id FROM `ngn_2025`.`chart_drop_events`
                WHERE event_date = :date
            ");
            $stmt->execute([':date' => $dateStr]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$event) {
                throw new Exception("Chart Drop event not found for date: {$dateStr}");
            }

            // Get all active users
            $stmt = $this->pdo->prepare("
                SELECT id FROM `ngn_2025`.`users`
                WHERE status = 'active'
                LIMIT 10000
            ");
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Send teaser to all users
            $title = "🎵 Chart Drop Tomorrow!";
            $body = "Get ready for the weekly chart reveal tomorrow at 6 AM UTC. Are you ready to see where you rank?";

            foreach ($users as $user) {
                $this->pushService->sendPush(
                    (int)$user['id'],
                    $title,
                    $body,
                    ['deep_link' => '/chart-drop'],
                    6
                );
            }

            // Mark teaser as sent
            $stmt = $this->pdo->prepare("
                UPDATE `ngn_2025`.`chart_drop_events`
                SET sunday_tease_sent = 1
                WHERE id = :id
            ");
            $stmt->execute([':id' => $event['id']]);

            return count($users);
        } catch (Exception $e) {
            error_log("Error sending Sunday teaser: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Start progressive reveal for Chart Drop event
     *
     * Begins the 100-minute reveal (rank 100→1)
     *
     * @param string $dateStr Monday date (YYYY-MM-DD)
     * @return bool Success
     * @throws Exception
     */
    public function startProgressiveReveal(string $dateStr): bool
    {
        try {
            // Get chart drop event
            $stmt = $this->pdo->prepare("
                SELECT id, status FROM `ngn_2025`.`chart_drop_events`
                WHERE event_date = :date
            ");
            $stmt->execute([':date' => $dateStr]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$event) {
                throw new Exception("Chart Drop event not found");
            }

            if ($event['status'] !== 'scheduled') {
                throw new Exception("Chart Drop event is already in progress or completed");
            }

            // Update event status
            $stmt = $this->pdo->prepare("
                UPDATE `ngn_2025`.`chart_drop_events`
                SET status = 'revealing',
                    current_rank_revealed = 100,
                    reveal_started_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $event['id']]);

            return true;
        } catch (Exception $e) {
            error_log("Error starting progressive reveal: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reveal next rank in Chart Drop sequence
     *
     * Called every minute to reveal ranks 100→1
     *
     * @param string $dateStr Monday date (YYYY-MM-DD)
     * @return ?array Revealed rank data
     * @throws Exception
     */
    public function revealNextRank(string $dateStr): ?array
    {
        try {
            // Get chart drop event
            $stmt = $this->pdo->prepare("
                SELECT id, status, current_rank_revealed
                FROM `ngn_2025`.`chart_drop_events`
                WHERE event_date = :date
            ");
            $stmt->execute([':date' => $dateStr]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$event) {
                throw new Exception("Chart Drop event not found");
            }

            if ($event['status'] !== 'revealing') {
                return null;
            }

            $currentRankRevealed = (int)$event['current_rank_revealed'];
            $nextRank = $currentRankRevealed - 1;

            // Get artist at this rank from leaderboard
            $artistRankStmt = $this->pdo->prepare("
                SELECT a.id, a.name AS display_name, g.genre_name AS genre
                FROM `ngn_rankings_2025`.`ranking_items` ri
                JOIN `ngn_2025`.`artists` a ON ri.entity_id = a.id
                LEFT JOIN `ngn_2025`.`genres` g ON a.primary_genre = g.genre_slug
                WHERE ri.rank = :rank AND ri.entity_type = 'artist'
                AND ri.window_id IN (SELECT id FROM `ngn_rankings_2025`.`ranking_windows` WHERE event_date = :date AND interval = 'weekly')
                LIMIT 1
            ");
            $artistRankStmt->execute([':rank' => $nextRank, ':date' => $dateStr]);
            $artistAtRank = $artistRankStmt->fetch(PDO::FETCH_ASSOC);

            if (!$artistAtRank) {
                $artistAtRank = [
                    'id' => 0,
                    'display_name' => 'Unknown Artist',
                    'genre' => 'Unknown'
                ];
            }

            // Update event with newly revealed rank
            $stmt = $this->pdo->prepare("
                UPDATE `ngn_2025`.`chart_drop_events`
                SET current_rank_revealed = :next_rank,
                    notification_count = notification_count + 1
                WHERE id = :id
            ");
            $stmt->execute([
                ':next_rank' => $nextRank,
                ':id' => $event['id']
            ]);

            // Send notification to artist at this rank
            if ($artistAtRank['id'] > 0) {
                $this->pushService->sendChartDropAlert($artistAtRank['id'], $nextRank);
            }

            // Check if reveal is complete
            if ($nextRank <= 1) {
                $stmt = $this->pdo->prepare("
                                    UPDATE `ngn_2025`.`chart_drop_events`
                                    SET status = 'completed',
                                        reveal_completed_at = NOW()
                                    WHERE id = :id
                                ");                $stmt->execute([':id' => $event['id']]);
            }

            return [
                'rank' => $nextRank,
                'artist_id' => $artistAtRank['id'],
                'artist_name' => $artistAtRank['display_name'],
                'genre' => $artistAtRank['genre'],
                'reveal_progress' => (self::RANKS_TO_REVEAL - $nextRank) / self::RANKS_TO_REVEAL * 100
            ];
        } catch (Exception $e) {
            error_log("Error revealing next rank: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get current Chart Drop event status
     *
     * @param string $dateStr Monday date (YYYY-MM-DD)
     * @return array Chart Drop status
     * @throws Exception
     */
    public function getChartDropStatus(string $dateStr): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, event_date, event_datetime, status,
                       current_rank_revealed, reveal_started_at, reveal_completed_at,
                       sunday_tease_sent, notification_count,
                       live_viewers_count, total_views
                FROM `ngn_2025`.`chart_drop_events`
                WHERE event_date = :date
            ");
            $stmt->execute([':date' => $dateStr]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$event) {
                throw new Exception("Chart Drop event not found");
            }

            $revealProgress = 0;
            if ($event['current_rank_revealed']) {
                $revealProgress = ((self::RANKS_TO_REVEAL - (int)$event['current_rank_revealed']) / self::RANKS_TO_REVEAL) * 100;
            }

            // Calculate next reveal time
            $nextRevealAt = null;
            if ($event['status'] === 'revealing' && $event['current_rank_revealed']) {
                $startTime = new DateTime($event['reveal_started_at'], new DateTimeZone('UTC'));
                $ranksSoFar = self::RANKS_TO_REVEAL - (int)$event['current_rank_revealed'];
                $nextRevealAt = $startTime->modify("+{$ranksSoFar} minutes")->format('c');
            }

            return [
                'event_id' => (int)$event['id'],
                'event_date' => $event['event_date'],
                'event_datetime' => $event['event_datetime'],
                'status' => $event['status'],
                'current_rank_revealed' => (int)$event['current_rank_revealed'],
                'next_reveal_at' => $nextRevealAt,
                'reveal_progress_percent' => round($revealProgress, 1),
                'live_viewers' => (int)$event['live_viewers_count'],
                'total_views' => (int)$event['total_views'],
                'notifications_sent' => (int)$event['notification_count']
            ];
        } catch (Exception $e) {
            error_log("Error getting chart drop status: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Track live viewer for Chart Drop
     *
     * @param int $userId User ID
     * @param string $dateStr Monday date (YYYY-MM-DD)
     * @return bool Success
     * @throws Exception
     */
    public function trackLiveViewer(int $userId, string $dateStr): bool
    {
        try {
            // Get chart drop event
            $stmt = $this->pdo->prepare("
                SELECT id FROM `ngn_2025`.`chart_drop_events`
                WHERE event_date = :date AND status = 'revealing'
            ");
            $stmt->execute([':date' => $dateStr]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$event) {
                return false;
            }

            // TODO: Implement live viewer tracking
            // Could use Redis for real-time counts or a viewing session table
            // For now, this is a placeholder

            return true;
        } catch (Exception $e) {
            error_log("Error tracking live viewer: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get top movers for teaser content
     *
     * Identifies artists with biggest rank movements
     *
     * @param string $dateStr Monday date (YYYY-MM-DD)
     * @param int $limit Number of top movers to return
     * @return array Top movers
     * @throws Exception
     */
    public function identifyTopMovers(string $dateStr, int $limit = 5): array
    {
        try {
            // TODO: Compare leaderboard rankings from previous week
            // Calculate biggest climbers and biggest fallers
            // Return top movers for teaser content

            return [];
        } catch (Exception $e) {
            error_log("Error identifying top movers: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get latest Chart Drop event
     *
     * @return ?array Latest event or null
     * @throws Exception
     */
    public function getLatestChartDrop(): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, event_date, event_datetime, status
                FROM `ngn_2025`.`chart_drop_events`
                ORDER BY event_date DESC
                LIMIT 1
            ");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            error_log("Error getting latest chart drop: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if Chart Drop event is currently active
     *
     * @return bool True if reveal is in progress
     * @throws Exception
     */
    public function isChartDropActive(): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM `ngn_2025`.`chart_drop_events`
                WHERE status = 'revealing'
                  AND reveal_started_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)
                LIMIT 1
            ");
            $stmt->execute();
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            error_log("Error checking chart drop active: " . $e->getMessage());
            return false;
        }
    }
}
