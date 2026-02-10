<?php

// This script is intended to be run via cron or a background worker.
// It processes scheduled email campaigns and populates the EmailQueue table.

require_once __DIR__ . '/../lib/bootstrap.php'; // Bootstrap NGN environment

use NGN\Lib\Config;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use App\Lib\Email\Mailer; // For potential direct sends if queue fails, or confirmation

// --- Configuration ---
$logFile = __DIR__ . '/../storage/logs/scheduler.log';
$batchSize = 50;

// --- Setup Logger ---
try {
    $logger = new Logger('mailing_list_scheduler');
    $logger->pushHandler(new StreamHandler($logFile, Logger::INFO));

    // Assume $pdo and $config are available from bootstrap.php
    if (!isset($pdo) || !($pdo instanceof \PDO)) {
        if (class_exists('NGN\Lib\Database\ConnectionFactory')) {
            $pdo = NGN\Lib\Database\ConnectionFactory::read(new Config());
        } else {
            throw new \RuntimeException("PDO connection not available and ConnectionFactory not found.");
        }
    }
    if (!isset($config) || !($config instanceof Config)) {
         $config = new Config();
    }

} catch (\Throwable $e) {
    error_log("Scheduler setup error: " . $e->getMessage());
    exit("Scheduler setup failed.");
}

$logger->info("Mailing list scheduler started.");

