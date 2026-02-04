<?php

// This script computes and stores artist rankings for the weekly NGN chart.
// It implements the scoring model S(a,w) = Σ_f [ w_f * T_f(a,w) ]
// Factors, normalization, and capping are applied dynamically.

require_once __DIR__ . '/../lib/bootstrap.php'; // Bootstrap NGN environment

use NGN\Lib\Config;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use NGN\Lib\Rankings\RankingCalculator;
use NGN\Lib\Rankings\RankingService;
use NGN\Lib\Http\HttpClient; // For fetching data via internal API if needed

// --- Configuration ---
$logFile = __DIR__ . '/../storage/logs/compute_rankings_weekly.log';
$chartSlug = 'ngn:artists:weekly';
$batchSize = 50; // For potential batch processing if needed

// --- Setup Logger ---
try {
    $logger = new Logger('compute_rankings_weekly');
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

    // Load Weights from Factors.json
    $factorsJsonPath = __DIR__ . '/../Factors.json'; // Assuming Factors.json is in the root
    if (!is_file($factorsJsonPath)) {
        throw new \RuntimeException("Factors.json not found at: {$factorsJsonPath}");
    }
    $factorsJson = file_get_contents($factorsJsonPath);
    $factorsConfig = json_decode($factorsJson, true);
    if ($factorsConfig === false) {
        throw new \RuntimeException("Failed to parse Factors.json.");
    }

    // Extract specific weights for this calculation
    $weights = $factorsConfig['weights'] ?? [];
    $normalizationMethods = $factorsConfig['normalization'] ?? [];
    $cappingRules = $factorsConfig['capping'] ?? [];

    // --- Initialize Services ---
    // RankingCalculator needs access to data (e.g., via direct DB queries or helper functions)
    // or it might need its own data fetching methods.
    $rankingCalculator = new RankingCalculator($config);

} catch (\Throwable $e) {
    error_log("Ranking calculation job setup error: " . $e->getMessage());
    exit("Ranking calculation job setup failed.");
}

$logger->info(sprintf("Ranking calculation job started for interval: weekly."));

