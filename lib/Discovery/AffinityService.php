<?php

namespace NGN\Lib\Discovery;

use NGN\Config;
use NGN\Lib\Database\ConnectionFactory;
use NGN\Lib\Logger\LoggerFactory;
use PDO;
use PDOException;

class AffinityService
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
     * Get user affinity for a specific artist
     */
    public function getUserAffinityForArtist(int $userId, int $artistId): ?array
    {
        try {
            'SELECT * FROM `ngn_2025`.`user_artist_affinity` WHERE user_id = ? AND artist_id = ?'
            );
            $stmt->execute([$userId, $artistId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error fetching user affinity', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get user's top artist affinities
     */
    public function getUserTopArtistAffinities(int $userId, int $limit = 20): array
    {
        try {
            $stmt = $this->readConnection->prepare(
                'SELECT * FROM `ngn_2025`.`user_artist_affinity`
                 WHERE user_id = ?
                 ORDER BY affinity_score DESC
                 LIMIT ?'
            );
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error fetching top artist affinities', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get user's genre affinities
     */
    public function getUserGenreAffinities(int $userId): array
    {
        try {
            $stmt = $this->readConnection->prepare(
                'SELECT * FROM `ngn_2025`.`user_genre_affinity`
                 WHERE user_id = ?
                 ORDER BY affinity_score DESC'
            );
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error fetching genre affinities', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Calculate composite affinity score for user-artist pair
     * Formula: (spark_weight × 0.4) + (engagement_weight × 0.3) + (listen_weight × 0.2) + (follow_weight × 0.1)
     */
    public function calculateArtistAffinity(int $userId, int $artistId): float
    {
        try {
            $affinity = $this->getUserAffinityForArtist($userId, $artistId);
            if (!$affinity) {
                return 0.0;
            }

            $sparkWeight = min($affinity['total_sparks'] / 100, 100);
            $engagementWeight = min($affinity['total_engagements'] * 2, 100);
            $listenWeight = min(($affinity['listen_weight'] ?? 0) * 0.5, 100);
            $followWeight = $affinity['is_following'] ? 100 : 0;

            $score = ($sparkWeight * 0.4) + ($engagementWeight * 0.3) + ($listenWeight * 0.2) + ($followWeight * 0.1);

            return round(min($score, 100), 4);
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error calculating artist affinity', ['error' => $e->getMessage()]);
            return 0.0;
        }
    }

    /**
     * Update affinity scores based on user engagement
     */
    public function updateAffinityFromEngagement(int $userId, int $artistId, string $engagementType, float $value = 1.0): void
    {
        try {
            $this->writeConnection->beginTransaction();

            // Get current affinity or create new
            $stmt = $this->writeConnection->prepare(
                'SELECT * FROM `ngn_2025`.`user_artist_affinity` WHERE user_id = ? AND artist_id = ?'
            );
            $stmt->execute([$userId, $artistId]);
            $affinity = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$affinity) {
                $stmt = $this->writeConnection->prepare(
                    'INSERT INTO `ngn_2025`.`user_artist_affinity` (user_id, artist_id, total_sparks, total_engagements)
                     VALUES (?, ?, 0, 0)'
                );
                $stmt->execute([$userId, $artistId]);
            }

            // Update based on engagement type
            switch ($engagementType) {
                case 'spark':
                    $this->writeConnection->prepare(
                        'UPDATE `ngn_2025`.`user_artist_affinity` SET total_sparks = total_sparks + ?, last_engagement_at = NOW()
                         WHERE user_id = ? AND artist_id = ?'
                    )->execute([$value, $userId, $artistId]);
                    break;

                case 'engagement':
                    $this->writeConnection->prepare(
                        'UPDATE `ngn_2025`.`user_artist_affinity` SET total_engagements = total_engagements + 1, last_engagement_at = NOW()
                         WHERE user_id = ? AND artist_id = ?'
                    )->execute([$userId, $artistId]);
                    break;

                case 'follow':
                    $this->writeConnection->prepare(
                        'UPDATE `ngn_2025`.`user_artist_affinity` SET is_following = 1, last_engagement_at = NOW()
                         WHERE user_id = ? AND artist_id = ?'
                    )->execute([$userId, $artistId]);
                    break;

                case 'unfollow':
                    $this->writeConnection->prepare(
                        'UPDATE `ngn_2025`.`user_artist_affinity` SET is_following = 0
                         WHERE user_id = ? AND artist_id = ?'
                    )->execute([$userId, $artistId]);
                    break;
            }

            // Recalculate affinity score
            $newScore = $this->calculateArtistAffinity($userId, $artistId);
            $this->writeConnection->prepare(
                'UPDATE `ngn_2025`.`user_artist_affinity` SET affinity_score = ? WHERE user_id = ? AND artist_id = ?'
            )->execute([$newScore, $userId, $artistId]);

            $this->writeConnection->commit();

            // Update genre affinity
            $this->updateGenreAffinityFromArtist($userId, $artistId);
        } catch (PDOException $e) {
            $this->writeConnection->rollBack();
            LoggerFactory::getLogger('discovery')->error('Error updating affinity from engagement', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Recalculate all affinities for a user
     */
    public function recalculateAllAffinities(int $userId): void
    {
        try {
            $this->writeConnection->beginTransaction();

            // Get all artist affinities for user
            $stmt = $this->readConnection->prepare(
                'SELECT artist_id FROM `ngn_2025`.`user_artist_affinity` WHERE user_id = ?'
            );
            $stmt->execute([$userId]);
            $artists = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Recalculate each
            foreach ($artists as $artistId) {
                $score = $this->calculateArtistAffinity($userId, $artistId);
                $this->writeConnection->prepare(
                    'UPDATE `ngn_2025`.`user_artist_affinity` SET affinity_score = ? WHERE user_id = ? AND artist_id = ?'
                )->execute([$score, $userId, $artistId]);
            }

            // Recalculate genre affinities
            $stmt = $this->readConnection->prepare(
                'SELECT DISTINCT genre_slug FROM `ngn_2025`.`user_genre_affinity` WHERE user_id = ?'
            );
            $stmt->execute([$userId]);
            $genres = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($genres as $genreSlug) {
                $this->updateGenreAffinity($userId, $genreSlug);
            }

            $this->writeConnection->commit();
            LoggerFactory::getLogger('discovery')->info('Recalculated affinities', ['user_id' => $userId, 'artist_count' => count($artists)]);
        } catch (PDOException $e) {
            $this->writeConnection->rollBack();
            LoggerFactory::getLogger('discovery')->error('Error recalculating affinities', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Calculate genre affinity score
     */
    public function calculateGenreAffinity(int $userId, string $genreSlug): float
    {
        try {
            $stmt = $this->readConnection->prepare(
                'SELECT total_engagements, total_sparks FROM `ngn_2025`.`user_genre_affinity`
                 WHERE user_id = ? AND genre_slug = ?'
            );
            $stmt->execute([$userId, $genreSlug]);
            $genre = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$genre) {
                return 0.0;
            }

            $engagementScore = min($genre['total_engagements'] * 2, 100);
            $sparkScore = min($genre['total_sparks'] / 100, 100);

            $score = ($engagementScore * 0.6) + ($sparkScore * 0.4);
            return round(min($score, 100), 4);
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error calculating genre affinity', ['error' => $e->getMessage()]);
            return 0.0;
        }
    }

    /**
     * Update genre affinity from artist engagement
     */
    public function updateGenreAffinityFromArtist(int $userId, int $artistId): void
    {
        try {
            // Get artist genres
            $stmt = $this->readConnection->prepare(
                'SELECT primary_genre, secondary_genre FROM `ngn_2025`.`artists` WHERE id = ?'
            );
            $stmt->execute([$artistId]);
            $artist = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$artist) {
                return;
            }

            $genres = array_filter([$artist['primary_genre'], $artist['secondary_genre']]);

            foreach ($genres as $genre) {
                $this->updateGenreAffinity($userId, $genre);
            }
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error updating genre affinity from artist', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update genre affinity totals
     */
    private function updateGenreAffinity(int $userId, string $genreSlug): void
    {
        try {
            // Get artists in genre that user engages with
            $stmt = $this->readConnection->prepare(
                'SELECT COUNT(*) as artist_count, SUM(total_engagements) as total_engagements, SUM(total_sparks) as total_sparks
                 FROM `ngn_2025`.`user_artist_affinity` uaa
                 JOIN `ngn_2025`.`artists` a ON uaa.artist_id = a.id
                 WHERE uaa.user_id = ? AND (a.primary_genre = ? OR a.secondary_genre = ?)'
            );
            $stmt->execute([$userId, $genreSlug, $genreSlug]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($stats && $stats['artist_count'] > 0) {
                $score = $this->calculateGenreAffinity($userId, $genreSlug);
                $genreName = $this->getGenreName($genreSlug);

                $this->writeConnection->prepare(
                    'INSERT INTO `ngn_2025`.`user_genre_affinity` (user_id, genre_slug, genre_name, affinity_score, artist_count, total_engagements, total_sparks, last_engagement_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE
                     affinity_score = VALUES(affinity_score),
                     artist_count = VALUES(artist_count),
                     total_engagements = VALUES(total_engagements),
                     total_sparks = VALUES(total_sparks),
                     last_engagement_at = NOW()'
                )->execute([
                    $userId, $genreSlug, $genreName, $score,
                    $stats['artist_count'],
                    $stats['total_engagements'] ?? 0,
                    $stats['total_sparks'] ?? 0
                ]);
            }
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error updating genre affinity', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get human-readable genre name
     */
    private function getGenreName(string $genreSlug): string
    {
        try {
            $stmt = $this->readConnection->prepare(
                'SELECT name FROM `ngn_2025`.`genres` WHERE slug = ? LIMIT 1'
            );
            $stmt->execute([$genreSlug]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['name'] ?? ucfirst(str_replace('-', ' ', $genreSlug));
        } catch (PDOException $e) {
            return ucfirst(str_replace('-', ' ', $genreSlug));
        }
    }
}
