<?php

function storeRankings($rankings) {
	// 1. Archive existing rankings before clearing
	archiveRankings('daily');
	archiveRankings('weekly');
	archiveRankings('monthly');
	archiveRankings('yearly');

	// 2. Clear existing rankings from main tables
	clearTable('NGNArtistRankings');
	clearTable('NGNLabelRankings');

	// 3. Insert new artist rankings
	$artistRank = 1;

	foreach ($rankings['topArtists'] as $artistId => $scoreData) {
		// Check if the score components are present, if not, set default values or handle accordingly
		$totalScore = $scoreData['totalScore'] ?? 0;
		$smrScore = $scoreData['smrScore'] ?? 0;
		$postMentionsScore = $scoreData['postMentionsScore'] ?? 0;
		$viewsScore = $scoreData['viewsScore'] ?? 0;
		// Add checks and default values for other score components as needed

		add('NGNArtistRankings', [
			'ArtistId' => $artistId,
			'Score' => $totalScore,
			'SMR_Score' => $smrScore,
			'Post_Mentions_Score' => $postMentionsScore,
			'Views_Score' => $viewsScore,
			// Add other score components here
			'Rank' => $artistRank
		]);
		$artistRank++;
	}

	// 4. Insert new label rankings
	$labelRank = 1;
	foreach ($rankings['topLabels'] as $labelId => $scoreData) {
		// Check if the score components are present, if not, set default values or handle accordingly
		$totalScore = $scoreData['totalScore'] ?? 0;
		// Add checks and default values for other label score components as needed

		add('NGNLabelRankings', [
			'LabelId' => $labelId,
			'Score' => $totalScore,
			// Add other label score components here
			'Rank' => $labelRank
		]);
		$labelRank++;
	}

	// echo 'NGN Charts rankings successfully stored and archived!'; // Optional success message
}
function archiveRankings($interval) {
	global $pdo;

	// 1. Determine source and destination tables based on the interval
	$sourceArtistTable = 'NGNArtistRankings';
	$sourceLabelTable = 'NGNLabelRankings';

	$destinationArtistTable = match ($interval) {
		'daily' => 'NGNArtistRankingsHistoryDaily',
		'weekly' => 'NGNArtistRankingsHistoryWeekly',
		'monthly' => 'NGNArtistRankingsHistoryMonthly',
		'yearly' => 'NGNArtistRankingsHistoryYearly',
		default => null, // Handle invalid intervals
	};

	$destinationLabelTable = match ($interval) {
		'daily' => 'NGNLabelRankingsHistoryDaily',
		'weekly' => 'NGNLabelRankingsHistoryWeekly',
		'monthly' => 'NGNLabelRankingsHistoryMonthly',
		'yearly' => 'NGNLabelRankingsHistoryYearly',
		default => null,
	};

	if (!$destinationArtistTable || !$destinationLabelTable) {
		die("Error: Invalid interval '$interval' for archiving rankings.");
	}

	try {
		// 2. Insert current rankings into history tables using add()
		$currentArtistRankings = browse($sourceArtistTable);

		// Check if there's data to archive
		if (!empty($currentArtistRankings)) {
			foreach ($currentArtistRankings as $ranking) {
				// Dump the ranking data before archiving
//				var_dump("Archiving artist ranking for interval $interval:");
//				var_dump($ranking);

				// Include all relevant score components in the archived data
				$dataToInsert = [
					'ArtistId' => $ranking['ArtistId'],
					'Score' => $ranking['Score'], // Keep the total score
					'SMR_Score' => $ranking['SMR_Score'],
					'Post_Mentions_Score' => $ranking['Post_Mentions_Score'],
					'Views_Score' => $ranking['Views_Score'],
					// Add other score components as needed
					'Rank' => $ranking['Rank'],
					'Timestamp' => date('Y-m-d H:i:s')
				];
				add($destinationArtistTable, $dataToInsert);
			}
		} else {
			error_log("No artist rankings to archive for interval: $interval");
		}

		$currentLabelRankings = browse($sourceLabelTable);

		// Check if there's data to archive
		if (!empty($currentLabelRankings)) {
			foreach ($currentLabelRankings as $ranking) {
				// Dump the ranking data before archiving
//				var_dump("Archiving label ranking for interval $interval:");
//				var_dump($ranking);

				// Reformat the data, include all necessary score components
				$dataToInsert = [
					'LabelId' => $ranking['LabelId'],
					'Score' => $ranking['Score'], // Assuming you have a total score for labels as well
					// Add other label score components as needed
					'Rank' => $ranking['Rank'],
					'Timestamp' => date('Y-m-d H:i:s')
				];
				add($destinationLabelTable, $dataToInsert);
			}
		} else {
			error_log("No label rankings to archive for interval: $interval");
		}

		// Log successful archiving
		error_log("Rankings successfully archived for interval: $interval");

	} catch (PDOException $e) {
		// Handle database errors specifically
		error_log("Database error archiving rankings for interval $interval: " . $e->getMessage());
		http_response_code(500);
		die('Error: Failed to archive rankings due to a database issue.');
	} catch (Exception $e) {
		// Handle other unexpected errors
		error_log("Unexpected error archiving rankings for interval $interval: " . $e->getMessage());
		http_response_code(500);
		die('Error: Failed to archive rankings.');
	}
}

