<?php

require_once __DIR__ . '/../../../../lib/bootstrap.php'; // Bootstrap NGN environment

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$pdo = ConnectionFactory::write($config); // Use ngn_2025 connection

// Previously included files containing helper functions
require 'get-artist-score-changes.php';
require 'get-artist-score-peaks.php';
require 'get-artist-impact-score.php';
require 'get-artist-popularity-score.php';
require 'get-artist-rising-score.php';

// Default date handling
$startDate = !isset($_POST['start_date']) ? '10/01/2024' : $_POST['start_date'];
$endDate = !isset($_POST['end_date']) ? 'Today' : $_POST['end_date'];
$startDate = convertDate($startDate);
$endDate = convertDate($endDate);

// Fetch all artists
$artistsStmt = $pdo->prepare("SELECT a.id, a.name AS title, a.slug, u.id AS user_id FROM `ngn_2025`.`artists` a JOIN `ngn_2025`.`users` u ON a.user_id = u.id WHERE u.role_id = 3 AND u.status = 'active'");
$artistsStmt->execute();
$artists = $artistsStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($artists as $key => $artist) {
    $artistId = $artist['id'];
    $artistTitle = $artist['title'];

    // Combine artist's metrics
    $metrics = [
        'ScoreChanges' => analyzeArtistScoreChanges($pdo, $artistId, $startDate, $endDate) ?? [],
        'ScorePeaks' => analyzeArtistScorePeaks($pdo, $artistId, $startDate, $endDate) ?? [],
        'ImpactScore' => getArtistImpact($pdo, $artistId, $artistTitle, $startDate, $endDate) ?? 0,
        'PopularityScore' => getArtistPopularityTrend($pdo, $artistId, $artistTitle, $startDate, $endDate) ?? 0,
        'RisingScore' => getRisingScore($pdo, $artistId, $startDate, $endDate) ?? 0,
        'MentionsData' => getMentionsData($pdo, $artistTitle, $startDate, $endDate),
        'releases' => getReleasesByArtistId($pdo, $artistId, $startDate, $endDate),
        'videos' => getVideosByArtistId($pdo, $artistId, $startDate, $endDate),
        'posts' => getPostsByUserId($pdo, $artistId, $startDate, $endDate),
        'SpinsData' => getSpinsData($pdo, $artistTitle, $startDate, $endDate),
        'ViewsData' => getViewsData($pdo, $artistId, 'artist', $startDate, $endDate), // Pass pdo, entityType
    ];

    // Generate and output the story for each artist
    echo generateArtistStory($pdo, $artist, $metrics, $startDate, $endDate);
}



/**
 * Generates an artist story based on their metrics.
 */
