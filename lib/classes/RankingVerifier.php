<?php

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

class RankingVerifier
{
    private $rankingCalculator;
    private $pdo;

    public function __construct(RankingCalculator $rankingCalculator = null)
    {
        $this->rankingCalculator = $rankingCalculator ?? new RankingCalculator();

        $config = new Config();
        $this->pdo = ConnectionFactory::write($config);
    }

    /**
     * Verify rankings for an individual artist or label based on their ID and type.
     *
     * @param string $id The ID of the artist or label.
     * @param string $type The type of ranking being verified ('Artist' or 'Label').
     * @param string $date The date for which to verify rankings (format: Y-m-d).
     * @return array Verification result for the specified artist or label.
     */
    public function verifyRanking($id, $type, $date)
    {
        // Validate type
        if (!in_array($type, ['Artist', 'Label'])) {
            throw new InvalidArgumentException("Invalid type. Only 'Artist' or 'Label' are allowed.");
        }

        // Format date to start and end of the day
        $startDate = $date . " 00:00:00";
        $endDate = $date . " 23:59:59";

        // Get stored and calculated rankings for the specific ID and type
        $storedRanking = $this->getStoredRanking($id, $type, $date, $date);
        $calculatedRanking = $this->calculateRankingByIdAndType($id, $type, $date, $date);

        // Check if calculated ranking is valid to prevent errors in RankingCalculator
        if (empty($calculatedRanking) || !is_array($calculatedRanking)) {
            return [
                'id' => $id,
                'type' => $type,
                'status' => 'Error',
                'message' => 'Calculated ranking data is invalid or empty.',
            ];
        }

        // Compare the rankings
        $result = $this->compareIndividualRanking($storedRanking, $calculatedRanking, $type);

        return $result;
    }

    /**
     * Retrieve a stored ranking from the database for a specific ID and type.
     *
     * @param string $id The ID of the artist or label.
     * @param string $type The type of ranking ('Artist' or 'Label').
     * @param string $startDate Start of the date range.
     * @param string $endDate End of the date range.
     * @return array|null Stored ranking or null if not found.
     */
    private function getStoredRanking($id, $type, $startDate, $endDate)
    {
        $tables = $type === 'Artist'
            ? ['Artists', 'ArtistsDaily', 'ArtistsWeekly', 'ArtistsMonthly', 'NGNArtistRankingsYearly']
            : ['Labels', 'LabelsDaily', 'LabelsWeekly', 'LabelsMonthly', 'LabelsYearly'];

        $idColumn = $type . 'Id';

        foreach ($tables as $table) {
            $query = "SELECT * FROM $table WHERE $idColumn = ? AND Timestamp BETWEEN ? AND ?";
            try {
                $ranking = queryByDb($this->pdo, $query, [$id, $startDate, $endDate]);
                if ($ranking) {
                    return $ranking[0];
                }
            } catch (PDOException $e) {
                // Reconnect to the database and retry the query
                try {
                    $ranking = queryByDb($this->pdo, $query, [$id, $startDate, $endDate]);
                    if ($ranking) {
                        return $ranking[0];
                    }
                } catch (PDOException $retryE) {
                    // Handle repeated failure (optional logging or rethrow)
                }
            }
        }

        return null;
    }

    /**
     * Calculate rankings for a specific ID and type.
     *
     * @param string $id The ID of the artist or label.
     * @param string $type The type of ranking ('Artist' or 'Label').
     * @param string $startDate Start of the date range.
     * @param string $endDate End of the date range.
     * @return array Calculated ranking.
     */
    private function calculateRankingByIdAndType($id, $type, $startDate, $endDate)
    {

        // When we calculate rankings, we need to set the start and end dates for the ranking calculation
        // This is because the ranking calculation is done based on the date range of the stored rankings
        // In fact, this should be our End Date - 90 days
        $this->rankingCalculator->startDate = (new DateTime($endDate))->modify('-90 days')->format('Y-m-d') . " 00:00:00";
        $this->rankingCalculator->endDate = $endDate;

        $user = read('users', 'Id', $id); // Retrieve user data by ID

        if (empty($user)) {
            return [];
        }

        return $type === 'Artist'
            ? $this->rankingCalculator->analyzeArtist($user)
            : $this->rankingCalculator->analyzeLabel($user);
    }

