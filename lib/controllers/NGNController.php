<?php

//**
//* NGN Music Chart System
//*
// * This script generates the Top 500 Artists and Labels charts based on
//* data from the SMR Charts API and the Posts API, using a weighted
//* ranking system.
// */



//function aggregateViews() {
//    global $pdo;
//
//    $entityIdCase = getEntityIdCaseStatement();
//    $relevantArticleCountCase = getRelevantArticleCountCaseStatement();
//
//    $sql = "
//        SELECT
//            Action,
//            $entityIdCase,
//            SUM(ViewCount) AS total_views,
//            $relevantArticleCountCase,
//            Timestamp
//        FROM hits
//        WHERE Action IN ('artist_view', 'label_view', 'article_view')
//        GROUP BY Action, entity_id;
//    ";
//
//    $stmt = $pdo->prepare($sql);
//    $stmt->execute();
//    $viewData = $stmt->fetchAll(PDO::FETCH_ASSOC);
//
//    return $viewData;
//}
//
//function populatePostMentionsTable() {
//    global $pdo;
//
//    $artists = readMany('users', 'role_id', 3);
//    $labels = readMany('users', 'role_id', 7);
//    $posts = browse('posts');
//
//    foreach ($artists as $artist) {
//        $artistId = $artist['Id'];
//        $artistName = $artist['Title'];
//
//        foreach ($posts as $post) {
//            $foundIn = []; // Store locations where the artist is mentioned
//
//            if (strpos($post['Title'], $artistName) !== false) {
//                $foundIn[] = 'Title';
//            }
//            if (strpos($post['Body'], $artistName) !== false) {
//                $foundIn[] = 'Body';
//            }
//            if (strpos($post['Tags'], $artistName) !== false) {
//                $foundIn[] = 'Tags';
//            }
//            if (strpos($post['Summary'], $artistName) !== false) {
//                $foundIn[] = 'Summary';
//            }
//
//            if (!empty($foundIn)) {
//                $data = [
//                    'PostId' => $post['Id'],
//                    'ArtistId' => $artistId,
//                    'FoundIn' => implode(',', $foundIn), // Store locations as comma-separated string
//                    'Timestamp' => date('Y-m-d H:i:s')
//                ];
//                add('post_mentions', $data);
//            }
//        }
//    }
//
//    // Handle labels
//    foreach ($labels as $label) {
//        $labelId = $label['Id'];
//        $labelName = $label['Title'];
//
//        foreach ($posts as $post) {
//            $foundIn = []; // Store locations where the artist is mentioned
//
//            if (strpos($post['Title'], $labelName) !== false) {
//                $foundIn[] = 'Title';
//            }
//            if (strpos($post['Body'], $labelName) !== false) {
//                $foundIn[] = 'Body';
//            }
//            if (strpos($post['Tags'], $labelName) !== false) {
//                $foundIn[] = 'Tags';
//            }
//            if (strpos($post['Summary'], $labelName) !== false) {
//                $foundIn[] = 'Summary';
//            }
//
//            if (!empty($foundIn)) {
//                $data = [
//                    'PostId' => $post['Id'],
//                    'LabelId' => $labelId,
//                    'FoundIn' => implode(',', $foundIn), // Store locations as comma-separated string
//                    'Timestamp' => date('Y-m-d H:i:s')
//                ];
//                add('post_mentions', $data);
//            }
//        }
//    }
//}
//
///////////////////////////
//// POST MENTIONS //
//////////////////////////
//
//function getRelevantArticleViews($aggregatedViewData, $entityId, $lookupKey) {
//    $relevantArticleViews = 0;
//    if (isset($aggregatedViewData['article_view'])) {
//        foreach ($aggregatedViewData['article_view'] as $articleId => $articleData) {
//            if (isEntityMentionedInArticle($articleId, $entityId, $lookupKey)) {
//                $relevantArticleViews += $articleData['total_views'];
//            }
//        }
//    }
//    return $relevantArticleViews;
//}
//
//function getRelevantArticleViewsForLabel($aggregatedViewData, $labelId) {
//    return getRelevantArticleViews($aggregatedViewData, $labelId, 'LabelId');
//}
//
//function getRelevantPostsForLabel($labelId) {
//    return getRelevantPosts($labelId, 'LabelId', 'PostId');
//}
//
//function getRelevantArticleViewsForArtist(array $aggregatedViewData, int $artistId): int {
//    $relevantArticleViews = 0;
//    foreach ($aggregatedViewData as $viewData) {
//        if ($viewData['Action'] === 'article_view' && $viewData['ItemId'] === $artistId) {
//            $relevantArticleViews += (int)$viewData['ViewCount'];
//        }
//    }
//    return $relevantArticleViews;
//}
//function getRelevantPosts($entityId, $entityKey, $postKey) {
//    $postMentions = readMany('post_mentions', $entityKey, $entityId);
//    $relevantPosts = [];
//    foreach ($postMentions as $mention) {
//        $post = read('posts', 'id', $mention[$postKey]);
//        if ($post) {
//            $relevantPosts[] = $post;
//        }
//    }
//    return $relevantPosts;
//}
//
//function isEntityMentionedInArticle($articleId, $entityId, $lookupKey) {
//    $mentions = readMany('post_mentions', 'PostId', $articleId);
//    foreach ($mentions as $mention) {
//        if ($mention[$lookupKey] == $entityId) {
//            return true;
//        }
//    }
//    return false;
//}
//
//
//
//
////////////////////////////
//// (GET) CHART RELATED //
//////////////////////////
//
//function getHitsForItem($action, $entityId) {
//    // Fetch all hits from the database
//    $allHits = readMany('hits', 'Action', $action);
//
//    $totalViews = 0;
//    foreach ($allHits as $hit) {
//        $otherData = json_decode($hit['OtherData'], true);
//        if (isset($otherData[$action . '_id']) && $otherData[$action . '_id'] == $entityId) {
//            $totalViews += $hit['ViewCount'];
//        }
//    }
//
//    return $totalViews;
//}
//
//function getRecentReleasesForArtists(array $artistIds, int $thresholdDays = 3): array
//{
//    global $pdo;
//
//    // If no artist IDs are provided, return an empty array
//    if (empty($artistIds)) {
//        return [];
//    }
//
//    $placeholders = implode(',', array_fill(0, count($artistIds), '?'));
//    $sql = "SELECT * FROM releases
//            WHERE ArtistId IN ($placeholders)
//            AND ReleaseDate >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
//
//    $stmt = $pdo->prepare($sql);
//    $stmt->execute(array_merge($artistIds, [$thresholdDays]));
//
//    return $stmt->fetchAll(PDO::FETCH_ASSOC);
//}
//
//function getNGNGainsForArtists(array $artistIds, int $thresholdDays = 3): array
//{
//    global $pdo;
//
//    if (empty($artistIds)) {
//        return [];
//    }
//
//    $placeholders = implode(',', array_fill(0, count($artistIds), '?'));
//
//    // You'll need to adjust the table names and date fields based on your actual schema
//    $sql = "SELECT
//                arh.ArtistId,
//                arh.Timestamp,
//                arh.Rank - arhp.Rank AS rank_change
//            FROM NGNArtistRankingsHistoryDaily arh
//            INNER JOIN NGNArtistRankingsHistoryDaily arhp ON arh.ArtistId = arhp.ArtistId
//            WHERE arh.ArtistId IN ($placeholders)
//              AND arh.Timestamp >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
//              AND arhp.Timestamp = (
//                  SELECT MAX(Timestamp)
//                  FROM NGNArtistRankingsHistoryDaily
//                  WHERE ArtistId = arh.ArtistId AND Timestamp < arh.Timestamp
//              )
//              AND arh.Rank - arhp.Rank <= -" . $_ENV['SIGNIFICANT_RANK_CHANGE_THRESHOLD']; // Negative change means improvement
//
//    $stmt = $pdo->prepare($sql);
//    $stmt->execute(array_merge($artistIds, [$thresholdDays]));
//
//    return $stmt->fetchAll(PDO::FETCH_ASSOC);
//}
//
//function getSignificantChartGainsForArtists(array $artistIds, int $thresholdDays = 3): array
//{
//    global $pdo;
//
//    if (empty($artistIds)) return [];
//
//    $placeholders = implode(',', array_fill(0, count($artistIds), '?'));
//    $sql = "SELECT Date FROM smr_chart
//            WHERE Artists IN ($placeholders)
//            AND Difference >= " . $_ENV['SIGNIFICANT_ARTIST_SCORE_CHANGE_THRESHOLD'] . "
//            AND Date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
//
//    $stmt = $pdo->prepare($sql);
//    $stmt->execute(array_merge($artistIds, [$thresholdDays]));
//
//    return $stmt->fetchAll(PDO::FETCH_ASSOC);
//}
//
//
/////////////////////////////////////////////////////////////////
//// CALCULATIONS
/////////////////////////////////////////////////////////////////
//
//
//function calculateArtistChanges($topArtists) {
//    $intervals = ['daily', 'weekly', 'monthly', 'yearly'];
//
//    $artistChanges = array_reduce($intervals, function($acc, $interval) use ($topArtists) {
//        $acc[$interval] = calculateArtistChangesForInterval($topArtists, $interval);
//        return $acc;
//    }, []);
//
//    return $artistChanges;
//}
//
//function calculateArtistChangesForInterval($currentArtistRankings, $interval) {
//    $previousArtistRankings = getHistoricalRankings($interval, 'artist');
//
//    if ($previousArtistRankings === false) {
//        throw new \Exception("Error fetching previous artist rankings for interval $interval");
//    }
//
//    $artistChanges = [];
//
//    foreach ($currentArtistRankings as $artistId => $currentRanking) {
//        $artistId = (int)$artistId;
//        $previousRanking = findRankingByArtistId($previousArtistRankings, $artistId);
//
//        // Separate function to calculate changes, handling missing data
//        $artistChanges[$artistId] = calculateRankingChanges($currentRanking, $previousRanking);
//    }
//
//    return $artistChanges;
//}
//
//function calculateLabelChanges($topLabels) {
//    $labelChangesDaily = calculateLabelChangesForInterval($topLabels, 'daily');
//    $labelChangesWeekly = calculateLabelChangesForInterval($topLabels, 'weekly');
//    $labelChangesMonthly = calculateLabelChangesForInterval($topLabels, 'monthly');
//    $labelChangesYearly = calculateLabelChangesForInterval($topLabels, 'yearly');
//
//    return [
//        'daily' => $labelChangesDaily,
//        'weekly' => $labelChangesWeekly,
//        'monthly' => $labelChangesMonthly,
//        'yearly' => $labelChangesYearly
//    ];
//}
//
//function calculateLabelChangesForInterval($topLabels, $interval) {
//    $previousLabelRankings = getHistoricalRankings($interval, 'label');
//
//    if ($previousLabelRankings === false) {
//        throw new \Exception("Error fetching previous label rankings for interval $interval");
//    }
//
//    $labelChanges = [];
//
//    foreach ($topLabels as $labelId => $currentRanking) {
//        $labelId = (int)$labelId;
//        $previousRanking = findRankingByLabelId($previousLabelRankings, $labelId);
//
//        // Use the same calculateRankingChanges function as for artists
//        $labelChanges[$labelId] = calculateRankingChanges($currentRanking, $previousRanking);
//    }
//
//    return $labelChanges;
//}
//
//function calculateRankingChanges($currentRanking, $previousRanking) {
//    if ($previousRanking && isset($previousRanking['Rank'], $previousRanking['totalScore'])) {
//        $positionChange = calculatePositionChange($currentRanking['Rank'], $previousRanking['Rank']);
//        $scoreChange = calculateScoreChange($currentRanking['totalScore'], $previousRanking['totalScore']);
//        $trend = getTrend($positionChange);
//    } else if (!$previousRanking) {
//        $positionChange = 0;
//        $scoreChange = 0;
//        $trend = 'New';
//    } else {
//        // Handle incomplete data (log or throw exception as needed)
//        throw new \Exception("Error: Incomplete previous ranking data");
//    }
//
//    return [
//        'previous_rank' => $previousRanking ? $previousRanking['Rank'] : null,
//        'previous_score' => $previousRanking ? $previousRanking['totalScore'] : null,
//        'position_change' => $positionChange,
//        'score_change' => $scoreChange,
//        'trend' => $trend
//    ];
//}
//
//function calculateScoreChange($currentScore, $previousScore) {
//    return $previousScore === null ? 0 : round($currentScore - $previousScore, 2);
//}
//
//function calculatePositionChange($currentRank, $previousRank) {
//    // If there's no previous rank, the change is 0
//    if ($previousRank === null) {
//        return 0;
//    }
//
//    return (int) round($currentRank) - (int) round($previousRank);
//}
//
//function calculateArtistScoreFromChartData($smrDataRow)
//{
//    // If any required data is missing or invalid, return 0
//    if (!isset($smrDataRow['TWS'], $smrDataRow['Peak'], $smrDataRow['WOC']) ||
//        !is_numeric($smrDataRow['TWS']) || !is_numeric($smrDataRow['Peak']) || !is_numeric($smrDataRow['WOC'])) {
//        return 0;
//    }
//    $totalArtists = count(readMany('users', 'role_id', 3));
//
//    $positionPoints = ($totalArtists - $smrDataRow['TWS']) * $_ENV['POSITION_WEIGHT'];
//
//    // Use the null coalescing operator to handle missing 'LWTW'
//    $gainPoints = ($smrDataRow['LWTW'] ?? $smrDataRow['TWS']) * $_ENV['GAIN_WEIGHT'];
//
//    $peakPoints = ($totalArtists - $smrDataRow['Peak']) * $_ENV['PEAK_WEIGHT'];
//    $longevityPoints = $smrDataRow['WOC'] * $_ENV['LONGEVITY_WEIGHT'];
//
//    $points = [];
//    $points['Position'] = $positionPoints;
//    $points['Gain'] = $gainPoints;
//    $points['Peak'] = $peakPoints;
//    $points['Longevity'] = $longevityPoints;
//
//    // Calculate and return the sum of the points
//    return $points['Position'] + $points['Gain'] + $points['Peak'] + $points['Longevity'];
//}
//
//function calculateSmrScore($artistId) {
//    $artist = read('users','Id', $artistId);
//    $smrData = search('smr_chart', 'Artists', $artist['Title']);
//
//    $smrScore = 0;
//    if (!empty($smrData)) {
//        foreach($smrData as $data){
//            $smrScore += calculateArtistScoreFromChartData($data);
//        }
//    }
//    return $smrScore;
//}
//
//
//
//function calculateRank($entityId, $scores) {
//    // Sort artist scores in descending order
//    arsort($scores);
//
//    // Get the rank of the artist in the sorted array
//    $rank = array_search($entityId, array_keys($scores)) + 1;
//
//    return $rank;
//}
//
//function calculatePostMentionsScore($entityId, $entityType) {
//    // Fetch post mentions for the entity (artist or label)
//    $postMentions = readMany('post_mentions', $entityType . 'Id', $entityId);
//
//    $postMentionsScore = 0;
//    foreach ($postMentions as $postMention) {
//        $postId = $postMention['PostId'];
//        $post = read('posts', 'id', $postId);
//        if ($post) {
//            // Add the FoundIn information from PostMentions to the $post array
//            $post['FoundIn'] = $postMention['FoundIn'];
//
//            // Calculate points based on FoundIn (assuming it's a CSV string)
//            $postMentionsScore += calculatePointsForPost($post);
//        }
//    }
//    return $postMentionsScore;
//}
//
//function calculatePointsForPost($post) {
//    $pointsLookup = [
//        'Body' => $_ENV['POST_RELEVANCE_WEIGHT'],
//        'Tags' => $_ENV['POST_RELEVANCE_WEIGHT'] / 2,
//        'Summary' => $_ENV['POST_RELEVANCE_WEIGHT'] * 1.5,
//        'Title' => $_ENV['POST_RELEVANCE_WEIGHT'] * 2,
//    ];
//
//    $totalPoints = 0;
//
//    if (is_string($post['FoundIn'])) {
//        $foundIn = explode(',', $post['FoundIn']);
//        foreach ($foundIn as $location) {
//            $totalPoints += $pointsLookup[$location] ?? 0;
//        }
//    }
//
//    return $totalPoints;
//}
//
//function calculatePointsForPosts($postMentions) { // Renamed $posts to $postMentions for clarity
//    $artistPoints = [];
//    $labelPoints = [];
//
//    if($postMentions){
//        foreach ($postMentions as $postMention) {
//            if($postMention){
//                $entityId = $postMention['ArtistId'] ?? $postMention['LabelId'];
//
//                // Fetch the full post data using PostId
//                $post = read('posts', 'id', $postMention['PostId']);
//
//                if ($post) {
//                    // Add the FoundIn information from PostMentions to the $post array
//                    $post['FoundIn'] = $postMention['FoundIn'];
//
//                    // Calculate points based on FoundIn
//                    $points = calculatePointsForPost($post);
//
//                    if (isset($postMention['ArtistId'])) {
//                        $artistPoints[$entityId] = ($artistPoints[$entityId] ?? 0) + $points;
//                    } else {
//                        $labelPoints[$entityId] = ($labelPoints[$entityId] ?? 0) + $points;
//                    }
//                }
//            }
//        }
//    }
//    return [$artistPoints, $labelPoints];
//}
//
//function calculateArtistsScores($artists, $smrData, $postMentionsData, $viewData) {
//    $scores = [];
//
//    // Prepare mappings from post mentions and view data for quick lookups
//    $mentionCounts = [];
//    foreach ($postMentionsData as $mention) {
//        $artistId = $mention['ArtistId'];
//        if (!isset($mentionCounts[$artistId])) {
//            $mentionCounts[$artistId] = 0;
//        }
//        $mentionCounts[$artistId] += 1; // Adjust the increment logic as needed
//    }
//
//    $viewCounts = [];
//    foreach ($viewData as $view) {
//
//        if($view['entity_id'] !== null) {
//            if(isset($viewCount[$view['entity_id']])) {
//                $viewCounts[$view['entity_id']] += $view['total_views'];
//            } else {
//                $viewCounts[$view['entity_id']] = $view['total_views'];
//            }
//
//        }
//
//    }
//
//    // Process each artist and calculate scores using smrData, postMentionsData, and viewData
//    foreach ($artists as $artist) {
//        $artistId = $artist['ArtistId'];
//        $score = 0;
//
////         Calculate SMR score component
//        foreach ($smrData as $entry) {
//            if (strpos($entry['Artists'], $artist['ArtistName']) !== false) {
//                $score += $entry['TWS']; // Decayed TWS
//            }
//        }
//
//        // Add Post Mentions score component
//        if (isset($mentionCounts[$artistId])) {
//            $score += $mentionCounts[$artistId] * $_ENV['MENTION_RELEVANCE_WEIGHT']; // Adjust the weight multiplier as needed
//        }
//
//        // Add View Counts score component
//        if (isset($viewCounts[$artistId])) {
//            $vcs = $viewCounts[$artistId] * $_ENV['ARTIST_VIEW_WEIGHT']; // Adjust the weight multiplier as needed
//            $score += $vcs;
//        }
//
//        $scores[$artistId] = [
//            'ArtistId' => $artistId,
//            'Score' => $score,
//            'SMR_Score' => array_sum(array_column(array_filter($smrData, function($entry) use ($artist) {
//                return $entry['Artists'] == $artist['ArtistName'];
//            }), 'TWS')),
//            'Post_Mentions_Score' => $mentionCounts[$artistId] ?? 0,
//            'Views_Score' => $viewCounts[$artistId] ?? 0,
//            'Rank' => calculateRank($artistId, array_column($scores, 'Score')), // Determine rank based on score
//        ];
//    }
//    return $scores;
//}
//
//function calculateLabelsScores($labels, $smrData, $postMentionsData, $viewData) {
//    $scores = [];
//
//    // Prepare mappings from post mentions and view data for quick lookups
//    $mentionCounts = [];
//    foreach ($postMentionsData as $mention) {
//        if (isset($mention['LabelId'])) {
//            $labelId = $mention['LabelId'];
//            if (!isset($mentionCounts[$labelId])) {
//                $mentionCounts[$labelId] = 0;
//            }
//            $mentionCounts[$labelId] += 1; // Adjust the increment logic as needed
//        }
//    }
//
//    $viewCounts = [];
//    foreach ($viewData as $view) {
//
//        if($view['entity_id'] !== null) {
//            if(isset($viewCount[$view['entity_id']])) {
//                $viewCounts[$view['entity_id']] += $view['total_views'];
//            } else {
//                $viewCounts[$view['entity_id']] = $view['total_views'];
//            }
//
//        }
//    }
//
//    // Process each label and calculate scores using smrData, postMentionsData, and viewData
//    foreach ($labels as $label) {
//        $labelId = $label['LabelId'];
//        $score = 0;
//
//        // Calculate SMR score component
//        foreach ($smrData as $entry) {
//            if ($entry['Label'] == $label['LabelName']) {
//                $score += $entry['TWS']; // Decayed TWS
//            }
//        }
//
//        // Add Post Mentions score component
//        if (isset($mentionCounts[$labelId])) {
//            $score += $mentionCounts[$labelId] * $_ENV['MENTION_RELEVANCE_WEIGHT']; // Adjust the weight multiplier as needed
//        }
//
//        // Add View Counts score component
//        if (isset($viewCounts[$labelId])) {
//            $vcs = $viewCounts[$labelId] * $_ENV['ARTIST_VIEW_WEIGHT']; // Adjust the weight multiplier as needed
//            $score += $vcs;
//        }
//
//        $scores[$labelId] = [
//            'LabelId' => $labelId,
//            'Score' => $score,
//            'SMR_Score' => array_sum(array_column(array_filter($smrData, function($entry) use ($label) {
//                return $entry['Label'] == $label['LabelName'];
//            }), 'TWS')),
//            'Post_Mentions_Score' => $mentionCounts[$labelId] ?? 0,
//            'Views_Score' => $viewCounts[$labelId] ?? 0,
//            'Rank' => calculateRank($labelId, array_column($scores, 'Score')), // Determine rank based on score
//        ];
//    }
//    return $scores;
//}
//
//function calculateViewsScore($entityId, array $viewData) {
//    if(!is_array($viewData)){die('View data is not an array');}
//    // Define scoring multipliers for different actions, usually this value should be obtained from configuration like .env
//    $actionMultipliers = [
//        'artist_view' => 3, // These values should match ARTIST_VIEW_WEIGHT
//        'article_view' => 1, // These values should match ARTICLE_VIEW_WEIGHT
//        'page_view' => 0.5, // These values should match PAGE_VIEW_WEIGHT
//    ];
//
//    // Iterate through viewData and calculate view score
//    foreach ($viewData as &$data) {
//        $action = $data['Action'];
//        $totalViews = $data['total_views'] ?? 0;
//        if($data['entity_id'] === $entityId){
//            if (isset($actionMultipliers[$action])) {
//                $multiplier = $actionMultipliers[$action];
//                $data['view_score'] = $totalViews * $multiplier;
//            } else {
//                // Default score if action is not recognized
//                $data['view_score'] = $totalViews;
//            }
//        }
//
//
//    }
//
//    return $viewData;
//}
//
/////////////////////////////////////////////////////////////////
//// HELPERS
/////////////////////////////////////////////////////////////////
//
//function prepareUserGroup($roleId){
//    $group = [];
//    $users = readMany('users','RoleId',$roleId);
//    foreach($users as $user){
//        switch($roleId){
//            case 3: // artist
//                $group[] = createNewArtistObject($user);
//                break;
//            case 7: // Label
//                $group[] = createNewLabelObject($user);
//                break;
//        }
//    }
//    return $group;
//}
//
//function getRelevantArticleCountCaseStatement() {
//    return "
//        SUM(CASE
//            WHEN Action = 'article_view' THEN (
//                SELECT COUNT(*)
//                FROM posts
//                WHERE
//                    Id = entity_id AND (
//                        Body LIKE CONCAT('%', (SELECT Title FROM users WHERE Id = entity_id), '%') OR
//                        JSON_SEARCH(Tags, 'one', (SELECT Title FROM users WHERE Id = entity_id)) IS NOT NULL
//                    )
//            )
//            WHEN Action = 'artist_view' THEN (
//                SELECT COUNT(*)
//                FROM posts
//                WHERE
//                    (
//                        Body LIKE CONCAT('%', (SELECT Title FROM users WHERE Id = entity_id), '%') OR
//                        JSON_SEARCH(Tags, 'one', (SELECT Title FROM users WHERE Id = entity_id)) IS NOT NULL
//                    )
//            )
//            WHEN Action = 'label_view' THEN (
//                SELECT COUNT(*)
//                FROM posts
//                WHERE
//                    (
//                        Body LIKE CONCAT('%', (SELECT Title FROM users WHERE Id = entity_id AND RoleId = 7), '%') OR
//                        JSON_SEARCH(Tags, 'one', (SELECT Title FROM users WHERE Id = entity_id AND RoleId = 7)) IS NOT NULL
//                    )
//            )
//            ELSE 0
//        END) AS relevant_article_count
//    ";
//}
//
//function getEntityIdCaseStatement() {
//    return "
//        CASE
//            WHEN Action = 'artist_view' THEN JSON_EXTRACT(OtherData, '$.artist_view_id')
//            WHEN Action = 'label_view' THEN JSON_EXTRACT(OtherData, '$.label_view_id')
//            WHEN Action = 'article_view' THEN JSON_EXTRACT(OtherData, '$.article_id')
//            WHEN Action = 'page_view' THEN JSON_EXTRACT(OtherData, '$.page_view_id')
//        END AS entity_id
//    ";
//}
//
//function createNewArtistObject($artist)
//{
//    if(!empty($artist['LabelId'])) {
//        $label = read('users', 'Id', $artist['LabelId']);
//        $labelId = $label['Id'] ?? false;
//    } else {
//        $labelId = false;
//    }
//    return [
//        'ArtistName' => $artist['Title'],
//        'ArtistId' => $artist['Id'],
//        'LabelId' => $labelId,
//        'ArtistPosts' => readMany('post_mentions', 'ArtistId', $artist['Id']) ?: [],
//        'LabelPosts' => $labelId ? readMany('post_mentions', 'LabelId', $labelId) ?: [] : [],
//        'station_spins' => search('station_spins', 'Artist', $artist['Title']) ?: 0,
//        'SMRSpins' => search('smr_chart', 'Artists', $artist['Title']) ?: 0,
//        'ArtistPageViews' => getHitsForItem('artist_view', $artist['Id']) ?: 0,
//        'LabelPageViews' => $labelId ? getHitsForItem('label_view', $labelId) : 0,
//    ];
//}
//
//function createNewLabelObject($label)
//{
//    $artists = readMany('users', 'LabelId', $label['Id']);
//    $artistIds = array_column($artists, 'Id');
//
//    // Build artists posts
//    $artistPosts = [];
//    $artistRadioSpins = [];
//    $artistSmrSpins = [];
//    $artistHits = [];
//
//    foreach($artists as $artist){
//        $posts = readMany('post_mentions', 'ArtistId', $artist['Id']);
//        if($posts) $artistPosts = array_merge($artistPosts, $posts);
//
//        $spins = readMany('station_spins', 'Artist', $artist['Title']);
//        if($spins) $artistRadioSpins = array_merge($artistRadioSpins, $spins);
//
//        $smrSpins = search('smr_chart', 'Artists', $artist['Title']);
//        if($smrSpins) $artistSmrSpins = array_merge($artistSmrSpins, $smrSpins);
//
//        $artistHits[] = getHitsForItem('artist_view', $artist['Id']);
//
//    }
//    return [
//        'LabelName' => $label['Title'],
//        'LabelId' => $label['Id'],
//        'LabelPosts' => readMany('post_mentions', 'LabelId', $label['Id']) ?: [],
//        'ArtistPosts' => $artistPosts,
//        'station_spins' => $artistRadioSpins,
//        'SMRSpins' => $artistSmrSpins,
//        'LabelPageViews' => getHitsForItem('label_view', $label['Id']) ?: 0,
//        'ArtistPageViews' => $artistHits,
//    ];
//}
//
//function hasRecentActivity($labelId) {
//    $artistIds = array_column(readMany('users', 'LabelId', $labelId), 'Id');
//
//    // Check for recent releases or significant chart gains
//    return !empty(getRecentReleasesForArtists($artistIds)) ||
//        hasSignificantChartGainForAnyArtist($artistIds);
//}
//
//function getTrend($positionChange) {
//    return match (true) {
//        $positionChange < 0 => 'Up',
//        $positionChange > 0 => 'Down',
//        default => 'Steady',
//    };
//}
//
//
//
//function hasSignificantChartGainForAnyArtist(array $artistIds): bool
//{
//    global $pdo;
//
//    if (empty($artistIds)) {
//        return false;
//    }
//
//    $placeholders = implode(',', array_fill(0, count($artistIds), '?'));
//    $sql = "SELECT COUNT(*) FROM smr_chart
//            WHERE Artists IN ($placeholders)
//            AND Difference >= " . $_ENV['SIGNIFICANT_ARTIST_SCORE_CHANGE_THRESHOLD'];
//
//    $stmt = $pdo->prepare($sql);
//    $stmt->execute($artistIds);
//
//    return (int)$stmt->fetchColumn() > 0;
//}
//
//
//
/////////////////////////////////////////////////////////////////
//// STORAGE
/////////////////////////////////////////////////////////////////
//
//function storeArtistScores($artistScores, array $viewData)
//{
//    $vd = $viewData;
//    foreach ($artistScores as $artistId => $scores) {
//        $data = [
//            'ArtistId' => $artistId,
//            'Score' => $scores['Score'],
//            'SMR_Score' => $scores['SMR_Score'],
//            'Post_Mentions_Score' => $scores['Post_Mentions_Score'],
//            'Views_Score' => $scores['Views_Score'],
//            'Rank' => $scores['Rank']
//        ];
//        add('NGNArtistRankings', $data);
//    }
//}
//
//function storeLabelScores($labelScores, $viewData)
//{
//    foreach ($labelScores as $labelId => $scores) {
//        $data = [
//            'LabelId' => $labelId,
//            'Score' => $scores['Score'],
//            'SMR_Score' => $scores['SMR_Score'],
//            'Post_Mentions_Score' => $scores['Post_Mentions_Score'],
//            'Views_Score' => $scores['Views_Score'],
//            'Rank' => $scores['Rank']
//        ];
//        add('NGNLabelRankings', $data);
//    }
//}
//
//function storeHistoricalData($artistScores, $labelScores, $viewData, $period)
//{
//    // Table names dynamically constructed based on period
//    $artistTable = "NGNArtistRankingsHistory" . ucfirst($period);
//    $labelTable = "NGNLabelRankingsHistory" . ucfirst($period);
//
//    global $pdo;
//
//    // Store artist scores
//    foreach ($artistScores as $artist) {
//        $add = add($artistTable,[
//            'ArtistId' => $artist['ArtistId'],
//            'Score' => $artist['Score'],
//            'SMR_Score' => $artist['SMR_Score'],
//            'Post_Mentions_Score' => $artist['Post_Mentions_Score'],
//            'Views_Score' => $artist['Views_Score'],
//            'Rank' => $artist['Rank'],
//            'Timestamp' => date('Y-m-d H:i:s')  // Current timestamp
//        ]);
//        if($add){
//            error_log("Stored artist data for period: $period | Data: " . json_encode($artist));
//        } else {
//            error_log("Results are: ".json_encode($add));
//        }
//    }
//
//    // Store label scores
//    foreach ($labelScores as $label) {
//        $stmt = $pdo->prepare("
//            INSERT INTO $labelTable (LabelId, Score, SMR_Score, Post_Mentions_Score, Views_Score, Rank, Timestamp)
//            VALUES (:LabelId, :Score, :SMR_Score, :Post_Mentions_Score, :Views_Score, :Rank, :Timestamp)
//        ");
//        $stmt->execute([
//            ':LabelId' => $label['LabelId'],
//            ':Score' => $label['Score'],
//            ':SMR_Score' => $label['SMR_Score'],
//            ':Post_Mentions_Score' => $label['Post_Mentions_Score'],
//            ':Views_Score' => $label['Views_Score'],
//            ':Rank' => $label['Rank'],
//            ':Timestamp' => date('Y-m-d H:i:s')  // Current timestamp
//        ]);
//        error_log("Stored label data for period: $period | Data: " . json_encode($label));
//    }
//
//    // Views data handling here if needs specific storing for historical data
//    // Implement as needed for specific requirements
//    // error_log("Handling view data for period: $period");
//}
//
//function getHistoricalPeriods(): array
//{
//    return ['Daily', 'Weekly', 'Monthly', 'Yearly'];
//}
//
//function storeHistoricalDataForAllPeriods($artistScores, $labelScores, $viewData)
//{
//    $periods = getHistoricalPeriods();
//
//    // Validate inputs
//    if (empty($artistScores) || empty($labelScores) || empty($viewData)) {
//        throw new InvalidArgumentException("Input data cannot be empty");
//    }
//
//    // Error handling and logging
//    foreach ($periods as $period) {
//        try {
//            error_log("Storing historical data for period: $period");
//            storeHistoricalData($artistScores, $labelScores, $viewData, $period);
//        } catch (Exception $e) {
//            error_log("Failed to store historical data for period: $period. Error: " . $e->getMessage());
//        }
//    }
//}
//
//function processViewDataToScores($viewData) {
//    $scores = [];
//
//    // Iterate over the view data and process each entry.
//    foreach ($viewData as $item) {
//        // Assuming 'views' is the key for view count in the items.
//        $score = calculateScoreFromViews($item);
//
//        // Store the item and its calculated score.
//        $scores[] = [
//            'item' => $item,
//            'score' => $score
//        ];
//    }
//
//    // Sort the scores array by the calculated scores in descending order.
//    usort($scores, function ($a, $b) {
//        return $b['score'] <=> $a['score'];
//    });
//
//    return $scores;
//}
//
//
//function calculateScoreFromViews($viewData) {
//
//    $viewCount = $viewData['ViewCount'];
//    $viewDate = new DateTime($viewData['Timestamp']);
//    $currentDate = new DateTime();
//
//    // Calculate recency factor: newer views are more valuable
//    $interval = $currentDate->diff($viewDate);
//    $daysElapsed = $interval->days;
//
//    // Example recency factor: Exponential decay function (this can be adjusted)
//    $recencyFactor = exp(-0.1 * $daysElapsed);
//
//    // Calculate the final score
//    $score = $viewCount * $recencyFactor;
//
//    return $score;
//}



