<?php

// This script is a recurring job to automate and maintain the Artist/Entity Linkage Rate.
// It processes recent unresolved SMR ingestions, attempts to match them to existing entities,
// and updates the cdm_identity_map and ingestion status.
// Intended to be run via cron every 5 minutes.

require_once __DIR__ . '/../../lib/bootstrap.php'; // Bootstrap NGN environment

use NGN\Lib\Config;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// --- Configuration ---
$logFile = __DIR__ . '/../storage/logs/linkage_resolver.log';
$batchSize = 100; // Process in batches to manage resources

// --- Setup Logger ---
try {
    $logger = new Logger('linkage_resolver');
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
    error_log("Linkage Resolver setup error: " . $e->getMessage());
    exit("Linkage Resolver setup failed.");
}

$logger->info('Linkage Resolver job started.');

// --- Main Logic ---
try {
    // --- 1. Scope: Query recent unresolved entries in smr_ingestions ---
    // Assuming smr_ingestions table exists with columns: id, ArtistName, TrackTitle, MatchStatus
    $unresolvedStmt = $pdo->prepare(
        "SELECT id, ArtistName, TrackTitle 
         FROM smr_ingestions 
         WHERE MatchStatus = 'unresolved' 
         ORDER BY id ASC LIMIT :batchSize"
    );
    $unresolvedStmt->bindParam(':batchSize', $batchSize, PDO::PARAM_INT);
    $unresolvedStmt->execute();
    $unresolvedEntries = $unresolvedStmt->fetchAll(PDO::FETCH_ASSOC);

    $logger->info(sprintf('Found %d unresolved entries to process.', count($unresolvedEntries)));

    if (empty($unresolvedEntries)) {
        $logger->info('No unresolved entries found. Exiting.');
        exit('No unresolved entries found.');
    }

    // Process entries in batches for updates
    $matchedCount = 0;
    $failedCount = 0;

    foreach ($unresolvedEntries as $entry) {
        $ingestionId = $entry['id'];
        $artistNameIngested = $entry['ArtistName'];
        // TrackTitle might be useful for matching, but prompt focuses on artist name.
        // $trackTitleIngested = $entry['TrackTitle']; 

        $matchedEntityId = null;
        $matchedEntityType = null;

        // --- 2. Action: Use fuzzy matching or heuristics to map names ---
        // Fuzzy matching logic requires a library or heuristic implementation.
        // For simplicity, we'll use a basic LIKE query for demonstration.
        // A real implementation might use a fuzzy matching library (e.g., fuzzysearch) or implement Levenshtein distance.

        // Try to match ArtistName against 'artists' table (assuming artists are stored in ngn_2025.artists)
        $artistMatchStmt = $pdo->prepare(
            "SELECT a.id, a.name
             FROM `ngn_2025`.`artists` a
             JOIN `ngn_2025`.`users` u ON a.user_id = u.id
             WHERE u.role_id = 3 AND (a.name LIKE :artistName OR a.name LIKE :artistNameFuzzy) ORDER BY a.id ASC LIMIT 1"
        );
        // Basic fuzzy match for artist name
        $artistMatchStmt->execute([
            ':artistName' => $artistNameIngested,
            ':artistNameFuzzy' => str_replace(' ', '%', $artistNameIngested) // Basic fuzzy like 'The Band' -> 'The%Band'
        ]);
        $matchedArtist = $artistMatchStmt->fetch(PDO::FETCH_ASSOC);

        if ($matchedArtist) {
            $matchedEntityId = $matchedArtist['Id'];
            $matchedEntityType = 'artist';
            $logger->info(sprintf("Matched ingested artist '%s' to user ID %d ('%s').", $artistNameIngested, $matchedEntityId, $matchedArtist['Name']));
        } else {
            // Try to match against labels if artist match fails
            $labelMatchStmt = $pdo->prepare(
                "SELECT id, name FROM `ngn_2025`.`labels` WHERE name LIKE :artistName ORDER BY id ASC LIMIT 1"
            );
            $labelMatchStmt->execute([':artistName' => $artistNameIngested]);
            $matchedLabel = $labelMatchStmt->fetch(PDO::FETCH_ASSOC);
            if ($matchedLabel) {
                $matchedEntityId = $matchedLabel['Id'];
                $matchedEntityType = 'label';
                $logger->info(sprintf("Matched ingested artist '%s' to label ID %d ('%s').", $artistNameIngested, $matchedEntityId, $matchedLabel['Name']));
            }
        }

        // --- 3. Update ---
        if ($matchedEntityId && $matchedEntityType) {
            try {
                // Begin transaction for consistency
                $pdo->beginTransaction();

                // Update cdm_identity_map
                // Assuming cdm_identity_map has columns: source_name, entity_type, entity_id, matched_at
                $mapInsertStmt = $pdo->prepare(
                    "INSERT IGNORE INTO cdm_identity_map (source_name, entity_type, entity_id, matched_at) VALUES (:source_name, :entity_type, :entity_id, NOW())"
                );
                $mapInsertSuccess = $mapInsertStmt->execute([
                    ':source_name' => $artistNameIngested,
                    ':entity_type' => $matchedEntityType,
                    ':entity_id' => $matchedEntityId
                ]);

                if ($mapInsertSuccess) {
                    // Change MatchStatus to 'matched' in smr_ingestions
                    $updateStmt = $pdo->prepare("UPDATE smr_ingestions SET MatchStatus = 'matched' WHERE id = :ingestion_id");
                    $updateSuccess = $updateStmt->execute([':ingestion_id' => $ingestionId]);

                    if ($updateSuccess) {
                        $pdo->commit(); // Commit transaction
                        $matchedCount++;
                        $logger->info(sprintf("Successfully matched and updated ingestion ID %d.", $ingestionId));
                    } else {
                        $pdo->rollBack(); // Rollback on update failure
                        $failedCount++;
                        $logger->error(sprintf("Failed to update MatchStatus for ingestion ID %d.", $ingestionId));
                    }
                } else {
                    $pdo->rollBack(); // Rollback on map insert failure
                    $failedCount++;
                    $logger->error(sprintf("Failed to insert into cdm_identity_map for ingestion ID %d.", $ingestionId));
                }

            } catch (\Throwable $e) {
                $pdo->rollBack(); // Rollback on any exception
                $failedCount++;
                $logger->error(sprintf("Error processing matched entry ID %d: %s", $ingestionId, $e->getMessage()));
            }
        } else {
            // No match found, keep status as 'unresolved' or mark as 'failed' if needed.
            // For now, we don't change status if no match is found, to allow retries.
            $logger->info(sprintf("No match found for ingested artist '%s' (Ingestion ID: %d)."));
        }
    }

    $logger->info(sprintf('Linkage resolution finished. Processed: %d. Matched: %d. Failed/No Match: %d.', count($unresolvedEntries), $matchedCount, $failedCount));

} catch (\Throwable $e) {
    $logger->critical("Linkage Resolver job encountered a critical error: " . $e->getMessage());
    exit("Linkage Resolver job failed.");
}

?>
