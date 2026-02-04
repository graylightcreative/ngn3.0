<?php

// This script validates chart data quality and adherence to NGN v2.0 fairness policies.
// It checks station coverage and linkage rates, generates a fairness summary, and flags runs if thresholds are not met.
// Intended to be run via cron or as part of a CI/CD pipeline.

require_once __DIR__ . '/../../lib/bootstrap.php'; // Bootstrap NGN environment

use NGN\Lib\Config;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// --- Configuration ---
$logFile = __DIR__ . '/../storage/logs/chart_qa_gatekeeper.log';

// Thresholds for validation checks
$coverageThreshold = 0.98; // 98% station coverage
$linkageThreshold = 0.95;  // 95% linkage rate

// --- Setup Logger ---
try {
    $logger = new Logger('chart_qa_gatekeeper');
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
    error_log("Chart QA Gatekeeper setup error: " . $e->getMessage());
    exit("Chart QA Gatekeeper setup failed.");
}

$logger->info('Chart QA Gatekeeper script started.');

// --- Main Validation Logic ---
try {
    $runStatus = 'passed'; // Default status
    $runMessages = [];

    // --- 1. Data Check: Station Coverage ---
    // Assuming cdm_stations table exists and has a way to determine active stations.
    // The exact query depends on how 'active stations' is defined.
    // For now, let's assume a simple count.
    
    // Placeholder for total active stations. This might be a count of stations meeting certain criteria.
    // For demonstration, let's assume a simple count.
    $totalStationsStmt = $pdo->query("SELECT COUNT(*) FROM stations WHERE status = 'active'");
    $totalStations = $totalStationsStmt ? $totalStationsStmt->fetchColumn() : 0;

    // Stations with valid linkage (e.g., mapped to identity or verified).
    // Assuming cdm_identity_map table or a similar mechanism to track linkage.
    // If NGN stations are directly linked to users/entities, this query would target that.
    // For now, we'll assume a table 'cdm_identity_map' with 'station_id' and 'user_id'.
    // If NGN stations are linked via `users` table `station_id`, that would be different.
    // Given the prompt mentions 'cdm_stations' and 'cdm_identity_map', I'll use these.
    // If NGN stations are linked directly via users.station_id, this would be simplified.
    
    // Assuming stations table has an 'id' and identity map is linked to station id.
    // This query assumes stations table and cdm_identity_map are linked and we are checking stations that appear in both.
    // A more robust query would JOIN based on station IDs.
    // For now, let's assume we count stations present in cdm_identity_map where the station is active.
    
    $linkedStationsStmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT c.station_id) 
         FROM cdm_identity_map cim 
         JOIN stations c ON cim.station_id = c.id 
         WHERE c.status = 'active'"
    );
    $linkedStationsStmt->execute();
    $linkedStations = $linkedStationsStmt ? $linkedStationsStmt->fetchColumn() : 0;

    $stationCoverage = ($totalStations > 0) ? ($linkedStations / $totalStations) : 0;

    if ($stationCoverage < $coverageThreshold) {
        $runStatus = 'failed';
        $messages[] = sprintf("STATION COVERAGE FAILURE: %.2f%% (Target: %.2f%%). Only %d out of %d active stations linked.",
            $stationCoverage * 100, $coverageThreshold * 100, $linkedStations, $totalStations);
        $logger->error($messages[count($messages) - 1]);
    } else {
        $logger->info(sprintf("Station Coverage OK: %.2f%% (Target: %.2f%%). %d/%d linked.",
            $stationCoverage * 100, $coverageThreshold * 100, $linkedStations, $totalStations));
    }

    // --- 2. Data Check: Linkage Rate ---
    // Verify name-to-ID mapping (linkage rate).
    // This could mean checking for artists/labels with missing IDs or names.
    // Let's assume linkage refers to unique artists/labels mapped to their NGN IDs.
    // This might be derived from the users table itself, or a dedicated mapping table.
    // Assuming linkage rate is checked against artists/labels that have a 'claimed' status or similar.
    
    // For demonstration, let's assume linkage rate is about artists/labels that have a valid 'Id' in the 'users' table.
    // A more accurate check would be against a specific mapping table or by analyzing claimed profiles.
    // Let's assume we are checking artists that have 'Id' and a corresponding entry in 'artists' or 'entity_scores' table.
    
    // Query for total artists/labels that SHOULD be linked (e.g., those with active profiles)
    $totalEntitiesStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id IN (3, 7) AND status = 'active'"); // Artists and Labels
    $totalEntities = $totalEntitiesStmt ? $totalEntitiesStmt->fetchColumn() : 0;

    // Query for entities that ARE linked (e.g., have a score entry, or a 'claimed' flag set).
    // Let's assume 'entity_scores' table exists and having an entry there implies linkage.
    $linkedEntitiesStmt = $pdo->query(
        "SELECT COUNT(DISTINCT es.entity_id) 
         FROM entity_scores es 
         JOIN users u ON es.entity_id = u.Id AND es.entity_type = 'user'
         WHERE u.role_id IN (3, 7) AND u.status = 'active'"
    );
    $linkedEntities = $linkedEntitiesStmt ? $linkedEntitiesStmt->fetchColumn() : 0;

    $linkageRate = ($totalEntities > 0) ? ($linkedEntities / $totalEntities) : 0;

    if ($linkageRate < $linkageThreshold) {
        $runStatus = 'failed'; // Set overall status to failed if any check fails
        $messages[] = sprintf("LINKAGE RATE FAILURE: %.2f%% (Target: %.2f%%). Only %d/%d entities have linkage.",
            $linkageRate * 100, $linkageThreshold * 100, $linkedEntities, $totalEntities);
        $logger->error($messages[count($messages) - 1]);
    } else {
        $logger->info(sprintf("Linkage Rate OK: %.2f%% (Target: %.2f%%). %d/%d entities linked.",
            $linkageRate * 100, $linkageThreshold * 100, $linkedEntities, $totalEntities));
    }

    // --- 2. Output: Generate Fairness Summary JSON ---
    $fairnessSummary = [
        'run_timestamp' => date('Y-m-d H:i:s'),
        'status' => $runStatus,
        'messages' => $messages,
        'station_coverage' => [
            'value' => $stationCoverage,
            'threshold' => $coverageThreshold,
            'passed' => $stationCoverage >= $coverageThreshold
        ],
        'linkage_rate' => [
            'value' => $linkageRate,
            'threshold' => $linkageThreshold,
            'passed' => $linkageRate >= $linkageThreshold
        ]
    ];

    $summaryJson = json_encode($fairnessSummary, JSON_PRETTY_PRINT);
    echo "\n--- Fairness Summary ---\n";
    echo $summaryJson;
    echo "\n-----------------------\n";

    // --- 3. Write: Store summary in cdm_chart_runs table ---
    // This assumes cdm_chart_runs table exists with columns: status, weights_checksum, inputs_checksum, summary_json, run_at.
    // The checksums would need to be determined based on actual weight/input file contents.
    // For this script, we'll use static placeholders for checksums.

    $weightsChecksum = hash('sha256', json_encode(getWeightsDynamically())); // Placeholder function
    $inputsChecksum = hash('sha256', json_encode(['stations' => $totalStations, 'linkedStations' => $linkedStations, 'totalEntities' => $totalEntities, 'linkedEntities' => $linkedEntities])); // Simple inputs hash

    $insertStmt = $pdo->prepare(
        "INSERT INTO cdm_chart_runs (status, weights_checksum, inputs_checksum, summary_json, run_at)"
        ." VALUES (:status, :weights_checksum, :inputs_checksum, :summary_json, NOW())"
    );

    $insertSuccess = $insertStmt->execute([
        ':status' => $runStatus,
        ':weights_checksum' => $weightsChecksum,
        ':inputs_checksum' => $inputsChecksum,
        ':summary_json' => $summaryJson
    ]);

    if ($insertSuccess) {
        $logger->info("Fairness summary stored successfully in cdm_chart_runs.");
    } else {
        $logger->error("Failed to store fairness summary in cdm_chart_runs.");
    }

    // --- 4. Logic: Prevent next job if failed ---
    if ($runStatus === 'failed') {
        $logger->critical("Chart QA Gatekeeper failed critical thresholds. Subsequent jobs should be prevented.");
        // In a real workflow, this script would set a flag or signal the scheduler/next job
        // to halt execution. For this script, we'll exit with a non-zero code.
        exit(1); // Indicate failure
    }

    $logger->info("Chart QA Gatekeeper script finished successfully.");
    exit(0); // Indicate success

} catch (\Throwable $e) {
    $logger->critical("Chart QA Gatekeeper encountered a critical error: " . $e->getMessage());
    echo "\n*** CRITICAL ERROR DURING QA GATEKEEPING ***\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1); // Indicate failure
}

// Placeholder function to get weights dynamically (for checksum generation)
function getWeightsDynamically(): array {
    // In a real scenario, this would load weights from Factors.json or config.
    // For this script, we'll use the hardcoded weights defined earlier for checksum purposes.
    global $weights;
    return $weights;
}

?>
