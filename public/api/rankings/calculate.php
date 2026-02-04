<?php

$root = '/www/wwwroot/nextgennoise/';
require $root . 'lib/definitions/site-settings.php';

$_POST = json_decode(file_get_contents('php://input'), true);
$response = new Response();
echo 'Starting hourly chart maintenance.'."...\n";
$rankings = new RankingCalculator();
$rankings->startDate = date('Y-m-d H:i:s', strtotime('-3 hours'));
$rankings->endDate = date('Y-m-d H:i:s');

//$analyze = $rankings->checkCache();
//if(!$analyze) die('Chart data is up-to-date.');
echo 'Chart data needs updated.'."...\n";
$rankings->startDate = date('Y-m-d H:i:s', strtotime('-90 days'));
echo 'Starting date is '.date('Y-m-d H:i:s', strtotime('-90 days'))."...\n";
// 1. LOAD
$artists = readMany('users','role_id',3);
$labels = readMany('users','role_id',7);
$artistResults = [];
$labelResults = [];


foreach($artists as $artist){
    $artistResults[] = $rankings->analyzeArtist($artist);
    echo 'Finished artist '.$artist['Title']."...\n";
}
sleep(2);
foreach($labels as $label){
    $labelResults[] = $rankings->analyzeLabel($label);
    echo 'Finished label '.$label['Title']."...\n";
}


//// If our results are empty we cannot proceed
if(empty($artistResults) OR empty($labelResults)){
    if(empty($artistResults)) killWithMessage('Artist results are empty', $response);
    if(empty($labelResults)) killWithMessage('Label results are empty', $response);
}
echo 'Results loaded.'."...\n";
sleep(2);
//// 2. BACKUP
if(!$rankings->backupCurrentRankings()) killWithMessage('Could not backup our rankings.', $response);
sleep(1);
echo 'Rankings backed up.'."...\n";
////// 3. CALCULATE
if(!$rankings->updateRankings($artistResults,$labelResults)) killWithMessage('Could not update rankings', $response);
sleep(1);
echo 'Rankings updated successfully.'."\n";
