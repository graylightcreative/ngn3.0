<?php

/**
 * Discovery Engine - Generate Recommendations Cron Job
 * Pre-generates recommendations for active users
 * Schedule: Daily at 4 AM
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use NGN\Config;
use NGN\Lib\Database\ConnectionFactory;
use NGN\Lib\Discovery\DiscoveryEngineService;
use NGN\Lib\Logger\LoggerFactory;
use PDO;

$config = Config::getInstance();
$logger = LoggerFactory::getLogger('discovery');
$readConnection = ConnectionFactory::read();

$startTime = microtime(true);
$processedUsers = 0;
$cacheHits = 0;
$cacheMisses = 0;
$errors = 0;
$batchSize = 1000;

try {
    $logger->info('Starting recommendation generation job');

    // Fetch active users (logged in last 7 days)
    $stmt = $readConnection->prepare(
        'SELECT id FROM `ngn_2025`.`users`
         WHERE status = "active"
         AND last_login > DATE_SUB(NOW(), INTERVAL 7 DAY)
         LIMIT 50000'
    );
    $stmt->execute();
    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $discoveryEngine = new DiscoveryEngineService($config);

    // Process in batches
    $batches = array_chunk($userIds, $batchSize);

    foreach ($batches as $batch) {
        foreach ($batch as $userId) {
            try {
                $cached = $discoveryEngine->getCachedRecommendations((int) $userId);
                if ($cached) {
                    $cacheHits++;
                } else {
                    $discoveryEngine->getRecommendedArtists((int) $userId, 10);
                    $cacheMisses++;
                }
                $processedUsers++;
            } catch (Exception $e) {
                $logger->error('Error generating recommendations for user', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                $errors++;
            }
        }
    }

    $executionTime = round(microtime(true) - $startTime, 2);
    $cacheHitRate = $processedUsers > 0 ? round($cacheHits / $processedUsers * 100, 2) : 0;

    // Alert if cache hit rate is low
    if ($cacheHitRate < 70) {
        $logger->warning('Low recommendation cache hit rate', [
            'cache_hit_rate' => $cacheHitRate,
            'hits' => $cacheHits,
            'misses' => $cacheMisses
        ]);
    } else {
        $logger->info('Recommendation generation job completed', [
            'execution_time' => $executionTime,
            'processed_users' => $processedUsers,
            'cache_hit_rate' => $cacheHitRate,
            'errors' => $errors
        ]);
    }
} catch (Exception $e) {
    $logger->error('Fatal error in recommendation generation job', ['error' => $e->getMessage()]);
    exit(1);
}

exit(0);
