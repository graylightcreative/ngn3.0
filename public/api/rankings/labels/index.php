<?php
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

// Ensure errors are reported for debugging (remove or adjust for production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$root = $_SERVER['DOCUMENT_ROOT'] . '/';
// Assume definitions and PDO connection ($GLOBALS['ngn_pdo']) are correctly set up
require_once $root . 'lib/definitions/site-settings.php';
// Assume DB functions exist (recommend modifying them to use PDO::FETCH_ASSOC)

header('Content-Type: application/json');

$results = []; // Initialize results array

// Initialize database connection
$config = new Config();
$pdo = ConnectionFactory::write($config);

try {
    // --- Parameter Handling ---
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null; // Prefer filter_input
    $interval = filter_input(INPUT_GET, 'i', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null; // Sanitize string input
    $top = filter_input(INPUT_GET, 'top', FILTER_VALIDATE_INT) ?: null;

    // --- Determine Table Name ---
    $tableBase = 'Labels'; // Consider defining table names in a config
    if ($interval && ctype_alpha($interval)) { // Basic validation for interval string
        $tableBase .= ucfirst(strtolower($interval)); // Consistent casing
    }
    // TODO: Add validation to ensure $tableBase corresponds to an actual, expected table name

    // --- Database Fetch ---
    // Recommend configuring readManyByDB and browseByDB to use PDO::FETCH_ASSOC
    // This will prevent the numeric keys from being included in the first place.
    if ($id) {
        // Fetch by specific LabelId
        $results = readManyByDB($pdo, $tableBase, 'LabelId', $id);
    } else {
        // Fetch all
        $results = browseByDB($pdo, $tableBase);

        // Sort and slice if 'top' parameter is present
        if ($top && $results) {
            // Ensure sortByColumnIndex handles potential errors and data types correctly
            // Assuming it returns the sorted array
            $results = sortByColumnIndex($results, 'Score', SORT_DESC, SORT_NUMERIC); // Add SORT_NUMERIC flag
            $results = array_slice($results, 0, $top); // Take only the top elements
        }
    }

    // --- Data Type Conversion and Cleanup ---
    if (is_array($results)) {
        foreach ($results as &$row) { // Use reference to modify in place
            // If fetch functions don't return assoc only, remove numeric keys:
            if (is_array($row)) {
                $row = array_filter($row, 'is_string', ARRAY_FILTER_USE_KEY); // Keep only string keys
            } else {
                // Handle unexpected row format if necessary
                continue; // Skip this row if it's not an array
            }


            // --- Explicit Type Casting for Swift Compatibility ---

            // Integers
            $row['Id'] = isset($row['Id']) ? intval($row['Id']) : null; // Use intval for safety
            $row['LabelId'] = isset($row['LabelId']) ? intval($row['LabelId']) : null;

            // Doubles (using floatval for consistency with PHP float type)
            $row['Score'] = isset($row['Score']) ? floatval($row['Score']) : null;
            $row['Artist_Boost_Score'] = isset($row['Artist_Boost_Score']) ? floatval($row['Artist_Boost_Score']) : null;
            $row['SMR_Score_Active'] = isset($row['SMR_Score_Active']) ? floatval($row['SMR_Score_Active']) : null;
            $row['SMR_Score_Historic'] = isset($row['SMR_Score_Historic']) ? floatval($row['SMR_Score_Historic']) : null;
            $row['Post_Mentions_Score_Active'] = isset($row['Post_Mentions_Score_Active']) ? floatval($row['Post_Mentions_Score_Active']) : null;
            $row['Post_Mentions_Score_Historic'] = isset($row['Post_Mentions_Score_Historic']) ? floatval($row['Post_Mentions_Score_Historic']) : null;
            $row['Views_Score_Active'] = isset($row['Views_Score_Active']) ? floatval($row['Views_Score_Active']) : null;
            $row['Views_Score_Historic'] = isset($row['Views_Score_Historic']) ? floatval($row['Views_Score_Historic']) : null;
            $row['Social_Score_Active'] = isset($row['Social_Score_Active']) ? floatval($row['Social_Score_Active']) : null;
            $row['Social_Score_Historic'] = isset($row['Social_Score_Historic']) ? floatval($row['Social_Score_Historic']) : null;
            $row['Releases_Score_Active'] = isset($row['Releases_Score_Active']) ? floatval($row['Releases_Score_Active']) : null; // Assuming float/double
            $row['Releases_Score_Historic'] = isset($row['Releases_Score_Historic']) ? floatval($row['Releases_Score_Historic']) : null; // Assuming float/double
            $row['Posts_Score_Active'] = isset($row['Posts_Score_Active']) ? floatval($row['Posts_Score_Active']) : null;
            $row['Posts_Score_Historic'] = isset($row['Posts_Score_Historic']) ? floatval($row['Posts_Score_Historic']) : null;
            $row['Videos_Score_Active'] = isset($row['Videos_Score_Active']) ? floatval($row['Videos_Score_Active']) : null;
            $row['Videos_Score_Historic'] = isset($row['Videos_Score_Historic']) ? floatval($row['Videos_Score_Historic']) : null;
            $row['Spins_Score_Active'] = isset($row['Spins_Score_Active']) ? floatval($row['Spins_Score_Active']) : null;
            $row['Spins_Score_Historic'] = isset($row['Spins_Score_Historic']) ? floatval($row['Spins_Score_Historic']) : null;
            $row['AgeScore'] = isset($row['AgeScore']) ? floatval($row['AgeScore']) : null;
            $row['ReputationScore'] = isset($row['ReputationScore']) ? floatval($row['ReputationScore']) : null;

            // Ensure Timestamp is a string (should already be from DB)
            $row['Timestamp'] = isset($row['Timestamp']) ? (string)$row['Timestamp'] : null;

            // Remove fields if they are null and the Swift model expects them to be absent?
            // Or ensure the Swift model uses optionals (e.g., Double?) for all these scores.
            // Current Swift model expects non-optional Doubles for most scores, so ensure API always provides a value (even 0.0)
            // or update Swift model to use optionals (Double?). Let's assume non-optional for now and default to 0.0 if null/missing.

            // Example: Ensure non-optional double fields default to 0.0 if null/missing
            $row['Score'] = $row['Score'] ?? 0.0;
            $row['Artist_Boost_Score'] = $row['Artist_Boost_Score'] ?? 0.0;
            // ... apply ?? 0.0 to all other fields declared as non-optional Double in Swift ...
            $row['SMR_Score_Active'] = $row['SMR_Score_Active'] ?? 0.0;
            $row['SMR_Score_Historic'] = $row['SMR_Score_Historic'] ?? 0.0;
            // ... etc ...
            $row['AgeScore'] = $row['AgeScore'] ?? 0.0;
            $row['ReputationScore'] = $row['ReputationScore'] ?? 0.0;


        }
        unset($row); // Unset the reference
    }

} catch (PDOException $e) {
    // Basic error handling
    http_response_code(500); // Internal Server Error
    $results = ['error' => 'Database error: ' . $e->getMessage()];
    // Log detailed error internally
} catch (Exception $e) {
    http_response_code(500);
    $results = ['error' => 'An unexpected error occurred: ' . $e->getMessage()];
    // Log detailed error internally
}


// --- JSON Encoding ---
// Use JSON_NUMERIC_CHECK to automatically convert numeric strings to numbers
// Use JSON_PRETTY_PRINT for easier debugging (optional for production)
echo json_encode($results, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);

?>
