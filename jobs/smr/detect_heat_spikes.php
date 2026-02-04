<?php

/**
 * SMR Heat Spike Detection Cron Job
 * Chapter 24 - Rule 2: Heat Spike Detection
 *
 * Schedule: Daily at 2:00 AM UTC (0 2 * * *)
 *
 * Detects heat spikes in recent SMR uploads and creates 90-day
 * attribution windows for bounty eligibility.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;
use NGN\Lib\Smr\HeatSpikeDetectionService;
use NGN\Lib\Smr\AttributionWindowService;

// Get database connection
$config = new Config();
$pdo = ConnectionFactory::write($config);

if (!$pdo) {
    die("Failed to connect to database\n");
}

// Check if bounty system is enabled
if (!($_ENV['SMR_ENABLE_BOUNTIES'] ?? true)) {
    echo "[detect_heat_spikes] SMR bounty system disabled, exiting\n";
    exit(0);
}

$heatService = new HeatSpikeDetectionService($pdo);
$attributionService = new AttributionWindowService($pdo);

try {
    // Get recent uploads from last 7 days
    $stmt = $pdo->prepare("
        SELECT id, uploaded_at
        FROM smr_uploads
        WHERE uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND status IN ('finalized', 'verified')
        ORDER BY uploaded_at DESC
    ");
    $stmt->execute();
    $uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "[detect_heat_spikes] Found " . count($uploads) . " uploads from last 7 days\n";

    $totalSpikesDetected = 0;
    $totalWindowsCreated = 0;

    foreach ($uploads as $upload) {
        try {
            echo "  Processing upload ID {$upload['id']} from {$upload['uploaded_at']}\n";

            // Detect spikes from this upload
            $spikes = $heatService->detectSpikesFromUpload((int)$upload['id']);

            echo "    Detected " . count($spikes) . " spike(s)\n";

            foreach ($spikes as $spike) {
                try {
                    // Create attribution window for each spike
                    $windowId = $attributionService->createWindow(
                        (int)$spike['artist_id'],
                        (int)$spike['spike_id'],
                        $spike['spike_start_date']
                    );

                    echo "    Created attribution window ID {$windowId} for artist {$spike['artist_id']}\n";
                    echo "      Spike multiplier: {$spike['spike_multiplier']}x\n";
                    echo "      Stations in spike: {$spike['stations_count']}\n";
                    echo "      Window: {$spike['spike_start_date']} to " .
                         date('Y-m-d', strtotime($spike['spike_start_date'] . ' +90 days')) . "\n";

                    $totalWindowsCreated++;
                } catch (\Throwable $e) {
                    echo "    ERROR: Failed to create attribution window for spike {$spike['spike_id']}: {$e->getMessage()}\n";
                }
            }

            $totalSpikesDetected += count($spikes);
        } catch (\Throwable $e) {
            echo "  ERROR: Failed to process upload {$upload['id']}: {$e->getMessage()}\n";
        }
    }

    echo "\n[detect_heat_spikes] Summary:\n";
    echo "  Total spikes detected: {$totalSpikesDetected}\n";
    echo "  Total windows created: {$totalWindowsCreated}\n";
    echo "  Status: SUCCESS\n";

    exit(0);
} catch (\Throwable $e) {
    echo "[detect_heat_spikes] FATAL ERROR: {$e->getMessage()}\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
