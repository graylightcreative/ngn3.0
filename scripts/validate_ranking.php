<?php

// This script validates the NGN ranking algorithm by comparing a manually calculated score
// against the score stored in the database for a sample artist.

require_once __DIR__ . '/../../lib/bootstrap.php'; // Bootstrap NGN environment

use NGN\Lib\Config;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// --- Configuration ---
$logFile = __DIR__ . '/../storage/logs/ranking_validation.log';
$artistNameFilter = 'Sleep Theory'; // Target artist name for validation
$scoreMismatchTolerance = 0.01; // Allowable difference for score match (1%)

// --- Define Weights (from NGN v2.0 Factors) ---
// These should ideally be fetched from environment variables or a dedicated config service.
// For this script, we'll define them directly as per the prompt.
$weights = [
    'SMR_Score' => 10,
    'Release_Count' => 250,
    'Video_View_Count' => 25,
    // Other weights mentioned but not used in the calculation example:
    // 'ARTIST_MERCH_COUNT_WEIGHT' => 300, // Not used in manual calculation formula
    // 'LABEL_REPUTATION_WEIGHT' => 250,   // Not directly applicable for artist validation
    // 'ARTIST_SPIN_COUNT_WEIGHT' => 0.25, // Not directly applicable for manual calc
    // 'ARTIST_STATION_ADD_WEIGHT' => 100, // Not directly applicable for manual calc
];

// --- Setup Logger ---
try {
    $logger = new Logger('ranking_validation');
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
    error_log("Ranking validation script setup error: " . $e->getMessage());
    exit("Ranking validation script setup failed.");
}

$logger->info("Ranking validation script started.");

// --- Validation Logic ---
try {
    // --- Step 1: Find a Test Artist ---
    // Query the 'users' table for an artist (role_id = 3).
    // Select the first artist found for testing.
    $artistStmt = $pdo->prepare("SELECT Id, Name FROM users WHERE role_id = 3 LIMIT 1");
    $artistStmt->execute();
    $artist = $artistStmt->fetch(PDO::FETCH_ASSOC);

    if (!$artist) {
        $logger->error("No artist found in the database. Cannot proceed with validation.");
        throw new \RuntimeException("No artist found. Please ensure there are artists with role_id 3.");
    }

    $artistId = $artist['Id'];
    $artistName = $artist['Name'];
    $logger->info(sprintf("Selected test artist: ID=%d, Name='%s'.", $artistId, $artistName));

    // --- Step 2: Fetch Artist Metrics and Actual NGN Score ---
    // Query the NGNArtistRankings table for the selected artist's metrics and current score.
    // Using *_Active columns as they represent the current state used in rankings.
    $rankingStmt = $pdo->prepare(
        "SELECT SMR_Score_Active, Releases_Score_Active, Videos_Score_Active, Score " .
        "FROM NGNArtistRankings WHERE ArtistId = :artist_id LIMIT 1"
    );
    $rankingStmt->execute([':artist_id' => $artistId]);
    $rankingData = $rankingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$rankingData) {
        $logger->error("Ranking data not found for artist ID {$artistId}. Cannot validate.");
        throw new \RuntimeException("Ranking data not found for artist. Ensure rankings are computed.");
    }

    $smrScore = (float)($rankingData['SMR_Score_Active'] ?? 0.0);
    // IMPORTANT ASSUMPTION: Using *_Score_Active fields as proxies for raw counts 
    // (Release Count and Video View Count) due to lack of direct count fields.
    // If raw counts are available elsewhere, this manual calculation might be inaccurate.
    $releaseCountProxy = (float)($rankingData['Releases_Score_Active'] ?? 0.0);
    $videoViewCountProxy = (float)($rankingData['Videos_Score_Active'] ?? 0.0);
    $actualSystemScore = (float)($rankingData['Score'] ?? 0.0);

    $logger->info(sprintf("Fetched metrics for %s (ID %d): SMR_Score=%.2f, Releases_Score_Proxy=%.2f, Videos_Score_Proxy=%.2f, Actual_System_Score=%.2f",
        $artistName, $artistId, $smrScore, $releaseCountProxy, $videoViewCountProxy, $actualSystemScore));

    // --- Step 3: Manually Calculate Expected Score ---
    // Formula: (SMR Score * 10) + (Release Count * 250) + (Video View Count * 25)
    $expectedScore = ($smrScore * $weights['SMR_Score']) +
                     ($releaseCountProxy * $weights['Release_Count']) +
                     ($videoViewCountProxy * $weights['Video_View_Count']);

    $logger->info(sprintf("Manually calculated expected score: (%.2f * %d) + (%.2f * %d) + (%.2f * %d) = %.2f",
        $smrScore, $weights['SMR_Score'], $releaseCountProxy, $weights['Release_Count'], $videoViewCountProxy, $weights['Video_View_Count'], $expectedScore));

    // --- Step 4: Compare Expected vs. Actual Score ---
    $scoreDifference = abs($expectedScore - $actualSystemScore);

    $logger->info(sprintf("Comparison: Expected Score = %.2f, Actual System Score = %.2f, Difference = %.2f",
        $expectedScore, $actualSystemScore, $scoreDifference));

    if ($scoreDifference > $tolerance) {
        $errorMessage = sprintf("Score Mismatch Alert! Expected Score (%.2f) does not match Actual System Score (%.2f) for artist '%s' (ID: %d). Difference: %.2f",
            $expectedScore, $actualSystemScore, $artistName, $artistId, $scoreDifference);
        $logger->error($errorMessage);
        
        echo "\n*** ALERT: RANKING ALGORITHM WEIGHT MISMATCH DETECTED ***\n";
        echo "Artist: %s (ID: %d)\n", $artistName, $artistId;
        echo "Expected Score (Manual Calc): %.2f\n", $expectedScore;
        echo "Actual System Score: %.2f\n", $actualSystemScore;
        echo "Difference: %.2f\n", $scoreDifference;
        echo "\nStatus: MISMATCH - Please investigate the ranking algorithm calculation and weights.\n";
    } else {
        echo "Validation Passed: Expected score (%.2f) matches Actual System Score (%.2f) for artist '%s' (ID: %d) within tolerance.\n", $expectedScore, $actualSystemScore, $artistName, $artistId;
        $logger->info("Score validation passed.");
    }

} catch (\Throwable $e) {
    $logger->critical("Ranking validation script encountered a critical error: " . $e->getMessage());
    echo "\n*** CRITICAL ERROR DURING VALIDATION ***\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit("Ranking validation script failed.");
}

?>