///////////////////////////////////////////////////////////////
// NEW FUNCTIONALITY //
///////////////////////////////////////////////////////////////

//BOOSTERS

// LABEL RANKINGS
function getLabelAgeAndReputationById($labelId,$activeCutOffDate) {
    $age = 0;
    $reputation = 0;
    $label = read('users','id',$labelId);
    if($label){
        $smrEntries = searchSmrByLableTitle(sanitizeString($label['title']));
        $check = sortByColumnIndex($smrEntries, 'date', SORT_ASC);
        if($check){
            $earliest = $check[0]['date'];
            // get days ago
            $now = new DateTime();
            $earliestDate = new DateTime($earliest);
            $interval = $earliestDate->diff($now);
            $labelAge = $interval->days;

            // Get our reputation
            $artistsOnLabel = readMany('users','label_id', $label['id']);
            $artistsCount = count($artistsOnLabel);
            $chartingScore = $artistsCount * $_ENV['ARTIST_CHARTING_WEIGHT']; // points for each artist charting
            $numberofArtistsChartingLastSixMonths = 0; // points for recently charting
            $artistsSpins = 0;
            $artistsSpinsLastSixMonths = 0;
            if($artistsOnLabel){
                foreach($artistsOnLabel as $artistOnLabel){
                    $smrChartData = search('smr_chart','artists',$artistOnLabel['title']);
                    if($smrChartData){
                        foreach($smrChartData as $smrChartEntry){
                            $smrChartDate = new DateTime($smrChartEntry['date']);
                            $interval = $smrChartDate->diff($now);
                            $daysAgo = $interval->days;
                            if($daysAgo <= 180){
                                $numberofArtistsChartingLastSixMonths++;
                            }
                        }
                    }
                    // radio spins
                    $rSpins = search('station_spins','artist',$artistOnLabel['title']);
                    if($rSpins){
                        foreach($rSpins as $rSpin){
                            $rSpinDate = new DateTime($rSpin['timestamp']);
                            $interval = $rSpinDate->diff($now);
                            $daysAgo = $interval->days;
                            if($daysAgo <= 180){
                                $artistsSpinsLastSixMonths += $rSpin['TWS'];
                            } else {
                                $artistsSpins += $rSpin['TWS'];
                            }
                        }
                    }

                }
            }

            $reputation = ($chartingScore + $numberofArtistsChartingLastSixMonths + $artistsSpins/10 + $artistsSpinsLastSixMonths) * ($labelAge/1000);
            $age = $labelAge*$_ENV['LABEL_AGE_WEIGHT'];
            $reputation = $reputation * $_ENV['LABEL_REPUTATION_WEIGHT'];
        }


    }

    return ['age_score' => $age, 'reputation_score' => $reputation];

}
function getLabelSmrScoreByTitle($label,$activeCutOffDate){
    // Todo:

    // How many total entries for this label?





}
function getLabelPostMentionsScores($label,$activeCutOffDate){
    $postMentionsScoreActive=0;
    $postMentionsScoreHistoric=0;
    $postMentions = readMany('post_mentions','label_id', $label['id']);
    foreach($postMentions as $postMention){
        $foundIn = explode(',',$postMention['FoundIn']);
        if(strtotime($postMention['Timestamp']) >= $activeCutOffDate){
            switch($foundIn){
                case "Body":
                    $postMentionsScoreActive += $_ENV['MENTIONS_BODY_WEIGHT'];
                    break;
                case "Title":
                    $postMentionsScoreActive += $_ENV['MENTIONS_TITLE_WEIGHT'];
                    break;
                case "Summary":
                    $postMentionsScoreActive += $_ENV['MENTIONS_SUMMARY_WEIGHT'];
                    break;
                case "Tags":
                    $postMentionsScoreActive += $_ENV['MENTIONS_TAGS_WEIGHT'];
                    break;

            }
        } else {
            switch($foundIn){
                case "Body":
                    $postMentionsScoreHistoric += $_ENV['MENTIONS_BODY_WEIGHT'];
                    break;
                case "Title":
                    $postMentionsScoreHistoric += $_ENV['MENTIONS_TITLE_WEIGHT'];
                    break;
                case "Summary":
                    $postMentionsScoreHistoric += $_ENV['MENTIONS_SUMMARY_WEIGHT'];
                    break;
                case "Tags":
                    $postMentionsScoreHistoric += $_ENV['MENTIONS_TAGS_WEIGHT'];
                    break;
            }

        }
    }

    return [
        'active'=>$postMentionsScoreActive,
        'historic'=>$postMentionsScoreHistoric
    ];
}
function getLabelViewsScores($label,$activeCutOffDate){
    $viewsScoreHistoric=0;
    $viewsScoreActive=0;
    $options = ['action'=>'label_view','entity_id'=>$label['id']];
    $hits = readByMultiple('hits',$options);
    foreach($hits as $hit){
        if(strtotime($hit['Timestamp']) >= $activeCutOffDate){
            // active
            $viewsScoreActive += $hit['ViewCount'] * $_ENV['PAGE_VIEW_WEIGHT'];
        } else {
            // old
            $viewsScoreHistoric += $hit['ViewCount'] * $_ENV['PAGE_VIEW_WEIGHT'];
        }
    }

    return [
        'active'=>$viewsScoreActive,
        'historic'=>$viewsScoreHistoric
    ];
}
function getLabelSocialScores($label,$activeCutOffDate){
    return [
        'active'=>0,
        'historic'=>0
    ];
}
function getLabelReleasesScores($label, $activeCutOffDate){
    $releasesScoreHistoric=0;
    $releasesScoreActive=0;
    $releases = readMany('releases','label_id', $label['id']);
    foreach($releases as $release){
        if(strtotime($release['release_date']) >= $activeCutOffDate){
            // new
            $releasesScoreActive += $_ENV['RELEASE_WEIGHT'];
        } else {
            // old
            $releasesScoreHistoric += $_ENV['RELEASE_WEIGHT'];

        }
    }

    return [
        'active'=>$releasesScoreActive,
        'historic'=>$releasesScoreHistoric
    ];
}
function getLabelVideosScores($label,$activeCutOffDate){
    $videosScoreHistoric=0;
    $videosScoreActive=0;

    // How many videos total?
    $labelArtists = readMany('users','label_id', $label['id']);
    $activeVideoCountScore = 0;
    $activeVideoViewsScore = 0;
    $historicVideoCountScore = 0;
    $historicVideoViewsScore = 0;
    foreach($labelArtists as $labelArtist){
        $videos = readMany('videos','artist_id', $labelArtist['id']);
        foreach($videos as $video){
            if(strtotime($video['release_date']) >= $activeCutOffDate){
                // active
                $conditions = [
                    'action'=>'video_view',
                    'entity_id'=>$labelArtist['id']
                ];
                $views = readByMultiple('hits',$conditions);
                foreach($views as $view){
                    $activeVideoViewsScore = $view['ViewCount'] * $_ENV['VIDEO_VIEW_WEIGHT'];
                }
                $activeVideoCountScore += $_ENV['VIDEO_COUNT_WEIGHT'];
            } else {
                // old
                $conditions = [
                    'action'=>'video_view',
                    'entity_id'=>$labelArtist['id']
                ];
                $views = readByMultiple('hits',$conditions);
                foreach($views as $view){
                    $historicVideoViewsScore = $view['ViewCount'] * $_ENV['VIDEO_VIEW_WEIGHT'];
                }
                $historicVideoCountScore += $_ENV['VIDEO_COUNT_WEIGHT'];

            }
        }
    }




    $videosScoreActive = $activeVideoCountScore+$activeVideoViewsScore;
    $videosScoreHistoric = $historicVideoCountScore+$historicVideoViewsScore;




    return [
        'active'=>$videosScoreActive,
        'historic'=>$videosScoreHistoric
    ];
}
function getLabelSpinsScores($label,$activeCutOffDate){
    $activeSpinScore=0;
    $historicSpinScore=0;

    // spins for each artist on the label
    $labelArtists = readMany('users','label_id', $label['id']);
    foreach($labelArtists as $labelArtist){
        $statement = $GLOBALS['pdo']->prepare("SELECT * FROM station_spins WHERE LOWER(artist) LIKE :value");
        $searchPattern = '%' . strtolower($labelArtist['title']) . '%';
        $statement->bindValue(':value', $searchPattern);
        $statement->execute();
        $spins = $statement->fetchAll(PDO::FETCH_ASSOC);
        if($spins){
            foreach($spins as $spin){
                if (strtotime($spin['timestamp']) >= $activeCutOffDate) {
                    // active
                    $activeSpinScore += $spin['tws'] * $_ENV['RADIO_SPINS_WEIGHT'];
                } else {
                    // old
                    $historicSpinScore += $spin['tws'] * $_ENV['RADIO_SPINS_WEIGHT'];
                }
            }
        }
    }

    return [
        'active'=>$activeSpinScore,
        'historic'=>$historicSpinScore
    ];
}


