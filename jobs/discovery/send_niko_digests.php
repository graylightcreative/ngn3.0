<?php

/**
 * Discovery Engine - Send Niko's Discovery Digests Cron Job
 * Sends weekly "Niko's Discovery" emails to eligible users
 * Schedule: Every Monday at 9 AM
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use NGN\Config;
use NGN\Lib\Discovery\NikoDiscoveryService;
use NGN\Lib\Logger\LoggerFactory;

$config = Config::getInstance();
$logger = LoggerFactory::getLogger('discovery');

$startTime = microtime(true);
$sent = 0;
$failed = 0;
$skipped = 0;
$batchSize = 500;

try {
    $logger->info('Starting Niko Discovery digest job');

    $nikoService = new NikoDiscoveryService($config);

    // Get eligible users
    $recipients = $nikoService->getDigestRecipients();
    $logger->info('Digest recipients found', ['count' => count($recipients)]);

    // Process in batches
    $batches = array_chunk($recipients, $batchSize);

    foreach ($batches as $batch) {
        try {
            $result = $nikoService->sendBatchDigests($batch);
            $sent += $result['sent'] ?? 0;
            $failed += $result['failed'] ?? 0;
            $skipped += $result['skipped'] ?? 0;
        } catch (Exception $e) {
            $logger->error('Error sending batch digests', ['error' => $e->getMessage()]);
            $failed += count($batch);
        }
    }

    $executionTime = round(microtime(true) - $startTime, 2);
    $totalAttempted = $sent + $failed + $skipped;
    $failureRate = $totalAttempted > 0 ? round($failed / $totalAttempted * 100, 2) : 0;

    // Alert if failure rate is high
    if ($failureRate > 5) {
        $logger->warning('High digest failure rate', [
            'failure_rate' => $failureRate,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped
        ]);
    } else {
        $logger->info('Niko Discovery digest job completed', [
            'execution_time' => $executionTime,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'failure_rate' => $failureRate
        ]);
    }
} catch (Exception $e) {
    $logger->error('Fatal error in Niko Discovery digest job', ['error' => $e->getMessage()]);
    exit(1);
}

exit(0);
