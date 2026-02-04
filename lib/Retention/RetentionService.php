<?php

namespace NGN\Lib\Retention;

use PDO;
use Exception;
use DateTime;

/**
 * Retention Service
 *
 * Manages XP, levels, badges, streaks, and engagement loops
 * Implements "Dopamine Facet" for daily engagement (Bible Ch. 23)
 */
class RetentionService
{
    private PDO $pdo;

    // XP Rewards for different actions
    private const XP_REWARDS = [
        'listen' => 1,              // 1 XP per minute
        'like' => 10,
        'comment' => 30,
        'share' => 100,
        'spark' => 1,               // 1 XP per spark sent
        'spark_received' => 15,     // 15 XP per spark received
        'post_created' => 50,
        'daily_checkin' => 25
    ];

    // Level progression formula: xp = 100 * (n^1.5)
    private const BASE_XP_FOR_LEVEL = 100;
    private const LEVEL_EXPONENT = 1.5;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Award XP to a user for an action
     *
     * @param int $userId User ID
     * @param int $xpAmount Amount of XP to award
     * @param string $source Source of XP (listen, engagement, sparks, etc.)
     * @return bool Success
     * @throws Exception
     */
    public function awardXP(int $userId, int $xpAmount, string $source): bool
    {
        if ($xpAmount <= 0) {
            throw new Exception("XP amount must be positive");
        }

        // Valid sources
        $validSources = ['listen', 'engagement', 'sparks', 'social', 'achievements'];
        if (!in_array($source, $validSources)) {
            throw new Exception("Invalid XP source: {$source}");
        }

        try {
            // Ensure user has XP record
            $this->ensureUserXPRecord($userId);

            // Map source to column name
            $sourceColumn = 'xp_from_' . $source;

            // Update total XP and source breakdown
            $stmt = $this->pdo->prepare("
                UPDATE ngn_2025.user_experience_points
                SET total_xp = total_xp + :xp,
                    {$sourceColumn} = {$sourceColumn} + :xp,
                    updated_at = NOW()
                WHERE user_id = :user_id
            ");

            $stmt->execute([
                ':xp' => $xpAmount,
                ':user_id' => $userId
            ]);

            // Check for level up
            $this->checkLevelUp($userId);

            return true;
        } catch (Exception $e) {
            error_log("Error awarding XP: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if user has leveled up and update accordingly
     *
     * @param int $userId User ID
     * @return ?array Level up data if leveled up, null otherwise
     * @throws Exception
     */
    public function checkLevelUp(int $userId): ?array
    {
        try {
            // Get current user XP
            $stmt = $this->pdo->prepare("
                SELECT current_level, total_xp, xp_to_next_level
                FROM ngn_2025.user_experience_points
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $userId]);
            $xpRecord = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$xpRecord) {
                return null;
            }

            $currentLevel = $xpRecord['current_level'];
            $totalXP = $xpRecord['total_xp'];
            $xpToNextLevel = $this->calculateXPForNextLevel($currentLevel);

            // Check if leveled up
            if ($totalXP >= $xpToNextLevel) {
                $newLevel = $currentLevel + 1;
                $newXPRequired = $this->calculateXPForNextLevel($newLevel);

                $stmt = $this->pdo->prepare("
                    UPDATE ngn_2025.user_experience_points
                    SET current_level = :new_level,
                        xp_to_next_level = :xp_required,
                        last_level_up_at = NOW(),
                        total_levels_gained = total_levels_gained + 1,
                        updated_at = NOW()
                    WHERE user_id = :user_id
                ");

                $stmt->execute([
                    ':new_level' => $newLevel,
                    ':xp_required' => $newXPRequired,
                    ':user_id' => $userId
                ]);

                return [
                    'user_id' => $userId,
                    'old_level' => $currentLevel,
                    'new_level' => $newLevel,
                    'xp_required_for_next' => $newXPRequired
                ];
            }

            return null;
        } catch (Exception $e) {
            error_log("Error checking level up: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calculate XP required for a specific level
     *
     * Formula: xp = 100 * (n^1.5)
     *
     * @param int $level Level number
     * @return int XP required
     */
    public function calculateXPForNextLevel(int $level): int
    {
        return (int) (self::BASE_XP_FOR_LEVEL * pow($level, self::LEVEL_EXPONENT));
    }

    /**
     * Get user level and XP data
     *
     * @param int $userId User ID
     * @return array User level data
     * @throws Exception
     */
    public function getUserLevel(int $userId): array
    {
        try {
            $this->ensureUserXPRecord($userId);

            $stmt = $this->pdo->prepare("
                SELECT
                    id, user_id, total_xp, current_level, xp_to_next_level,
                    xp_from_listening, xp_from_engagement, xp_from_sparks,
                    xp_from_social, xp_from_achievements,
                    last_level_up_at, total_levels_gained
                FROM ngn_2025.user_experience_points
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $userId]);
            $xpData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$xpData) {
                throw new Exception("Failed to retrieve XP data");
            }

            // Calculate progress to next level
            $xpForCurrentLevel = $this->calculateXPForNextLevel((int)$xpData['current_level']);
            $xpForPreviousLevel = $xpData['current_level'] > 1
                ? $this->calculateXPForNextLevel((int)$xpData['current_level'] - 1)
                : 0;

            $xpInCurrentLevel = (int)$xpData['total_xp'] - $xpForPreviousLevel;
            $xpNeededForLevel = $xpForCurrentLevel - $xpForPreviousLevel;
            $progressPercent = $xpNeededForLevel > 0 ? ($xpInCurrentLevel / $xpNeededForLevel) * 100 : 0;

            return [
                'user_id' => (int)$xpData['user_id'],
                'total_xp' => (int)$xpData['total_xp'],
                'current_level' => (int)$xpData['current_level'],
                'xp_to_next_level' => (int)$xpData['xp_to_next_level'],
                'xp_progress_percent' => round($progressPercent, 1),
                'xp_breakdown' => [
                    'listening' => (int)$xpData['xp_from_listening'],
                    'engagement' => (int)$xpData['xp_from_engagement'],
                    'sparks' => (int)$xpData['xp_from_sparks'],
                    'social' => (int)$xpData['xp_from_social'],
                    'achievements' => (int)$xpData['xp_from_achievements']
                ],
                'last_level_up_at' => $xpData['last_level_up_at'],
                'total_levels_gained' => (int)$xpData['total_levels_gained']
            ];
        } catch (Exception $e) {
            error_log("Error getting user level: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Award a badge to a user
     *
     * @param int $userId User ID
     * @param string $badgeKey Badge key
     * @return bool Success
     * @throws Exception
     */
    public function awardBadge(int $userId, string $badgeKey): bool
    {
        try {
            // Get badge definition
            $stmt = $this->pdo->prepare("
                SELECT id, xp_reward FROM ngn_2025.badges
                WHERE badge_key = :badge_key
            ");
            $stmt->execute([':badge_key' => $badgeKey]);
            $badge = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$badge) {
                throw new Exception("Badge not found: {$badgeKey}");
            }

            // Check if user already has badge
            $stmt = $this->pdo->prepare("
                SELECT id FROM ngn_2025.user_badges
                WHERE user_id = :user_id AND badge_id = :badge_id
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':badge_id' => $badge['id']
            ]);

            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                // User already has this badge
                return false;
            }

            // Award badge
            $stmt = $this->pdo->prepare("
                INSERT INTO ngn_2025.user_badges (user_id, badge_id, unlocked_at)
                VALUES (:user_id, :badge_id, NOW())
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':badge_id' => $badge['id']
            ]);

            // Award XP for badge unlock
            if ($badge['xp_reward'] > 0) {
                $this->awardXP($userId, (int)$badge['xp_reward'], 'achievements');
            }

            return true;
        } catch (Exception $e) {
            error_log("Error awarding badge: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check badge progress for a user
     *
     * @param int $userId User ID
     * @param string $badgeKey Badge key
     * @return float Progress percent (0-100)
     * @throws Exception
     */
    public function checkBadgeProgress(int $userId, string $badgeKey): float
    {
        try {
            // Get badge definition
            $stmt = $this->pdo->prepare("
                SELECT requirement_type, requirement_value
                FROM ngn_2025.badges
                WHERE badge_key = :badge_key
            ");
            $stmt->execute([':badge_key' => $badgeKey]);
            $badge = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$badge) {
                return 0;
            }

            $requirementType = $badge['requirement_type'];
            $requirementValue = $badge['requirement_value'];

            // Calculate current progress based on requirement type
            $currentValue = 0;

            switch ($requirementType) {
                case 'xp':
                    $xpData = $this->getUserLevel($userId);
                    $currentValue = $xpData['total_xp'];
                    break;

                case 'sparks':
                    // TODO: Integrate with SparkService
                    $currentValue = 0;
                    break;

                case 'engagement':
                    // TODO: Integrate with EngagementService
                    $currentValue = 0;
                    break;

                case 'streak':
                    $streakData = $this->getUserStreak($userId);
                    $currentValue = $streakData['current_streak'] ?? 0;
                    break;

                case 'rank':
                    // TODO: Integrate with LeaderboardCalculator
                    $currentValue = 0;
                    break;

                default:
                    return 0;
            }

            $progress = $requirementValue > 0 ? ($currentValue / $requirementValue) * 100 : 0;
            return min($progress, 100);
        } catch (Exception $e) {
            error_log("Error checking badge progress: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get all badges for a user
     *
     * @param int $userId User ID
     * @return array User badges with details
     * @throws Exception
     */
    public function getUserBadges(int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    b.id, b.badge_key, b.badge_name, b.badge_description,
                    b.badge_icon_url, b.rarity,
                    ub.unlocked_at, ub.clout_value, ub.clout_rank
                FROM ngn_2025.user_badges ub
                JOIN ngn_2025.badges b ON ub.badge_id = b.id
                WHERE ub.user_id = :user_id AND ub.is_displayed = 1
                ORDER BY ub.unlocked_at DESC
            ");
            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Error getting user badges: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Record a user check-in for streak tracking
     *
     * @param int $userId User ID
     * @param string $streakType Type of streak (listening, engagement, login)
     * @return array Updated streak data
     * @throws Exception
     */
    public function recordCheckIn(int $userId, string $streakType = 'listening'): array
    {
        try {
            // Validate streak type to prevent SQL injection
            $validStreakTypes = ['listening', 'engagement', 'login'];
            if (!in_array($streakType, $validStreakTypes)) {
                throw new Exception("Invalid streak type: {$streakType}");
            }

            // Ensure user has streak record
            $this->ensureUserStreakRecord($userId);

            // Get current streak data
            $streakTypeColumn = $streakType . '_streak';
            $stmt = $this->pdo->prepare("
                SELECT current_streak, last_check_in_at, grace_period_active,
                       longest_streak, {$streakTypeColumn}
                FROM ngn_2025.user_streaks
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $userId]);
            $streakData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$streakData) {
                throw new Exception("Streak record not found");
            }

            $now = new DateTime('now', new \DateTimeZone('UTC'));
            $lastCheckIn = new DateTime($streakData['last_check_in_at'], new \DateTimeZone('UTC'));

            // Calculate hours since last check-in
            $hoursSinceLastCheckIn = $lastCheckIn->diff($now)->h + ($lastCheckIn->diff($now)->d * 24);

            $currentStreak = (int)$streakData['current_streak'];
            $newStreak = $currentStreak;
            $gracePeriodActive = (bool)$streakData['grace_period_active'];

            // Check if streak should continue or be broken
            if ($hoursSinceLastCheckIn < 24) {
                // Same day check-in, maintain streak
                $newStreak = $currentStreak;
            } else if ($hoursSinceLastCheckIn < 48) {
                // Within 24-48 hour window
                if ($gracePeriodActive) {
                    // Grace period is active - user is recovering a broken streak
                    // Start a new streak at 1
                    $newStreak = 1;
                } else {
                    // Normal next-day check-in, increment streak
                    $newStreak = $currentStreak + 1;
                }
            } else {
                // More than 48 hours - streak is broken, start fresh at 1
                $newStreak = 1;
            }

            // Update longest streak if necessary
            $longestStreak = max((int)$streakData['longest_streak'], $newStreak);

            // Calculate next check-in deadline (24 hours from now)
            $nextDeadline = $now->modify('+24 hours');

            // Update streak record
            $streakTypeColumn = $streakType . '_streak';
            $stmt = $this->pdo->prepare("
                UPDATE ngn_2025.user_streaks
                SET current_streak = :current_streak,
                    longest_streak = :longest_streak,
                    {$streakTypeColumn} = {$streakTypeColumn} + 1,
                    last_check_in_at = NOW(),
                    next_check_in_deadline = :next_deadline,
                    grace_period_active = 0,
                    updated_at = NOW()
                WHERE user_id = :user_id
            ");
            $stmt->execute([
                ':current_streak' => $newStreak,
                ':longest_streak' => $longestStreak,
                ':next_deadline' => $nextDeadline->format('Y-m-d H:i:s'),
                ':user_id' => $userId
            ]);

            // Award XP for daily check-in
            $this->awardXP($userId, self::XP_REWARDS['daily_checkin'], 'social');

            return $this->getUserStreak($userId);
        } catch (Exception $e) {
            error_log("Error recording check-in: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get user streak data
     *
     * @param int $userId User ID
     * @return array Streak data
     * @throws Exception
     */
    public function getUserStreak(int $userId): array
    {
        try {
            $this->ensureUserStreakRecord($userId);

            $stmt = $this->pdo->prepare("
                SELECT current_streak, longest_streak, last_check_in_at,
                       next_check_in_deadline, listening_streak, engagement_streak,
                       login_streak, grace_period_active, grace_period_expires_at,
                       streak_broken_count, last_broken_at, last_broken_streak_length
                FROM ngn_2025.user_streaks
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $userId]);
            $streakData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$streakData) {
                throw new Exception("Streak record not found");
            }

            // Calculate hours remaining to deadline
            $now = new DateTime('now', new \DateTimeZone('UTC'));
            $deadline = new DateTime($streakData['next_check_in_deadline'], new \DateTimeZone('UTC'));
            $hoursRemaining = $deadline->diff($now)->h + ($deadline->diff($now)->d * 24);
            if ($deadline < $now) {
                $hoursRemaining = 0;
            }

            return [
                'current_streak' => (int)$streakData['current_streak'],
                'longest_streak' => (int)$streakData['longest_streak'],
                'last_check_in_at' => $streakData['last_check_in_at'],
                'next_check_in_deadline' => $streakData['next_check_in_deadline'],
                'hours_remaining' => $hoursRemaining,
                'listening_streak' => (int)$streakData['listening_streak'],
                'engagement_streak' => (int)$streakData['engagement_streak'],
                'login_streak' => (int)$streakData['login_streak'],
                'grace_period_active' => (bool)$streakData['grace_period_active'],
                'streak_broken_count' => (int)$streakData['streak_broken_count'],
                'last_broken_at' => $streakData['last_broken_at'],
                'last_broken_streak_length' => (int)$streakData['last_broken_streak_length']
            ];
        } catch (Exception $e) {
            error_log("Error getting user streak: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Detect milestones for a user
     *
     * @param int $userId User ID
     * @return array Array of approaching milestones
     * @throws Exception
     */
    public function detectMilestones(int $userId): array
    {
        $milestones = [];

        try {
            // Get current XP level
            $xpData = $this->getUserLevel($userId);
            $xpToNextLevel = $xpData['xp_to_next_level'];

            // Check badge progress for near-complete badges
            $stmt = $this->pdo->prepare("
                SELECT badge_key, requirement_value
                FROM ngn_2025.badges
                WHERE requirement_type != 'special'
                LIMIT 20
            ");
            $stmt->execute();
            $badges = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($badges as $badge) {
                $progress = $this->checkBadgeProgress($userId, $badge['badge_key']);
                if ($progress >= 75 && $progress < 100) {
                    $milestones[] = [
                        'type' => 'badge',
                        'badge_key' => $badge['badge_key'],
                        'progress' => $progress,
                        'target' => $badge['requirement_value']
                    ];
                }
            }

            return $milestones;
        } catch (Exception $e) {
            error_log("Error detecting milestones: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Ensure user has XP record
     *
     * @param int $userId User ID
     * @throws Exception
     */
    private function ensureUserXPRecord(int $userId): void
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM ngn_2025.user_experience_points
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $userId]);

            if (!$stmt->fetch()) {
                // Create new XP record
                $stmt = $this->pdo->prepare("
                    INSERT INTO ngn_2025.user_experience_points (
                        user_id, total_xp, current_level,
                        xp_to_next_level, created_at, updated_at
                    ) VALUES (
                        :user_id, 0, 1,
                        :xp_required, NOW(), NOW()
                    )
                ");
                $stmt->execute([
                    ':user_id' => $userId,
                    ':xp_required' => $this->calculateXPForNextLevel(1)
                ]);
            }
        } catch (Exception $e) {
            error_log("Error ensuring XP record: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Ensure user has streak record
     *
     * @param int $userId User ID
     * @throws Exception
     */
    private function ensureUserStreakRecord(int $userId): void
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM ngn_2025.user_streaks
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $userId]);

            if (!$stmt->fetch()) {
                // Create new streak record
                $nextDeadline = new DateTime('now', new \DateTimeZone('UTC'));
                $nextDeadline->modify('+24 hours');

                $stmt = $this->pdo->prepare("
                    INSERT INTO ngn_2025.user_streaks (
                        user_id, current_streak, longest_streak,
                        last_check_in_at, next_check_in_deadline,
                        created_at, updated_at
                    ) VALUES (
                        :user_id, 0, 0,
                        NOW(), :next_deadline,
                        NOW(), NOW()
                    )
                ");
                $stmt->execute([
                    ':user_id' => $userId,
                    ':next_deadline' => $nextDeadline->format('Y-m-d H:i:s')
                ]);
            }
        } catch (Exception $e) {
            error_log("Error ensuring streak record: " . $e->getMessage());
            throw $e;
        }
    }
}