// ARTIST RANKINGS
function getArtistSmrScoreByTitle($title, $interval){
    $smrData = search('smr_chart','artists',$title);
    $artistSmrScore = 0;
    $artistSmrScorePerInterval = 0;
    foreach ($smrData as $data) {
        // Calculate the individual scores
        $position_score = $data['TWP'] * $_ENV['POSITION_WEIGHT'];
        $gain_score = abs($data['TWP'] - $data['LWP']) * $_ENV['GAIN_WEIGHT'];
        $peak_score = $data['Peak'] * $_ENV['PEAK_WEIGHT'];
        $longevity_score = (int)$data['WOC'] * (int)$_ENV['LONGEVITY_WEIGHT'];
        $station_add_score = $data['Adds'] * $_ENV['STATION_ADD_WEIGHT'];
        $radio_spins_score = $data['TWS'] * $_ENV['RADIO_SPINS_WEIGHT'];

        // Todo: Implement Historical Data Factors

        $smrResults[] = [
            'artist' => $data['Artists'],
            'song' => $data['Song'],
            'position_score' => $position_score,
            'gain_score' => $gain_score,
            'peak_score' => $peak_score,
            'longevity_score' => $longevity_score,
            'station_add_score' => $station_add_score,
            'smr_spins_score' => $radio_spins_score
        ];

        $newScore = $position_score + $gain_score + $peak_score + $longevity_score + $station_add_score + $radio_spins_score;



        // Check if the timestamp is within the last six months

        if (strtotime($data['Date']) >= $interval) {
            // Aggregate the new score to the last six months score if applicable
            $artistSmrScorePerInterval += ($newScore * $_ENV['SMR_SCORE_WEIGHT'])/10000;
        } else {
            $artistSmrScore += (($newScore/2) * $_ENV['SMR_SCORE_WEIGHT'])/10000;
        }
    }
    return [
        'historic'=>$artistSmrScore,
        'active'=>$artistSmrScorePerInterval
    ];
}
function getArtistPostMentionsScoreById($id,$interval){
    $mentions = readMany('post_mentions','artist_id',$id);
    $mentionsScoreHistoric = 0;
    $mentionsScoreActive = 0;
    foreach($mentions as $mention){
        $foundInAreas = explode(',', $mention['FoundIn']);
        $tempScore = 0;
        switch($foundInAreas){
            case "Body":
                $tempScore += $_ENV['MENTIONS_BODY_WEIGHT'] + $_ENV['POST_ACTIVITY_WEIGHT'];
                break;
            case "Tags":
                $tempScore += $_ENV['MENTIONS_TAGS_WEIGHT'] + $_ENV['POST_ACTIVITY_WEIGHT'];
                break;
            case "Summary":
                $tempScore += $_ENV['MENTIONS_SUMMARY_WEIGHT'] + $_ENV['POST_ACTIVITY_WEIGHT'];
                break;
            case "Title":
                $tempScore += $_ENV['MENTIONS_TITLE_WEIGHT'] + $_ENV['POST_ACTIVITY_WEIGHT'];
                break;
        }

        // Calculate the score for the mention
        // Using: RECENT_ACTIVITY_WEIGHT, POST_RELEVANCE_WEIGHT, POST_ACTIVITY_WEIGHT
        $mentionScore = $tempScore + $_ENV['POST_RELEVANCE_WEIGHT'];

        // Check if the mention is within the last six months
        if (strtotime($mention['Timestamp']) >= $interval) {
            $mentionsScoreActive += $mentionScore;
        } else {
            $mentionsScoreHistoric += $mentionScore; // Aggregate total mentions score
        }

    }
    return [
        'historic'=>$mentionsScoreHistoric,
        'active'=>$mentionsScoreActive
    ];
}
function getArtistViewScore($id,$interval){
    $options = ['action'=>'artist_view','entity_id'=>$id];
    $artistViews = readByMultiple('hits',$options);
    $artistViewScoreHistorical = 0;
    $artistViewScoreActive = 0;
    if($artistViews){
        foreach($artistViews as $artistView){
            if (strtotime($artistView['Timestamp']) >= $interval) {
                $artistViewScoreActive += $artistView['ViewCount']*$_ENV['ARTIST_VIEW_WEIGHT'];
            } else {
                $artistViewScoreHistorical += $artistView['ViewCount']*$_ENV['ARTIST_VIEW_WEIGHT'];

            }
        }
    }

    return [
        'historic'=>$artistViewScoreHistorical,
        'active'=>$artistViewScoreActive
    ];
}
function getArtistSocialScoreById($id,$interval){
    // Todo: Create logic to gather social media scores
    // Todo: Pre-req: Facebook, Instagram, Tiktok, YouTube data
    return [
        'historic'=>0,
        'active'=>0
    ];
}
function getArtistVideoScoresById($id,$interval){
    $videos = readMany('videos','artist_id',$id);

    $artistVideoViewsHistorical = 0;
    $artistVideoViewsActive = 0;
    if($videos){
        foreach($videos as $video){
            $hits = readByMultiple('hits',['action'=>'video_view','entity_id'=>$video['id']]);
            if($hits){
                foreach($hits as $hit){
                    if(strtotime($hit['Timestamp']) >= $interval){
                        $artistVideoViewsActive += $hit['ViewCount']*$_ENV['VIDEO_VIEW_WEIGHT'];
                    } else {
                        $artistVideoViewsHistorical += $hit['ViewCount']*$_ENV['VIDEO_VIEW_WEIGHT'];
                    }
                }
            } else {
                // no hits
                // just give points without views
                if (strtotime($video['release_date']) >= $interval) {
                    // old (just points)
                    $artistVideoViewsActive += $_ENV['VIDEO_COUNT_WEIGHT'];
                } else {
                    // active
                    $artistVideoViewsHistorical += $_ENV['VIDEO_COUNT_WEIGHT'];
                }
            }
        }
    }

    return [
        'historic'=>$artistVideoViewsHistorical,
        'active'=>$artistVideoViewsActive
    ];
}
function getArtistSpinScoreByTitle($title,$interval){
    $statement = $GLOBALS['pdo']->prepare("SELECT * FROM station_spins WHERE LOWER(artist) LIKE :value AND approved = 1");
    $searchPattern = '%' . strtolower($title) . '%';
    $statement->bindValue(':value', $searchPattern);
    $statement->execute();
    $spins = $statement->fetchAll(PDO::FETCH_ASSOC);
    $spinsHistorical = 0;
    $spinsActive = 0;
    if($spins){
        foreach($spins as $spin){
            if(strtotime($spin['timestamp']) >= $interval){
                $spinsActive += $spin['tws'] * $_ENV['RADIO_SPINS_WEIGHT'];
            } else {
                $spinsHistorical += $spin['tws'] * $_ENV['RADIO_SPINS_WEIGHT'];

            }
        }
    }


    return [
        'historic'=>$spinsHistorical,
        'active'=>$spinsActive
    ];
}
function getArtistReleasesScore($artist,$activeCutOffDate){
    $activeScore = 0;
    $historicScore = 0;

    $releases = readMany('releases','artist_id',$artist['id']);
    if($releases){
        foreach($releases as $release){
            $read = [
                'entity_id'=>$release['id'],
                'action'=>'release_view'
            ];
            $hits = readByMultiple('hits',$read);


            if($hits){
                foreach ($hits as $hit) {
                    if (strtotime($hit['Timestamp']) < $activeCutOffDate) {
                        // old (just points)
                        $historicScore += ($_ENV['RELEASE_WEIGHT'] * $hit['ViewCount']) / 2;
                    } else {
                        // active
                        $activeScore += $_ENV['RELEASE_WEIGHT'] * $hit['ViewCount'];
                    }
                }
            } else {
                // no hits
                // just give points for the releases themselves
                if (strtotime($release['release_date']) < $activeCutOffDate) {
                    // old (just points)
                    $historicScore += $_ENV['RELEASE_COUNT_WEIGHT'] / 2;
                } else {
                    // active
                    $activeScore += $_ENV['RELEASE_COUNT_WEIGHT'];
                }
            }

        }
    }

    return [
        'active'=>$activeScore,
        'historic'=>$historicScore
    ];
}