    /**
     * Compare a single stored ranking with a calculated ranking.
     *
     * @param array|null $stored Stored ranking (or null if not found).
     * @param array $calculated Calculated ranking.
     * @param string $type The type of ranking ('Artist' or 'Label').
     * @return array Comparison result for the given ranking.
     */
    private function compareIndividualRanking($stored, $calculated, $type)
    {
        $fields = $type === 'Artist'
            ? [
                'ArtistId',
                'Score',
                'Label_Boost_Score',
                'SMR_Score_Active',
                'SMR_Score_Historic',
                'Post_Mentions_Score_Active',
                'Post_Mentions_Score_Historic',
                'Views_Score_Active',
                'Views_Score_Historic',
                'Social_Score_Active',
                'Social_Score_Historic',
                'Videos_Score_Active',
                'Videos_Score_Historic',
                'Spins_Score_Active',
                'Spins_Score_Historic',
                'Releases_Score_Active',
                'Releases_Score_Historic',
                'Posts_Score_Active',
                'Posts_Score_Historic'
            ]
            : [
                'LabelId',
                'Score',
                'Artist_Boost_Score',
                'SMR_Score_Active',
                'SMR_Score_Historic',
                'Post_Mentions_Score_Active',
                'Post_Mentions_Score_Historic',
                'Views_Score_Active',
                'Views_Score_Historic',
                'Social_Score_Active',
                'Social_Score_Historic',
                'Videos_Score_Active',
                'Videos_Score_Historic',
                'Spins_Score_Active',
                'Spins_Score_Historic',
                'Releases_Score_Active',
                'Releases_Score_Historic',
                'Posts_Score_Active',
                'Posts_Score_Historic',
                'AgeScore',
                'ReputationScore',
            ];

        $user = read('users', 'Id', $calculated["{$type}Id"]);
        $name = $user['Title'] ?? 'Unknown';

        if ($stored) {
            $mismatchedValues = [];
            $isCorrect = true;

            foreach ($fields as $field) {
                // Compare values with proper rounding for decimal storage
                if (round((float)$stored[$field], 2) !== round((float)$calculated[$field], 2)) {
                    $isCorrect = false;
                    $mismatchedValues[$field] = [
                        'stored' => $stored[$field],
                        'calculated' => $calculated[$field],
                    ];
                }
            }

            return [
                'id' => $stored["{$type}Id"],
                'name' => $name,
                'storedScore' => $stored['Score'],
                'calculatedScore' => $calculated['Score'],
                'status' => $isCorrect ? 'Correct' : 'Incorrect',
                'mismatchedValues' => $mismatchedValues,
                'storedValues' => $stored,
                'calculatedValues' => $calculated,
            ];
        }

        $score = $calculated['Score'] ?? null;

        return [
            'id' => $calculated["{$type}Id"],
            'name' => $name,
            'storedScore' => null,
            'calculatedScore' => $score,
            'status' => 'Not Found in Stored Rankings',
            'storedValues' => null,
            'calculatedValues' => $calculated,
        ];
    }

    /**
     * Retrieve the start and end dates for ranking calculations.
     *
     * @param string $type The type of ranking ('Artist' or 'Label').
     * @return array Array with 'startDate' and 'endDate' keys.
     */
    public function getRankingDates($id, $type)
    {
        $currentDate = new DateTime();
        $endDate = $currentDate->format('Y-m-d') . " 23:59:59";

        // Assuming the ranking period is last 90 days for both Artists and Labels
        $startDate = $currentDate->modify('-1000 days')->format('Y-m-d') . " 00:00:00";

        $tables = $type === 'Artist'
            ? ['Artists', 'ArtistsDaily', 'ArtistsWeekly', 'ArtistsMonthly', 'ArtistsYearly']
            : ['Labels', 'LabelsDaily', 'LabelsWeekly', 'LabelsMonthly', 'LabelsYearly'];

        $idColumn = $type === 'Artist' ? 'ArtistId' : 'LabelId';

        $dates = [];
        foreach ($tables as $table) {
            $query = "SELECT Timestamp FROM $table WHERE $idColumn = :id AND Timestamp BETWEEN :startDate AND :endDate";
            try {
                $result = queryByDb($this->pdo, $query, [
                    'id' => $id,
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                ]);
                if ($result) {
                    $dates = array_merge($dates, array_column($result, 'Timestamp'));
                }
            } catch (\PDOException $e) {
                // Handle query failure (e.g., log the error, but continue with the next table)
            }
        }

        return $dates;
    }


