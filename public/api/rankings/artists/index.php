<?php
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

$root = $_SERVER['DOCUMENT_ROOT'] . '/';
require $root . 'lib/definitions/site-settings.php';
header('Content-Type: application/json');

// Initialize database connection
$config = new Config();
$pdo = ConnectionFactory::write($config);

$id = $_REQUEST['id'] ?? null;
$interval = $_REQUEST['i'] ?? null;
$top = $_REQUEST['top'] ?? null; // top will be a # of top artists to show


// do we have an interval?
$tableBase = 'Artists';
if ($interval) {
    $tableBase .= ucfirst($interval);
}

// do we have an artist
if ($id) {
    // we get artist and interval
    $results = readManyByDB($pdo, $tableBase, 'ArtistId', $id);
} else {
    // we get just interval all artists
    $results = browseByDB($pdo, $tableBase);
    // Unset any index that is an integer

    foreach ($results as &$row) {
        foreach ($row as $key => $value) {
            if (is_int($key)) {
                unset($row[$key]);
            }
        }
    }
    
    if($top){
        // we are asking for the top (x) artists
        // we need to sort results by Score
        $collection = [];
        $results = sortByColumnIndex($results,'Score', SORT_DESC);
        foreach($results as $row){
            $collection[] = $row;
            if(count($collection) >= $top){
                break;
            }
        }
        $results = $collection;
        
    }
}

foreach ($results as &$row) {
    $row['Id'] = (int) $row['Id'];
    $row['ArtistId'] = (int) $row['ArtistId'];
    $row['Score'] = (double) $row['Score'];
    $row['Label_Boost_Score'] = (double) $row['Label_Boost_Score'];
    $row['Timestamp'] = (string) $row['Timestamp']; // Ensure Timestamp is a string

    // Handle optional Double fields
    $row['SMR_Score_Active'] = (isset($row['SMR_Score_Active'])) ? (double) $row['SMR_Score_Active'] : null;
    $row['SMR_Score_Historic'] = (isset($row['SMR_Score_Historic'])) ? (double) $row['SMR_Score_Historic'] : null;
    $row['Post_Mentions_Score_Active'] = (isset($row['Post_Mentions_Score_Active'])) ? (double) $row['Post_Mentions_Score_Active'] : null;
    $row['Post_Mentions_Score_Historic'] = (isset($row['Post_Mentions_Score_Historic'])) ? (double) $row['Post_Mentions_Score_Historic'] : null;
    $row['Views_Score_Active'] = (isset($row['Views_Score_Active'])) ? (double) $row['Views_Score_Active'] : null;
    $row['Views_Score_Historic'] = (isset($row['Views_Score_Historic'])) ? (double) $row['Views_Score_Historic'] : null;
    $row['Social_Score_Active'] = (isset($row['Social_Score_Active'])) ? (double) $row['Social_Score_Active'] : null;
    $row['Social_Score_Historic'] = (isset($row['Social_Score_Historic'])) ? (double) $row['Social_Score_Historic'] : null;
    $row['Videos_Score_Active'] = (isset($row['Videos_Score_Active'])) ? (double) $row['Videos_Score_Active'] : null;
    $row['Videos_Score_Historic'] = (isset($row['Videos_Score_Historic'])) ? (double) $row['Videos_Score_Historic'] : null;
    $row['Spins_Score_Active'] = (isset($row['Spins_Score_Active'])) ? (double) $row['Spins_Score_Active'] : null;
    $row['Spins_Score_Historic'] = (isset($row['Spins_Score_Historic'])) ? (double) $row['Spins_Score_Historic'] : null;
    $row['Posts_Score_Active'] = (isset($row['Posts_Score_Active'])) ? (double) $row['Posts_Score_Active'] : null;
    $row['Posts_Score_Historic'] = (isset($row['Posts_Score_Historic'])) ? (double) $row['Posts_Score_Historic'] : null;

    // Ensure integer types for release scores
    $row['Releases_Score_Active'] = (int) $row['Releases_Score_Active'];
    $row['Releases_Score_Historic'] = (int) $row['Releases_Score_Historic'];
}


echo json_encode($results); // Remove the 'true' argument
?>