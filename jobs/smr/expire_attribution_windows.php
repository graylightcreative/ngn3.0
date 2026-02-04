<?php

/**
 * SMR Attribution Window Expiration Cron Job
 * Chapter 24 - Rule 2: Window Lifecycle Management
 *
 * Schedule: Daily at 3:00 AM UTC (0 3 * * *)
 *
 * Marks attribution windows older than 90 days as expired.
 * These windows will no longer trigger bounties.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;
use NGN\Lib\Smr\AttributionWindowService;

// Get database connection
$config = new Config();
$pdo = ConnectionFactory::write($config);

if (!$pdo) {
    die("Failed to connect to database\n");
}

// Check if bounty system is enabled
if (!($_ENV['SMR_ENABLE_BOUNTIES'] ?? true)) {
    echo "[expire_attribution_windows] SMR bounty system disabled, exiting\n";
    exit(0);
}

$attributionService = new AttributionWindowService($pdo);

try {
    echo "[expire_attribution_windows] Starting attribution window expiration\n";

    // Get current active windows that should expire
    $stmt = $pdo->prepare("
        SELECT aw.id, aw.artist_id, aw.window_start, aw.window_end,
               a.name as artist_name
        FROM smr_attribution_windows aw
        JOIN cdm_artists a ON aw.artist_id = a.id
        WHERE aw.status = 'active'
        AND aw.window_end < CURDATE()
        ORDER BY aw.window_end ASC
    ");
    $stmt->execute();
    $windowsToExpire = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "  Found " . count($windowsToExpire) . " window(s) to expire\n";

    $expiredCount = 0;

    foreach ($windowsToExpire as $window) {
        try {
            // Mark as expired
            $attributionService->markWindowClaimed((int)$window['id']);

            echo "  Expired window ID {$window['id']} (artist: {$window['artist_name']})\n";
            echo "    Window period: {$window['window_start']} to {$window['window_end']}\n";

            $expiredCount++;
        } catch (\Throwable $e) {
            echo "  ERROR: Failed to expire window {$window['id']}: {$e->getMessage()}\n";
        }
    }

    // Log expiration statistics
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_active,
            COUNT(CASE WHEN window_end < CURDATE() THEN 1 END) as overdue,
            COUNT(CASE WHEN window_end >= CURDATE() THEN 1 END) as current
        FROM smr_attribution_windows
        WHERE status = 'active'
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "\n[expire_attribution_windows] Summary:\n";
    echo "  Windows expired: {$expiredCount}\n";
    echo "  Remaining active windows: " . ($stats['total_active'] - $expiredCount) . "\n";
    echo "  Status: SUCCESS\n";

    exit(0);
} catch (\Throwable $e) {
    echo "[expire_attribution_windows] FATAL ERROR: {$e->getMessage()}\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
