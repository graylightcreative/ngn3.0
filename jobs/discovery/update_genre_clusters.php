<?php

/**
 * Discovery Engine - Update Genre Clusters Cron Job
 * Maintains genre_clusters table with current artist/user membership
 * Schedule: Daily at 5 AM
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use NGN\Config;
use NGN\Lib\Database\ConnectionFactory;
use NGN\Lib\Logger\LoggerFactory;
use PDO;

$config = Config::getInstance();
$logger = LoggerFactory::getLogger('discovery');
$readConnection = ConnectionFactory::read();
$writeConnection = ConnectionFactory::write();

$startTime = microtime(true);
$processedGenres = 0;
$errors = 0;

try {
    $logger->info('Starting genre clusters update job');

    // Get all genres with clusters
    $stmt = $readConnection->prepare(
        'SELECT DISTINCT slug FROM `ngn_2025`.`genres` ORDER BY name'
    );
    $stmt->execute();
    $genres = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($genres as $genre) {
        try {
            $genreSlug = $genre['slug'];

            // Get artists with primary or secondary genre match
            $stmt = $readConnection->prepare(
                            'SELECT id FROM `ngn_2025`.`artists`
                             WHERE (primary_genre = ? OR secondary_genre = ?)
                             AND status = "active"'            );
            $stmt->execute([$genreSlug, $genreSlug]);
            $artistIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Get users with affinity for this genre
            $stmt = $readConnection->prepare(
                            'SELECT id FROM `ngn_2025`.`users`
                             WHERE id IN (
                                 SELECT DISTINCT user_id FROM `ngn_2025`.`user_genre_affinity`
                                 WHERE genre_slug = ? AND affinity_score >= 50
                             )'            );
            $stmt->execute([$genreSlug]);
            $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Calculate average NGN Score for genre
            $placeholders = implode(',', array_fill(0, count($artistIds), '?'));
            $avgNgnScore = 0;

            if (!empty($artistIds)) {
                $stmt = $readConnection->prepare(
                                "SELECT AVG(ais.ngn_score) as avg_score
                                 FROM `ngn_2025`.`artist_intelligence_scores` ais
                                 WHERE ais.artist_id IN ($placeholders)"                );
                $stmt->execute($artistIds);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $avgNgnScore = $result['avg_score'] ?? 0;
            }

            // Update genre_clusters table
            $stmt = $readConnection->prepare(
                'SELECT id FROM `ngn_2025`.`genre_clusters` WHERE genre_slug = ? LIMIT 1'
            );
            $stmt->execute([$genreSlug]);
            $cluster = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($cluster) {
                $writeConnection->prepare(
                    'UPDATE `ngn_2025`.`genre_clusters`
                     SET artist_ids = ?, artist_count = ?, user_ids = ?, user_count = ?, avg_ngn_score = ?, updated_at = NOW()
                     WHERE genre_slug = ?'
                )->execute([
                    json_encode($artistIds),
                    count($artistIds),
                    json_encode($userIds),
                    count($userIds),
                    round($avgNgnScore, 4),
                    $genreSlug
                ]);
            } else {
                $writeConnection->prepare(
                    'INSERT INTO `ngn_2025`.`genre_clusters` (genre_slug, genre_name, artist_ids, artist_count, user_ids, user_count, avg_ngn_score)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $genreSlug,
                    $genre['name'] ?? ucfirst(str_replace('-', ' ', $genreSlug)),
                    json_encode($artistIds),
                    count($artistIds),
                    json_encode($userIds),
                    count($userIds),
                    round($avgNgnScore, 4)
                ]);
            }

            // Alert if genre has 0 artists
            if (count($artistIds) === 0) {
                $logger->warning('Genre has no artists', ['genre_slug' => $genreSlug]);
            }

            $processedGenres++;
        } catch (Exception $e) {
            $logger->error('Error updating genre cluster', [
                'genre_slug' => $genre['slug'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            $errors++;
        }
    }

    $executionTime = round(microtime(true) - $startTime, 2);

    $logger->info('Genre clusters update job completed', [
        'execution_time' => $executionTime,
        'processed_genres' => $processedGenres,
        'errors' => $errors
    ]);
} catch (Exception $e) {
    $logger->error('Fatal error in genre clusters update job', ['error' => $e->getMessage()]);
    exit(1);
}

exit(0);