// Helper to remove special characters from label title
// for search SMR Data
function sanitizeString($string) {
    return preg_replace('/[^a-zA-Z0-9 ]/', '', $string);
}
function searchSmrByLableTitle($title){
    $sql = "
        SELECT *
        FROM smr_chart
        WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
               REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(label, '@', ''), '#', ''), '$', ''), '%', ''), '^', ''), '&', ''), '*', ''), '(', ''), ')', ''),
               '-', ''), '_', ''), '=', ''), '+', ''), '/', ''), '!', '') LIKE :searchString
    ";

    // Prepare the statement
    $stmt = $GLOBALS['pdo']->prepare($sql);

    // Bind the sanitized search string with wildcards for LIKE pattern
    $stmt->bindValue(':searchString', '%' . $title . '%', PDO::PARAM_STR);
    if($stmt->execute()){
        return $stmt->fetchAll();
    } else {
        return false;
    }
}


function createArtistData($value){
    return [
        'ArtistId'=>$value['ArtistId'],
        'Score'=>$value['Score'],
        'SMR_Score_Active'=>$value['SMR_Score_Active'],
        'SMR_Score_Historic'=>$value['SMR_Score_Historic'],
        'Post_Mentions_Score_Active'=>$value['Post_Mentions_Score_Active'],
        'Post_Mentions_Score_Historic'=>$value['Post_Mentions_Score_Historic'],
        'Views_Score_Active'=>$value['Views_Score_Active'],
        'Views_Score_Historic'=>$value['Views_Score_Historic'],
        'Social_Score_Active'=>$value['Social_Score_Active'],
        'Social_Score_Historic'=>$value['Social_Score_Historic'],
        'Videos_Score_Active'=>$value['Videos_Score_Active'],
        'Videos_Score_Historic'=>$value['Videos_Score_Historic'],
        'Spins_Score_Active'=>$value['Spins_Score_Active'],
        'Spins_Score_Historic'=>$value['Spins_Score_Historic'],
        'Label_Boost_Score'=>$value['Label_Boost_Score'],
        'Timestamp'=>$value['Timestamp']
    ];
}
function createLabelData($value){
    return [
        'LabelId'=>$value['LabelId'],
        'Score'=>$value['Score'],
        'Post_Mentions_Score_Active'=>$value['Post_Mentions_Score_Active'],
        'Post_Mentions_Score_Historic'=>$value['Post_Mentions_Score_Historic'],
        'Views_Score_Active'=>$value['Views_Score_Active'],
        'Views_Score_Historic'=>$value['Views_Score_Historic'],
        'Social_Score_Active'=>$value['Social_Score_Active'],
        'Social_Score_Historic'=>$value['Social_Score_Historic'],
        'Releases_Score_Active'=>$value['Releases_Score_Active'],
        'Releases_Score_Historic'=>$value['Releases_Score_Historic'],
        'Videos_Score_Active'=>$value['Videos_Score_Active'],
        'Videos_Score_Historic'=>$value['Videos_Score_Historic'],
        'Spins_Score_Active'=>$value['Spins_Score_Active'],
        'Spins_Score_Historic'=>$value['Spins_Score_Historic'],
        'AgeScore'=>$value['AgeScore'],
        'ReputationScore'=>$value['ReputationScore'],
        'Artist_Boost_Score'=>$value['Artist_Boost_Score'],
        'Timestamp'=>$value['Timestamp']

    ];
}


