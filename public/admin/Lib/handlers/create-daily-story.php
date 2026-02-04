<?php

$root = $_SERVER['DOCUMENT_ROOT'] . '/';
$_POST = json_decode(file_get_contents("php://input"), true);

// Necessary includes/restorations
require __DIR__ . '/../../lib/bootstrap.php';

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
$id = !isset($_POST['id']) ? 1286 : $_POST['id'];
$startDate = !isset($_POST['start_date']) ? '10/01/2024' : $_POST['start_date'];
$endDate = !isset($_POST['end_date']) ? 'Today' : $_POST['end_date'];
$startDate = convertDate($startDate);
$endDate = convertDate($endDate);

$pdo = $GLOBALS['pdo']; // Assume pdo is already available and connected to ngn_2025

// Fetch artist from ngn_2025.artists
$artistStmt = $pdo->prepare("SELECT id, name AS title, bio AS body FROM `ngn_2025`.`artists` WHERE id = :id LIMIT 1");
$artistStmt->execute([':id' => $id]);
$artist = $artistStmt->fetch(PDO::FETCH_ASSOC);

if (!$artist) {
    die('Artist not found in NGN 2.0.');
}

// Convert column names to match legacy expectations for downstream code if necessary
$artist['Id'] = $artist['id'];
$artist['Title'] = $artist['title'];
$artist['Body'] = $artist['body'];

// Combine artist's metrics
$mentionsData = getMentionsData($pdo, $artist['Title'], $startDate, $endDate);
$mentionsMetrics = [];
if($mentionsData){
    foreach($mentionsData as $mention){
        $mentionsMetrics[] = [
          'Title' => $mention['title'],
          'Id' => $mention['id'],
          'Author' => $mention['author_id'],
          'PublishedDate' => $mention['published_at'],
          'hits' => getHitsByActionAndEntityId($pdo, $mention['id'],'article_view')
        ];
    }
}

$posts = getPostsByUserId($pdo, $artist['Id'], $startDate, $endDate);
$postMetrics = [];
if($posts){
    foreach($posts as $post) {
        $postMetrics = [
            'Title' => $post['title'],
            'Id' => $post['id'],
            'Author' => $post['author_id'],
            'PublishedDate' => $post['published_at'],
            'hits' => getHitsByActionAndEntityId($pdo, $post['id'],'article_view')
        ];
    }
}

$spins = getSpinsData($pdo, $artist['Title'], $startDate, $endDate);
$spinMetrics = [];
if($spins){
    foreach($spins as $spin){
        $artistTitle = isset($spin['Artist']) ? $spin['Artist'] : $spin['Artists'];
        $spinMetrics[] = [
            'Title' => $spin['Song'],
            'Artist' => $artistTitle,
            'Label' => null,
            'Timestamp' => $spin['Timestamp'],
            'TWS' => $spin['TWS']
        ];
    }
}

$releases = getReleasesByArtistId($pdo, $artist['Id'], $startDate, $endDate);
$releaseMetrics = [];
if($releases){
    foreach($releases as $release){
        $releaseMetrics[] = [
            'Title' => $release['title'],
            'Id' => $release['id'],
            'hits' => getHitsByActionAndEntityId($pdo, $release['id'],'release_view'),
            'ReleaseDate' => $release['released_at']
        ];
    }
}

$videos = getVideosByArtistId($pdo, $artist['Id'], $startDate, $endDate);
$videoMetrics = [];
if($videos){
    foreach($videos as $video){
        $videoMetrics[] = [
            'Title' => $video['title'],
            'Id' => $video['id'],
            'hits' => getHitsByActionAndEntityId($pdo, $video['id'],'video_view'),
            'ReleaseDate' => $video['published_at'],
        ];
    }
}

$metrics = [
    'ScoreChanges' => analyzeArtistScoreChanges($artist['Id'], $startDate, $endDate) ?? [],
    'ScorePeaks' => analyzeArtistScorePeaks($artist['Id'], $startDate, $endDate) ?? [],
    'ImpactScore' => getArtistImpact($artist, $startDate, $endDate) ?? 0,
    'PopularityScore' => getArtistPopularityTrend($artist, $startDate, $endDate) ?? 0,
    'RisingScore' => getRisingScore($artist, $startDate, $endDate) ?? 0,
    'MentionsData' => $mentionsMetrics,
    'releases' => $releaseMetrics,
    'videos' => $videoMetrics,
    'posts' => $postMetrics,
    'SpinsData' => $spinMetrics,
    'hits' => getHitsByActionAndEntityId($artist['Id'],'artist_view') ?? 0,
];

