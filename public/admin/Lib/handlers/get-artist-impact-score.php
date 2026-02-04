<?php

//$root = $_SERVER['DOCUMENT_ROOT'] . '/';
//
//require $root.'lib/definitions/site-settings.php';
//require $root.'lib/controllers/ResponseController.php';
//require $root.'admin/lib/definitions/admin-settings.php';
//
////$_POST = json_decode(file_get_contents("php://input"), true);
//
//$startDate = !isset($_REQUEST['start_date']) ? '01/01/2024' : $_REQUEST['start_date'];
//$endDate = !isset($_REQUEST['end_date']) ? 'Today' : $_REQUEST['end_date'];
//$id = !isset($_REQUEST['id']) ? 1286 : $_REQUEST['id'];
//$artist = read('users','Id',$id);
//
//$response = makeResponse();


function getArtistImpact(PDO $pdo, int $artistId, string $artistName, string $startDate, string $endDate) {
    $impactScore = 0; // Initialize impact score

    // --- 1. New Releases Impact (using ngn_2025.releases) ---
    // Instead of Hits, we count releases within the period and attribute impact
    $releasesCountStmt = $pdo->prepare("
        SELECT COUNT(*) FROM `ngn_2025`.`releases`
        WHERE artist_id = :artist_id AND released_at BETWEEN :startDate AND :endDate
    ");
    $releasesCountStmt->execute([':artist_id' => $artistId, ':startDate' => $startDate, ':endDate' => $endDate]);
    $newReleasesCount = (int)$releasesCountStmt->fetchColumn();
    $impactScore += $newReleasesCount * 5; // Example weight for new releases

    // --- 2. New Videos Impact (using ngn_2025.videos) ---
    // Count new videos and their view counts
    $videosDataStmt = $pdo->prepare("
        SELECT SUM(view_count) AS TotalViews, COUNT(*) AS TotalVideos
        FROM `ngn_2025`.`videos`
        WHERE artist_id = :artist_id AND created_at BETWEEN :startDate AND :endDate
    ");
    $videosDataStmt->execute([':artist_id' => $artistId, ':startDate' => $startDate, ':endDate' => $endDate]);
    $videosData = $videosDataStmt->fetch(PDO::FETCH_ASSOC);
    $newVideosCount = (int)($videosData['TotalVideos'] ?? 0);
    $videoViews = (int)($videosData['TotalViews'] ?? 0);
    $impactScore += $newVideosCount * 10; // Example weight for new videos
    $impactScore += $videoViews * 0.05; // Example weight for video views

    // --- 3. Post Mentions Impact (using ngn_2025.posts) ---
    $mentionsQuery = "
        SELECT id, title, teaser, tags, body
        FROM `ngn_2025`.`posts`
        WHERE (title LIKE :q1 OR teaser LIKE :q2 OR tags LIKE :q3 OR body LIKE :q4)
          AND published_at BETWEEN :startDate AND :endDate";
    $mentionsStmt = $pdo->prepare($mentionsQuery);
    $searchTerm = '%' . $artistName . '%';
    $mentionsStmt->execute([
        ':q1' => $searchTerm,
        ':q2' => $searchTerm,
        ':q3' => $searchTerm,
        ':q4' => $searchTerm,
        ':startDate' => $startDate,
        ':endDate' => $endDate
    ]);
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

    // --- 4. Radio Spins Impact (using ngn_spins_2025.station_spins and ngn_smr_2025.smr_chart) ---
    // Get total spins from ngn_spins_2025
    $spinsPdo = NGN\Lib\DB\ConnectionFactory::named($GLOBALS['config'], 'spins2025');
    $smrPdo = NGN\Lib\DB\ConnectionFactory::named($GLOBALS['config'], 'smr2025');

    $stationSpinsStmt = $spinsPdo->prepare("
        SELECT COALESCE(SUM(meta->>'$.tws'), 0) AS TotalSpinWeight
        FROM `ngn_spins_2025`.`station_spins`
        WHERE artist_id = :artist_id AND played_at BETWEEN :startDate AND :endDate
    ");
    $stationSpinsStmt->execute([':artist_id' => $artistId, ':startDate' => $startDate, ':endDate' => $endDate]);
    $radioSpinWeight = (int)$stationSpinsStmt->fetchColumn();

    // Get total spins from ngn_smr_2025.smr_chart
    $smrSpinsStmt = $smrPdo->prepare("
        SELECT COALESCE(SUM(tws), 0) AS TotalSMRSpinWeight
        FROM `ngn_smr_2025`.`smr_chart`
        WHERE artist_id = :artist_id AND window_date BETWEEN :startDate AND :endDate
    ");
    // NOTE: ngn_smr_2025.smr_chart currently stores artist name as string, not artist_id.
    // Assuming artist_id will be mapped or this query needs to use artist name.
    // For now, let's adapt to use artist name.
    $smrSpinsStmt = $smrPdo->prepare("
        SELECT COALESCE(SUM(tws), 0) AS TotalSMRSpinWeight
        FROM `ngn_smr_2025`.`smr_chart`
        WHERE artist LIKE :artist_name AND window_date BETWEEN :startDate AND :endDate
    ");
    $smrSpinsStmt->execute([':artist_name' => '%'.$artistName.'%', ':startDate' => $startDate, ':endDate' => $endDate]);
    $smrSpinWeight = (int)$smrSpinsStmt->fetchColumn();


    $combinedSpinWeight = $radioSpinWeight + $smrSpinWeight;
    $impactScore += $combinedSpinWeight * 5; // Example weight

    return $impactScore;
}

//echo json_encode(getArtistImpact($artist, $startDate, $endDate));