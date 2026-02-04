<?php

/**
 * Discovery Engine - Update Affinities Cron Job
 * Updates user affinity scores based on recent engagement activity
 * Schedule: Every 30 minutes
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use NGN\Config;
use NGN\Lib\Database\ConnectionFactory;
use NGN\Lib\Discovery\AffinityService;
use NGN\Lib\Logger\LoggerFactory;
use PDO;

$config = Config::getInstance();
$logger = LoggerFactory::getLogger('discovery');
$readConnection = ConnectionFactory::read();

$startTime = microtime(true);
$processedUsers = 0;
$errors = 0;

try {
    $logger->info('Starting affinity update job');

    // Get users with engagement in last 30 minutes
    $stmt = $readConnection->prepare(
        'SELECT DISTINCT user_id FROM cdm_engagements
         WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
         LIMIT 5000'
    );
    $stmt->execute();
    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $affinityService = new AffinityService($config);

    foreach ($userIds as $userId) {
        try {
            // Get recent engagements for this user
            $stmt = $readConnection->prepare(
                'SELECT DISTINCT artist_id, type FROM cdm_engagements
                 WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)'
            );
            $stmt->execute([$userId]);
            $engagements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Update affinity for each artist
            foreach ($engagements as $engagement) {
                $affinityService->updateAffinityFromEngagement(
                    (int) $userId,
                    (int) $engagement['artist_id'],
                    $engagement['type'] === 'spark' ? 'spark' : 'engagement',
                    1.0
                );
            }

            $processedUsers++;
        } catch (Exception $e) {
            $logger->error('Error processing user affinities', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            $errors++;
        }
    }

    $executionTime = round(microtime(true) - $startTime, 2);

    // Alert if processing took too long
    if ($executionTime > 300) {
        $logger->warning('Affinity update job took too long', [
            'execution_time' => $executionTime,
            'processed_users' => $processedUsers,
            'errors' => $errors
        ]);
    } else {
        $logger->info('Affinity update job completed', [
            'execution_time' => $executionTime,
            'processed_users' => $processedUsers,
            'errors' => $errors
        ]);
    }
} catch (Exception $e) {
    $logger->error('Fatal error in affinity update job', ['error' => $e->getMessage()]);
    exit(1);
}

exit(0);
