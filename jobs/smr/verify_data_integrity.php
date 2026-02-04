<?php

/**
 * SMR Data Integrity Verification Cron Job
 * Chapter 24 - Rule 3: Bot Detection & Cemetery Logging
 *
 * Schedule: Daily at 1:00 AM UTC (0 1 * * *)
 *
 * Verifies row-level SHA-256 hashes for bot detection.
 * Flags duplicates/suspicious data to cemetery table.
 * Blocks bounties for flagged uploads.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;
use NGN\Lib\Smr\IntegrityVerificationService;

// Get database connection
$config = new Config();
$pdo = ConnectionFactory::write($config);

if (!$pdo) {
    die("Failed to connect to database\n");
}

// Check if bounty system is enabled
if (!($_ENV['SMR_ENABLE_BOUNTIES'] ?? true)) {
    echo "[verify_data_integrity] SMR bounty system disabled, exiting\n";
    exit(0);
}

$integrityService = new IntegrityVerificationService($pdo);

try {
    echo "[verify_data_integrity] Starting data integrity scan\n";

    // Phase 1: Scan for duplicate row hashes
    echo "  Phase 1: Scanning for duplicate hashes...\n";
    $duplicates = $integrityService->scanForDuplicateHashes();

    echo "    Found " . count($duplicates) . " duplicate hash set(s)\n";

    $flaggedCount = 0;

    foreach ($duplicates as $dup) {
        echo "    Hash: {$dup['row_hash']} appears {$dup['count']} times\n";

        // Flag each duplicate entry to cemetery
        $entryIds = array_filter(explode(',', $dup['entry_ids']));

        foreach ($entryIds as $entryId) {
            try {
                // Get entry data
                $stmt = $pdo->prepare("
                    SELECT id, source_id, artist_name
                    FROM cdm_chart_entries
                    WHERE id = ?
                ");
                $stmt->execute([$entryId]);
                $entry = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($entry) {
                    // Flag to cemetery
                    $cemeteryId = $integrityService->flagToCemetery([
                        'upload_id' => $entry['source_id'],
                        'failure_type' => 'duplicate_hash',
                        'expected_hash' => $dup['row_hash'],
                        'actual_hash' => $dup['row_hash'],
                        'row_number' => $entry['id'],
                        'artist_name' => $entry['artist_name'],
                        'detected_by' => 'automated_scan',
                    ]);

                    echo "      Entry {$entryId}: Flagged to cemetery (ID: {$cemeteryId})\n";

                    // Update chart entry to mark as flagged
                    $stmt = $pdo->prepare("
                        UPDATE cdm_chart_entries
                        SET flagged_in_cemetery = TRUE
                        WHERE id = ?
                    ");
                    $stmt->execute([$entryId]);

                    $flaggedCount++;
                }
            } catch (\Throwable $e) {
                echo "      ERROR: Failed to process entry {$entryId}: {$e->getMessage()}\n";
            }
        }
    }

    echo "\n  Phase 1 Complete: {$flaggedCount} entries flagged\n";

    // Phase 2: Block bounties for flagged entries
    echo "\n  Phase 2: Blocking bounties for flagged uploads...\n";

    $stmt = $pdo->prepare("
        SELECT DISTINCT source_id
        FROM cdm_chart_entries
        WHERE flagged_in_cemetery = TRUE
        AND source_type = 'smr'
    ");
    $stmt->execute();
    $flaggedUploads = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "    Found " . count($flaggedUploads) . " flagged upload(s)\n";

    $bountyBlockCount = 0;

    foreach ($flaggedUploads as $uploadId) {
        try {
            // Get affected artists
            $stmt = $pdo->prepare("
                SELECT DISTINCT artist_id
                FROM cdm_chart_entries
                WHERE source_id = ? AND flagged_in_cemetery = TRUE
            ");
            $stmt->execute([$uploadId]);
            $artistIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($artistIds as $artistId) {
                // Reverse pending bounties for this artist
                $stmt = $pdo->prepare("
                    UPDATE smr_bounty_transactions
                    SET status = 'reversed'
                    WHERE artist_id = ? AND status = 'pending'
                ");
                $stmt->execute([$artistId]);

                $reversedCount = $stmt->rowCount();
                if ($reversedCount > 0) {
                    echo "    Upload {$uploadId}: Reversed {$reversedCount} bounty/bounties for artist {$artistId}\n";
                    $bountyBlockCount += $reversedCount;
                }
            }
        } catch (\Throwable $e) {
            echo "    ERROR: Failed to block bounties for upload {$uploadId}: {$e->getMessage()}\n";
        }
    }

    echo "\n  Phase 2 Complete: {$bountyBlockCount} bounty transaction(s) reversed\n";

    // Phase 3: Get cemetery statistics
    echo "\n  Phase 3: Cemetery statistics\n";
    $stats = $integrityService->getBotDetectionStats();

    echo "    Total flagged items: {$stats['total_flagged']}\n";
    echo "    Pending review: {$stats['pending_review']}\n";
    echo "    Resolved: {$stats['resolved']}\n";
    echo "    False positives: {$stats['false_positives']}\n";
    echo "    Total bounties blocked: {$stats['total_bounties_blocked']}\n";

    echo "\n[verify_data_integrity] Summary:\n";
    echo "  Duplicate hash sets found: " . count($duplicates) . "\n";
    echo "  Entries flagged to cemetery: {$flaggedCount}\n";
    echo "  Bounty transactions reversed: {$bountyBlockCount}\n";
    echo "  Status: SUCCESS\n";

    exit(0);
} catch (\Throwable $e) {
    echo "[verify_data_integrity] FATAL ERROR: {$e->getMessage()}\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
