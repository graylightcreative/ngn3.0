<?php

use Carbon\Carbon;

class DataManager
{
	private $pdo;

	public function __construct($pdo)
	{
		$this->pdo = $pdo;
	}

	public function browse($table, $conditions = [], $orderBy = [], $columns = [])
	{
		try {
			// ... (your existing input validation)

			// Handle column selection
			$selectClause = empty($columns) ? '*' : implode(', ', $columns);

			// Build the WHERE clause (if conditions are provided)
			$whereClause = '';
			if (!empty($conditions)) {
				$whereConditions = [];
				foreach ($conditions as $column => $value) {
					$whereConditions[] = "$column = :$column";
				}
				$whereClause = ' WHERE ' . implode(' AND ', $whereConditions);
			}

			// Build the ORDER BY clause (if orderBy is provided)
			$orderByClause = '';
			if (!empty($orderBy)) {
				$orderByConditions = [];
				foreach ($orderBy as $column => $direction) {
					$orderByConditions[] = "$column $direction";
				}
				$orderByClause = ' ORDER BY ' . implode(', ', $orderByConditions);
			}

			// Prepare the statement
			$statement = $this->pdo->prepare("SELECT $selectClause FROM $table $whereClause $orderByClause");

			// Bind parameters (if conditions are provided)
			if (!empty($conditions)) {
				foreach ($conditions as $column => $value) {
					$statement->bindValue(":$column", $value);
				}
			}

			// Execute the statement
			if ($statement->execute()) {
				return $statement->fetchAll();
			} else {
				throw new PDOException("Failed to execute browse() for table: $table");
			}
		} catch (PDOException $e) {
			// Handle exceptions
			error_log('Database Error in browse(): ' . $e->getMessage());
			throw $e; // Re-throw for higher-level handling
		}
	}

	public function read($table, $column, $value)
	{
		$statement = $this->pdo->prepare("SELECT * FROM $table WHERE $column = :value");
		$statement->bindValue(':value', $value);
		return $statement->execute() ? $statement->fetch() : false;
	}

    public function dbTest($db, $table, $column, $value)
	{
		$statement = $db->prepare("SELECT * FROM $table WHERE $column = :value");
		$statement->bindValue(':value', $value);
		return $statement->execute() ? $statement->fetch() : false;
	}



	public function readMany($table, $column, $value, $chunkSize = 1000)
	{
		try {
			// ... (your existing input validation)

			// Initialize offset and results array
			$offset = 0;
			$results = [];

			do {
				// Prepare the statement with LIMIT and OFFSET clauses
				$statement = $this->pdo->prepare("SELECT * FROM $table WHERE $column = CAST(:value AS UNSIGNED) LIMIT $chunkSize OFFSET $offset");

				// Bind parameters
				$statement->bindValue(':value', $value, PDO::PARAM_INT);

				// Execute the statement
				if ($statement->execute()) {
					// Fetch the chunk of results
					$chunk = $statement->fetchAll(PDO::FETCH_ASSOC);
					$results = array_merge($results, $chunk);

					// Increment the offset for the next chunk
					$offset += $chunkSize;
				} else {
					throw new PDOException("Failed to execute readMany() for table: $table, column: $column, value: $value");
				}
			} while (count($chunk) == $chunkSize); // Continue until a chunk is less than the chunk size


			return $results;
		} catch (PDOException $e) {
			error_log('Database Error in readMany(): ' . $e->getMessage());
			throw $e;
		}
	}

