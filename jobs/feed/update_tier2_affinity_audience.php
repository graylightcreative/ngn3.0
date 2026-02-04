<?php

/**
 * Update Tier 2 Affinity Audience
 *
 * Updates the materialized view of users eligible for Tier 2 (Affinity) pushes.
 *
 * Logic:
 * 1. For each user with genre affinity > 50
 * 2. Fetch their affinity scores from user_genre_affinity
 * 3. Filter: affinity_score > 50
 * 4. Insert into tier2_affinity_audience
 * 5. Mark already_follows = true if following creator
 * 6. Delete old records (> 7 days)
 *
 * Bible Ch. 22: Social Feed & Engagement Algorithm
 *
 * Schedule: 0 2 * * * (daily at 2 AM)
 * Command: php /path/to/jobs/feed/update_tier2_affinity_audience.php
 */

require_once __DIR__ . '/../../lib/autoload.php';
require_once __DIR__ . '/../../lib/config/config.php';

use NGN\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Logger\LoggerFactory;

// Log file
$logFile = __DIR__ . '/../../storage/logs/feed_tier2_audience.log';

function logMessage(string $message): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);
    echo $logLine;
}

try {
    logMessage("=== Starting Tier 2 affinity audience update ===");

    $config = Config::getInstance();
    $readConn = ConnectionFactory::read();
    $writeConn = ConnectionFactory::write();

    // Get all users with genre affinity > 50
    $stmt = $readConn->prepare("
        SELECT user_id, genre_slug, affinity_score
        FROM user_genre_affinity
        WHERE affinity_score > 50
        ORDER BY affinity_score DESC
    ");
    $stmt->execute();
    $affinityRecords = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if (empty($affinityRecords)) {
        logMessage("No users with affinity > 50 found");
        exit(0);
    }

    logMessage(sprintf("Found %d user-genre affinity pairs", count($affinityRecords)));

    $writeConn->beginTransaction();

    try {
        // Clear existing audience
        $clearStmt = $writeConn->prepare("DELETE FROM tier2_affinity_audience");
        $clearStmt->execute();
        logMessage("Cleared existing tier2_affinity_audience records");

        // Insert qualified users
        $insertStmt = $writeConn->prepare("
            INSERT INTO tier2_affinity_audience (
                user_id, genre_slug, affinity_score, last_updated_at
            ) VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                affinity_score = ?,
                last_updated_at = NOW()
        ");

        $insertedCount = 0;
        foreach ($affinityRecords as $record) {
            $insertStmt->execute([
                $record['user_id'],
                $record['genre_slug'],
                $record['affinity_score'],
                $record['affinity_score']
            ]);
            $insertedCount++;
        }

        logMessage(sprintf("Inserted %d user-genre affinity pairs into tier2_affinity_audience", $insertedCount));

        // Mark already_follows for users who follow creators
        $followStmt = $writeConn->prepare("
            UPDATE tier2_affinity_audience taa
            SET already_follows = 1
            WHERE EXISTS (
                SELECT 1 FROM follows f
                WHERE f.user_id = taa.user_id
                AND f.deleted_at IS NULL
            )
        ");
        $followStmt->execute();
        logMessage(sprintf("Marked %d users as already following", $followStmt->rowCount()));

        $writeConn->commit();
        logMessage("Transaction committed");

    } catch (\Exception $e) {
        $writeConn->rollBack();
        throw $e;
    }

    // Check for genres with insufficient audience
    $genreStmt = $readConn->prepare("
        SELECT genre_slug, COUNT(*) as user_count
        FROM tier2_affinity_audience
        GROUP BY genre_slug
        HAVING user_count < 100
    ");
    $genreStmt->execute();
    $lowGenres = $genreStmt->fetchAll(\PDO::FETCH_ASSOC);

    if (!empty($lowGenres)) {
        logMessage(sprintf("WARNING: %d genres with < 100 affinity users:", count($lowGenres)));
        foreach ($lowGenres as $genre) {
            logMessage(sprintf("  Genre: %s (%d users)", $genre['genre_slug'], $genre['user_count']));
        }
    }

    logMessage("=== Tier 2 audience update complete ===");

    exit(0);
} catch (\Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
    LoggerFactory::getLogger('feed')->error('Tier 2 audience update job failed', [
        'error' => $e->getMessage()
    ]);
    exit(1);
}