// --- Main Scheduler Logic ---
try {
    // 1. Get Active Campaigns that are due
    $campaignsStmt = $pdo->prepare(
        "SELECT * FROM EmailCampaigns WHERE status IN ('scheduled', 'sending') AND (
            (frequency_type = 'daily' AND (scheduled_run_at IS NULL OR scheduled_run_at <= NOW() - INTERVAL 24 HOUR)) OR
            (frequency_type = 'weekly' AND (scheduled_run_at IS NULL OR scheduled_run_at <= NOW() - INTERVAL 7 DAY)) OR
            (frequency_type = 'monthly' AND (scheduled_run_at IS NULL OR scheduled_run_at <= DATE_SUB(CURDATE(), INTERVAL 1 MONTH))) OR
            (frequency_type = 'once' AND status = 'scheduled') 
        )"
    );
    $campaignsStmt->execute();
    $activeCampaigns = $campaignsStmt->fetchAll(PDO::FETCH_ASSOC);

    $logger->info(sprintf("Found %d active campaigns to process.", count($activeCampaigns)));

    // 2. Process each campaign
    foreach ($activeCampaigns as $campaign) {
        $campaignId = $campaign['id'];
        $frequencyType = $campaign['frequency_type'];
        $targetGroupId = $campaign['target_group_id'];
        $currentStatus = $campaign['status'];

        $logger->info(sprintf("Processing Campaign ID: %d, Subject: %s, Frequency: %s, Status: %s", $campaignId, $campaign['subject'], $frequencyType, $currentStatus));

        // Update campaign status to 'sending' to prevent concurrent runs if it was 'scheduled'
        if ($currentStatus === 'scheduled') {
            $updateStatusStmt = $pdo->prepare("UPDATE EmailCampaigns SET status = 'sending' WHERE id = :campaign_id");
            $updateStatusStmt->execute([':campaign_id' => $campaignId]);
        }

        $users = [];
        $targetUsersQuery = "";

        // 3. Fetch Target Users
        if ($targetGroupId === null) { // Assuming NULL means 'All Users'
            // Fetch all users. Direct DB query is assumed here for simplicity in a script.
            // If API call is strictly required, this part would need an HTTP client.
            $userStmt = $pdo->query("SELECT Id FROM users WHERE status = 'active'");
            $users = $userStmt->fetchAll(PDO::FETCH_COLUMN);
            $logger->info(sprintf("Fetched %d users for Campaign ID %d (All Users)."));
        } elseif ($targetGroupId == 3) { // Artists (Role ID 3)
            $userStmt = $pdo->prepare("SELECT Id FROM users WHERE role_id = 3 AND status = 'active'");
            $userStmt->execute();
            $users = $userStmt->fetchAll(PDO::FETCH_COLUMN);
            $logger->info(sprintf("Fetched %d users for Campaign ID %d (Role: Artist)."));
        } elseif ($targetGroupId == 7) { // Labels (Role ID 7)
            $userStmt = $pdo->prepare("SELECT Id FROM users WHERE role_id = 7 AND status = 'active'");
            $userStmt->execute();
            $users = $userStmt->fetchAll(PDO::FETCH_COLUMN);
            $logger->info(sprintf("Fetched %d users for Campaign ID %d (Role: Label)."));
        } else {
            $logger->warning(sprintf("Unknown target_group_id %d for Campaign ID %d. Skipping user fetch.", $targetGroupId, $campaignId));
        }

        // 4. Populate EmailQueue in batches
        if (!empty($users)) {
            $totalUsers = count($users);
            $insertedCount = 0;
            $batchNumber = 1;
            
            $logger->info(sprintf("Queueing %d emails for Campaign ID %d.", $totalUsers, $campaignId));

            $stmtQueue = $pdo->prepare(
                "INSERT INTO EmailQueue (campaign_id, user_id, send_at, status) VALUES (:campaign_id, :user_id, NOW(), 'pending')"
            );

            $pdo->beginTransaction(); // Start transaction for batch insert
            try {
                foreach (array_chunk($users, $batchSize) as $batch) {
                    foreach ($batch as $userId) {
                        // Ensure user is not unsubscribed for this campaign type/list
                        // Basic check: if unsubscribe for 'all_campaigns' or specific campaign type exists.
                        // For now, assuming no unsubscribe check here, but it should be added.
                        // Example check: SELECT 1 FROM Unsubscribes WHERE user_id = ? AND list_type = ?
                        
                        $stmtQueue->execute([
                            ':campaign_id' => $campaignId,
                            ':user_id' => $userId
                        ]);
                        $insertedCount++;
                    }
                    $pdo->commit(); // Commit batch
                    $logger->info(sprintf("Committed batch %d (%d records) for Campaign ID %d.", $batchNumber, count($batch), $campaignId));
                    $batchNumber++;
                    $pdo->beginTransaction(); // Start new transaction for next batch
                }
                $pdo->commit(); // Commit any remaining transactions

            } catch (\PDOException $e) {
                $pdo->rollBack(); // Rollback on error
                $logger->error(sprintf("Database error during queue insertion for Campaign ID %d: %s", $campaignId, $e->getMessage()));
            }

            // 5. Update campaign status after processing
            $finalStatus = ($insertedCount > 0) ? 'scheduled' : 'failed'; // If no users found or error, set to failed?
            // A better approach might be to track if any emails were actually queued, not just if users were found.
            // For simplicity, if users were processed, keep it 'scheduled' for next run if frequency allows, or 'sent' if it was 'once'.
            // If no users found, maybe set to 'completed' or 'failed' depending on frequency.

            $finalStatus = ($frequencyType === 'once' && $insertedCount > 0) ? 'sent' : 'scheduled';
            if ($insertedCount == 0 && $currentStatus === 'sending') {
                 // If no users were found, and it was scheduled to send, mark as 'failed' or 'completed' with no action.
                 // For simplicity, let's mark as 'completed' if no users to send to.
                 $finalStatus = 'completed';
                 $logger->info(sprintf("No users found for Campaign ID %d. Marked as completed.", $campaignId));
            } else {
                $logger->info(sprintf("Queued %d emails for Campaign ID %d. Updating status to %s.", $insertedCount, $campaignId, $finalStatus));
            }

            $updateFinalStatusStmt = $pdo->prepare("UPDATE EmailCampaigns SET status = :status, scheduled_run_at = NOW() WHERE id = :campaign_id");
            $updateFinalStatusStmt->execute([':status' => $finalStatus, ':campaign_id' => $campaignId]);

        } else {
            // No users found for this campaign
            $logger->info(sprintf("No target users found for Campaign ID %d. Marking as completed.", $campaignId));
            // Update campaign status to 'completed' if no users were found
            $updateFinalStatusStmt = $pdo->prepare("UPDATE EmailCampaigns SET status = 'completed', scheduled_run_at = NOW() WHERE id = :campaign_id");
            $updateFinalStatusStmt->execute([':status' => 'completed', ':campaign_id' => $campaignId]);
        }
    }

    $logger->info("Mailing list scheduler finished.");

} catch (\Throwable $e) {
    $logger->critical("Scheduler encountered a critical error: " . $e->getMessage());
    // Attempt to update all running campaigns to failed status if an error occurs mid-process
    $updateAllFailedStmt = $pdo->prepare("UPDATE EmailCampaigns SET status = 'failed' WHERE status = 'sending'");
    $updateAllFailedStmt->execute();
    exit("Scheduler failed.");
}

?>
