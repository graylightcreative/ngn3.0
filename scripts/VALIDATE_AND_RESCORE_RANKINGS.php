<?php
/**
 * VALIDATE_AND_RESCORE_RANKINGS.php
 * Validates ranking scores and re-scores them according to NGN 2.0 scoring model
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$remoteHost = 'server.starrship1.com';
$pass = 'NextGenNoise!1';

$rescore = in_array('--rescore', $argv);

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];

// Connect to databases
$rankingsDb = new PDO(
    "mysql:host={$remoteHost};dbname=ngn_rankings_2025",
    'ngn_rankings_2025',
    $pass,
    $pdoOptions
);

$spinsDb = new PDO(
    "mysql:host={$remoteHost};dbname=ngn_spins_2025",
    'ngn_spins_2025',
    $pass,
    $pdoOptions
);

// Load scoring configuration
$factors = json_decode(file_get_contents(dirname(__DIR__) . '/docs/Factors.json'), true);
$config = $factors['charts']['ngn:artists:weekly'] ?? $factors['defaults'];

echo "\n" . str_repeat("=", 80) . "\n";
echo "RANKING VALIDATION & RE-SCORING\n";
echo "NGN Scoring Model v2.0\n";
echo str_repeat("=", 80) . "\n\n";

// ============================================================================
// PHASE 1: VALIDATION
// ============================================================================
echo str_repeat("-", 80) . "\n";
echo "PHASE 1: VALIDATE EXISTING SCORES\n";
echo str_repeat("-", 80) . "\n\n";

try {
    // Check score integrity
    $stmt = $rankingsDb->prepare("
        SELECT
          entity_type,
          COUNT(*) as total,
          COUNT(CASE WHEN score < 0 THEN 1 END) as negative_scores,
          COUNT(CASE WHEN score IS NULL THEN 1 END) as null_scores,
          COUNT(CASE WHEN score > 999999 THEN 1 END) as anomaly_high,
          MIN(score) as min_score,
          MAX(score) as max_score,
          AVG(score) as avg_score,
          STDDEV(score) as stddev_score
        FROM ranking_items
        GROUP BY entity_type
    ");
    $stmt->execute();
    $scoreStats = $stmt->fetchAll();

    echo "[INFO] Score Statistics:\n";
    $hasIssues = false;
    foreach ($scoreStats as $row) {
        echo "\n{$row['entity_type']}s:\n";
        echo "  Total: {$row['total']}\n";
        echo "  Score range: {$row['min_score']} - {$row['max_score']}\n";
        echo "  Average: " . number_format($row['avg_score'], 2) . "\n";
        echo "  Std Dev: " . number_format($row['stddev_score'], 2) . "\n";

        if ($row['negative_scores'] > 0) {
            echo "  ⚠️  NEGATIVE SCORES: {$row['negative_scores']}\n";
            $hasIssues = true;
        }
        if ($row['null_scores'] > 0) {
            echo "  ⚠️  NULL SCORES: {$row['null_scores']}\n";
            $hasIssues = true;
        }
        if ($row['anomaly_high'] > 0) {
            echo "  ⚠️  ANOMALY (>999k): {$row['anomaly_high']}\n";
            $hasIssues = true;
        }
    }

    echo "\n";

    // Check ranking consistency
    echo "[INFO] Checking ranking sequencing per window...\n";

    $stmt = $rankingsDb->prepare("
        SELECT COUNT(*) as issue_count
        FROM ranking_items ri
        WHERE `rank` > (SELECT COUNT(*) FROM ranking_items ri2 WHERE ri2.window_id = ri.window_id AND ri2.entity_type = ri.entity_type)
    ");
    $stmt->execute();
    $rankIssueCount = $stmt->fetch()['issue_count'];

    if ($rankIssueCount > 0) {
        echo "  ⚠️  Found $rankIssueCount items with invalid rank\n";
        $hasIssues = true;
    } else {
        echo "  ✓ All ranking sequences valid\n";
    }

    echo "\n";

    // Check coverage
    echo "[INFO] Checking data coverage and sources...\n";

    $stmt = $rankingsDb->prepare("SELECT COUNT(DISTINCT id) as total_windows FROM ranking_windows");
    $stmt->execute();
    $totalWindows = $stmt->fetch()['total_windows'];

    $stmt = $rankingsDb->prepare("
        SELECT AVG(entity_count) as avg_count
        FROM (
          SELECT COUNT(DISTINCT entity_id) as entity_count FROM ranking_items GROUP BY window_id
        ) t
    ");
    $stmt->execute();
    $avgCoverage = $stmt->fetch()['avg_count'];

    echo "  Total ranking windows: $totalWindows\n";
    echo "  Average entities per window: " . number_format($avgCoverage, 0) . "\n";

    // Check spins availability
    $stmt = $spinsDb->prepare("
        SELECT
          COUNT(*) as total_spins,
          COUNT(DISTINCT artist_id) as unique_artists,
          COUNT(DISTINCT station_id) as unique_stations,
          MIN(played_at) as earliest,
          MAX(played_at) as latest
        FROM station_spins
    ");
    $stmt->execute();
    $spinStats = $stmt->fetch();

    echo "  Available spins data: {$spinStats['total_spins']} spins\n";
    echo "  Spins coverage: {$spinStats['unique_artists']} artists, {$spinStats['unique_stations']} stations\n";
    echo "  Spins date range: {$spinStats['earliest']} to {$spinStats['latest']}\n";

    if (!$hasIssues) {
        echo "\n✓ All validation checks passed\n";
    }

} catch (Exception $e) {
    echo "[ERROR] Validation failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

// ============================================================================
// PHASE 2: OPTIONAL RE-SCORING
// ============================================================================
if ($rescore) {
    echo "\n" . str_repeat("-", 80) . "\n";
    echo "PHASE 2: RE-SCORE RANKINGS\n";
    echo str_repeat("-", 80) . "\n\n";

    try {
        echo "[INFO] Re-scoring based on available factors (spins primary)...\n\n";

        // Get last 5 windows
        $stmt = $rankingsDb->prepare("
            SELECT id, window_start, `interval`
            FROM ranking_windows
            ORDER BY window_start DESC
            LIMIT 5
        ");
        $stmt->execute();
        $windows = $stmt->fetchAll();

        $rescoreCount = 0;
        foreach ($windows as $window) {
            echo "[WINDOW] {$window['window_start']} ({$window['interval']})\n";

            // Calculate scores based on spins
            $stmt = $spinsDb->prepare("
                SELECT
                  artist_id,
                  COUNT(*) as spin_count,
                  COUNT(DISTINCT station_id) as station_count
                FROM station_spins
                WHERE DATE(played_at) = ?
                GROUP BY artist_id
                ORDER BY spin_count DESC
            ");
            $stmt->execute([$window['window_start']]);
            $artistSpins = $stmt->fetchAll();

            $spinCount = count($artistSpins);
            echo "  Artists with spins: $spinCount\n";

            if (empty($artistSpins)) {
                echo "  (no spins data for this period)\n\n";
                continue;
            }

            // Apply log1p normalization
            $maxSpins = max(array_column($artistSpins, 'spin_count')) ?: 1;

            $updatedCount = 0;
            foreach ($artistSpins as $artist) {
                if (!$artist['artist_id']) continue;

                $normalized = log(1 + $artist['spin_count']) / log(1 + $maxSpins);
                $newScore = $normalized * 100000;

                $stmt = $rankingsDb->prepare("
                    UPDATE ranking_items
                    SET score = ?
                    WHERE window_id = ? AND entity_type = 'artist' AND entity_id = ?
                ");
                $stmt->execute([$newScore, $window['id'], $artist['artist_id']]);

                if ($stmt->rowCount() > 0) {
                    $updatedCount++;
                }
            }

            echo "  Updated scores: $updatedCount\n\n";
            $rescoreCount += $updatedCount;
        }

        echo "[OK] Re-scoring complete. Updated $rescoreCount ranking items.\n";
        echo "[WARNING] Limited re-score: only using spins factor (insufficient data for full model)\n\n";

    } catch (Exception $e) {
        echo "[ERROR] Re-scoring failed: " . $e->getMessage() . "\n\n";
    }
}

// ============================================================================
// PHASE 3: FAIRNESS SUMMARY
// ============================================================================
echo str_repeat("-", 80) . "\n";
echo "PHASE 3: FAIRNESS SUMMARY\n";
echo str_repeat("-", 80) . "\n\n";

try {
    $stmt = $rankingsDb->prepare("
        SELECT
          COUNT(DISTINCT window_id) as windows_scored,
          COUNT(*) as total_items,
          COUNT(DISTINCT entity_id) as unique_entities,
          AVG(score) as avg_score,
          MIN(score) as min_score,
          MAX(score) as max_score
        FROM ranking_items
    ");
    $stmt->execute();
    $summary = $stmt->fetch();

    echo "Coverage:\n";
    echo "  Windows: {$summary['windows_scored']}\n";
    echo "  Total ranking items: {$summary['total_items']}\n";
    echo "  Unique entities: {$summary['unique_entities']}\n";
    echo "  Avg score: " . number_format($summary['avg_score'], 2) . "\n";
    echo "  Score range: {$summary['min_score']} - {$summary['max_score']}\n\n";

    echo "Active Factors (from Factors.json):\n";
    echo "  spins: " . ($config['weights']['spins'] * 100) . "%\n";
    echo "  plays: " . ($config['weights']['plays'] * 100) . "%\n";
    echo "  adds: " . ($config['weights']['adds'] * 100) . "%\n";
    echo "  views: " . ($config['weights']['views'] * 100) . "%\n";
    echo "  posts: " . ($config['weights']['posts'] * 100) . "%\n\n";

    echo "Normalizers:\n";
    echo "  spins: per_station_z (z_cap=3.0)\n";
    echo "  plays: log1p_scale\n";
    echo "  adds: minmax\n";
    echo "  views: log1p_scale\n";
    echo "  posts: log1p_scale\n\n";

    echo "Status: ✓ Validation complete\n";
    if ($rescore) {
        echo "        ⚠️  Limited re-score applied\n";
    }

} catch (Exception $e) {
    echo "[ERROR] Summary generation failed: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "NEXT STEPS\n";
echo str_repeat("=", 80) . "\n\n";

echo "To enable full re-scoring, you need:\n";
echo "  1. Streaming plays data (Spotify/Apple Music APIs)\n";
echo "  2. Station adds data (new adds within ranking window)\n";
echo "  3. Page views data (analytics)\n";
echo "  4. Post/mention data (editorial coverage)\n\n";

echo "To re-score with available data:\n";
echo "  php scripts/VALIDATE_AND_RESCORE_RANKINGS.php --rescore\n\n";

echo str_repeat("=", 80) . "\n\n";
?>
