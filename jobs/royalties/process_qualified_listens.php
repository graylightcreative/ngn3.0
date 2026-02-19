<?php
/**
 * Cron Job: Process Qualified Listens for Royalty Distribution
 * Schedule: 0 * * * * (Every hour at :00)
 * Purpose: Process pending playback_events and create royalty transactions
 *
 * This job:
 * 1. Fetches all unprocessed qualified listens from playback_events
 * 2. Looks up royalty splits from cdm_rights_ledger/cdm_rights_splits
 * 3. Calculates royalty amounts and distributes to rights holders
 * 4. Creates transaction records in cdm_royalty_transactions
 * 5. Marks events as processed
 * 6. Logs processing stats for monitoring
 */

require_once __DIR__ . '/../../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Services\Royalties\PlaybackService;

try {
    $config = new Config();
    $playbackService = new PlaybackService($config);

    error_log("=== Process Qualified Listens Started ===");

    // Process up to 5000 events per run
    // This allows processing ~120,000 listens per day (hourly cron)
    $stats = $playbackService->processPendingEvents(5000);

    error_log(sprintf(
        "[Royalties] Processed: %d, Failed: %d, Total Royalties: $%.4f",
        $stats['processed'],
        $stats['failed'],
        $stats['total_royalties']
    ));

    if (!empty($stats['errors'])) {
        error_log("[Royalties] Errors encountered:");
        foreach ($stats['errors'] as $error) {
            error_log("  - {$error}");
        }
    }

    error_log("=== Process Qualified Listens Complete ===");
    exit(0);

} catch (Exception $e) {
    error_log("[Royalties] Fatal error: " . $e->getMessage());
    error_log($e->getTraceAsString());
    exit(1);
}
