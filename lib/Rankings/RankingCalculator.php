<?php
namespace NGN\Lib\Rankings;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

/**
 * RankingCalculator for NGN 2.0
 * Computes artist/label rankings and populates ngn_rankings_2025 tables.
 */
class RankingCalculator
{
    private PDO $pdoPrimary;
    private PDO $pdoRankings;
    private PDO $pdoCore; // Renamed from pdoDev

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdoPrimary = ConnectionFactory::read($config); // Connects to the main ngn_2025 database
        $this->pdoRankings = ConnectionFactory::named($config, 'rankings2025');
        $this->pdoCore = ConnectionFactory::read($config); // Explicitly connect pdoCore to ngn_2025
    }

    /**
     * Checks if a user is an investor.
     *
     * @param int $userId
     * @return bool True if the user is an investor, false otherwise.
     */
    private function isInvestor(int $userId): bool
    {
        try {
            $stmt = $this->pdoPrimary->prepare("SELECT is_investor FROM `users` WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);

            return ($userData && isset($userData['is_investor']) && (bool)$userData['is_investor']);
        } catch (\Throwable $e) {
            // Log error and return false if database query fails.
            error_log("Database error checking investor status for user {$userId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves the NGN User ID for a given entity type and its CDM ID.
     *
     * @param string $entityType (e.g., 'artist', 'label')
     * @param int $entityId The CDM ID of the entity.
     * @return int|null The NGN User ID, or null if not found.
     */
    private function getUserIdFromEntityId(string $entityType, int $entityId): ?int
    {
        try {
            $tableName = '';
            switch ($entityType) {
                case 'artist':
                    $tableName = 'artists';
                    break;
                case 'label':
                    $tableName = 'labels';
                    break;
                default:
                    return null; // Unsupported entity type
            }

            $stmt = $this->pdoPrimary->prepare(
                "SELECT user_id FROM `{$tableName}` WHERE id = :entityId LIMIT 1"
            );
            $stmt->execute([
                ':entityId' => $entityId,
            ]);
            $entityData = $stmt->fetch(PDO::FETCH_ASSOC);
            return $entityData ? (int)$entityData['user_id'] : null;
        } catch (\Throwable $e) {
            error_log("Failed to get User ID for {$entityType} ID {$entityId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Compute rankings for all intervals (daily, weekly, monthly).
     * Creates ranking windows and populates ranking items.
     */
    public function computeAll(): array
    {
        $results = [];
        foreach (['daily', 'weekly', 'monthly'] as $interval) {
            $results[$interval] = $this->computeForInterval($interval);
        }
        return $results;
    }

    /**
     * Compute rankings for a specific interval.
     */
    public function computeForInterval(string $interval): array
    {
        $today = date('Y-m-d');
        
        // Determine window dates based on interval
        switch ($interval) {
            case 'daily':
                $windowStart = $today;
                $windowEnd = $today;
                break;
            case 'weekly':
                $windowStart = date('Y-m-d', strtotime('monday this week'));
                $windowEnd = date('Y-m-d', strtotime('sunday this week'));
                break;
            case 'monthly':
                $windowStart = date('Y-m-01');
                $windowEnd = date('Y-m-t');
                break;
            default:
                throw new \InvalidArgumentException("Invalid interval: $interval");
        }

        // Create or get ranking window
        $windowId = $this->getOrCreateWindow($interval, $windowStart, $windowEnd);

        // Get previous window for delta calculation
        $prevWindowId = $this->getPreviousWindow($interval, $windowStart);

        // Compute artist rankings
        $artistCount = $this->computeArtistRankings($windowId, $prevWindowId);

        // Compute label rankings
        $labelCount = $this->computeLabelRankings($windowId, $prevWindowId);

        return [
            'interval' => $interval,
            'window_id' => $windowId,
            'window_start' => $windowStart,
            'window_end' => $windowEnd,
            'artists_ranked' => $artistCount,
            'labels_ranked' => $labelCount,
        ];
    }

    private function getOrCreateWindow(string $interval, string $start, string $end): int
    {
        // Check if window exists
        $stmt = $this->pdoRankings->prepare(
            'SELECT id FROM `ranking_windows` 
             WHERE `interval` = :interval AND window_start = :start LIMIT 1'
        );
        $stmt->execute([':interval' => $interval, ':start' => $start]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            return (int)$row['id'];
        }

        // Create new window
        $ins = $this->pdoRankings->prepare(
            'INSERT INTO `ranking_windows` (`interval`, window_start, window_end) 
             VALUES (:interval, :start, :end)'
        );
        $ins->execute([':interval' => $interval, ':start' => $start, ':end' => $end]);
        
        return (int)$this->pdoRankings->lastInsertId();
    }

    private function getPreviousWindow(string $interval, string $currentStart): ?int
    {
        $stmt = $this->pdoRankings->prepare(
            'SELECT id FROM `ranking_windows` 
             WHERE `interval` = :interval AND window_start < :start 
             ORDER BY window_start DESC LIMIT 1'
        );
        $stmt->execute([':interval' => $interval, ':start' => $currentStart]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? (int)$row['id'] : null;
    }

    private function computeArtistRankings(int $windowId, ?int $prevWindowId): int
    {
        // Get all artists from primary
        $artists = $this->pdoCore->query(
            'SELECT id, slug, name, claimed FROM `artists` WHERE status = "active" ORDER BY id'
        )->fetchAll(PDO::FETCH_ASSOC);

        if (!$artists) return 0;

        // Get previous rankings for delta calculation
        $prevRanks = [];
        if ($prevWindowId) {
            $stmt = $this->pdoRankings->prepare(
                'SELECT entity_id, rank FROM `ranking_items` 
                 WHERE window_id = :wid AND entity_type = "artist"'
            );
            $stmt->execute([':wid' => $prevWindowId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $prevRanks[(int)$row['entity_id']] = (int)$row['rank'];
            }
        }

        // Calculate scores for each artist
        $scores = [];
        $factorHistory = [];
        foreach ($artists as $artist) {
            $artistId = (int)$artist['id'];
            $result = $this->calculateArtistScore($artistId, $artist);
            $scores[$artistId] = $result['score'];
            $factorHistory[$artistId] = $result['factors'];
        }

        // Sort by score descending
        arsort($scores);

        // Clear existing items for this window
        $del = $this->pdoRankings->prepare(
            'DELETE FROM `ranking_items` 
             WHERE window_id = :wid AND entity_type = "artist"'
        );
        $del->execute([':wid' => $windowId]);

        // Insert ranked items
        $ins = $this->pdoRankings->prepare(
            'INSERT INTO `ranking_items`
             (window_id, entity_type, entity_id, `rank`, score, prev_rank, deltas)
             VALUES (:wid, "artist", :eid, :rank, :score, :prev_rank, :deltas)'
        );

        $receiptService = new \NGN\Lib\Fairness\FairnessReceipt($this->pdoPrimary);

        $rank = 1;
        foreach ($scores as $artistId => $score) {
            $prevRank = $prevRanks[$artistId] ?? null;
            $deltas = $prevRank ? json_encode(['rank_change' => $prevRank - $rank]) : null;
            
            // Get User ID to check for investor status
            $userId = $this->getUserIdFromEntityId('artist', $artistId);
            $isInvestor = false;
            $investorMultiplier = 1.0;
            if ($userId !== null) {
                $isInvestor = $this->isInvestor($userId);
            }

            // Apply community funding multiplier if user is an investor
            if ($isInvestor) {
                $score *= 1.05; // Community Funding Multiplier (BFL 1.1)
                $investorMultiplier = 1.05;
            }

            $ins->execute([
                ':wid' => $windowId,
                ':eid' => $artistId,
                ':rank' => $rank,
                ':score' => round($score, 4), // Round score before saving
                ':prev_rank' => $prevRank,
                ':deltas' => $deltas,
            ]);

            // NGN 2.0.3: Generate Fairness Receipt for Audit Shard
            try {
                $receiptService->generateArtistReceipt($artistId, $windowId, true);
            } catch (\Throwable $e) {
                error_log("Failed to log fairness receipt for artist {$artistId}: " . $e->getMessage());
            }

            $rank++;
        }

        return count($scores);
    }

    private function computeLabelRankings(int $windowId, ?int $prevWindowId): int
    {
        // Get all labels from primary
        $labels = $this->pdoCore->query(
            'SELECT id, slug, name, claimed FROM `labels` WHERE status = "active" ORDER BY id'
        )->fetchAll(PDO::FETCH_ASSOC);

        if (!$labels) return 0;

        // Get previous rankings for delta calculation
        $prevRanks = [];
        if ($prevWindowId) {
            $stmt = $this->pdoRankings->prepare(
                'SELECT entity_id, rank FROM `ranking_items` 
                 WHERE window_id = :wid AND entity_type = "label"'
            );
            $stmt->execute([':wid' => $prevWindowId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $prevRanks[(int)$row['entity_id']] = (int)$row['rank'];
            }
        }

        // Calculate scores for each label
        $scores = [];
        foreach ($labels as $label) {
            $labelId = (int)$label['id'];
            $result = $this->calculateLabelScore($labelId, $label);
            $scores[$labelId] = $result['score'];
        }

        // Sort by score descending
        arsort($scores);

        // Clear existing items for this window
        $del = $this->pdoRankings->prepare(
            'DELETE FROM `ranking_items` 
             WHERE window_id = :wid AND entity_type = "label"'
        );
        $del->execute([':wid' => $windowId]);

        // Insert ranked items
        $ins = $this->pdoRankings->prepare(
            'INSERT INTO `ranking_items`
             (window_id, entity_type, entity_id, `rank`, score, prev_rank, deltas)
             VALUES (:wid, "label", :eid, :rank, :score, :prev_rank, :deltas)'
        );

        $rank = 1;
        foreach ($scores as $labelId => $score) {
            $prevRank = $prevRanks[$labelId] ?? null;
            $deltas = $prevRank ? json_encode(['rank_change' => $prevRank - $rank]) : null;
            
            // Get User ID to check for investor status
            $userId = $this->getUserIdFromEntityId('label', $labelId);
            $isInvestor = false;
            if ($userId !== null) {
                $isInvestor = $this->isInvestor($userId);
            }

            // Apply community funding multiplier if user is an investor
            if ($isInvestor) {
                $score *= 1.05; // Community Funding Multiplier (BFL 1.1)
            }

            $ins->execute([
                ':wid' => $windowId,
                ':eid' => $labelId,
                ':rank' => $rank,
                ':score' => round($score, 4), // Round score before saving
                ':prev_rank' => $prevRank,
                ':deltas' => $deltas,
            ]);
            $rank++;
        }

        return count($scores);
    }

    /**
     * Calculate artist score based on multiple factors.
     * Returns both total score and factor breakdown for audit.
     */
    private function calculateArtistScore(int $artistId, array $artist): array
    {
        $factors = [];
        $score = 0.0;

        // Base score for claimed profiles
        $factors['claimed_profile'] = !empty($artist['claimed']) ? 1000.0 : 0.0;
        $score += $factors['claimed_profile'];

        // Radio spins score
        $factors['radio_spins'] = $this->getSpinsScore($artistId);
        $score += $factors['radio_spins'];

        // SMR chart spins score
        $factors['smr_chart_spins'] = $this->getSmrSpinsScore($artistId);
        $score += $factors['smr_chart_spins'];

        // Social media score
        $factors['social_media'] = $this->getSocialScore($artistId);
        $score += $factors['social_media'];

        // Releases score
        $factors['releases'] = $this->getReleasesScore($artistId);
        $score += $factors['releases'];

        // Videos score
        $factors['videos'] = $this->getVideosScore($artistId);
        $score += $factors['videos'];

        // Mentions score
        $factors['mentions'] = $this->getMentionsScore($artistId);
        $score += $factors['mentions'];

        // Views score
        $factors['views'] = $this->getViewsScore($artistId);
        $score += $factors['views'];

        // Engagement Quality Score (EQS)
        $factors['engagement_quality'] = $this->getEngagementScore('artist', $artistId);
        $score += $factors['engagement_quality'];

        return [
            'score' => round($score, 4),
            'factors' => $factors
        ];
    }

    /**
     * Calculate label score based on roster performance.
     */
    private function calculateLabelScore(int $labelId, array $label): array
    {
        $factors = [];
        $score = 0.0;

        // Base score for claimed profiles
        $factors['claimed_profile'] = !empty($label['claimed']) ? 500.0 : 0.0;
        $score += $factors['claimed_profile'];

        // Get roster artists and sum their scores
        $factors['roster_performance'] = $this->getRosterScore($labelId);
        $score += $factors['roster_performance'];

        // Label's own social/views
        $factors['social_media'] = $this->getSocialScore($labelId) * 0.5;
        $score += $factors['social_media'];

        $factors['views'] = $this->getViewsScore($labelId) * 0.5;
        $score += $factors['views'];

        // Engagement Quality Score (EQS) - likes, shares, comments, sparks
        $factors['engagement_quality'] = $this->getEngagementScore('label', $labelId) * 0.5;
        $score += $factors['engagement_quality'];

        return [
            'score' => round($score, 4),
            'factors' => $factors
        ];
    }



    private function getSpinsScore(int $artistId): float
    {
        try {
            $pdoSpins = ConnectionFactory::named($this->config, 'spins2025'); // Use dedicated spins connection
            $stmt = $pdoSpins->prepare(
                'SELECT COUNT(*) as cnt FROM `ngn_spins_2025`.`station_spins` WHERE artist_id = :id'
            );
            $stmt->execute([':id' => $artistId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $row ? (int)$row['cnt'] : 0;
            return $count * ($_ENV['ARTIST_SPIN_COUNT_WEIGHT'] ?? 10);
        } catch (\Throwable $e) {
            LoggerFactory::getLogger('rankings')->error("Error getting spins score for artist {$artistId}: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Get SMR (Spins Music Radio) chart spins score.
     * SMR is a separate radio chart used for marketing/scoring purposes.
     * Data is ingested by admins via Excel uploads.
     */
    private function getSmrSpinsScore(int $artistId): float
    {
        try {
            $pdoSmr = ConnectionFactory::named($this->config, 'smr2025'); // Use dedicated SMR connection
            // Query SMR chart data by artist ID
            $stmt = $pdoSmr->prepare(
                'SELECT SUM(tws) as total_spins, COUNT(*) as chart_appearances
                 FROM `ngn_smr_2025`.`smr_chart`
                 WHERE artist_id = :id'
            );
            $stmt->execute([':id' => $artistId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) return 0.0;

            $totalSpins = (int)($row['total_spins'] ?? 0);
            $appearances = (int)($row['chart_appearances'] ?? 0);

            // SMR weight: spins count + bonus for chart appearances
            $spinWeight = $_ENV['SMR_SPIN_WEIGHT'] ?? 50;
            $appearanceBonus = $_ENV['SMR_APPEARANCE_BONUS'] ?? 50;

            return ($totalSpins * $spinWeight) + ($appearances * $appearanceBonus);
        } catch (\Throwable $e) {
            LoggerFactory::getLogger('rankings')->error("Error getting SMR spins score for artist {$artistId}: " . $e->getMessage());
            return 0.0;
        }
    }

    private function getSocialScore(int $artistId): float
    {
        try {
            // Count connected social accounts from ngn_2025.oauth_tokens
            $stmt = $this->pdoPrimary->prepare(
                'SELECT COUNT(DISTINCT provider) AS connected_accounts FROM `ngn_2025`.`oauth_tokens` WHERE entity_id = :id AND entity_type = "artist"'
            );
            $stmt->execute([':id' => $artistId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $connectedAccounts = $row ? (int)$row['connected_accounts'] : 0;

            // Assign score based on number of connected accounts
            return $connectedAccounts * ($_ENV['ARTIST_CONNECTED_SOCIAL_WEIGHT'] ?? 50);
        } catch (\Throwable $e) {
            LoggerFactory::getLogger('rankings')->error("Error getting social score for artist {$artistId}: " . $e->getMessage());
            return 0.0;
        }
    }

    private function getReleasesScore(int $artistId): float
    {
        try {
            $stmt = $this->pdoPrimary->prepare(
                'SELECT COUNT(*) as cnt FROM `ngn_2025`.`releases` WHERE artist_id = :id'
            );
            $stmt->execute([':id' => $artistId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $row ? (int)$row['cnt'] : 0;
            return $count * ($_ENV['ARTIST_RELEASE_COUNT_WEIGHT'] ?? 25);
        } catch (\Throwable $e) {
            LoggerFactory::getLogger('rankings')->error("Error getting releases score for artist {$artistId}: " . $e->getMessage());
            return 0.0;
        }
    }

    private function getVideosScore(int $artistId): float
    {
        try {
            $stmt = $this->pdoPrimary->prepare(
                'SELECT COUNT(*) as cnt FROM `ngn_2025`.`videos` WHERE artist_id = :id'
            );
            $stmt->execute([':id' => $artistId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $row ? (int)$row['cnt'] : 0;
            return $count * ($_ENV['ARTIST_VIDEO_COUNT_WEIGHT'] ?? 15);
        } catch (\Throwable $e) {
            LoggerFactory::getLogger('rankings')->error("Error getting videos score for artist {$artistId}: " . $e->getMessage());
            return 0.0;
        }
    }

    private function getMentionsScore(int $artistId): float
    {
        try {
            // Get artist name to search for mentions
            $nameStmt = $this->pdoPrimary->prepare("SELECT name FROM `ngn_2025`.`artists` WHERE id = :id LIMIT 1");
            $nameStmt->execute([':id' => $artistId]);
            $artistName = $nameStmt->fetchColumn();

            if (!$artistName) return 0.0;

            $stmt = $this->pdoPrimary->prepare(
                'SELECT COUNT(*) as cnt FROM `ngn_2025`.`posts` WHERE (title LIKE :name OR teaser LIKE :name OR tags LIKE :name OR body LIKE :name)'
            );
            $searchTerm = '%' . $artistName . '%';
            $stmt->execute([':name' => $searchTerm]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $row ? (int)$row['cnt'] : 0;
            return $count * ($_ENV['MENTION_WEIGHT'] ?? 5);
        } catch (\Throwable $e) {
            LoggerFactory::getLogger('rankings')->error("Error getting mentions score for artist {$artistId}: " . $e->getMessage());
            return 0.0;
        }
    }

    private function getViewsScore(int $artistId): float
    {
        try {
            $stmt = $this->pdoPrimary->prepare(
                'SELECT COALESCE(SUM(view_count), 0) as total FROM `ngn_2025`.`videos` WHERE artist_id = :id'
            );
            $stmt->execute([':id' => $artistId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $total = $row ? (int)$row['total'] : 0;
            return $total * ($_ENV['VIEW_WEIGHT'] ?? 0.1);
        } catch (\Throwable $e) {
            LoggerFactory::getLogger('rankings')->error("Error getting views score for artist {$artistId}: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Get Engagement Quality Score (EQS) for an entity
     *
     * EQS aggregates likes, shares, comments, and sparks with weighted scoring:
     * - Like: 1 point
     * - Share: 3 points
     * - Comment: 2 points
     * - Spark: 5 points per spark
     *
     * @param string $entityType Entity type (artist, label, venue, station, post, etc.)
     * @param int $entityId Entity CDM ID
     * @return float EQS score
     */
    private function getEngagementScore(string $entityType, int $entityId): float
    {
        try {
            $stmt = $this->pdoPrimary->prepare(
                'SELECT eqs_score FROM ngn_2025.cdm_engagement_counts
                 WHERE entity_type = :entity_type AND entity_id = :entity_id
                 LIMIT 1'
            );
            $stmt->execute([
                ':entity_type' => $entityType,
                ':entity_id' => $entityId
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? (float)$row['eqs_score'] : 0.0;
        } catch (\Throwable $e) {
            error_log("Failed to get engagement score for {$entityType} {$entityId}: " . $e->getMessage());
            return 0.0;
        }
    }

    private function getRosterScore(int $labelId): float
    {
        try {
            // Get artists under this label
            $stmt = $this->pdoPrimary->prepare(
                'SELECT id FROM `ngn_2025`.`artists` WHERE label_id = :id AND status = "active"'
            );
            $stmt->execute([':id' => $labelId]);
            $artists = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $score = 0.0;
            foreach ($artists as $artist) {
                // Add portion of each artist's score (e.g., from spins)
                $spins = $this->getSpinsScore((int)$artist['id']);
                $score += $spins * 0.25; // 25% of artist spins contribute to label score
            }

            // Bonus for roster size
            $score += count($artists) * 10;

            return $score;
        } catch (\Throwable $e) {
            LoggerFactory::getLogger('rankings')->error("Error getting roster score for label {$labelId}: " . $e->getMessage());
            return 0.0;
        }
    }
}