function handleRadioSpinsArtists($artists){
    $artists = strtolower($artists);
    $knownTitles = [
        'hearts & hand grenades',
        'judge & jury',
        'attack attack!'
    ];
    $artistsArray = [];
    $delimiters = ['and',' and ','ft','\sfeaturing', ' ft ', ' & ', ',', '/', '&', '\sfeaturing\s', '\sFEATURING\s', '\sFEAT\.\s', '\sfeat\.\s', '\sfeat\s', '\sFEAT\s'];
    if (in_array($artists, $knownTitles)) {
        $artistsArray[] = $artists; // Add the input string if it's a known title
    } else {
        if (preg_match('/\s&\s|,|\/|&|\sfeaturing\s|\sFEATURING\s|\sFEAT\.\s|\sfeat\.\s|\sfeat\s|\sFEAT\s/', $artists)) {
            $artistsArray = preg_split('/\s&\s|,|\/|&|\sfeaturing\s|\sFEATURING\s|\sFEAT\.\s|\sfeat\.\s|\sfeat\s|\sFEAT\s/', $artists);
            $artistsArray = array_map('trim', $artistsArray); // Trim whitespace from each name
        } else {
            $artistsArray[] = $artists; // Add as is if no delimiters are found
        }
    }
    return $artistsArray;
}