function clearTable($tableName) {
	global $pdo; // Assuming you have a global PDO connection

	try {
		// Execute the TRUNCATE TABLE query
		$pdo->exec("TRUNCATE TABLE $tableName");

	} catch (PDOException $e) {
		// Handle potential errors during table clearing
		error_log("Error clearing table $tableName: " . $e->getMessage());
		http_response_code(500);
		die('Error: Failed to clear table data.');
	}
}

function getLatestRankingsTimestamp($cacheType) {
	// Determine the table to query based on cacheType
	$tableName = match ($cacheType) {
		'artist' => 'NGNArtistRankings',
		'label' => 'NGNLabelRankings',
		default => null,
	};

	if (!$tableName) {
		throw new InvalidArgumentException("Invalid cache type: $cacheType");
	}

	// Fetch the latest timestamp using browse and sorting
	$rankings = browse($tableName, [], ['Timestamp' => 'DESC']); // Get all rankings, sorted by Timestamp descending
	return $rankings[0]['Timestamp'] ?? null; // Return the timestamp of the first (latest) ranking or null if no rankings exist
}



function getRankingsForEntity($entityId, $entityType)
{
	// Determine the table to query based on entityType
	$tableName = ($entityType == 'artist') ? 'NGNArtistRankings' : 'NGNLabelRankings';

	// Fetch the ranking for the specific entity
	return read($tableName, ($entityType == 'artist' ? 'ArtistId' : 'LabelId'), $entityId);
}

function getHistoricalRankingsForEntity($entityId, $entityType, $interval)
{
	// Determine the table to fetch from based on interval and entityType
	$tableName = match ($interval) {
		'daily' => $entityType == 'artist' ? 'NGNArtistRankingsHistoryDaily' : 'NGNLabelRankingsHistoryDaily',
		'weekly' => $entityType == 'artist' ? 'NGNArtistRankingsHistoryWeekly' : 'NGNLabelRankingsHistoryWeekly',
		'monthly' => $entityType == 'artist' ? 'NGNArtistRankingsHistoryMonthly' : 'NGNLabelRankingsHistoryMonthly',
		'yearly' => $entityType == 'artist' ? 'NGNArtistRankingsHistoryYearly' : 'NGNLabelRankingsHistoryYearly',
		default => null,
	};

	if (!$tableName) {
		throw new InvalidArgumentException('Invalid interval or entity type');
	}

	// Fetch historical rankings for the specific entity
	return readMany($tableName, ($entityType == 'artist' ? 'ArtistId' : 'LabelId'), $entityId);
}

function deleteOldRankings($interval, $cutoffDate)
{
	global $pdo;

	// Determine the table to delete from based on interval
	$tableName = match ($interval) {
		'daily' => 'NGNArtistRankingsHistoryDaily',
		'weekly' => 'NGNArtistRankingsHistoryWeekly',
		'monthly' => 'NGNArtistRankingsHistoryMonthly',
		'yearly' => 'NGNArtistRankingsHistoryYearly',
		default => null,
	};

	if (!$tableName) {
		throw new InvalidArgumentException('Invalid interval for deleting old rankings');
	}

	try {
		// Delete rankings older than the cutoff date
		$sql = "DELETE FROM $tableName WHERE Timestamp < :cutoffDate";
		$stmt = $pdo->prepare($sql);
		$stmt->bindParam(':cutoffDate', $cutoffDate);
		$stmt->execute();

//		echo "Old rankings for interval '$interval' deleted successfully!";

	} catch (PDOException $e) {
		// Handle potential errors during deletion
		error_log("Error deleting old rankings from $tableName: " . $e->getMessage());
		// Consider additional error handling or reporting as needed
	}
}