    public function updateRanking($id, $type, $date)
    {
        $startDate = date('Y-m-d H:i:s', strtotime('-90 days', strtotime($date)));
        $endDate = $date;

        $tableMatched = null;
        $tables = $type === 'Artist'
            ? ['Artists', 'ArtistsDaily', 'ArtistsWeekly', 'ArtistsMonthly', 'ArtistsYearly']
            : ['Labels', 'LabelsDaily', 'LabelsWeekly', 'LabelsMonthly', 'LabelsYearly'];
        $idColumn = $type === 'Artist' ? 'ArtistId' : 'LabelId';

        foreach ($tables as $table) {
            $query = "SELECT * FROM $table WHERE $idColumn = :id AND Timestamp = :timestamp LIMIT 1";
            try {
                $result = queryByDb($this->pdo, $query, [
                    'id' => $id,
                    'timestamp' => $date,
                ]);
                if (!empty($result)) {
                    $tableMatched = $table;
                    break;
                }
            } catch (\PDOException $e) {
                // Handle query failure
            }
        }

        if (!$tableMatched) {
            return [
                'id' => $id,
                'type' => $type,
                'status' => 'Error',
                'message' => 'Matching table not found for update.',
            ];
        }

        $storedRanking = $this->getStoredRankingByIdAndType($id, $type, $startDate, $endDate);
        $calculatedRanking = $this->calculateRankingByIdAndType($id, $type, $startDate, $endDate);

        if (empty($calculatedRanking) || !is_array($calculatedRanking)) {
            return [
                'id' => $id,
                'type' => $type,
                'status' => 'Error',
                'message' => 'Calculated ranking data is invalid or empty.',
            ];
        }

        $comparison = $this->compareIndividualRanking($storedRanking, $calculatedRanking, $type);

        if ($comparison['status'] !== 'Correct') {
            $data = $comparison['calculatedValues'];
            unset($data['Change']);
            $recordId = $result[0]['Id'] ?? null;

            if (!$recordId) {
                return [
                    'id' => $id,
                    'type' => $type,
                    'status' => 'Error',
                    'message' => 'No ID found for updating the rankings.',
                ];
            }

            try {
                if (edit($tableMatched, $recordId, $data)) {
                    return [
                        'id' => $id,
                        'type' => $type,
                        'status' => 'Updated',
                        'message' => 'Ranking successfully updated.',
                    ];
                }

                return [
                    'id' => $id,
                    'type' => $type,
                    'status' => 'Error',
                    'message' => 'Failed to update the ranking.',
                ];
            } catch (PDOException $e) {
                throw new \PDOException('An error occurred while updating the rankings: ' . $e->getMessage(), $e->getCode(), $e);
            }
        }

        return [
            'id' => $id,
            'type' => $type,
            'status' => 'No Update Needed',
            'message' => 'Stored ranking already matches the calculated ranking.',
        ];
    }


    /**
     * Retrieve a stored ranking for a specific entity (Artist or Label) based on ID, type, and date range.
     *
     * @param string $id The ID of the artist or label.
     * @param string $type The type of ranking ('Artist' or 'Label').
     * @param string $startDate The start date of the ranking period.
     * @param string $endDate The end date of the ranking period.
     * @return array|null The stored ranking data or null if not found.
     */
    private function getStoredRankingByIdAndType($id, $type, $startDate, $endDate)
    {
        $tables = $type === 'Artist'
            ? ['Artists', 'ArtistsDaily', 'ArtistsWeekly', 'ArtistsMonthly', 'ArtistsYearly']
            : ['Labels', 'LabelsDaily', 'LabelsWeekly', 'LabelsMonthly', 'LabelsYearly'];
        $idColumn = $type === 'Artist' ? 'ArtistId' : 'LabelId';

        $queries = [];
        foreach ($tables as $table) {
            $queries[] = "
        SELECT *
        FROM $table
        WHERE $idColumn = :id
          AND Timestamp BETWEEN :startDate AND :endDate";
        }
        $query = implode(" UNION ALL ", $queries);

        // Execute the database query to fetch stored rankings
        try {
            $result = queryByDb($this->pdo, $query, [
                'id' => $id,
                'startDate' => $startDate,
                'endDate' => $endDate
            ]);
        } catch (\PDOException $e) {
            // Handle query failure (e.g., log the error)
            return null;
        }

        // Return the first result or null if no ranking exists
        return $result[0] ?? null;
    }
}