function generateArtistStory($artist, $metrics, $startDate, $endDate)
{
    // Define thresholds for "mind-blowing" metrics
    $dayDifference = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24);
    $thresholds = [
        'ImpactScore' => $dayDifference > 90 ? 12000 : ($dayDifference > 60 ? 10000 : 8000),
        'PopularityScore' => $dayDifference > 90 ? 12000 : ($dayDifference > 60 ? 10000 : 8000),
        'RisingScore' => $dayDifference > 90 ? 5 : ($dayDifference > 60 ? 3 : 1),
        'MentionsData' => $dayDifference > 90 ? 3 : ($dayDifference > 60 ? 2 : 1),
        'releases' => $dayDifference > 90 ? 2 : ($dayDifference > 60 ? 1 : 1),
        'videos' => $dayDifference > 90 ? 2 : ($dayDifference > 60 ? 1 : 1),
        'SpinsData' => $dayDifference > 90 ? 3000 : ($dayDifference > 60 ? 1500 : 800),
        'ViewsData' => $dayDifference > 90 ? 25 : ($dayDifference > 60 ? 15 : 8)
    ];

    // Filter metrics to only consider those above their respective thresholds
    $priorities = [
        'ImpactScore' => ($metrics['ImpactScore'] > $thresholds['ImpactScore']) ? $metrics['ImpactScore'] : 0,
        'PopularityScore' => ($metrics['PopularityScore'] > $thresholds['PopularityScore']) ? $metrics['PopularityScore'] : 0,
        'RisingScore' => ($metrics['RisingScore'] > $thresholds['RisingScore']) ? $metrics['RisingScore'] : 0,
        'MentionsData' => (!empty($metrics['MentionsData']) && count($metrics['MentionsData']) > $thresholds['MentionsData']) ? count($metrics['MentionsData']) : 0,
        'releases' => (!empty($metrics['releases']) && count($metrics['releases']) > $thresholds['releases']) ? count($metrics['releases']) : 0,
        'videos' => (!empty($metrics['videos']) && count($metrics['videos']) > $thresholds['videos']) ? count($metrics['videos']) : 0,
        'SpinsData' => (!empty($metrics['SpinsData']) && array_sum(array_column($metrics['SpinsData'], 'tws')) > $thresholds['SpinsData'])
            ? array_sum(array_column($metrics['SpinsData'], 'tws')) : 0,
        'ViewsData' => (!empty($metrics['ViewsData']) && array_sum(array_column($metrics['ViewsData'], 'view_count')) > $thresholds['ViewsData'])
            ? array_sum(array_column($metrics['ViewsData'], 'view_count')) : 0,
    ];

    // Find the metric with the highest priority score
    $bestMetric = array_keys($priorities, max($priorities))[0];

    // Generate the corresponding story only if there is a valid standout metric
    if ($priorities[$bestMetric] > 0) {
        switch ($bestMetric) {
            case 'ImpactScore':
                return "<div>💣 {$artist['Title']} is leading the industry with an Impact Score of {$metrics['ImpactScore']}!</div>";
            case 'PopularityScore':
                return "<div>🔥 {$artist['Title']} is breaking records with a Popularity Score of {$metrics['PopularityScore']}!</div>";
            case 'RisingScore':
                return "<div>🚀 {$artist['Title']} is on a high-growth trajectory with a Rising Score of {$metrics['RisingScore']}!</div>";
            case 'MentionsData':
                $mentionCount = count($metrics['MentionsData']);
                return "<div>💬 {$artist['Title']} was mentioned {$mentionCount} times recently, showing their growing influence.</div>";
            case 'releases':
                $releaseCount = count($metrics['releases']);
                return "<div>🎶 {$artist['Title']} has released {$releaseCount} tracks recently. Check out their new music!</div>";
            case 'videos':
                $videoCount = count($metrics['videos']);
                return "<div>📹 {$artist['Title']} shared {$videoCount} new videos. Fans are loving the visuals!</div>";
            case 'SpinsData':
                $spins = array_sum(array_column($metrics['SpinsData'], 'tws'));
                return "<div>📻 {$artist['Title']}'s tracks received {$spins} spins on the airwaves recently.</div>";
            case 'ViewsData':
                $viewCount = array_sum(array_column($metrics['ViewsData'], 'view_count'));
                return "<div>👀 {$artist['Title']} received {$viewCount} views on their content recently!</div>";
        }
    }

    // If no metric passes the threshold, return empty (no story generated)
    return '';
}

/**
 * Converts a date from any standard format to 'Y-m-d H:i:s'.
 */
function convertDate($date)
{
    return date('Y-m-d H:i:s', strtotime($date));
}

/**
 * Retrieves rankings data for an artist.
 */