function updateRankingCacheStatus($status)
{
	// Update the 'RankingsCachedAt' column in NGNChartCache based on the status
	edit('NGNChartCache', 1, ['RankingsCachedAt' => $status ? date('Y-m-d H:i:s') : null]);
}

function storeChanges($changes, $entityType, $interval)
{

	// Determine the destination table based on entity type and interval
	$tableName = match ($entityType) {
		'artist' => match ($interval) {
			'daily' => 'NGNArtistRankingsHistoryDaily',
			'weekly' => 'NGNArtistRankingsHistoryWeekly',
			'monthly' => 'NGNArtistRankingsHistoryMonthly',
			'yearly' => 'NGNArtistRankingsHistoryYearly',
			default => null,
		},
		'label' => match ($interval) {
			'daily' => 'NGNLabelRankingsHistoryDaily',
			'weekly' => 'NGNLabelRankingsHistoryWeekly',
			'monthly' => 'NGNLabelRankingsHistoryMonthly',
			'yearly' => 'NGNLabelRankingsHistoryYearly',
			default => null,
		},
		default => null,
	};

	if (!$tableName) {
		throw new InvalidArgumentException('Invalid entity type or interval for storing changes');
	}

	try {
		foreach ($changes as $entityId => $changeData) {

			// Determine the entity ID column name based on the entity type
			$entityIdColumn = ($entityType == 'artist') ? 'ArtistId' : 'LabelId';

			// Prepare the data to be inserted
			$dataToInsert = [
				$entityIdColumn => $entityId,
				'PreviousScore' => $changeData['previous_score'],
				'PreviousRank' => $changeData['previous_rank'],
				'PositionChange' => $changeData['position_change'],
				'ScoreChange' => $changeData['score_change'],
				'Trend' => $changeData['trend']
			];

			// Use the 'add' function to insert the data
			add($tableName, $dataToInsert);
		}

		echo "Changes for $entityType rankings successfully stored for interval: $interval";

		return true; // Indicate success

	} catch (PDOException $e) {
//		 Handle specific database errors
		error_log("Database error storing changes for $entityType rankings for interval $interval: " . $e->getMessage());
//		 Optionally, provide more informative feedback or throw a custom exception
		return false; // Indicate failure
	}
}



function getHistoricalRankings($interval, $entityType) {
	// Determine the table to fetch from based on interval and entityType
	$tableName = match ($interval) {
		'daily' => $entityType == 'artist' ? 'NGNArtistRankingsHistoryDaily' : 'NGNLabelRankingsHistoryDaily',
		'weekly' => $entityType == 'artist' ? 'NGNArtistRankingsHistoryWeekly' : 'NGNLabelRankingsHistoryWeekly',
		'monthly' => $entityType == 'artist' ? 'NGNArtistRankingsHistoryMonthly' : 'NGNLabelRankingsHistoryMonthly',
		'yearly' => $entityType == 'artist' ? 'NGNArtistRankingsHistoryYearly' : 'NGNLabelRankingsHistoryYearly',
		default => null,
	};

	if (!$tableName) {
		throw new InvalidArgumentException('Invalid interval or entity type');
	}

	// Specify the columns we want to fetch, including score components
	$columnsToFetch = ($entityType == 'artist')
		? ['ArtistId', 'Score', 'SMR_Score', 'Post_Mentions_Score', 'Views_Score', 'Rank']
		: ['LabelId', 'Score', 'Rank']; // Adjust for label score components if needed

	// Fetch the latest historical rankings with specified columns
	$rankings = browse($tableName, [], ['Timestamp' => 'DESC'], $columnsToFetch);

	// Check if any rankings were found
	if (empty($rankings)) {
		// Log a more detailed error message
		error_log("Error: No historical rankings found for $entityType in interval $interval. Table: $tableName");
		return []; // Return an empty array to indicate no data found
	}

	// Basic data validation - check if expected columns are present
	$firstRow = reset($rankings);
	$expectedColumns = $entityType == 'artist' ? ['ArtistId', 'Score', 'Rank'] : ['LabelId', 'Score', 'Rank'];
	foreach ($expectedColumns as $column) {
		if (!isset($firstRow[$column])) {
			// Log an error if a column is missing
			error_log("Error: Column '$column' missing in historical rankings for $entityType in interval $interval. Table: $tableName");
			return []; // Return an empty array to indicate invalid data
		}
	}

	return $rankings;
}