// Generate and output the story for each artist
echo '<div class="story-container">';
echo generateArtistStory($artist, $metrics, $startDate, $endDate);
echo '<hr>';
echo '<div class="border p-3 small">';
echo '<h5>Metrics FOR AI</h5>';
$startDate = convertDate('01/01/2024');
$endDate = convertDate('Today');

// Combine artist's metrics
$mentionsData = getMentionsData($pdo, $artist['Title'], $startDate, $endDate);
$mentionsMetrics = [];
if($mentionsData){
    foreach($mentionsData as $mention){
        $mentionsMetrics[] = [
            'Title' => $mention['title'],
            'Id' => $mention['id'],
            'Author' => $mention['author_id'],
            'PublishedDate' => $mention['published_at'],
            'hits' => getHitsByActionAndEntityId($pdo, $mention['id'],'article_view')
        ];
    }
}

$posts = getPostsByUserId($pdo, $artist['Id'], $startDate, $endDate);
$postMetrics = [];
if($posts){
    foreach($posts as $post) {
        $postMetrics = [
            'Title' => $post['title'],
            'Id' => $post['id'],
            'Author' => $post['author_id'],
            'PublishedDate' => $post['published_at'],
            'hits' => getHitsByActionAndEntityId($pdo, $post['id'],'article_view')
        ];
    }
}

$spins = getSpinsData($pdo, $artist['Title'], $startDate, $endDate);
$spinMetrics = [];
if($spins){
    foreach($spins as $spin){
        $artistTitle = isset($spin['Artist']) ? $spin['Artist'] : $spin['Artists'];
        $label = isset($spin['Label']) ? $spin['Label'] : '';
        $spinMetrics[] = [
            'Title' => $spin['Song'],
            'Artist' => $artistTitle,
            'Label' => $label,
            'Timestamp' => $spin['Timestamp'],
            'TWS' => $spin['TWS']
        ];
    }
}

$releases = getReleasesByArtistId($pdo, $artist['Id'], $startDate, $endDate);
$releaseMetrics = [];
if($releases){
    foreach($releases as $release){
        $releaseMetrics[] = [
            'Title' => $release['title'],
            'Id' => $release['id'],
            'hits' => getHitsByActionAndEntityId($pdo, $release['id'],'release_view'),
            'ReleaseDate' => $release['released_at']
        ];
    }
}

$videos = getVideosByArtistId($pdo, $artist['Id'], $startDate, $endDate);
$videoMetrics = [];
if($videos){
    foreach($videos as $video){
        $videoMetrics[] = [
            'Title' => $video['title'],
            'Id' => $video['id'],
            'hits' => getHitsByActionAndEntityId($pdo, $video['id'],'video_view'),
            'ReleaseDate' => $video['published_at'],
        ];
    }
}

$metrics = [
    'ScoreChanges' => analyzeArtistScoreChanges($artist['Id'], $startDate, $endDate) ?? [],
    'ScorePeaks' => analyzeArtistScorePeaks($artist['Id'], $startDate, $endDate) ?? [],
    'ImpactScore' => getArtistImpact($artist, $startDate, $endDate) ?? 0,
    'PopularityScore' => getArtistPopularityTrend($artist, $startDate, $endDate) ?? 0,
    'RisingScore' => getRisingScore($artist, $startDate, $endDate) ?? 0,
    'MentionsData' => $mentionsMetrics,
    'releases' => $releaseMetrics,
    'videos' => $videoMetrics,
    'posts' => $postMetrics,
    'SpinsData' => $spinMetrics,
    'hits' => getHitsByActionAndEntityId($artist['Id'],'artist_view') ?? 0,
];
var_dump($metrics);
echo '</div>';
echo '</div>';






/**
 * Generates an artist story based on their metrics.
 */
