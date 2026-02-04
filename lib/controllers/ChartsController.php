<?php

// Todo: Expansion: When users finally log in they should be able to favorite artists and those #s help charts

function getLabelIdFromArtistId($id){
	$a = read('users','Id',$id);
	$l = read('users','Id',$a['LabelId']);
	return ($l) ? $l['Id'] : false;
}

function getLabelArtists($id){
	return advancedRead('users', ['RoleId' => 3, 'LabelId' => $id], ['Id', 'Title']);

}






// 1. Gather the Evidence
//    * Fetch SMR Chart Data and NGN Rankings
//    * Fetch Aggregated View Data
//    * Get Relevant Posts

// 2. Craft the Narrative
//    * Separate sections for Artist and Label
//    * SMR Chart Performance breakdown
//    * NGN Chart Performance breakdown (including historical changes)
//    * Post Mentions breakdown by location and points
//    * View Counts breakdown and points
//    * Recent Mentions score
//    * Label Activity Boost (tell yes or no)
//    * Final Score Breakdown with all components

// 3. Error Handling and Polish
//    * Handle missing data and errors gracefully
//    * Format the output for clarity and readability
//    * Ensure accessibility for all users

function generateProveItSheet($entityId, $entityType, $smrChartData, $viewData, $artistScores, $labelScores, $allRelevantPosts)
{
	// Fetch entity details (with error handling)
	$entity = read('users', 'Id', $entityId);
	if (!$entity) {
		return 'Error: Entity not found.';
	}

	// Prepare basic HTML content
	$html = <<<HTML
        <h1>NGN Prove-It Sheet</h1>
        <h2>{$entityType}: {$entity['Title']}</h2>
    HTML;

	if ($entityType == 'artist') {
		$html .= showArtistProveIt($entityId, $smrChartData, $viewData, $artistScores, $labelScores, $allRelevantPosts);
	} else if ($entityType == 'label') {
		$html .= showLabelProveIt($entityId, $smrChartData, $viewData, $artistScores, $labelScores, $allRelevantPosts);
	}

	return $html;
}

