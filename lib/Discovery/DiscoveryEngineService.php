<?php

namespace NGN\Lib\Discovery;

use NGN\Config;
use NGN\Lib\Database\ConnectionFactory;
use NGN\Lib\Logger\LoggerFactory;
use NGN\Lib\Artists\ArtistService;
use PDO;
use PDOException;

class DiscoveryEngineService
{
    private PDO $readConnection;
    private PDO $writeConnection;
    private Config $config;
    private AffinityService $affinityService;
    private SimilarityService $similarityService;
    private ArtistService $artistService;

    public function __construct(Config $config, ?AffinityService $affinityService = null, ?SimilarityService $similarityService = null, ?ArtistService $artistService = null)
    {
        $this->config = $config;
        $this->readConnection = ConnectionFactory::read();
        $this->writeConnection = ConnectionFactory::write();
        $this->affinityService = $affinityService ?? new AffinityService($config);
        $this->similarityService = $similarityService ?? new SimilarityService($config);
        $this->artistService = $artistService ?? new ArtistService($config);
    }

    /**
     * Get personalized artist recommendations for user
     */
    public function getRecommendedArtists(int $userId, int $limit = 10, array $options = []): array
    {
        try {
            $startTime = microtime(true);

            // Check cache first
            $cached = $this->getCachedRecommendations($userId);
            if ($cached) {
                return array_slice($cached, 0, $limit);
            }

            // Generate recommendations from multiple sources
            $affinityRecs = $this->getAffinityBasedRecommendations($userId, $limit * 2);
            $similarityRecs = $this->getSimilarityBasedRecommendations($userId, $limit * 2);
            $communityRecs = $this->getCommunityBasedRecommendations($userId, $limit * 2);

            // Blend and filter recommendations
            $allRecs = array_merge($affinityRecs, $similarityRecs, $communityRecs);
            $blended = $this->blendRecommendations($allRecs, $limit * 3);
            $filtered = $this->filterAlreadyFollowing($userId, $blended);
            $filtered = $this->filterRecentlyRecommended($userId, $filtered);
            $diversified = $this->applyDiversityRules($filtered);

            $final = array_slice($diversified, 0, $limit);

            // Cache results
            $generationTime = round((microtime(true) - $startTime) * 1000);
            $this->cacheRecommendations($userId, $final, $generationTime);

            return $final;
        } catch (Exception $e) {
            LoggerFactory::getLogger('discovery')->error('Error getting recommendations', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get emerging artists for user
     */
    public function getEmergingArtists(int $userId, int $limit = 10): array
    {
        try {
            // Get user's genre affinities
            $genres = $this->affinityService->getUserGenreAffinities($userId);
            $genreSlugs = array_column($genres, 'genre_slug');

            if (empty($genreSlugs)) {
                return [];
            }

            $placeholders = implode(',', array_fill(0, count($genreSlugs), '?'));

            // Get emerging artists (rising NGN Score) in user's genres
            $stmt = $this->readConnection->prepare(
                "SELECT a.id, a.name, a.primary_genre, ais.ngn_score, ais.ngn_momentum
                 FROM `ngn_2025`.`artists` a
                 JOIN `ngn_2025`.`artist_intelligence_scores` ais ON a.id = ais.artist_id
                 WHERE (a.primary_genre IN ($placeholders) OR a.secondary_genre IN ($placeholders))
                 AND ais.ngn_momentum > 0
                 AND a.status = 'active'
                 ORDER BY ais.ngn_momentum DESC
                 LIMIT ?"
            );

            $params = array_merge($genreSlugs, $genreSlugs, [$limit]);
            $stmt->execute($params);
            $artists = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return array_map(function ($artist) {
                return [
                    'artist_id' => (int) $artist['id'],
                    'artist_name' => $artist['name'],
                    'ngn_score' => (float) $artist['ngn_score'],
                    'is_emerging' => true,
                    'reason' => 'Emerging artist in ' . ucfirst(str_replace('-', ' ', $artist['primary_genre']))
                ];
            }, $artists);
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error getting emerging artists', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get genre-based recommendations
     */
    public function getGenreBasedRecommendations(int $userId, string $genreSlug, int $limit = 10): array
    {
        try {
            $stmt = $this->readConnection->prepare(
                'SELECT a.id, a.name, ais.ngn_score
                 FROM `ngn_2025`.`artists` a
                 JOIN `ngn_2025`.`artist_intelligence_scores` ais ON a.id = ais.artist_id
                 WHERE (a.primary_genre = ? OR a.secondary_genre = ?)
                 AND a.status = "active"
                 AND ais.ngn_score >= 30
                 ORDER BY ais.ngn_score DESC
                 LIMIT ?'
            );
            $stmt->execute([$genreSlug, $genreSlug, $limit]);
            $artists = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return array_map(function ($artist) use ($genreSlug) {
                return [
                    'artist_id' => (int) $artist['id'],
                    'artist_name' => $artist['name'],
                    'ngn_score' => (float) $artist['ngn_score'],
                    'reason' => 'Popular in ' . ucfirst(str_replace('-', ' ', $genreSlug))
                ];
            }, $artists);
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error getting genre recommendations', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get affinity-based recommendations
     */
    private function getAffinityBasedRecommendations(int $userId, int $limit): array
    {
        try {
            // Get user's top affinity artists
            $topArtists = $this->affinityService->getUserTopArtistAffinities($userId, 5);
            $recommendations = [];

            foreach ($topArtists as $topArtist) {
                // Get similar artists
                $similarArtists = $this->similarityService->getSimilarArtists((int) $topArtist['artist_id'], $limit / count($topArtists));

                foreach ($similarArtists as $similar) {
                    $score = ((float) $topArtist['affinity_score'] * 0.5) + ((float) $similar['similarity_score'] * 0.5);
                    $recommendations[] = [
                        'artist_id' => (int) $similar['similar_artist_id'],
                        'score' => round($score, 4),
                        'reason' => 'Similar to ' . $topArtist['artist_id'],
                        'source' => 'affinity'
                    ];
                }
            }

            return array_slice($recommendations, 0, $limit);
        } catch (Exception $e) {
            LoggerFactory::getLogger('discovery')->error('Error getting affinity recommendations', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get similarity-based recommendations
     */
    private function getSimilarityBasedRecommendations(int $userId, int $limit): array
    {
        try {
            // Get artists in same genres as user's affinities
            $genres = $this->affinityService->getUserGenreAffinities($userId);
            $recommendations = [];

            foreach (array_slice($genres, 0, 3) as $genre) {
                $artists = $this->getGenreBasedRecommendations($userId, $genre['genre_slug'], $limit / 3);
                foreach ($artists as $artist) {
                    $artist['source'] = 'genre_match';
                    $recommendations[] = $artist;
                }
            }

            return array_slice($recommendations, 0, $limit);
        } catch (Exception $e) {
            LoggerFactory::getLogger('discovery')->error('Error getting similarity recommendations', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get community-based recommendations
     */
    private function getCommunityBasedRecommendations(int $userId, int $limit): array
    {
        try {
            // Get artists popular among users with similar tastes
            $stmt = $this->readConnection->prepare(
                'SELECT a.id, a.name, ais.ngn_score, COUNT(DISTINCT uaa.user_id) as similar_users
                 FROM `ngn_2025`.`artists` a
                 JOIN `ngn_2025`.`artist_intelligence_scores` ais ON a.id = ais.artist_id
                 JOIN `ngn_2025`.`user_artist_affinity` uaa ON a.id = uaa.artist_id
                 WHERE uaa.user_id IN (
                     SELECT DISTINCT user_id FROM `ngn_2025`.`user_genre_affinity`
                     WHERE genre_slug IN (
                         SELECT genre_slug FROM `ngn_2025`.`user_genre_affinity` WHERE user_id = ?
                     )
                 )
                 AND a.status = "active"
                 AND ais.ngn_score >= 30
                 GROUP BY a.id
                 ORDER BY similar_users DESC, ais.ngn_score DESC
                 LIMIT ?'
            );
            $stmt->execute([$userId, $limit]);
            $artists = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return array_map(function ($artist) {
                return [
                    'artist_id' => (int) $artist['id'],
                    'artist_name' => $artist['name'],
                    'ngn_score' => (float) $artist['ngn_score'],
                    'reason' => 'Popular with similar listeners',
                    'source' => 'community'
                ];
            }, $artists);
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error getting community recommendations', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Blend recommendations from multiple sources
     */
    private function blendRecommendations(array $sources, int $limit): array
    {
        $blended = [];
        $seen = [];

        // Aggregate scores for duplicate artist IDs
        foreach ($sources as $rec) {
            $artistId = $rec['artist_id'];
            if (!isset($seen[$artistId])) {
                $seen[$artistId] = $rec;
            } else {
                $seen[$artistId]['score'] = ($seen[$artistId]['score'] + ($rec['score'] ?? 0)) / 2;
            }
        }

        // Sort by score and return top limit
        usort($seen, function ($a, $b) {
            return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
        });

        return array_slice($seen, 0, $limit);
    }

    /**
     * Filter out artists user already follows
     */
    private function filterAlreadyFollowing(int $userId, array $artists): array
    {
        try {
            $artistIds = array_column($artists, 'artist_id');
            if (empty($artistIds)) {
                return $artists;
            }

            $placeholders = implode(',', array_fill(0, count($artistIds), '?'));
            $stmt = $this->readConnection->prepare(
                "SELECT artist_id FROM `ngn_2025`.`follows` WHERE user_id = ? AND artist_id IN ($placeholders)"
            );
            $params = array_merge([$userId], $artistIds);
            $stmt->execute($params);
            $following = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $followingSet = array_flip($following);

            return array_filter($artists, function ($artist) use ($followingSet) {
                return !isset($followingSet[$artist['artist_id']]);
            });
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error filtering following', ['error' => $e->getMessage()]);
            return $artists;
        }
    }

    /**
     * Filter recently recommended artists (7 days)
     */
    private function filterRecentlyRecommended(int $userId, array $artists): array
    {
        try {
            $artistIds = array_column($artists, 'artist_id');
            if (empty($artistIds)) {
                return $artists;
            }

            $placeholders = implode(',', array_fill(0, count($artistIds), '?'));
            $stmt = $this->readConnection->prepare(
                "SELECT DISTINCT fa.artist_id
                 FROM `ngn_2025`.`niko_discovery_digests` ndd,
                 JSON_TABLE(ndd.featured_artists, '$[*]' COLUMNS(artist_id INT PATH '$.artist_id')) AS fa
                 WHERE ndd.user_id = ?
                 AND fa.artist_id IN ($placeholders)
                 AND DATE_SUB(NOW(), INTERVAL 7 DAY) < ndd.created_at"
            );
            $params = array_merge([$userId], $artistIds);
            $stmt->execute($params);
            $recent = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $recentSet = array_flip($recent);

            return array_filter($artists, function ($artist) use ($recentSet) {
                return !isset($recentSet[$artist['artist_id']]);
            });
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error filtering recently recommended', ['error' => $e->getMessage()]);
            return $artists;
        }
    }

    /**
     * Apply genre diversity rules (max 40% from single genre)
     */
    private function applyDiversityRules(array $recommendations): array
    {
        $limit = count($recommendations);
        $maxPerGenre = max(1, (int) ceil($limit * 0.4));
        $genreCounts = [];
        $result = [];

        foreach ($recommendations as $rec) {
            $artistId = $rec['artist_id'];

            // Get artist genre
            try {
                $stmt = $this->readConnection->prepare(
                    'SELECT primary_genre FROM `ngn_2025`.`artists` WHERE id = ? LIMIT 1'
                );
                $stmt->execute([$artistId]);
                $artist = $stmt->fetch(PDO::FETCH_ASSOC);
                $genre = $artist['primary_genre'] ?? 'unknown';
            } catch (PDOException $e) {
                $genre = 'unknown';
            }

            if (!isset($genreCounts[$genre])) {
                $genreCounts[$genre] = 0;
            }

            if ($genreCounts[$genre] < $maxPerGenre) {
                $result[] = $rec;
                $genreCounts[$genre]++;
            }
        }

        return $result;
    }

    /**
     * Get cached recommendations for user
     */
    public function getCachedRecommendations(int $userId): ?array
    {
        try {
            $stmt = $this->readConnection->prepare(
                'SELECT recommended_artists FROM `ngn_2025`.`discovery_recommendations`
                 WHERE user_id = ? AND expires_at > NOW()'
            );
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return json_decode($result['recommended_artists'], true) ?: null;
            }
            return null;
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error getting cached recommendations', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Cache recommendations for user
     */
    public function cacheRecommendations(int $userId, array $recommendations, int $generationTime = 0): void
    {
        try {
            $this->writeConnection->prepare(
                'INSERT INTO `ngn_2025`.`discovery_recommendations` (user_id, recommended_artists, algorithm_version, generation_time_ms, expires_at)
                 VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
                 ON DUPLICATE KEY UPDATE
                 recommended_artists = VALUES(recommended_artists),
                 generation_time_ms = VALUES(generation_time_ms),
                 expires_at = VALUES(expires_at)'
            )->execute([
                $userId,
                json_encode($recommendations),
                'v1.0',
                $generationTime
            ]);
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error caching recommendations', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Invalidate cache for user
     */
    public function invalidateCache(int $userId): void
    {
        try {
            $this->writeConnection->prepare(
                'DELETE FROM `ngn_2025`.`discovery_recommendations` WHERE user_id = ?'
            )->execute([$userId]);
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error invalidating cache', ['error' => $e->getMessage()]);
        }
    }
}
