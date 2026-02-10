<?php

function getArtistPopularityTrend(PDO $pdo, int $artistId, string $startDate, string $endDate)
{
    $trendScore = 0;

    // --- 1. Ranking Score Trend (using ngn_rankings_2025.ranking_items) ---
    // Fetch weekly ranking scores for the artist within the period
    $rankingScoresStmt = $pdo->prepare("
        SELECT nri.score, nrh.window_end
        FROM `ngn_rankings_2025`.`ranking_items` nri
        JOIN `ngn_rankings_2025`.`ranking_windows` nrh ON nri.window_id = nrh.id
        WHERE nri.entity_type = 'artist' AND nri.entity_id = :artist_id
          AND nrh.interval = 'weekly' AND nrh.window_end BETWEEN :startDate AND :endDate
        ORDER BY nrh.window_end ASC
    ");
    $rankingScoresStmt->execute([':artist_id' => $artistId, ':startDate' => $startDate, ':endDate' => $endDate]);
    $rankingData = $rankingScoresStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rankingData) > 1) {
        $scoreIncrease = $rankingData[count($rankingData) - 1]['score'] - $rankingData[0]['score'];
        $trendScore += $scoreIncrease * 50; // Example weight
    }

    // --- 2. Views Trend (using ngn_2025.history for video views) ---
    $viewsDataStmt = $pdo->prepare("
        SELECT COALESCE(SUM(v.view_count), 0) as TotalViews
        FROM `ngn_2025`.`videos` v
        WHERE v.artist_id = :artist_id AND v.published_at BETWEEN :startDate AND :endDate
    ");
    $viewsDataStmt->execute([':artist_id' => $artistId, ':startDate' => $startDate, ':endDate' => $endDate]);
    $viewsData = $viewsDataStmt->fetch(PDO::FETCH_ASSOC);
    $viewCountIncrease = $viewsData['TotalViews'] ?? 0;
    $trendScore += $viewCountIncrease * 25;

    // --- 3. Spins Trend (using ngn_spins_2025.station_spins) ---
    $spinsDataStmt = $pdo->prepare("
        SELECT COUNT(*) as TotalSpins
        FROM `ngn_spins_2025`.`station_spins` ss
        WHERE ss.artist_id = :artist_id AND ss.played_at BETWEEN :startDate AND :endDate
    ");
    $spinsDataStmt->execute([':artist_id' => $artistId, ':startDate' => $startDate, ':endDate' => $endDate]);
    $spinsData = $spinsDataStmt->fetch(PDO::FETCH_ASSOC);
    $spinsCountIncrease = $spinsData['TotalSpins'] ?? 0;
    $trendScore += $spinsCountIncrease * 50;

    // --- 4. Post Mentions Trend (using ngn_2025.posts) ---
    // Assuming 'tags' or 'body' can contain mentions. This is a heuristic.
    $postMentionsDataStmt = $pdo->prepare("
        SELECT id, created_at, tags, body
        FROM `ngn_2025`.`posts`
        WHERE author_id = :artist_id AND created_at BETWEEN :startDate AND :endDate
        ORDER BY created_at ASC
    ");
    $postMentionsDataStmt->execute([':artist_id' => $artistId, ':startDate' => $startDate, ':endDate' => $endDate]);
    $postMentionsData = $postMentionsDataStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($postMentionsData)) {
        $now = strtotime($endDate);
        $recentMentions = 0;
        $previousMentions = 0;

        foreach ($postMentionsData as $data) {
            $timestamp = strtotime($data['created_at']);
            if ($timestamp >= $now - 30 * 24 * 60 * 60) { // Last 30 days
                $recentMentions++;
            } elseif ($timestamp >= $now - 60 * 24 * 60 * 60 && $timestamp < $now - 30 * 24 * 60 * 60) { // 30-60 days ago
                $previousMentions++;
            }
        }
        $mentionsDifference = $recentMentions - $previousMentions;
        $trendScore += $mentionsDifference * 15;
    }

    // --- 5. Releases Trend (using ngn_2025.releases) ---
    $releasesDataStmt = $pdo->prepare("
        SELECT COUNT(*) as TotalReleases
        FROM `ngn_2025`.`releases`
        WHERE artist_id = :artist_id AND released_at BETWEEN :startDate AND :endDate
    ");
    $releasesDataStmt->execute([':artist_id' => $artistId, ':startDate' => $startDate, ':endDate' => $endDate]);
    $releasesData = $releasesDataStmt->fetch(PDO::FETCH_ASSOC);
    $recentReleases = $releasesData['TotalReleases'] ?? 0;
    $trendScore += $recentReleases * 5;

    // --- 6. Videos Upload Trend (using ngn_2025.videos) ---
    $videoUploadDataStmt = $pdo->prepare("
        SELECT COUNT(*) as TotalVideos
        FROM `ngn_2025`.`videos`
        WHERE artist_id = :artist_id AND created_at BETWEEN :startDate AND :endDate
    ");
    $videoUploadDataStmt->execute([':artist_id' => $artistId, ':startDate' => $startDate, ':endDate' => $endDate]);
    $videoUploadData = $videoUploadDataStmt->fetch(PDO::FETCH_ASSOC);
    $recentVideos = $videoUploadData['TotalVideos'] ?? 0;
    $trendScore += $recentVideos * 10;

    return $trendScore;
}