function generateArtistStory($artist, $metrics, $startDate, $endDate)
{
    // Define thresholds for "mind-blowing" metrics
    $dayDifference = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24);
    $thresholds = [
        'ImpactScore' => $dayDifference > 90 ? 5000 : ($dayDifference > 60 ? 2500 : 1000),
        'PopularityScore' => $dayDifference > 90 ? 10000 : ($dayDifference > 60 ? 2500 : 1000),
        'RisingScore' => $dayDifference > 90 ? 10 : ($dayDifference > 60 ? 8 : 5),
        'MentionsData' => $dayDifference > 90 ? 5 : ($dayDifference > 60 ? 3 : 1),
        'releases' => $dayDifference > 90 ? 2 : ($dayDifference > 60 ? 1 : 1),
        'videos' => $dayDifference > 90 ? 2 : ($dayDifference > 60 ? 1 : 1),
        'SpinsData' => $dayDifference > 90 ? 300 : ($dayDifference > 60 ? 200 : 50),
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

    // Generate stories for all notable metrics
    $stories = [];
    foreach ($priorities as $metric => $value) {
        if ($value > 0) {
            switch ($metric) {
                case 'ImpactScore':
                    $stories[] = "<div>💣 {$artist['Title']} is leading the industry with an Impact Score of {$metrics['ImpactScore']}!</div>";
                    break;
                case 'PopularityScore':
                    $stories[] = "<div>🔥 {$artist['Title']} is breaking records with a Popularity Score of {$metrics['PopularityScore']}!</div>";
                    break;
                case 'RisingScore':
                    $stories[] = "<div>🚀 {$artist['Title']} is on a high-growth trajectory with a Rising Score of {$metrics['RisingScore']}!</div>";
                    break;
                case 'MentionsData':
                    $mentionCount = count($metrics['MentionsData']);
                    $stories[] = "<div>💬 {$artist['Title']} was mentioned {$mentionCount} times recently, showing their growing influence.</div>";
                    break;
                case 'releases':
                    $releaseCount = count($metrics['releases']);
                    $stories[] = "<div>🎶 {$artist['Title']} has released {$releaseCount} tracks recently. Check out their new music!</div>";
                    break;
                case 'videos':
                    $videoCount = count($metrics['videos']);
                    $stories[] = "<div>📹 {$artist['Title']} shared {$videoCount} new videos. Fans are loving the visuals!</div>";
                    break;
                case 'SpinsData':
                    $spins = array_sum(array_column($metrics['SpinsData'], 'tws'));
                    $stories[] = "<div>📻 {$artist['Title']}'s tracks received {$spins} spins on the airwaves recently.</div>";
                    break;
                case 'ViewsData':
                    $viewCount = array_sum(array_column($metrics['ViewsData'], 'view_count'));
                    $stories[] = "<div>👀 {$artist['Title']} received {$viewCount} views on their content recently!</div>";
                    break;
            }
        }
    }

    // Return all stories as a concatenated string
    return implode("\n", $stories);
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
    $postViewsStmt = $pdo->prepare("
        SELECT COALESCE(SUM(view_count), 0) AS post_views
        FROM `ngn_2025`.`posts`
        WHERE author_id = :entityId AND published_at BETWEEN :startDate AND :endDate
    ");
    // Only execute if posts have view_count. Currently, ngn_2025.posts doesn't have a view_count.
    // So, this part would be refined if posts views are tracked. For now, assume 0 for posts.
    // $postViewsStmt->execute([':entityId' => $entityId, ':startDate' => $startDate, ':endDate' => $endDate]);
    // $totalViews += (int)$postViewsStmt->fetchColumn();

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

function getHitsByActionAndEntityId(PDO $pdo, int $entityId, string $action): int
{
    switch ($action) {
        case 'video_view':
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(view_count), 0) FROM `ngn_2025`.`videos` WHERE id = :entityId");
            $stmt->execute([':entityId' => $entityId]);
            return (int)$stmt->fetchColumn();
        case 'article_view':
            // ngn_2025.posts doesn't have a direct view_count. Returning 0 for now.
            // If post views are tracked, this query needs to be updated.
            return 0;
        case 'release_view':
            // ngn_2025.releases doesn't have a direct view_count. Returning 0 for now.
            // If release views are tracked, this query needs to be updated.
            return 0;
        case 'artist_view':
            // Aggregating views for an artist is complex. Placeholder for now.
            // Could sum video views, post views etc. related to the artist.
            return 0;
        default:
            return 0;
    }
}