	public function search($table, $column, $value)
	{
		try {
			// Validate input
			if (!is_string($table) || empty($table)) {
				throw new InvalidArgumentException('Invalid table name.');
			}
			if (!is_string($column) || empty($column)) {
				throw new InvalidArgumentException('Invalid column name.');
			}

			// Sanitize the table and column names
			$table = preg_replace('/[^a-zA-Z0-9_]+/', '', $table);
			$column = preg_replace('/[^a-zA-Z0-9_]+/', '', $column);

			// Prepare the statement with placeholders
			$statement = $this->pdo->prepare("SELECT * FROM $table WHERE LOWER($column) LIKE LOWER(:value)");

			// Bind parameters
			$searchPattern = '%' . $value . '%';
			$statement->bindValue(':value', $searchPattern);

			// Execute the statement
			$statement->execute();

			// Fetch and return results
			return $statement->fetchAll(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			// Handle PDO exceptions specifically
			error_log('Database Error in search(): ' . $e->getMessage());
			return [];
		}
	}

	public function edit($table, $id, $data)
	{
		// (Input validation remains the same) ...

		// Sanitize the table name
		$table = preg_replace('/[^a-zA-Z0-9_]+/', '', $table);

		$updateFields = [];
		foreach ($data as $key => $value) {
			$updateFields[] = "$key = :$key";
		}

		$query = "UPDATE $table SET " . implode(', ', $updateFields) . ' WHERE Id = :id';
		$stmt = $this->pdo->prepare($query);

		foreach ($data as $key => $value) {
			$stmt->bindValue(":$key", $value, $this->getPDOParamType($value));
		}
		$stmt->bindValue(':id', $id, PDO::PARAM_INT);

		return $stmt->execute() ? true : $stmt->errorInfo();
	}

	public function add($table, $data)
	{
		try {
			$insertColumns = array_keys($data);
			$placeholders = array_fill(0, count($data), '?');

			$q = "INSERT INTO $table (" . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';
			$stmt = $this->pdo->prepare($q);

			return $stmt->execute(array_values($data)) ? true : $stmt->errorInfo();
		} catch (PDOException $e) {
			error_log('Database Error in add(): ' . $e->getMessage());
			throw $e;
		}
	}

	public function delete($table, $id)
	{
		// Validate the table name against a whitelist
		$allowedTables = ['users','VerificationCodes','Ads', 'Albums', 'Contacts', 'Labels', 'LinkLocations', 'Links', 'Orders', 'OrderStatuses', 'Pages', 'posts', 'Products', 'ProductTypes', 'shows', 'SlideLocations', 'Slides', 'Songs', 'Tours', 'videos']; // Add other valid tables
		if (!in_array($table, $allowedTables)) {
			throw new InvalidArgumentException('This table is not allowed for deletion.');
		}

		// Validate the ID (should be an integer)
		if (!filter_var($id, FILTER_VALIDATE_INT)) {
			throw new InvalidArgumentException('Invalid ID.');
		}

		try {
			// Construct the SQL query dynamically, escaping the table name
			$sql = 'DELETE FROM ' . $table . ' WHERE Id = :id';

			$statement = $this->pdo->prepare($sql);
			$statement->bindValue(':id', $id, PDO::PARAM_INT);
			return $statement->execute();
		} catch (PDOException $e) {
			error_log('Database Error in delete(): ' . $e->getMessage());
			throw new \Exception('Failed to delete record.');
		}
	}

	public function readByMultipleColumns($table, $conditions, $columns = [])
	{
		try {
			// Validate input
			if (!is_string($table) || empty($table)) {
				throw new InvalidArgumentException('Invalid table name.');
			}
			if (!is_array($conditions) || empty($conditions)) {
				throw new InvalidArgumentException('Invalid conditions.');
			}

			// Handle empty $columns - fetch all columns if none specified
			$columnsStr = empty($columns) ? '*' : implode(',', $columns);

			// Build the WHERE clause dynamically
			$whereClause = '';
			$params = [];
			foreach ($conditions as $column => $value) {
				$whereClause .= ($whereClause ? ' AND ' : '') . $column . ' = :' . $column;
				$params[':' . $column] = $value;
			}

			// Prepare and execute the SQL query
			$sql = "SELECT $columnsStr FROM $table WHERE $whereClause";
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute($params);

			// Fetch and return the results
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			// Handle exceptions
			error_log('Database Error in readByMultipleColumns(): ' . $e->getMessage());
			throw $e; // Re-throw for higher-level handling
		}
	}

	public function advancedRead($table, $conditions = [], $columns = [])
	{
		try {
			// ... (your existing input validation)

			// Handle column selection
			$selectClause = empty($columns) ? '*' : implode(', ', $columns);

			// Build the WHERE clause dynamically
			$whereClause = '';
			$params = [];
			foreach ($conditions as $column => $value) {
				if (is_array($value)) {
					// Check if it's a numeric array (for operators)
					if (array_keys($value) === range(0, count($value) - 1)) {
						if (count($value) >= 2) {
							$operator = $value[0];
							$bindValue = $value[1];

							if ($bindValue === null) {
								continue; // Skip this condition for now
							}

							$whereClause .= ($whereClause ? ' AND ' : '') . $column . ' ' . $operator . ' :' . $column;
							$params[':' . $column] = $bindValue;
						} else {
							error_log("Invalid condition format for column $column. Expected a numeric array with at least two elements.");
							continue;
						}
					} else { // It's an associative array (for IN clauses)
						$inPlaceholders = [];
						foreach ($value as $key => $inValue) {
							$inPlaceholder = $column . '_' . $key; // Create unique named placeholders for IN clause values
							$inPlaceholders[] = ':' . $inPlaceholder;
							$params[':' . $inPlaceholder] = $inValue; // Add to $params for binding later
						}
						$inPlaceholdersStr = implode(',', $inPlaceholders);
						$whereClause .= ($whereClause ? ' AND ' : '') . $column . ' IN (' . $inPlaceholdersStr . ')';
					}
				} else {
					$whereClause .= ($whereClause ? ' AND ' : '') . $column . ' = :' . $column;
					$params[':' . $column] = $value;
				}
			}

			// Prepare and execute the SQL query
			$sql = "SELECT $selectClause FROM $table WHERE $whereClause";
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute($params);

			// Fetch and return the result
			return $stmt->fetch(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			// Handle exceptions
			error_log('Database Error in advancedRead(): ' . $e->getMessage());
			throw $e;
		}
	}

	private function getPDOParamType($value)
	{
		if (is_int($value)) {
			return PDO::PARAM_INT;
		} elseif (is_bool($value)) {
			return PDO::PARAM_BOOL;
		} elseif (is_null($value)) {
			return PDO::PARAM_NULL;
		} else {
			return PDO::PARAM_STR;
		}
	}

	public function executeQuery($query, $params = [])
	{
		try {
			$stmt = $this->pdo->prepare($query); // Assuming $this->pdo is your PDO connection
			$stmt->execute($params);
			return $stmt->fetchAll(\PDO::FETCH_ASSOC); // Fetch all rows as associative arrays
		} catch (\PDOException $e) {
			// Handle database errors (log, throw custom exception, etc.)
			throw new \Exception('Database Error: ' . $e->getMessage());
		}
	}

	public function customQuery($sql, $params = [])
	{
		try {
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute($params);
			$result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
			return $result;
		} catch (\PDOException $e) {
			// Log the error or handle it as needed
			echo 'Error executing query: ' . $e->getMessage() . "\n";
			echo 'SQL: ' . $sql . "\n";
			echo 'Params: ' . print_r($params, true) . "\n";
			return false; // Or throw an exception
		}
	}


	public function getDistinctIdsFromTable($table, $column)
	{
		// Handle Posts table differently as it doesn't have ArtistId
		if ($table === 'posts') {
			// Fetch all posts
			$allPosts = $this->browse('posts');
			$artistIds = [];

			foreach ($allPosts as $post) {
				// Get all artist IDs from the database
				$allArtists = $this->fetchAllArtists();

				foreach ($allArtists as $artist) {
					$artistName = $artist['Title'];
					$postFields = ['Title', 'Body', 'Summary', 'Tags'];
					foreach ($postFields as $field) {
						if (stripos($post[$field], $artistName) !== false) {
							$artistIds[] = $artist['Id'];
							break 2; // Move to the next post if a match is found
						}
					}
				}
			}

			return array_unique($artistIds); // Return distinct artist IDs found in posts
		}

		// For other tables, use the standard query
		$sql = "SELECT DISTINCT $column FROM $table";
		$result = $this->customQuery($sql);

		// Handle empty result sets
		if (empty($result)) {
			return [];
		}

		// Debugging: Check the result before using array_column
		if (!is_array($result)) {
			echo "Error: customQuery returned a non-array value for table '$table' and column '$column'\n";
			var_dump($result);
			echo '<hr>';
		}

		return array_column($result, $column);
	}

	//////////////////////////
	////// CHART RELATED /////
	//////////////////////////

	public function aggregateViewData()	{
		global $pdo;

		$sql = "
        SELECT 
            Action,
            CASE 
                WHEN Action = 'artist_view' THEN JSON_EXTRACT(OtherData, '$.artist_view_id')
                WHEN Action = 'label_view' THEN JSON_EXTRACT(OtherData, '$.label_view_id')
                WHEN Action = 'article_view' THEN JSON_EXTRACT(OtherData, '$.article_view_id')
                WHEN Action = 'page_view' THEN JSON_EXTRACT(OtherData, '$.page_view_id')
            END AS entity_id,
            SUM(ViewCount) AS total_views,
            -- Count articles mentioning the entity in title or tags, considering entity type
            SUM(CASE 
                WHEN Action = 'article_view' THEN 0  -- Articles don't mention other articles
                ELSE (
                    SELECT COUNT(*) 
                    FROM posts 
                    WHERE 
                        (Action = 'artist_view' AND 
                            (
                                Body LIKE CONCAT('%', (SELECT Title FROM users WHERE Id = entity_id), '%') OR
                                JSON_SEARCH(Tags, 'one', (SELECT Title FROM users WHERE Id = entity_id)) IS NOT NULL
                            )
                        ) 
                        OR 
                        (Action = 'label_view' AND 
                            (
                                Body LIKE CONCAT('%', (SELECT Title FROM users WHERE Id = entity_id AND RoleId = 7), '%') OR
                                JSON_SEARCH(Tags, 'one', (SELECT Title FROM users WHERE Id = entity_id AND RoleId = 7)) IS NOT NULL
                            )
                        )
                )
            END) AS relevant_article_count
        FROM hits
        GROUP BY Action, entity_id;
    ";

		$stmt = $pdo->prepare($sql);
		$stmt->execute();

		$viewData = [];
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$action = $row['Action'];
			$entityId = $row['entity_id'];
			$totalViews = $row['total_views'];
			$relevantArticleCount = $row['relevant_article_count'];

			if (!isset($viewData[$action])) {
				$viewData[$action] = [];
			}
			$viewData[$action][$entityId] = [
				'total_views' => $totalViews,
				'relevant_article_count' => $relevantArticleCount
			];
		}

		return $viewData;
	}

	public function fetchSMRCharts() {
		$SMRCharts = $this->browse('smr_chart');
		if (!$SMRCharts) {
			http_response_code(500);
			die('Error: Chart data is currently unavailable.');
		}
		return $SMRCharts;
	}

	public function fetchAllLabels() {
		return $this->readMany('users', 'RoleId', 7);
	}

	public function fetchAllArtists() {
		return $this->readMany('users', 'RoleId', 3);
	}

	public function fetchRankingsFromDatabase() {
		return [
			'topArtists' => $this->browse('NGNArtistRankings'),
			'topLabels' => $this->browse('NGNLabelRankings')
		];
	}




//	update 10/02/2024 4:16am est
	public function getLatestActivityTimestampForArtist($artistId)
	{
		$tablesToCheck = [
			'NGNArtistRankings' => 'Timestamp', // correct in db
			'NGNArtistRankingsHistoryDaily' => 'Timestamp', // correct in db
			'NGNArtistRankingsHistoryWeekly' => 'Timestamp',  // correct in db
			'NGNArtistRankingsHistoryMonthly' => 'Timestamp',  // correct in db
			'NGNArtistRankingsHistoryYearly' => 'Timestamp',  // correct in db
			'posts' => 'PublishedDate', // should be either Created, Updated, or PublishedDate
			'station_spins' => 'SpinDateTime',  // correct in db
			'post_mentions' => 'Timestamp',  // correct in db
			'shows' => 'ShowDate',  // correct in db
			'videos' => 'Timestamp',  // correct in db
			'SocialMediaPosts' => 'Timestamp'  // correct in db
		];

		$latestTimestamp = null;

		foreach ($tablesToCheck as $table => $timestampColumn) {
			if ($table === 'posts') {
				// Fetch all posts
				$allPosts = $this->browse('posts');
				$latestPostTimestamp = null;

				// Iterate through posts and check for artist name in relevant fields
				foreach ($allPosts as $post) {
					$artistName = $this->read('users', 'Id', $artistId)['Title'];
					$postFields = ['Title', 'Body', 'Summary', 'Tags'];
					foreach ($postFields as $field) {
						if (stripos($post[$field], $artistName) !== false) {
							$postTimestamp = strtotime($post['PublishedDate']); // Use PublishedDate
							$latestPostTimestamp = max($latestPostTimestamp, $postTimestamp);
							break;
						}
					}
				}

				// Update $latestTimestamp if a newer post timestamp is found
				if ($latestPostTimestamp !== null) {
					$latestTimestamp = max($latestTimestamp, $latestPostTimestamp);
				}
			} else if ($table === 'station_spins') {
				$sql = "SELECT MAX($timestampColumn) as latest_timestamp 
                  FROM $table 
                  WHERE ArtistId = :artistId AND Approved = 1";
				$result = $this->customQuery($sql, [':artistId' => $artistId]);



				if ($result && $result[0]['latest_timestamp']) {
					$timestamp = strtotime($result[0]['latest_timestamp']);
					$latestTimestamp = max($latestTimestamp, $timestamp);
				}
			} else {
				$sql = "SELECT MAX($timestampColumn) as latest_timestamp 
                  FROM $table 
                  WHERE ArtistId = :artistId";
				$result = $this->customQuery($sql, [':artistId' => $artistId]);


				if ($result && $result[0]['latest_timestamp']) {
					$timestamp = strtotime($result[0]['latest_timestamp']);
					$latestTimestamp = max($latestTimestamp, $timestamp);
				}
			}
		}

		// If no activity found in NGN tables, check SMRCharts
		if ($latestTimestamp === null) {
			$sql = 'SELECT MAX(Date) as latest_timestamp 
            FROM smr_chart 
            WHERE Artists LIKE :artistNamePrefix'; // Use LIKE for partial match

			// Fetch the artist's name from the Users table
			$artistName = $this->read('users', 'Id', $artistId)['Title'];

			$result = $this->customQuery($sql, [':artistNamePrefix' => $artistName . '%']);


			if ($result && $result[0]['latest_timestamp']) {
				$latestTimestamp = strtotime($result[0]['latest_timestamp']);
			}
		}

		return $latestTimestamp ? date('Y-m-d H:i:s', $latestTimestamp) : null;
	}




	public function getArtistsFromChartEntry($chartEntry) {
		$artistsString = $chartEntry['Artists'];

		// Replace '&' with ',' for consistent splitting
		$artistsString = str_replace(' & ', ', ', $artistsString);

		// Split the string into an array of artist names
		$artists = explode(',', $artistsString);

		// Trim any leading/trailing whitespace from each artist name
		$artists = array_map('trim', $artists);

		return $artists;
	}

	// Last updated: 2024-10-02 07:18:29
	public function getChartRankings($finalArtistScores, $finalLabelScores)
	{
		// Validate input data types
		if (!is_array($finalArtistScores) || !is_array($finalLabelScores)) {
			throw new \InvalidArgumentException('Error in getChartRankings: Invalid input data types. Both finalArtistScores and finalLabelScores must be arrays.');
		}

		// Top 500
		$topArtists = array_slice($finalArtistScores, 0, 500, true);
		$topLabels = array_slice($finalLabelScores, 0, 500, true);

		// Assign ranks, preserving the original score structure AND 'last_activity'
		$artistRank = 1;
		foreach ($topArtists as $artistId => &$scoreData) { // Use reference to modify the original array
			$scoreData['Rank'] = $artistRank++;
			// If you need to add 'last_activity' to labels as well, do it similarly here for $topLabels
		}

		$labelRank = 1;
		foreach ($topLabels as $labelId => $scoreData) {
			$topLabels[$labelId]['Rank'] = $labelRank++;
		}

		return [
			'topArtists' => $topArtists,
			'topLabels' => $topLabels
		];
	}

	public function getHitsForItem($action, $entityId) {
		// Fetch all hits from the database
		$allHits = $this->browse('hits');

		$totalViews = 0;
		foreach ($allHits as $hit) {
			if ($hit['Action'] == $action) {
				$otherData = json_decode($hit['OtherData'], true);
				if (isset($otherData[$action . '_id']) && $otherData[$action . '_id'] == $entityId) {
					$totalViews += $hit['ViewCount'];
				}
			}
		}

		return $totalViews;
	}

	public function getLabelIdFromArtistId($artistId)
	{
		// Check cache first
		if (isset($this->labelIdCache[$artistId])) {
			return $this->labelIdCache[$artistId];
		}

		// Fetch from database if not in cache
		$a = read('users','Id',$artistId);
		if(!$a) return false;
		$l = read('users','LabelId',$a['LabelId']);
		if($l){
			$labelId = $l['Id'];
		} else {
			return false;
		}

		// Cache the result
		$this->labelIdCache[$artistId] = $labelId;

		return $labelId;
	}

	public function getArtistIdFromName($name) {
		// Convert the incoming name to title case (first letter of each word capitalized)
		$formattedName = ucwords(strtolower($name));

		$u = $this->read('users','Title',$formattedName);
		if ($u) {
			return $u['Id'];
		} else {
			throw new \Exception("Artist not found: $name");
		}
	}

	public function hasSignificantChartGainForAnyArtist(array $artistIds, $timeframe = '1 week', $threshold = 5, $minConsecutiveGains = 2)
	{
		// Calculate the cut-off date based on the timeframe
		$cutoffDate = date('Y-m-d', strtotime('-' . $timeframe));

		// Modify the SQL query to filter by artist IDs
		$sql = '
    SELECT Artists, WOC, LWTW 
    FROM smr_chart 
    WHERE Artists IN (' . implode(',', $artistIds) . ') AND Date >= :cutoffDate
    ORDER BY Date DESC; 
';

		$chartEntries = $this->customQuery($sql, [
			':artistIds' => $artistIds,
			':cutoffDate' => $cutoffDate
		]);

// If no chart entries are found, there's no gain to assess
		if (empty($chartEntries)) {
			return false;
		}


		// Check for a significant gain in the most recent entry
		$mostRecentEntry = $chartEntries[0];
		$gain = $mostRecentEntry['LWTW'] - $mostRecentEntry['WOC'];
		if ($gain >= $threshold) {
			return true;
		}

		// Check for consistent gains over multiple weeks
		$consecutiveGains = 0;
		foreach ($chartEntries as $entry) {
			$gain = $entry['LWTW'] - $entry['WOC'];
			if ($gain > 0) {
				$consecutiveGains++;
				if ($consecutiveGains >= $minConsecutiveGains) {
					return true;
				}
			} else {
				$consecutiveGains = 0;
			}
		}

		return false;	}

	public function getAllRelevantPosts($labels, $artists) {

		// Extract all label and artist names for efficient querying, case-insensitive
		$labelNames = array_map('strtolower', array_column($labels, 'Title'));
		$artistNames = array_map('strtolower', array_column($artists, 'Title'));

		// Fetch all posts
		$allPosts = $this->browse('posts');

		$relevantPosts = [];

		foreach ($allPosts as $post) {
			$isRelevant = false;
			$matchedIn = null;
			$matchedEntity = null;

			$searchableFields = [
				'Title' => 2 * POST_RELEVANCE_WEIGHT,
				'Summary' => 1.5 * POST_RELEVANCE_WEIGHT,
				'Body' => POST_RELEVANCE_WEIGHT,
				'Tags' => 0.5 * POST_RELEVANCE_WEIGHT
			];

			foreach ($searchableFields as $field => $weight) {
				$fieldValue = strtolower($post[$field]);

				foreach ($labelNames as $labelName) {
					if (stripos($fieldValue, $labelName) !== false) {
						$isRelevant = true;
						$matchedIn = $field;
						$matchedEntity = $labelName;
						break 2;
					}
				}

				foreach ($artistNames as $artistName) {
					if (stripos($fieldValue, $artistName) !== false) {
						$isRelevant = true;
						$matchedIn = $field;
						$matchedEntity = $artistName;
						break 2;
					}
				}
			}

			if ($isRelevant) {
				$relevantPostData = [
					'post' => $post,
					'matched_in' => $matchedIn
				];

				if (in_array($matchedEntity, $labelNames)) {
					$relevantPostData['label_name'] = $matchedEntity;
				} elseif (in_array($matchedEntity, $artistNames)) {
					$relevantPostData['artist_name'] = $matchedEntity;
				} else {
					die('Unable to determine entity type for post ID: ' . $post['Id']);
				}

				$relevantPosts[] = $relevantPostData;
			}
		}


		return $relevantPosts;
	}

	public function isArticleRelevantToArtist($articleId, $artistId) {
		// 1. Fetch Article Details
		if (empty($articleId)) {
			throw new \InvalidArgumentException('No article ID provided');
		}

		$article = $this->read('posts', 'Id', $articleId);
		if (!$article) {
			throw new \Exception('Article not found');
		}

		// 2. Check if Artist's Name is in Article Title or Body
		$artistData = $this->read('users', 'Id', $artistId);

		// Check if $artistData is valid
		if (!$artistData || !is_array($artistData)) {
			throw new \Exception('Artist not found');
		}

		$artistName = $artistData['Title'];

		// Case-insensitive checks for artist name in title, body, and tags
		if (stripos($article['Title'], $artistName) !== false ||
			stripos($article['Body'], $artistName) !== false ||
			stripos($article['Tags'], $artistName) !== false) {
			return true;
		}

		// No relevance found
		return false;
	}

	public function getRelevantArticleViewsForLabel($viewData, $labelId) {
		$relevantArticleViews = 0;

		if (isset($viewData['article_view'])) {
			// 1. Get article IDs and their view counts in one go
			$articleViewCounts = array_map(function($data) {
				return $data['total_views'];
			}, $viewData['article_view']);

			// 2. Fetch relevant articles, potentially optimizing the query
			$relevantArticles = $this->dataManager->getArticlesWithLabelOrArtistMention($articleViewCounts, $labelId);

			// 3. Aggregate views from relevant articles
			foreach ($relevantArticles as $article) {
				$relevantArticleViews += $articleViewCounts[$article['Id']];
			}
		}

		return $relevantArticleViews;
	}

	public function getRelevantPostsForLabel($labelId) {
		// Fetch the label's name
		$label = $this->read('users', 'Id', $labelId);
		if (!$label) {
			return []; // Return an empty array if the label is not found
		}

		$labelName = $label['Title'];

		$allRelevantPosts = [];

		// Search for posts mentioning the label in various fields
		$postsInBody = $this->search('posts', 'Body', $labelName);
		foreach ($postsInBody as $post) {
			$allRelevantPosts[] = ['post' => $post, 'matched_in' => 'Body'];
		}

		$postsInTitle = $this->search('posts', 'Title', $labelName);
		foreach ($postsInTitle as $post) {
			$allRelevantPosts[] = ['post' => $post, 'matched_in' => 'Title'];
		}

		$postsInSummary = $this->search('posts', 'Summary', $labelName);
		foreach ($postsInSummary as $post) {
			$allRelevantPosts[] = ['post' => $post, 'matched_in' => 'Summary'];
		}

		// Search for posts with the label's name in the tags
		$allPosts = $this->browse('posts');
		foreach ($allPosts as $post) {
			$tagsString = strtolower($post['Tags']);
			$normalizedTagsString = preg_replace('/,\s+/', ',', $tagsString);
			$tags = explode(',', $normalizedTagsString);

			foreach ($tags as $tag) {
				if ($tag === strtolower($labelName)) {
					$allRelevantPosts[] = ['post' => $post, 'matched_in' => 'Tags'];
				}
			}
		}

		return $allRelevantPosts;
	}

	public function getRecentReleasesForArtists(array $artistIds, $timeframe = '1 month')
	{
		// Calculate the cut-off date based on the timeframe
		$cutoffDate = date('Y-m-d', strtotime('-' . $timeframe));

		// Use advancedRead to fetch recent releases, filtering by artist IDs
		return $this->advancedRead('releases', [
			'ArtistId' => ['in', $artistIds], // Filter by artist IDs
			'ReleaseDate' => ['>=', $cutoffDate]
		]);
	}

	public function getArticlesWithLabelOrArtistMention(array $articleIds, $labelId)
	{
		// 1. Fetch label details
		$label = $this->read('users', 'Id', $labelId);
		if (!$label) {
			return []; // Or handle the missing label case differently
		}
		$labelName = $label['Title'];

		// 2. Fetch artists associated with the label
		$artists = $this->readMany('users', 'LabelId', $labelId);
		$artistNames = array_column($artists, 'Title');

		// 3. Construct the SQL query with placeholders
		$sql = "SELECT Id, Tags, Body 
        FROM posts 
        WHERE Id IN (:articleIds) AND (
            Body LIKE CONCAT('%', :labelName, '%') OR 
            JSON_SEARCH(Tags, 'one', :labelName) IS NOT NULL OR ";

		// Add conditions for each artist
		foreach ($artistNames as $index => $artistName) {
			$sql .= "Body LIKE CONCAT('%', :artistName$index, '%') OR 
            JSON_SEARCH(Tags, 'one', :artistName$index) IS NOT NULL";
			if ($index < count($artistNames) - 1) {
				$sql .= ' OR ';
			}
		}

		$sql .= ')'; // Close the parentheses

		// 4. Prepare and execute the query using customQuery
		$params = [':articleIds' => $articleIds, ':labelName' => $labelName];
		foreach ($artistNames as $index => $artistName) {
			$params[":artistName$index"] = $artistName;
		}

		return $this->customQuery($sql, $params);
	}

	public function getRecentReleases($artistId, $timeframe = '1 month')
	{
		// Calculate the cut-off date based on the timeframe
		$cutoffDate = date('Y-m-d', strtotime('-' . $timeframe));

		// Use advancedRead to fetch recent releases
		return $this->advancedRead('releases', [
			'ArtistId' => $artistId,
			'ReleaseDate' => ['>=', $cutoffDate]
		]);
	}


	/////////////////////////
	/// POST RELATED/////////
	/// ////////////////////

	public function getRelevantPostsByTitle($title)
	{
		$collection = [];
		$posts = browse('posts');
		$title = strtolower($title);
		foreach($posts as $post){
			$t = strtolower($post['Title']);
			$body = strtolower(strip_tags($post['Body']));
			$summary = strtolower($post['Summary']);
			$tags = strtolower(strip_tags($post['Tags']));
			if(str_contains($t, $title)){
				$collection[] = $post;
			} else if(str_contains($body, $title)){
				$collection[] = $post;
			} else if(str_contains($summary, $title)){
				$collection[] = $post;
			} else if(str_contains($tags, $title)){
				$collection[] = $post;
			}
		}
		return $collection;
	}
	public function getRelevantArtistPostsByLabelId($id)
	{
		$artists = readMany('users','LabelId',$id);
		if(!$artists) return false;

		$posts = browse('posts');
		$postsCollection = [];

		foreach($posts as $post){
			$t = strtolower($post['Title']);
			$body = strtolower(strip_tags($post['Body']));
			$summary = strtolower($post['Summary']);
			$tags = strtolower(strip_tags($post['Tags']));

			foreach($artists as $artist){
				if(str_contains($t, strtolower($artist['Title']))){
					$postsCollection[] = $post;
				} else if(str_contains($body, strtolower($artist['Title']))){
					$postsCollection[] = $post;
				} else if(str_contains($summary, strtolower($artist['Title']))){
					$postsCollection[] = $post;
				} else if(str_contains($tags,strtolower($artist['Title']))){
					$postsCollection[] = $post;
				}
			}
		}
		return $postsCollection;
	}

}