function getRankingsData(PDO $pdo, string $interval, int $artistId, string $startDate, string $endDate)
{
    // Map legacy table names (Artists, ArtistsDaily, etc.) to ranking_windows.interval
    $intervalMap = [
        'Artists' => 'weekly', // Assuming "Artists" refers to weekly in this context
        'ArtistsDaily' => 'daily',
        'ArtistsWeekly' => 'weekly',
        'ArtistsMonthly' => 'monthly',
        'ArtistsYearly' => 'yearly',
    ];
    $mappedInterval = $intervalMap[$interval] ?? 'weekly'; // Default to weekly if not mapped

    $query = "
        SELECT nri.score, nrh.window_end AS Timestamp
        FROM `ngn_rankings_2025`.`ranking_items` nri
        JOIN `ngn_rankings_2025`.`ranking_windows` nrh ON nri.window_id = nrh.id
        WHERE nri.entity_type = 'artist' AND nri.entity_id = :artist_id
          AND nrh.interval = :interval AND nrh.window_end BETWEEN :startDate AND :endDate
        ORDER BY nrh.window_end ASC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':artist_id' => $artistId,
        ':interval' => $mappedInterval,
        ':startDate' => $startDate,
        ':endDate' => $endDate
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Retrieves views data for a user.
 */
function getViewsData(PDO $pdo, int $entityId, string $entityType, string $startDate, string $endDate)
{
    $totalViews = 0;
    
    // Aggregate views from videos
    $videoViewsStmt = $pdo->prepare("
        SELECT COALESCE(SUM(view_count), 0) AS video_views
        FROM `ngn_2025`.`videos`
        WHERE artist_id = :entityId AND published_at BETWEEN :startDate AND :endDate
    ");
    $videoViewsStmt->execute([':entityId' => $entityId, ':startDate' => $startDate, ':endDate' => $endDate]);
    $totalViews += (int)$videoViewsStmt->fetchColumn();

    // Aggregate views from posts (if posts table has view_count, otherwise this is a gap)
    // For now, assume 0 for posts as ngn_2025.posts doesn't have a view_count column
    return $totalViews;
}

/**
 * Retrieves spins data for an artist.
 */
function getSpinsData(PDO $pdo, string $artistName, string $startDate, string $endDate): array
{
    $spins = [];

    // Query ngn_spins_2025.station_spins
    $spinsStmt = $pdo->prepare("
        SELECT artist_name AS Artist, track_name AS Song, played_at AS Timestamp, meta->>'$.tws' AS TWS
        FROM `ngn_spins_2025`.`station_spins`
        WHERE LOWER(artist_name) LIKE :artistName AND played_at BETWEEN :startDate AND :endDate
        ORDER BY played_at ASC
    ");
    $spinsStmt->execute([
        ':artistName' => '%' . strtolower($artistName) . '%',
        ':startDate' => $startDate,
        ':endDate' => $endDate
    ]);
    $spins = $spinsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Query ngn_smr_2025.smr_chart
    $smrStmt = $pdo->prepare("
        SELECT artist AS Artists, track AS Song, label AS Label, tws AS TWS, window_date AS Timestamp
        FROM `ngn_smr_2025`.`smr_chart`
        WHERE LOWER(artist) LIKE :artistName AND window_date BETWEEN :startDate AND :endDate
        ORDER BY window_date ASC
    ");
    $smrStmt->execute([
        ':artistName' => '%' . strtolower($artistName) . '%',
        ':startDate' => $startDate,
        ':endDate' => $endDate
    ]);
    $smr = $smrStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_merge($spins, $smr);
}

/**
 * Retrieves mentions for an artist.
 */
function getMentionsData(PDO $pdo, string $artistName, string $startDate, string $endDate): array
{
    $query = "SELECT id, title, author_id, published_at FROM `ngn_2025`.`posts` WHERE (title LIKE :q1 OR teaser LIKE :q2 OR tags LIKE :q3 OR body LIKE :q4) AND published_at BETWEEN :startDate AND :endDate";
    $stmt = $pdo->prepare($query);
    $searchTerm = "%{$artistName}%";
    $stmt->execute([
        ':q1' => $searchTerm,
        ':q2' => $searchTerm,
        ':q3' => $searchTerm,
        ':q4' => $searchTerm,
        ':startDate' => $startDate,
        ':endDate' => $endDate
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Retrieves releases by an artist's ID.
 */
function getReleasesByArtistId(PDO $pdo, int $artistId, string $startDate, string $endDate): array
{
    $query = "SELECT id, slug, title, released_at, cover_url FROM `ngn_2025`.`releases` WHERE artist_id = :artistId AND released_at BETWEEN :startDate AND :endDate";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':artistId' => $artistId, ':startDate' => $startDate, ':endDate' => $endDate]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Retrieves videos by an artist's ID.
 */
function getVideosByArtistId(PDO $pdo, int $artistId, string $startDate, string $endDate): array
{
    $query = "SELECT id, slug, title, published_at, image_url, view_count FROM `ngn_2025`.`videos` WHERE artist_id = :artistId AND published_at BETWEEN :startDate AND :endDate";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':artistId' => $artistId, ':startDate' => $startDate, ':endDate' => $endDate]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Retrieves posts by a user's ID.
 */
function getPostsByUserId(PDO $pdo, int $userId, string $startDate, string $endDate): array
{
    $query = "SELECT id, slug, title, author_id, published_at, image_url FROM `ngn_2025`.`posts` WHERE status = 'published' AND author_id = :userId AND published_at BETWEEN :startDate AND :endDate";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':userId' => $userId, ':startDate' => $startDate, ':endDate' => $endDate]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}