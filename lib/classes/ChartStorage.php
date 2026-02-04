<?php
//
//class ChartStorage {
//
//	private $dataManager;
//
//	public function __construct(DataManager $dataManager) {
//		$this->dataManager = $dataManager;
//	}
//
//	public function storeRankings($rankings) {
//		// 1. Archive Existing Rankings
//		$this->archiveRankings('daily');
//		$this->archiveRankings('weekly');
//		$this->archiveRankings('monthly');
//		$this->archiveRankings('yearly');
//
//		// 2. Clear Existing Rankings
//		$this->clearTable('NGNArtistRankings');
//		$this->clearTable('NGNLabelRankings');
//
//		// 3. Insert New Artist Rankings
//		$this->insertArtistRankings($rankings['topArtists']);
//
//		// 4. Insert New Label Rankings
//		$this->insertLabelRankings($rankings['topLabels']);
//	}
//
//	private function archiveRankings($interval) {
//		// 1. Determine source and destination tables based on the interval
//		$sourceArtistTable = 'NGNArtistRankings';
//		$sourceLabelTable = 'NGNLabelRankings';
//
//		$destinationArtistTable = match ($interval) {
//			'daily' => 'NGNArtistRankingsHistoryDaily',
//			'weekly' => 'NGNArtistRankingsHistoryWeekly',
//			'monthly' => 'NGNArtistRankingsHistoryMonthly',
//			'yearly' => 'NGNArtistRankingsHistoryYearly',
//			default => null, // Handle invalid intervals
//		};
//
//		$destinationLabelTable = match ($interval) {
//			'daily' => 'NGNLabelRankingsHistoryDaily',
//			'weekly' => 'NGNLabelRankingsHistoryWeekly',
//			'monthly' => 'NGNLabelRankingsHistoryMonthly',
//			'yearly' => 'NGNLabelRankingsHistoryYearly',
//			default => null,
//		};
//
//		if (!$destinationArtistTable || !$destinationLabelTable) {
//			die("Error: Invalid interval '$interval' for archiving rankings.");
//		}
//
//		try {
//			// 2. Insert current rankings into history tables using add()
//			$currentArtistRankings = $this->dataManager->browse($sourceArtistTable);
//			if (!empty($currentArtistRankings)) {
//				foreach ($currentArtistRankings as $ranking) {
//					$dataToInsert = [
//						'ArtistId' => $ranking['ArtistId'],
//						'Score' => $ranking['Score'],
//						'SMR_Score' => $ranking['SMR_Score'],
//						'Post_Mentions_Score' => $ranking['Post_Mentions_Score'],
//						'Views_Score' => $ranking['Views_Score'],
//						'Rank' => $ranking['Rank'],
//						'Timestamp' => date('Y-m-d H:i:s')
//					];
//					$this->dataManager->add($destinationArtistTable, $dataToInsert);
//				}
//			} else {
//				error_log("No artist rankings to archive for interval: $interval");
//			}
//
//			$currentLabelRankings = $this->dataManager->browse($sourceLabelTable);
//			if (!empty($currentLabelRankings)) {
//				foreach ($currentLabelRankings as $ranking) {
//					$dataToInsert = [
//						'LabelId' => $ranking['LabelId'],
//						'Score' => $ranking['Score'],
//						'Rank' => $ranking['Rank'],
//						'Timestamp' => date('Y-m-d H:i:s')
//					];
//					$this->dataManager->add($destinationLabelTable, $dataToInsert);
//				}
//			} else {
//				error_log("No label rankings to archive for interval: $interval");
//			}
//
//			error_log("Rankings successfully archived for interval: $interval");
//
//		} catch (PDOException $e) {
//			error_log("Database error archiving rankings for interval $interval: " . $e->getMessage());
//			http_response_code(500);
//			die('Error: Failed to archive rankings due to a database issue.');
//		} catch (Exception $e) {
//			error_log("Unexpected error archiving rankings for interval $interval: " . $e->getMessage());
//			http_response_code(500);
//			die('Error: Failed to archive rankings.');
//		}
//	}
//
//	private function clearTable($tableName) {
//		try {
//			$this->dataManager->customQuery("TRUNCATE TABLE $tableName");
//		} catch (PDOException $e) {
//			error_log("Error clearing table $tableName: " . $e->getMessage());
//			http_response_code(500);
//			die('Error: Failed to clear table data.');
//		}
//	}
//
//	private function insertArtistRankings($topArtists) {
//		$artistRank = 1;
//		foreach ($topArtists as $artistId => $scoreData) {
//			$totalScore = $scoreData['totalScore'] ?? 0;
//			$smrScore = $scoreData['smrScore'] ?? 0;
//			$postMentionsScore = $scoreData['postMentionsScore'] ?? 0;
//			$viewsScore = $scoreData['viewsScore'] ?? 0;
//
//			$this->dataManager->add('NGNArtistRankings', [
//				'ArtistId' => $artistId,
//				'Score' => $totalScore,
//				'SMR_Score' => $smrScore,
//				'Post_Mentions_Score' => $postMentionsScore,
//				'Views_Score' => $viewsScore,
//				'Rank' => $artistRank
//			]);
//			$artistRank++;
//		}
//	}
//
//	private function insertLabelRankings($topLabels) {
//		$labelRank = 1;
//		foreach ($topLabels as $labelId => $scoreData) {
//			$totalScore = $scoreData['totalScore'] ?? 0;
//
//			$this->dataManager->add('NGNLabelRankings', [
//				'LabelId' => $labelId,
//				'Score' => $totalScore,
//				'Rank' => $labelRank
//			]);
//			$labelRank++;
//		}
//	}
//
//	public function isCacheValid($cacheType)
//	{
//		try {
//			// Fetch the latest timestamp from the appropriate cache table
//			$latestTimestamp = $this->getLatestRankingsTimestamp($cacheType);
//
//			// If no timestamp is found, the cache is invalid
//			if (!$latestTimestamp) {
//				return false;
//			}
//
//			// Define the cache expiration time (e.g., 1 hour)
//			$cacheExpiration = 3600; // Adjust as needed
//
//			// Check if the cache has expired
//			$currentTime = time();
//			$cacheAge = $currentTime - strtotime($latestTimestamp);
//			return $cacheAge <= $cacheExpiration;
//
//		} catch (InvalidArgumentException $e) {
//			// Handle invalid cache type
//			error_log('Error in isCacheValid: ' . $e->getMessage());
//			return false;
//		}
//	}
//
//	private function getLatestRankingsTimestamp($cacheType)
//	{
//		// Determine the table to query based on cacheType
//		$tableName = match ($cacheType) {
//			'artist' => 'NGNArtistRankings',
//			'label' => 'NGNLabelRankings',
//			default => null,
//		};
//
//		if (!$tableName) {
//			throw new InvalidArgumentException("Invalid cache type: $cacheType");
//		}
//
//		// Fetch the latest timestamp using browse and sorting
//		$rankings = $this->dataManager->browse($tableName, [], ['Timestamp' => 'DESC']);
//		return $rankings[0]['Timestamp'] ?? null;
//	}
//
//	public function getHistoricalRankings($interval, $entityType) {
//		// Determine the table to fetch from based on interval and entityType
//		$tableName = match ($interval) {
//			'daily' => $entityType == 'artist' ? 'NGNArtistRankingsHistoryDaily' : 'NGNLabelRankingsHistoryDaily',
//			'weekly' => $entityType == 'artist' ? 'NGNArtistRankingsHistoryWeekly' : 'NGNLabelRankingsHistoryWeekly',
//			'monthly' => $entityType == 'artist' ? 'NGNArtistRankingsHistoryMonthly' : 'NGNLabelRankingsHistoryMonthly',
//			'yearly' => $entityType == 'artist' ? 'NGNArtistRankingsHistoryYearly' : 'NGNLabelRankingsHistoryYearly',
//			default => null,
//		};
//
//		if (!$tableName) {
//			throw new InvalidArgumentException('Invalid interval or entity type');
//		}
//
//		// Specify the columns we want to fetch, including score components
//        $columnsToFetch = ($entityType == 'artist')
//	    ? ['ArtistId', 'Score', 'SMR_Score', 'Post_Mentions_Score', 'Views_Score', 'Rank', 'Timestamp'] // Add 'Timestamp'
//	    : ['LabelId', 'Score', 'Rank', 'Timestamp']; // Add 'Timestamp' for labels too
//
//
//		// Fetch the latest historical rankings with specified columns
//		$rankings = $this->dataManager->browse($tableName, [], ['Timestamp' => 'DESC'], $columnsToFetch);
//
//		// Check if any rankings were found
//		if (empty($rankings)) {
//			// Log a more detailed error message
//			error_log("Error: No historical rankings found for $entityType in interval $interval. Table: $tableName");
//			return []; // Return an empty array to indicate no data found
//		}
//
//		// Basic data validation - check if expected columns are present
//		$firstRow = reset($rankings);
//		$expectedColumns = $entityType == 'artist' ? ['ArtistId', 'Score', 'Rank'] : ['LabelId', 'Score', 'Rank'];
//		foreach ($expectedColumns as $column) {
//			if (!isset($firstRow[$column])) {
//				// Log an error if a column is missing
//				error_log("Error: Column '$column' missing in historical rankings for $entityType in interval $interval. Table: $tableName");
//				return []; // Return an empty array to indicate invalid data
//			}
//		}
//
//		return $rankings;
//	}
//
//	public function storeChanges($changes, $entityType, $interval) {
//		// Determine the destination table based on entity type and interval
//		$tableName = match ($entityType) {
//			'artist' => match ($interval) {
//				'daily' => 'NGNArtistRankingsHistoryDaily',
//				'weekly' => 'NGNArtistRankingsHistoryWeekly',
//				'monthly' => 'NGNArtistRankingsHistoryMonthly',
//				'yearly' => 'NGNArtistRankingsHistoryYearly',
//				default => null,
//			},
//			'label' => match ($interval) {
//				'daily' => 'NGNLabelRankingsHistoryDaily',
//				'weekly' => 'NGNLabelRankingsHistoryWeekly',
//				'monthly' => 'NGNLabelRankingsHistoryMonthly',
//				'yearly' => 'NGNLabelRankingsHistoryYearly',
//				default => null,
//			},
//			default => null,
//		};
//
//		if (!$tableName) {
//			throw new InvalidArgumentException('Invalid entity type or interval for storing changes');
//		}
//
//		try {
//			foreach ($changes as $entityId => $changeData) {
//				// Determine the entity ID column name based on the entity type
//				$entityIdColumn = ($entityType == 'artist') ? 'ArtistId' : 'LabelId';
//
//				// Prepare the data to be inserted
//				$dataToInsert = [
//					$entityIdColumn => $entityId,
//					'PreviousScore' => $changeData['previous_score'],
//					'PreviousRank' => $changeData['previous_rank'],
//					'PositionChange' => $changeData['position_change'],
//					'ScoreChange' => $changeData['score_change'],
//					'Trend' => $changeData['trend']
//				];
//
//				// Use the 'add' function to insert the data
//				$this->dataManager->add($tableName, $dataToInsert);
//			}
//
//			// echo "Changes for $entityType rankings successfully stored for interval: $interval"; // Optional feedback
//
//			return true;
//		} catch (PDOException $e) {
//			error_log("Database error storing changes for $entityType rankings for interval $interval: " . $e->getMessage());
//			return false;
//		}
//	}
//
//	public function storeRankingsAndChanges($rankings, $artistChanges, $labelChanges) {
//		// Last updated: 2024-10-02 06:24:44
//		$this->storeRankings($rankings);
//		$this->storeChanges($artistChanges['daily'], 'artist', 'daily');
//		$this->storeChanges($artistChanges['weekly'], 'artist', 'weekly');
//		$this->storeChanges($artistChanges['monthly'], 'artist', 'monthly');
//		$this->storeChanges($artistChanges['yearly'], 'artist', 'yearly');
//
//		$this->storeChanges($labelChanges['daily'], 'label', 'daily');
//		$this->storeChanges($labelChanges['weekly'], 'label', 'weekly');
//		$this->storeChanges($labelChanges['monthly'], 'label', 'monthly');
//		$this->storeChanges($labelChanges['yearly'], 'label', 'yearly');
//	}
//
//	public function updateLabelScore(&$finalLabelScores, $labelId, $artistScore)
//	{
//		if (isset($finalLabelScores[$labelId])) {
//			$finalLabelScores[$labelId]['totalScore'] += $artistScore;
//		} else {
////			die("Label with ID $labelId not found in finalLabelScores. Skipping score update.");
//		}
//	}
//
//
//	public function findRankingByArtistId($rankings, $artistId) {
//		foreach ($rankings as $ranking) {
//			if ($ranking['ArtistId'] == $artistId) {
//				return [
//					'Rank' => $ranking['Rank'],
//					'Score' => $ranking['Score']
//				];
//			}
//		}
//		return null; // Return null if no ranking is found for the given artistId
//	}
//
//	public function findRankingByLabelId($rankings, $labelId) {
//		foreach ($rankings as $ranking) {
//			if ($ranking['LabelId'] == $labelId) {
//				return [
//					'Rank' => $ranking['Rank'],
//					'Score' => $ranking['Score']
//				];
//			}
//		}
//		return null;
//	}
//
//	public function getViewCount($data,$action,$entityId){
//		$viewCount = 0;
//
//		// Iterate through the view data
//		foreach ($data as $row) {
//			// Initialize $otherData as an empty array
//			$otherData = [];
//
//			// If 'OtherData' exists, attempt to decode it as JSON
//			if (isset($row['OtherData']) && !empty($row['OtherData'])) {
//				$otherData = json_decode($row['OtherData'], true);
//			}
//
//			// Check if the action and entity ID match
//			if (isset($row['Action']) &&
//				$row['Action'] == $action &&
//				isset($otherData[$action . '_id']) &&
//				$otherData[$action . '_id'] == $entityId) {
//				$viewCount += $row['ViewCount'];
//			}
//		}
//		return $viewCount;
//	}
//}