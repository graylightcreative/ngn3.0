<?php

require_once __DIR__ . '/../../../../lib/bootstrap.php'; // Bootstrap NGN environment

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Http\Response as NGNResponse; // Alias to avoid conflict

$_POST = json_decode(file_get_contents("php://input"), true); // This line needs to be kept if data comes via POST

$config = new Config();
$pdo = ConnectionFactory::write($config); // Use ngn_2025 connection
$spinsPdo = ConnectionFactory::named($config, 'spins2025'); // Use ngn_spins_2025 connection
$smrPdo = ConnectionFactory::named($config, 'smr2025'); // Use ngn_smr_2025 connection

$startDate = !isset($_REQUEST['start_date']) ? '2024-01-01' : $_REQUEST['start_date'];
$endDate = !isset($_REQUEST['end_date']) ? date('Y-m-d') : $_REQUEST['end_date'];
$count = !isset($_REQUEST['c']) ? 5000 : $_REQUEST['c'];

$response = new NGNResponse(); // Use aliased Response class

function convertDate($date) {
    return date('Y-m-d H:i:s', strtotime($date));
}

// Ensure dates are in Y-m-d H:i:s format for database queries
$startDateFormatted = convertDate($startDate);
$endDateFormatted = convertDate($endDate);

// Call the refactored function
$impactfulArtists = getImpactfulArtists($pdo, $spinsPdo, $smrPdo, $startDateFormatted, $endDateFormatted, $count);

$response->success = true;
$response->message = 'Impactful artists fetched successfully.';
$response->data = $impactfulArtists;
echo json_encode($response);
exit;

// The getRankingsData function was removed as it's no longer used outside of getArtistImpact
// (and its logic was integrated into getArtistImpact for NGN 2.0 structure)