// --- Main Calculation Logic ---
try {
    // --- 1. Fetch Artists for Weekly Ranking ---
    // We need a list of artists and their associated raw data (spins, plays, views, posts)
    // This data might come from multiple tables, or aggregated views.
    // For simplicity, let's assume a query can join necessary data.
    // A more accurate approach might involve internal API calls or specialized services.

    // --- 1. Fetch Artists and Factor Data for Weekly Ranking ---
    $periodEnd = (new DateTime('last Sunday'))->format('Y-m-d'); // End of the last full week
    $periodStart = (new DateTime('last Sunday - 7 days'))->format('Y-m-d'); // Start of the week before last

    $artistDataStmt = $pdo->prepare(
        "SELECT a.id AS artist_id, a.name AS artist_name, u.id AS user_id,
                (SELECT SUM(s.tws) FROM ngn_smr_2025.smr_chart s WHERE s.artist = a.name AND s.window_date BETWEEN :periodStart AND :periodEnd) AS smr_score,
                (SELECT COUNT(DISTINCT r.id) FROM ngn_2025.releases r WHERE r.artist_id = a.id AND r.released_at BETWEEN :periodStart AND :periodEnd) AS release_count,
                (SELECT SUM(v.view_count) FROM ngn_2025.videos v WHERE v.artist_id = a.id AND v.created_at BETWEEN :periodStart AND :periodEnd) AS video_views,
                (SELECT COUNT(ss.id) FROM ngn_spins_2025.station_spins ss WHERE ss.artist_id = a.id AND ss.played_at BETWEEN :periodStart AND :periodEnd) AS spins,
                (SELECT COUNT(h.id) FROM ngn_2025.history h WHERE h.entity_type = 'track' AND h.entity_id IN (SELECT t.id FROM ngn_2025.tracks t WHERE t.artist_id = a.id) AND h.occurred_at BETWEEN :periodStart AND :periodEnd) AS plays,
                (SELECT COUNT(p.id) FROM ngn_2025.posts p WHERE p.author_id = a.id AND p.created_at BETWEEN :periodStart AND :periodEnd) AS posts
         FROM `ngn_2025`.`artists` a
         JOIN `ngn_2025`.`users` u ON a.user_id = u.id
         WHERE u.role_id = 3 AND u.status = 'active'
         LIMIT 1000"
    );
    $artistDataStmt->bindValue(':periodStart', $periodStart);
    $artistDataStmt->bindValue(':periodEnd', $periodEnd);
    $artistDataStmt->execute();
    $artistsData = $artistDataStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($artistsData)) {
        $logger->warning("No artist data found for weekly ranking calculation.");
        exit("No artist data found.");
    }

    $logger->info(sprintf("Fetched %d artists for weekly ranking calculation.", count($artistsData)));

    // --- 2. Apply Normalization and Capping, then Calculate Score ---
    $processedArtists = [];
    $logger->info("Applying normalization, capping, and calculating scores.");

    // Helper to calculate percentile (simplified, for demonstration)
    $percentile = function(array $data, float $percentileValue): float {
        if (empty($data)) return 0.0;
        sort($data);
        $index = (int) floor($percentileValue * count($data));
        return $data[$index] ?? 0.0;
    };


    foreach ($artistsData as $artist) {
        $artistId = $artist['Id'];
        $artistName = $artist['Name'];

        // Initialize scores and factors
        $factors = [
            'spins' => (float)($artist['spins'] ?? 0),
            'plays' => (float)($artist['plays'] ?? 0),
            'views' => (float)($artist['views'] ?? 0),
            'posts' => (float)($artist['posts'] ?? 0),
            'smr_score' => (float)($artist['smr_score'] ?? 0),
            'release_count' => (float)($artist['release_count'] ?? 0),
            'video_view_count' => (float)($artist['video_view_count'] ?? 0),
        ];

        // Placeholder for normalization methods. Actual methods might be in services.
        // Normalization: spins (per_station_z), plays/views/posts (log1p_scale), adds (minmax)
        // Capping: spins/plays/views/posts (percentile), adds (absolute max=50)
        
        // For demonstration, let's directly use the fetched values and apply weights from config.
        // A real implementation would involve more complex normalization and capping logic.

        $normalizedSpins = $factors['spins']; // Placeholder for per_station_z normalization
        $normalizedPlays = log1p($factors['plays']); // log1p_scale
        $normalizedViews = log1p($factors['views']); // log1p_scale
        $normalizedPosts = log1p($factors['posts']); // log1p_scale
        $normalizedSmr = $factors['smr_score']; // SMR score is used directly or normalized differently

        // Capping (T_f)
        $cappedSpins = min($normalizedSpins, percentile($normalizedSpins, 0.98)); // Example capping
        $cappedPlays = min($normalizedPlays, percentile($normalizedPlays, 0.98));
        $cappedViews = min($normalizedViews, percentile($normalizedViews, 0.98));
        $cappedPosts = min($normalizedPosts, percentile($normalizedPosts, 0.98));
        // Note: Capping for adds is mentioned but 'adds' factor is not fetched here.
        
        // Calculate final score S(a,w) = Σ_f [ w_f * T_f(a,w) ]
        $finalScore = 0;
        $finalScore += $cappedSpins * ($weights['spins'] ?? 0.60);
        $finalScore += $cappedPlays * ($weights['plays'] ?? 0.20);
        $finalScore += $cappedViews * ($weights['views'] ?? 0.05);
        $finalScore += $cappedPosts * ($weights['posts'] ?? 0.05);

        // Add other factors if available and relevant for the weekly NGN score.
        // Example: SMR Score contribution might be separate or integrated differently.
        // The prompt specified 'SMR Score * 10' for validation, but the formula here is Σ_f [ w_f * T_f(a,w) ]
        // Let's integrate SMR score into the sum with its weight.
        // Assuming SMR score is also normalized and capped if needed.
        $normalizedSmr = $factors['smr_score']; // Assuming SMR score is already normalized or used as is.
        $cappedSmr = min($normalizedSmr, percentile($normalizedSmr, 0.95)); // Example capping for SMR
        $finalScore += $cappedSmr * ($weights['smr'] ?? 10.0); // Using weight 10 from prompt for SMR as an example factor.

        // Store calculated score and rank (rank needs to be determined after all scores are computed)
        $processedArtists[] = [
            'artist_id' => $artistId,
            'artist_name' => $artistName,
            'raw_metrics' => $factors,
            'normalized_factors' => [
                'spins' => $normalizedSpins, 
                'plays' => $normalizedPlays, 
                'views' => $normalizedViews, 
                'posts' => $normalizedPosts,
                'smr_score' => $normalizedSmr
            ],
            'capped_factors' => [
                'spins' => $cappedSpins,
                'plays' => $cappedPlays,
                'views' => $cappedViews,
                'posts' => $cappedPosts,
                'smr_score' => $cappedSmr
            ],
            'final_score' => $finalScore
        ];
    }

    // --- Rank the artists based on the final score ---
    usort($processedArtists, function ($a, $b) {
        return $b['final_score'] <=> $a['final_score']; // Sort descending by score
    });

    $rankedArtists = [];
    foreach ($processedArtists as $index => $artist) {
        $rank = $index + 1;
        $processedArtists[$index]['rank'] = $rank;
        $processedArtists[$index]['final_score'] = round($artist['final_score'], 2);
        $rankedArtists[] = $processedArtists[$index]; // Store final ranked data
    }

    // --- 4. Final Output: Write the final score (S) and rank to ngn_rankings_2025.ranking_items ---
    // Get/create ranking window for the current week
    $windowStmt = $pdo->prepare(
        "INSERT INTO `ngn_rankings_2025`.`ranking_windows` (interval, window_start, window_end)
         VALUES (:interval, :window_start, :window_end)
         ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
    );
    $windowStmt->execute([
        ':interval' => 'weekly',
        ':window_start' => $periodStart,
        ':window_end' => $periodEnd,
    ]);
    $windowId = (int)$pdo->lastInsertId();

    if (!$windowId) {
        $logger->critical("Failed to get or create ranking window.");
        exit("Ranking calculation job failed.");
    }

    $insertStmt = $pdo->prepare(
        "INSERT INTO `ngn_rankings_2025`.`ranking_items` (window_id, entity_type, entity_id, rank, score, prev_rank, deltas, flags)"
        ." VALUES (:window_id, :entity_type, :entity_id, :rank, :score, :prev_rank, :deltas, :flags)"
        ." ON DUPLICATE KEY UPDATE rank = :rank, score = :score, prev_rank = :prev_rank, deltas = :deltas, flags = :flags"
    );

    $insertedCount = 0;
    $errorsCount = 0;

    // Fetch previous week's rankings to calculate prev_rank and deltas
    $prevWindowId = null;
    $prevWindowStmt = $pdo->prepare("SELECT id FROM `ngn_rankings_2025`.`ranking_windows` WHERE `interval` = 'weekly' AND `window_end` = DATE_SUB(:periodStart, INTERVAL 1 DAY) LIMIT 1");
    $prevWindowStmt->execute([':periodStart' => $periodStart]);
    $prevWindow = $prevWindowStmt->fetch(PDO::FETCH_ASSOC);
    if ($prevWindow) {
        $prevWindowId = $prevWindow['id'];
        $prevRankingsStmt = $pdo->prepare("SELECT entity_id, rank FROM `ngn_rankings_2025`.`ranking_items` WHERE window_id = :prevWindowId AND entity_type = 'artist'");
        $prevRankingsStmt->execute([':prevWindowId' => $prevWindowId]);
        $prevRankings = $prevRankingsStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: []; // [entity_id => rank]
    } else {
        $prevRankings = [];
    }

    foreach ($rankedArtists as $artist) {
        $prevRank = $prevRankings[$artist['artist_id']] ?? null;
        $deltas = null; // Placeholder for actual delta calculation
        $flags = null; // Placeholder for actual flag setting

        try {
            $insertStmt->execute([
                ':window_id' => $windowId,
                ':entity_type' => 'artist',
                ':entity_id' => $artist['artist_id'],
                ':rank' => $artist['rank'],
                ':score' => $artist['final_score'],
                ':prev_rank' => $prevRank,
                ':deltas' => json_encode($deltas),
                ':flags' => json_encode($flags),
            ]);
            $insertedCount++;
        } catch (\Throwable $e) {
            $errorsCount++;
            $logger->error(sprintf("Failed to insert/update ranking for artist ID %d: %s", $artist['artist_id'], $e->getMessage()));
        }
    }

    $logger->info(sprintf("Ranking calculation complete. Processed: %d artists. Inserted/Updated: %d. Errors: %d.", count($rankedArtists), $insertedCount, $errorsCount));

} catch (\Throwable $e) {
    $logger->critical("Ranking calculation job encountered a critical error: " . $e->getMessage());
    exit("Ranking calculation job failed.");
}

?>


