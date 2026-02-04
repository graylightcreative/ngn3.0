<?php
//
////namespace App\Validators; // Or adjust namespace as needed
//use Carbon\Carbon;
//
//class ChartValidator
//{
//	public $errors = [];
//	protected $dataManager;
//	protected $recentActivityThreshold;
//
//
//	public function __construct(DataManager $dataManager)
//	{
//		$this->dataManager = $dataManager;
//		$this->recentActivityThreshold = Carbon::now()->subDays((int)$_ENV['RECENT_ACTIVITY_THRESHOLD']);
//	}
//
//	// 1. Test validateRankings with valid data (both artists and labels)
//	public function validateRankings(array $rankings)
//	{
//		$this->errors = []; // Reset errors
//
//		// 1. Basic Structure Validation
//		if (!isset($rankings['topArtists']) || !is_array($rankings['topArtists']) ||
//			!isset($rankings['topLabels']) || !is_array($rankings['topLabels'])) {
//			$this->errors[] = 'Error: Invalid rankings array structure.';
//			return false;
//		}
//
//		// 2. Check if rankings are empty
//		if (empty($rankings['topArtists']) || empty($rankings['topLabels'])) {
//			$this->errors[] = 'Error: Rankings are empty.';
//			return false;
//		}
//
//		// 3. Detailed Artist & Label Rankings Validation (missing keys)
//		if (!$this->validateArtistRankings($rankings['topArtists'])) {
//			return false;
//		}
//
//		if (!$this->validateLabelRankings($rankings['topLabels'])) {
//			return false;
//		}
//
//		// 4. Test validateRankings with non-array data
//		if (!$this->validateRankingsWithNonArrayData($rankings)) {
//			return false;
//		}
//
//		// 5. Test validateArtistRankings and validateLabelRankings with invalid score data
//		$chartData = ['entries' => array_merge($rankings['topArtists'], $rankings['topLabels'])];
//
//		if (!$this->validateScoreData($rankings['topArtists'], 'artist')) {
//			return false;
//		}
//
//		if (!$this->validateScoreData($rankings['topLabels'], 'label')) {
//			return false;
//		}
//
//		// 6. Duplicate Ranks
//		if (!$this->checkForDuplicateRanks($rankings['topArtists'], 'artist') ||
//			!$this->checkForDuplicateRanks($rankings['topLabels'], 'label')) {
//			return false;
//		}
//
//		// 7. artistIdsExistInUsersTable and labelIdsExistInUsersTable
//		if (!$this->artistIdsExistInUsersTable($chartData)) {
//			return false;
//		}
//
//		if (!$this->labelIdsExistInUsersTable($chartData)) {
//			return false;
//		}
//
//		// 8. validateRecentActivity
//		if (!$this->validateRecentActivity($chartData)) {
//			return false;
//		}
//
//		// 9. validateScoreConsistency
//		if (!$this->validateScoreConsistency($chartData)) {
//			return false;
//		}
//
//		// 10. validateHistoricalData
//		if (!$this->validateHistoricalData($chartData)) {
//			return false;
//		}
//
//		return true; // All validations passed
//	}
//	// 2. Test validateRankings with invalid structure (missing key)
//	public function validateArtistRankings(array $artistRankings)
//	{
//		// Check if required keys exist within each artist's data
//		foreach ($artistRankings as $artistId => $artistData) {
//			if (!isset($artistData['totalScore']) ||
//				!isset($artistData['Rank']) ||
//				!isset($artistData['smrScore']) ||
//				!isset($artistData['postMentionsScore']) ||
//				!isset($artistData['viewsScore'])) {
//
//				$this->errors[] = "Error: Invalid score data for artist ID $artistId. Missing required keys.";
//				return false;
//			}
//		}
//
//		return true; // All validations passed for artist rankings
//	}
//
//	// 3. Test validateRankings with non-array data for "topArtists" or "topLabels"
//	public function validateLabelRankings(array $labelRankings)
//	{
//		// Check if required keys exist within each label's data
//		foreach ($labelRankings as $labelId => $labelData) {
//			if (!isset($labelData['totalScore']) ||
//				!isset($labelData['Rank'])) {
//
//				$this->errors[] = "Error: Invalid score data for label ID $labelId. Missing required keys.";
//				return false;
//			}
//		}
//
//		return true; // All validations passed for label rankings
//	}
//
//	// 4a. Test validateArtistRankings and validateLabelRankings with invalid score data
//	public function validateRankingsWithNonArrayData(array $rankings)
//	{
//		if (!is_array($rankings['topArtists']) || !is_array($rankings['topLabels'])) {
//			$this->errors[] = 'Error: topArtists or topLabels is not an array.';
//			return false;
//		}
//
//		return true; // All validations passed
//	}
//
//	// 4b. Test validateArtistRankings and validateLabelRankings with invalid score data
//	// Last updated: 2024-10-01 08:23:37
//	public function validateScoreData(array $rankingsData, $type)
//	{
//		$maxTotalScoreKey = ($type === 'artist') ? 'MAX_ARTIST_SCORE' : 'MAX_LABEL_SCORE';
//		$maxSmrScoreKey = ($type === 'artist') ? 'MAX_ARTIST_SMR_SCORE' : null;
//
//		foreach ($rankingsData as $entityId => $entityData) {
//			// Check if required keys exist and have numeric values
//			if (!isset($entityData['totalScore'], $entityData['Rank']) ||
//				!is_numeric($entityData['totalScore']) || !is_numeric($entityData['Rank'])) {
//				$this->errors[] = "Error: Invalid or missing score data for $type ID $entityId.";
//				return false;
//			}
//
//			// Check ranges for common fields
//			if ($entityData['totalScore'] < 0 ||
//				$entityData['totalScore'] > (float)$_ENV[$maxTotalScoreKey] ||
//				$entityData['Rank'] <= 0) {
//				$this->errors[] = "Error: Score or rank out of range for $type ID $entityId.";
//				return false;
//			}
//
//			// Additional checks for artist-specific fields
//			if ($type === 'artist') {
//				// Check if required keys exist and have numeric values
//				if (!isset($entityData['smrScore'], $entityData['postMentionsScore'], $entityData['viewsScore']) ||
//					!is_numeric($entityData['smrScore']) || !is_numeric($entityData['postMentionsScore']) || !is_numeric($entityData['viewsScore'])) {
//					$this->errors[] = "Error: Invalid or missing score data for artist ID $entityId.";
//					return false;
//				}
//
//				// Check ranges for artist-specific fields
//				if ($entityData['smrScore'] < 0 || $entityData['smrScore'] > (float)$_ENV[$maxSmrScoreKey] ||
//					$entityData['postMentionsScore'] < 0 ||
//					$entityData['viewsScore'] < 0) {
//					$this->errors[] = "Error: Artist score out of range for ID $entityId.";
//					return false;
//				}
//			}
//		}
//
//		return true;
//	}
//
//	// 6. Test artistIdsExistInUsersTable and labelIdsExistInUsersTable with missing IDs
//	public function artistIdsExistInUsersTable(array $chartData)
//	{
//		$artistIds = array_column($chartData['entries'], 'artist_id');
//		$activityTables = [
//			'NGNArtistRankings',
//			'NGNArtistRankingsHistoryDaily',
//			'NGNArtistRankingsHistoryWeekly',
//			'NGNArtistRankingsHistoryMonthly',
//			'NGNArtistRankingsHistoryYearly',
//			'posts',
//			'station_spins',
//			'post_mentions',
//			'shows',
//			'videos',
//			'SocialMediaPosts'
//		];
//
//		$missingArtistIds = [];
//
//		foreach ($activityTables as $table) {
//			$existingIds = $this->dataManager->getDistinctIdsFromTable($table, 'ArtistId'); // Assuming this method exists in DataManager
//			$missingIds = array_diff($artistIds, $existingIds);
//			$missingArtistIds = array_merge($missingArtistIds, $missingIds);
//		}
//
//		if (!empty($missingArtistIds)) {
//			foreach ($missingArtistIds as $missingId) {
//				$this->errors[] = "The selected artist_id ($missingId) is invalid.";
//			}
//			return false;
//		}
//
//		return true;
//	}
//
//	// 6. Test artistIdsExistInUsersTable and labelIdsExistInUsersTable with missing IDs
//	public function labelIdsExistInUsersTable(array $chartData)
//	{
//		$labelIds = array_column($chartData['entries'], 'label_id');
//		$activityTables = [
//			'posts' // Add other relevant tables for label activity if needed
//		];
//
//		$missingLabelIds = [];
//
//		foreach ($activityTables as $table) {
//			$existingIds = $this->dataManager->getDistinctIdsFromTable($table, 'UserId'); // Assuming labels are also users
//			$missingIds = array_diff($labelIds, $existingIds);
//			$missingLabelIds = array_merge($missingLabelIds, $missingIds);
//		}
//
//		if (!empty($missingLabelIds)) {
//			foreach ($missingLabelIds as $missingId) {
//				$this->errors[] = "The selected label_id ($missingId) is invalid.";
//			}
//			return false;
//		}
//
//		return true;
//	}
//
//	// 7. Test validateRecentActivity with entities lacking recent activity
//	public function validateRecentActivity(array $chartData)
//	{
//		foreach ($chartData['entries'] as $key => $entry) {
//			// Check if last_activity is set and not empty
//			if (!isset($entry['last_activity']) || empty($entry['last_activity'])) {
//				$this->errors[] = "The entries.$key.last_activity field is required.";
//				return false;
//			}
//
//			// Check if last_activity is a valid date
//			try {
//				$lastActivity = Carbon::parse($entry['last_activity']);
//			} catch (\Exception $e) {
//				$this->errors[] = "The entries.$key.last_activity is not a valid date.";
//				return false;
//			}
//
//			// Check if last_activity is within the threshold
//			if ($lastActivity < $this->recentActivityThreshold) {
//				$this->errors[] = "The entries.$key.last_activity must be a recent date.";
//				return false;
//			}
//		}
//
//		return true;
//	}
//	// 8. Test validateScoreConsistency with inconsistent score calculations
//
//	// 8. Test validateScoreConsistency with inconsistent score calculations
//	public function validateScoreConsistency(array $chartData)
//	{
//		$streamsWeight = (float)$_ENV['STREAMING_PLAYS_WEIGHT'];
//		$salesWeight = (float)$_ENV['MERCH_SALES_WEIGHT'];
//		$radioPlaysWeight = (float)$_ENV['RADIO_SPINS_WEIGHT'];
//
//		foreach ($chartData['entries'] as $key => $entry) {
//			// Ensure the necessary keys exist and have numeric values
//			if (!isset($entry['streams'], $entry['sales'], $entry['radio_plays'], $entry['score']) ||
//				!is_numeric($entry['streams']) || !is_numeric($entry['sales']) ||
//				!is_numeric($entry['radio_plays']) || !is_numeric($entry['score'])) {
//				$this->errors[] = "The entries.$key has missing or invalid score data.";
//				return false;
//			}
//
//			$expectedScore =
//				($entry['streams'] * $streamsWeight) +
//				($entry['sales'] * $salesWeight) +
//				($entry['radio_plays'] * $radioPlaysWeight);
//
//			// Use a tolerance for comparison to account for potential floating-point rounding errors
//			$tolerance = 0.01; // Adjust as needed
//			if (abs($entry['score'] - $expectedScore) > $tolerance) {
//				$this->errors[] = "The entries.$key.score calculation is inconsistent.";
//				return false;
//			}
//		}
//
//		return true;
//	}
//
//// 9. Test validateHistoricalData with various scenarios
//	// 9. Test validateHistoricalData with various scenarios
//	public function validateHistoricalData(array $chartData, $startDate = null, $endDate = null)
//	{
//		// Last updated: 2024-10-02 07:21:47
//		// Parse start and end dates if provided
//		$startDate = $startDate ? Carbon::parse($startDate) : null;
//		$endDate = $endDate ? Carbon::parse($endDate) : null;
//
//		foreach ($chartData['entries'] as $key => $entry) {
//			// Ensure 'historical_data' exists and is an array
//			if (!isset($entry['historical_data']) || !is_array($entry['historical_data'])) {
//				$this->errors[] = "The entries.$key.historical_data field is required and must be an array.";
//				return false;
//			}
//
//			$previousWeekEnding = null;
//			$seenWeekEndings = [];
//
//			foreach ($entry['historical_data'] as $weekKey => $weekData) {
//				// Ensure 'week_ending' and 'position' fields exist
//				if (!isset($weekData['week_ending']) || !isset($weekData['position'])) {
//					$this->errors[] = "The entries.$key.historical_data.$weekKey entry is missing required fields (week_ending or position).";
//					return false;
//				}
//
//				// Validate date format (YYYY-MM-DD)
//				try {
//					$weekEnding = Carbon::createFromFormat('Y-m-d', $weekData['week_ending']);
//				} catch (\Exception $e) {
//					$this->errors[] = "The entries.$key.historical_data.$weekKey.week_ending is not a valid date.";
//					return false;
//				}
//
//				// Check if week_ending is within the specified timeframe (if provided)
//				if (($startDate && $weekEnding->lt($startDate)) ||
//					($endDate && $weekEnding->gt($endDate))) {
//					$this->errors[] = "The entries.$key.historical_data.$weekKey.week_ending is outside the allowed timeframe.";
//					return false;
//				}
//
//				// Check for duplicate week_ending dates
//				if (in_array($weekData['week_ending'], $seenWeekEndings)) {
//					$this->errors[] = "The entries.$key.historical_data contains duplicate week_ending dates.";
//					return false;
//				}
//				$seenWeekEndings[] = $weekData['week_ending'];
//
//				// Check for chronological order
//				if ($previousWeekEnding !== null && $weekEnding->lte($previousWeekEnding)) {
//					$this->errors[] = "The entries.$key.historical_data must be sorted chronologically by week_ending.";
//					return false;
//				}
//				$previousWeekEnding = $weekEnding;
//
//				// Validate position range (assuming 1-100)
//				if (!is_int($weekData['position']) || $weekData['position'] < 1 || $weekData['position'] > 100) {
//					$this->errors[] = "The entries.$key.historical_data.$weekKey.position must be between 1 and 100.";
//					return false;
//				}
//			}
//		}
//
//		return true;
//	}
//
//	// Helper method to check for duplicate ranks
//	public function checkForDuplicateRanks(array $rankingsData, $type)
//	{
//		$ranks = array_column($rankingsData, 'Rank');
//		if (count($ranks) !== count(array_unique($ranks))) {
//			$this->errors[] = "Error: Duplicate ranks found in $type rankings.";
//			return false;
//		}
//		return true;
//	}
//
//
//	protected function getConfigValue($key)
//	{
//		static $config = null; // Cache the config data
//
//		if ($config === null) {
//			$config = require $_SERVER['DOCUMENT_ROOT'] . '/lib/definitions/site-setting.php'; // Adjust path if needed
//		}
//
//		return $config[$key] ?? null; // Return the value or null if key doesn't exist
//	}
//
//	public function errors()
//	{
//		return $this->errors;
//	}
//}