function getImpactfulArtists(PDO $pdo, PDO $spinsPdo, PDO $smrPdo, string $startDate, string $endDate, int $topN) {
    $impactfulArtists = [];

    // Fetch distinct artists from ngn_2025.artists
    $artistQuery = $pdo->prepare("SELECT id, name, slug FROM `ngn_2025`.`artists`");
    $artistQuery->execute();
    $artists = $artistQuery->fetchAll(PDO::FETCH_ASSOC);

    // Convert dates to standardized format
    $startDateFormatted = $startDate;
    $endDateFormatted = $endDate;

    foreach ($artists as $artist) {
        $artistId = $artist['id'];
        $artistName = $artist['name'];
        $impactScore = 0; // Initialize impact score

        // 1. New Releases Impact (using ngn_2025.releases)
        // Impact is based on the count of new releases by the artist in the period
        $releasesCountStmt = $pdo->prepare("
            SELECT COUNT(*) FROM `ngn_2025`.`releases`
            WHERE artist_id = :artist_id AND released_at BETWEEN :startDate AND :endDate
        ");
        $releasesCountStmt->execute([':artist_id' => $artistId, ':startDate' => $startDateFormatted, ':endDate' => $endDateFormatted]);
        $newReleasesCount = (int)$releasesCountStmt->fetchColumn();
        $impactScore += $newReleasesCount * 5; // Example weight

        // 2. New Videos Impact (using ngn_2025.videos)
        // Count new videos and their total view counts by the artist in the period
        $videosDataStmt = $pdo->prepare("
            SELECT COALESCE(SUM(view_count), 0) AS TotalViews, COUNT(*) AS TotalVideos
            FROM `ngn_2025`.`videos`
            WHERE artist_id = :artist_id AND created_at BETWEEN :startDate AND :endDate
        ");
        $videosDataStmt->execute([':artist_id' => $artistId, ':startDate' => $startDateFormatted, ':endDate' => $endDateFormatted]);
        $videosData = $videosDataStmt->fetch(PDO::FETCH_ASSOC);
        $newVideosCount = (int)($videosData['TotalVideos'] ?? 0);
        $videoViews = (int)($videosData['TotalViews'] ?? 0);
        $impactScore += $newVideosCount * 10; // Example weight for new videos
        $impactScore += $videoViews * 0.05; // Example weight for video views

        // 3. Post Mentions Impact (using ngn_2025.posts)
        $mentionsQuery = "
            SELECT id, title, teaser, tags, body
            FROM `ngn_2025`.`posts`
            WHERE (title LIKE :q1 OR teaser LIKE :q2 OR tags LIKE :q3 OR body LIKE :q4)
              AND published_at BETWEEN :startDate AND :endDate";
        $mentionsStmt = $pdo->prepare($mentionsQuery);
        $searchTerm = '%' . $artistName . '%';
        $mentionsStmt->execute(
            [
                ':q1' => $searchTerm,
                ':q2' => $searchTerm,
                ':q3' => $searchTerm,
                ':q4' => $searchTerm,
                ':startDate' => $startDateFormatted,
                ':endDate' => $endDateFormatted
            ]
        );
        $mentions = $mentionsStmt->fetchAll(PDO::FETCH_ASSOC);

        $mentionImpactScore = 0;
        $weights = [
            'title' => 4.0, 'tags' => 3.0, 'teaser' => 2.0, 'body' => 1.0,
        ];
        foreach ($mentions as $mention) {
            if (stripos($mention['title'], $artistName) !== false) { $mentionImpactScore += $weights['title']; }
            if (stripos($mention['tags'], $artistName) !== false) { $mentionImpactScore += $weights['tags']; }
            if (stripos($mention['teaser'], $artistName) !== false) { $mentionImpactScore += $weights['teaser']; }
            if (stripos($mention['body'], $artistName) !== false) { $mentionImpactScore += $weights['body']; }
        }
        $impactScore += $mentionImpactScore;

        // 4. Radio Spins Impact (using ngn_spins_2025.station_spins and ngn_smr_2025.smr_chart)
        // Get total spins from ngn_spins_2025
        $stationSpinsStmt = $spinsPdo->prepare("
            SELECT COALESCE(SUM(meta->>'$.tws'), 0) AS TotalSpinWeight
            FROM `ngn_spins_2025`.`station_spins`
            WHERE artist_id = :artist_id AND played_at BETWEEN :startDate AND :endDate
        ");
        $stationSpinsStmt->execute([':artist_id' => $artistId, ':startDate' => $startDateFormatted, ':endDate' => $endDateFormatted]);
        $radioSpinWeight = (int)$stationSpinsStmt->fetchColumn();

        // Get total spins from ngn_smr_2025.smr_chart
        $smrSpinsStmt = $smrPdo->prepare("
            SELECT COALESCE(SUM(tws), 0) AS TotalSMRSpinWeight
            FROM `ngn_smr_2025`.`smr_chart`
            WHERE artist LIKE :artist_name AND window_date BETWEEN :startDate AND :endDate
        ");
        $smrSpinsStmt->execute([':artist_name' => '%'.$artistName.'%', ':startDate' => $startDateFormatted, ':endDate' => $endDateFormatted]);
        $smrSpinWeight = (int)$smrSpinsStmt->fetchColumn();

        $combinedSpinWeight = $radioSpinWeight + $smrSpinWeight;
        $impactScore += $combinedSpinWeight * 5; // Example weight

        // Add the artist to the list if they have an impact score > 0
        if ($impactScore > 0) {
            $impactfulArtists[] = [
                'artist_id' => $artistId,
                'artist_name' => $artistName,
                'impact_score' => $impactScore,
            ];
        }
    }

    // Sort the impactful artists by impact score in descending order
    usort($impactfulArtists, function ($a, $b) {
        return $b['impact_score'] <=> $a['impact_score'];
    });

    // Return only the top N impactful artists
    return array_slice($impactfulArtists, 0, $topN);
}

echo json_encode(getImpactfulArtists($startDate, $endDate, $count));