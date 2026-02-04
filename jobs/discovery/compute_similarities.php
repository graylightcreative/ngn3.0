<?php

/**
 * Discovery Engine - Compute Similarities Cron Job
 * Batch computes artist similarity scores
 * Schedule: Daily at 3 AM
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use NGN\Config;
use NGN\Lib\Database\ConnectionFactory;
use NGN\Lib\Discovery\SimilarityService;
use NGN\Lib\Logger\LoggerFactory;
use PDO;

$config = Config::getInstance();
$logger = LoggerFactory::getLogger('discovery');
$readConnection = ConnectionFactory::read();

$startTime = microtime(true);
$processedArtists = 0;
$errors = 0;

try {
    $logger->info('Starting similarity computation job');

    // Get artists with no recent similarity computation (>7 days)
    $stmt = $readConnection->prepare(
        'SELECT id FROM artists
         WHERE id NOT IN (
             SELECT artist_id FROM artist_similarity WHERE computed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
         )
         AND status = "active"
         LIMIT 100'
    );
    $stmt->execute();
    $artistIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $similarityService = new SimilarityService($config);

    foreach ($artistIds as $artistId) {
        try {
            $start = microtime(true);
            $similarityService->batchComputeSimilarities((int) $artistId);
            $duration = round(microtime(true) - $start, 2);

            if ($duration > 60) {
                $logger->warning('Artist similarity computation took too long', [
                    'artist_id' => $artistId,
                    'duration' => $duration
                ]);
            }

            $processedArtists++;
        } catch (Exception $e) {
            $logger->error('Error computing artist similarities', [
                'artist_id' => $artistId,
                'error' => $e->getMessage()
            ]);
            $errors++;
        }
    }

    $executionTime = round(microtime(true) - $startTime, 2);

    $logger->info('Similarity computation job completed', [
        'execution_time' => $executionTime,
        'processed_artists' => $processedArtists,
        'errors' => $errors
    ]);
} catch (Exception $e) {
    $logger->error('Fatal error in similarity computation job', ['error' => $e->getMessage()]);
    exit(1);
}

exit(0);
