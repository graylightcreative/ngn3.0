<?php

namespace NGN\Lib\Discovery;

use NGN\Config;
use NGN\Lib\Database\ConnectionFactory;
use NGN\Lib\Logger\LoggerFactory;
use PDO;
use PDOException;

class SimilarityService
{
    private PDO $readConnection;
    private PDO $writeConnection;
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->readConnection = ConnectionFactory::read();
        $this->writeConnection = ConnectionFactory::write();
    }

    /**
     * Get similar artists for a given artist
     */
    public function getSimilarArtists(int $artistId, int $limit = 10): array
    {
        try {
            $stmt = $this->readConnection->prepare(
                'SELECT artist_id, similar_artist_id, similarity_score FROM `ngn_2025`.`artist_similarity`
                 WHERE artist_id = ?
                 ORDER BY similarity_score DESC
                 LIMIT ?'
            );
            $stmt->execute([$artistId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error fetching similar artists', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get similarity score between two artists
     */
    public function getArtistSimilarity(int $artistId1, int $artistId2): float
    {
        try {
            $stmt = $this->readConnection->prepare(
                'SELECT similarity_score FROM `ngn_2025`.`artist_similarity`
                 WHERE (artist_id = ? AND similar_artist_id = ?)
                 OR (artist_id = ? AND similar_artist_id = ?)
                 LIMIT 1'
            );
            $stmt->execute([$artistId1, $artistId2, $artistId2, $artistId1]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (float) $result['similarity_score'] : 0.0;
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error fetching artist similarity', ['error' => $e->getMessage()]);
            return 0.0;
        }
    }

    /**
     * Compute similarity between two artists
     * Formula: (genre_match × 0.4) + (fanbase_overlap × 0.35) + (engagement_pattern × 0.25)
     */
    public function computeSimilarity(int $artistId1, int $artistId2): float
    {
        if ($artistId1 === $artistId2) {
            return 0.0;
        }

        try {
            $genreMatch = $this->calculateGenreMatchScore($artistId1, $artistId2);
            $fanbaseOverlap = $this->calculateFanbaseOverlapScore($artistId1, $artistId2);
            $engagementPattern = $this->calculateEngagementPatternScore($artistId1, $artistId2);

            $similarity = ($genreMatch * 0.4) + ($fanbaseOverlap * 0.35) + ($engagementPattern * 0.25);
            return round(min($similarity, 1.0), 4);
        } catch (Exception $e) {
            LoggerFactory::getLogger('discovery')->error('Error computing similarity', ['error' => $e->getMessage()]);
            return 0.0;
        }
    }

    /**
     * Batch compute similarities for an artist against others
     */
    public function batchComputeSimilarities(int $artistId): void
    {
        try {
            // Get all other artists (limit to active artists)
            $stmt = $this->readConnection->prepare(
                'SELECT id FROM `ngn_2025`.`artists` WHERE id != ? AND status = "active" LIMIT 500'
            );
            $stmt->execute([$artistId]);
            $artists = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $this->writeConnection->beginTransaction();

            foreach ($artists as $similarArtistId) {
                $similarity = $this->computeSimilarity($artistId, $similarArtistId);

                if ($similarity >= 0.1) { // Only store meaningful similarities
                    // Simpler approach to count shared fans
                    $stmt = $this->readConnection->prepare(
                        'SELECT COUNT(DISTINCT f1.user_id) as shared_fans
                         FROM `ngn_2025`.`follows` f1
                         INNER JOIN `ngn_2025`.`follows` f2 ON f1.user_id = f2.user_id
                         WHERE f1.artist_id = ? AND f2.artist_id = ?'
                    );
                    $stmt->execute([$artistId, $similarArtistId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $sharedFans = $result['shared_fans'] ?? 0;

                    $genreMatch = $this->calculateGenreMatchScore($artistId, $similarArtistId);
                    $fanbaseOverlap = $this->calculateFanbaseOverlapScore($artistId, $similarArtistId);
                    $engagementPattern = $this->calculateEngagementPatternScore($artistId, $similarArtistId);

                    $this->writeConnection->prepare(
                        'INSERT INTO `ngn_2025`.`artist_similarity` (artist_id, similar_artist_id, similarity_score, genre_match_score, fanbase_overlap_score, engagement_pattern_score, shared_fans_count, computed_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                         ON DUPLICATE KEY UPDATE
                         similarity_score = VALUES(similarity_score),
                         genre_match_score = VALUES(genre_match_score),
                         fanbase_overlap_score = VALUES(fanbase_overlap_score),
                         engagement_pattern_score = VALUES(engagement_pattern_score),
                         shared_fans_count = VALUES(shared_fans_count),
                         computed_at = NOW()'
                    )->execute([
                        $artistId, $similarArtistId, $similarity,
                        $genreMatch, $fanbaseOverlap, $engagementPattern,
                        $sharedFans
                    ]);
                }
            }

            $this->writeConnection->commit();
            LoggerFactory::getLogger('discovery')->info('Batch computed similarities', ['artist_id' => $artistId, 'artist_count' => count($artists)]);
        } catch (PDOException $e) {
            $this->writeConnection->rollBack();
            LoggerFactory::getLogger('discovery')->error('Error batch computing similarities', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Recompute all artist similarities
     */
    public function recomputeAllSimilarities(int $limit = 100): void
    {
        try {
            // Get artists that haven't been computed recently
            $stmt = $this->readConnection->prepare(
                'SELECT id FROM `ngn_2025`.`artists`
                 WHERE id NOT IN (
                     SELECT artist_id FROM `ngn_2025`.`artist_similarity` WHERE computed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                 )
                 AND status = "active"
                 LIMIT ?'
            );
            $stmt->execute([$limit]);
            $artists = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($artists as $artistId) {
                $this->batchComputeSimilarities($artistId);
            }

            LoggerFactory::getLogger('discovery')->info('Recomputed similarities', ['artist_count' => count($artists)]);
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error recomputing all similarities', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Calculate genre match score
     */
    private function calculateGenreMatchScore(int $artistId1, int $artistId2): float
    {
        try {
            $stmt = $this->readConnection->prepare(
                'SELECT primary_genre, secondary_genre FROM `ngn_2025`.`artists` WHERE id = ?'
            );

            $stmt->execute([$artistId1]);
            $artist1 = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt->execute([$artistId2]);
            $artist2 = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$artist1 || !$artist2) {
                return 0.0;
            }

            $genres1 = array_filter([$artist1['primary_genre'], $artist1['secondary_genre']]);
            $genres2 = array_filter([$artist2['primary_genre'], $artist2['secondary_genre']]);

            $intersection = count(array_intersect($genres1, $genres2));
            $union = count(array_unique(array_merge($genres1, $genres2)));

            if ($union === 0) {
                return 0.0;
            }

            return round($intersection / $union, 4);
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error calculating genre match', ['error' => $e->getMessage()]);
            return 0.0;
        }
    }

    /**
     * Calculate fanbase overlap score
     */
    private function calculateFanbaseOverlapScore(int $artistId1, int $artistId2): float
    {
        try {
            // Count shared followers
            $stmt = $this->readConnection->prepare(
                'SELECT COUNT(DISTINCT f1.user_id) as shared_fans
                 FROM `ngn_2025`.`follows` f1
                 INNER JOIN `ngn_2025`.`follows` f2 ON f1.user_id = f2.user_id
                 WHERE f1.artist_id = ? AND f2.artist_id = ?'
            );
            $stmt->execute([$artistId1, $artistId2]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $sharedFans = (int) ($result['shared_fans'] ?? 0);

            // Get individual follower counts
            $stmt = $this->readConnection->prepare(
                'SELECT COUNT(*) as followers FROM `ngn_2025`.`follows` WHERE artist_id = ?'
            );

            $stmt->execute([$artistId1]);
            $followers1 = (int) $stmt->fetch(PDO::FETCH_COLUMN);

            $stmt->execute([$artistId2]);
            $followers2 = (int) $stmt->fetch(PDO::FETCH_COLUMN);

            $minFollowers = min($followers1, $followers2);
            if ($minFollowers === 0) {
                return 0.0;
            }

            return round(min($sharedFans / $minFollowers, 1.0), 4);
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error calculating fanbase overlap', ['error' => $e->getMessage()]);
            return 0.0;
        }
    }

    /**
     * Calculate engagement pattern similarity
     */
    private function calculateEngagementPatternScore(int $artistId1, int $artistId2): float
    {
        try {
            // Get engagement metrics for both artists
            $stmt = $this->readConnection->prepare(
                'SELECT
                    COUNT(CASE WHEN engagement_type = "like" THEN 1 END) as likes,
                    COUNT(CASE WHEN engagement_type = "comment" THEN 1 END) as comments,
                    COUNT(CASE WHEN engagement_type = "share" THEN 1 END) as shares,
                    COUNT(CASE WHEN engagement_type = "spark" THEN 1 END) as sparks
                 FROM `ngn_2025`.`cdm_engagements`
                 WHERE artist_id = ?'
            );

            $stmt->execute([$artistId1]);
            $metrics1 = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt->execute([$artistId2]);
            $metrics2 = $stmt->fetch(PDO::FETCH_ASSOC);

            // Calculate engagement patterns as vectors
            $vector1 = [
                $metrics1['likes'] ?? 0,
                $metrics1['comments'] ?? 0,
                $metrics1['shares'] ?? 0,
                $metrics1['sparks'] ?? 0
            ];

            $vector2 = [
                $metrics2['likes'] ?? 0,
                $metrics2['comments'] ?? 0,
                $metrics2['shares'] ?? 0,
                $metrics2['sparks'] ?? 0
            ];

            // Cosine similarity
            $dotProduct = 0;
            $mag1 = 0;
            $mag2 = 0;

            for ($i = 0; $i < count($vector1); $i++) {
                $dotProduct += $vector1[$i] * $vector2[$i];
                $mag1 += $vector1[$i] ** 2;
                $mag2 += $vector2[$i] ** 2;
            }

            $mag1 = sqrt($mag1);
            $mag2 = sqrt($mag2);

            if ($mag1 === 0 || $mag2 === 0) {
                return 0.0;
            }

            return round($dotProduct / ($mag1 * $mag2), 4);
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error calculating engagement pattern', ['error' => $e->getMessage()]);
            return 0.0;
        }
    }
}