function showArtistProveIt($entityId, $smrChartData, $viewData, $artistScores, $labelScores, $allRelevantPosts)
{
	$html = ''; // Initialize HTML content

	// Fetch label details (with error handling)
	$labelId = getLabelIdFromArtistId($entityId);
	$label = read('users', 'Id', $labelId);
	if (!$label) {
		$html .= '<h3>Label: Not Found</h3>';
	} else {
		$html .= "<h3>Label: {$label['Title']}</h3>";
	}

	// Retrieve artist's chart entries (both SMR and NGN)
	$smrChartEntries = array_filter($smrChartData, function ($row) use ($entityId) {
		return getArtistIdFromName($row['Artists']) == $entityId;
	});
	$ngnRanking = getRankingsForEntity($entityId, 'artist');

	// Display chart performance (both SMR and NGN)
	$html .= '<h4>Chart Performance</h4>';

	// SMR Chart Performance
	if (!empty($smrChartEntries)) {
		$html .= '<h5>SMR Chart</h5><ul>';
		$totalSmrScore = 0;
		foreach ($smrChartEntries as $entry) {
			$positionPoints = $entry['TW'] * POSITION_WEIGHT;
			$gainPoints = ($entry['LWTW'] - $entry['TW']) * GAIN_WEIGHT;
			$totalSmrScore += $positionPoints + $gainPoints;
			$html .= "<li>Song: {$entry['Song']}</li>
                        <ul>
                            <li>Current Position (TW): {$entry['TW']} ({$positionPoints} points)</li>
                            <li>Chart Gain/Loss: " . ($entry['LWTW'] - $entry['TW']) . " ({$gainPoints} points)</li>
                        </ul>";
		}
		$html .= '</ul>';
		$html .= "<p><strong>Total SMR Score: {$totalSmrScore}</strong></p>";
	} else {
		$html .= '<p>No SMR chart entries found.</p>';
	}

	// NGN Chart Performance
	if ($ngnRanking) {
		$html .= '<h5>NGN Chart</h5><ul>';
		$html .= "<li>Current Rank: {$ngnRanking['Rank']} ({$ngnRanking['Score']} points)</li>";
		// TODO: Add logic to display NGN chart changes (daily, weekly, etc.) once implemented
		$html .= '</ul>';
	} else {
		$html .= '<p>Not currently ranked on the NGN Chart.</p>';
	}

	// Display post mentions and their contribution
	$relevantArtistPosts = array_filter($allRelevantPosts, function ($post) {
		return isset($post['artist_name']);
	});

	// Initialize counters for each mention type
	$titleMentions = 0;
	$summaryMentions = 0;
	$bodyMentions = 0;
	$tagMentions = 0;

	$html .= '<h4>Post Mentions</h4>';
	if (!empty($relevantArtistPosts)) {
		$html .= '<ul>';
		foreach ($relevantArtistPosts as $post) {
			$mentionLocation = $post['matched_in'];
			$points = match ($mentionLocation) {
				'Body' => POST_RELEVANCE_WEIGHT,
				'Tags' => POST_RELEVANCE_WEIGHT / 2,
				'Summary' => POST_RELEVANCE_WEIGHT * 1.5,
				'Title' => POST_RELEVANCE_WEIGHT * 2,
			};

			// Increment the corresponding mention counter
			switch ($mentionLocation) {
				case 'Title':
					$titleMentions++;
					break;
				case 'Summary':
					$summaryMentions++;
					break;
				case 'Body':
					$bodyMentions++;
					break;
				case 'Tags':
					$tagMentions++;
					break;
			}

			$html .= "<li>Mention in {$mentionLocation} of '{$post['post']['Title']}' ({$points} points)</li>";
		}
		$html .= '</ul>';

		// Display total points for each mention type, even if 0
		$html .= "
            <ul>
                <li>Total Title Mentions: {$titleMentions} (" . ($titleMentions * POST_RELEVANCE_WEIGHT * 2) . " points)</li>
                <li>Total Summary Mentions: {$summaryMentions} (" . ($summaryMentions * POST_RELEVANCE_WEIGHT * 1.5) . " points)</li>
                <li>Total Body Mentions: {$bodyMentions} (" . ($bodyMentions * POST_RELEVANCE_WEIGHT) . " points)</li>
                <li>Total Tag Mentions: {$tagMentions} (" . ($tagMentions * POST_RELEVANCE_WEIGHT / 2) . ' points)</li>
            </ul>
        ';

	} else {
		$html .= '<p>No post mentions found.</p>';
	}

	// Add recent mentions and their contribution
	$recentMentions = getRecentMentionsCount($entityId);
	$decayFactor = 0.9;
	$relevanceScore = 0;
	for ($i = 0; $i < $recentMentions; $i++) {
		$relevanceScore += pow($decayFactor, $i);
	}
	$mentionPoints = $relevanceScore * MENTION_RELEVANCE_WEIGHT;

	$html .= "
        <h4>Recent Mentions</h4>
        <ul>
            <li>Recent Mentions: {$recentMentions} ({$mentionPoints} points)</li>
        </ul>
    ";

	// Add view counts
	$artistViews = getViewCount($viewData, 'artist_view', $entityId);
	$relevantArticleViews = getRelevantArticleViews($viewData, $entityId);
	$pageViews = getViewCount($viewData, 'page_view', $entityId);

	$html .= '
        <h4>View Counts</h4>
        <ul>
            <li>Artist Views: ' . $artistViews . ' (' . ($artistViews * ARTIST_VIEW_WEIGHT) . ' points)</li>
            <li>Relevant Article Views: ' . $relevantArticleViews . ' (' . ($relevantArticleViews * ARTICLE_VIEW_WEIGHT) . ' points)</li>
            <li>Page Views: ' . $pageViews . ' (' . ($pageViews * PAGE_VIEW_WEIGHT) . ' points)</li>
        </ul>
    ';

	// Add recent activity boost for the label
	if (hasRecentActivity($labelId)) {
		$html .= '<p>Label has recent activity (10% boost applied)</p>';
	} else {
		$html .= '<p>No recent activity for the label</p>';
	}

	// Final scores breakdown
	$html .= "
        <h4>Final Score Breakdown</h4>
        <ul>
            <li>SMR Chart Score: {$totalSmrScore}</li> 
            <li>Post Mentions Score: " . ($titleMentions * POST_RELEVANCE_WEIGHT * 2 + $summaryMentions * POST_RELEVANCE_WEIGHT * 1.5 + $bodyMentions * POST_RELEVANCE_WEIGHT + $tagMentions * POST_RELEVANCE_WEIGHT / 2) . "</li>
            <li>Recent Mentions Score: {$mentionPoints}</li>
            <li>View Counts Score: " . ($artistViews * ARTIST_VIEW_WEIGHT + $relevantArticleViews * ARTICLE_VIEW_WEIGHT + $pageViews * PAGE_VIEW_WEIGHT) . '</li>';

	if (hasRecentActivity($labelId)) {
		$html .= '<li>Label Boost: ' . ($artistScores[$entityId] * 0.1) . '</li>';
	}

	$html .= '</ul>';

	return $html;
}

function showLabelProveIt($entityId, $smrChartData, $viewData, $artistScores, $labelScores, $allRelevantPosts)
{
	$html = ''; // Initialize HTML content

	// Get artists associated with the label (with error handling)
	$labelArtists = getArtistsForLabel($entityId);
	$artistCount = count($labelArtists);

	if (empty($labelArtists)) {
		$html .= '<p>No artists found for this label.</p>';
	} else {
		$html .= '
            <h4>Artists and Scores</h4>
            <ul>
        ';

		foreach ($labelArtists as $artistId) {
			$artist = read('users', 'Id', $artistId);
			if (!$artist) {
				$html .= "<li>Artist (ID: $artistId) Not Found</li>"; // Handle missing artist
				continue;
			}
			$artistScore = $artistScores[$artistId] ?? 0;
			$html .= "<li>{$artist['Title']}: {$artistScore}</li>";
		}

		$html .= '</ul>';

		// Calculate and display label score breakdown
		$totalArtistScore = array_sum(array_intersect_key($artistScores, array_flip($labelArtists)));
		$normalizedScore = $artistCount > 0 ? $totalArtistScore / $artistCount : 0;

		$html .= '
            <h4>Label Score Calculation</h4>
            <ul>
                <li>Total Artist Score: ' . $totalArtistScore . '</li>
                <li>Number of Artists: ' . $artistCount . '</li>
                <li>Normalized Score: ' . $normalizedScore . '</li>
        ';

		// Add article and page views for the label
		$labelArticleViews = getViewCount($viewData, 'article_view', $entityId);
		$labelPageViews = getViewCount($viewData, 'page_view', $entityId);

		// Get the count of relevant articles for the label
		$relevantArticleCount = getRelevantArticleCountForLabel($viewData, $entityId);

		$html .= "
            <li>Label Article Views: {$labelArticleViews} (" . ($labelArticleViews * ARTICLE_VIEW_WEIGHT) . " points)</li>
            <li>Label Page Views: {$labelPageViews} (" . ($labelPageViews * PAGE_VIEW_WEIGHT) . " points)</li>
            <li>Relevant Articles: {$relevantArticleCount}</li> 
        ";

		if (hasRecentActivity($entityId)) {
			$html .= '<li>Recent Activity Boost: 10% applied</li>';
		}

		$html .= '
            </ul>

            <h4>Final Score</h4>
            <p>Label Score: ' . ($labelScores[$entityId] ?? 0) . '</p> 
        ';

		// NGN Chart Performance for the label
		$ngnRanking = getRankingsForEntity($entityId, 'label');
		if ($ngnRanking) {
			$html .= '<h5>NGN Chart</h5><ul>';
			$html .= "<li>Current Rank: {$ngnRanking['Rank']} ({$ngnRanking['Score']} points)</li>";
			// TODO: Add logic to display NGN chart changes (daily, weekly, etc.) once implemented
			$html .= '</ul>';
		} else {
			$html .= '<p>Not currently ranked on the NGN Chart.</p>';
		}

		// Display post mentions and their contribution, including breakdown by type
		$relevantLabelPosts = array_filter($allRelevantPosts, function ($post) {
			return isset($post['label_name']);
		});

		// Initialize counters for each mention type
		$titleMentions = 0;
		$summaryMentions = 0;
		$bodyMentions = 0;
		$tagMentions = 0;

		$html .= '<h4>Post Mentions</h4>';
		if (!empty($relevantLabelPosts)) {
			$html .= '<ul>';
			foreach ($relevantLabelPosts as $post) {
				$mentionLocation = $post['matched_in'];
				$points = match ($mentionLocation) {
					'Body' => POST_RELEVANCE_WEIGHT,
					'Tags' => POST_RELEVANCE_WEIGHT / 2,
					'Summary' => POST_RELEVANCE_WEIGHT * 1.5,
					'Title' => POST_RELEVANCE_WEIGHT * 2,
				};

				// Increment the corresponding mention counter
				switch ($mentionLocation) {
					case 'Title':
						$titleMentions++;
						break;
					case 'Summary':
						$summaryMentions++;
						break;
					case 'Body':
						$bodyMentions++;
						break;
					case 'Tags':
						$tagMentions++;
						break;
				}

				$html .= "<li>Mention in {$mentionLocation} of '{$post['post']['Title']}' ({$points} points)</li>";
			}
			$html .= '</ul>';

			// Display total points for each mention type, even if 0
			$html .= "
                <ul>
                    <li>Total Title Mentions: {$titleMentions} (" . ($titleMentions * POST_RELEVANCE_WEIGHT * 2) . " points)</li>
                    <li>Total Summary Mentions: {$summaryMentions} (" . ($summaryMentions * POST_RELEVANCE_WEIGHT * 1.5) . " points)</li>
                    <li>Total Body Mentions: {$bodyMentions} (" . ($bodyMentions * POST_RELEVANCE_WEIGHT) . " points)</li>
                    <li>Total Tag Mentions: {$tagMentions} (" . ($tagMentions * POST_RELEVANCE_WEIGHT / 2) . ' points)</li>
                </ul>
            ';

		} else {
			$html .= '<p>No post mentions found.</p>';
		}
	}

	return $html;
}