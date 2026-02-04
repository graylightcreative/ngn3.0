<?php

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

$root = $_SERVER['DOCUMENT_ROOT'] . '/';

require $root.'lib/definitions/site-settings.php';
require $root.'lib/controllers/ResponseController.php';
require $root.'lib/controllers/NGNController.php';
require $root.'lib/controllers/SMRController.php';
require $root.'admin/lib/definitions/admin-settings.php';

$config = new Config();
$spins_pdo = ConnectionFactory::named($config, 'SPINS2025');
$smr_pdo = ConnectionFactory::named($config, 'SMR2025');

$response = makeResponse();

// We need to search all spins for unadded artists


$pool = [];

$alreadyDone = [];
$items = '';
$spins = browseByDB($spins_pdo,'SpinData');
if(!$spins) killWithMessage('Could not load radio spins', $response);
foreach($spins as $spin) {
    $artists = handleRadioSpinsArtists($spin['Artist']);
    foreach($artists as $artist) {
        if(!in_array($artist, $alreadyDone)){
            $artist = preg_replace('/[^a-zA-Z0-9\s\']/', '', $artist); // Remove special characters but keep apostrophes
            $artist = preg_replace('/\s+/', ' ', $artist); // Replace multiple spaces or invisible line breaks with a single space
            $alreadyDone[] = $artist;
            $check = read('users', 'LOWER(title)', strtolower($artist));
            if(!$check) {
                $items .= "<div class='tiny'>(SPINS) {$artist} <button class='btn btn-sm tiny btn-outline-primary popup-toggle add-user' data-popup='addUserPopup' data-type='radio' data-title='{$artist}'>Add</button></div>";
            }
        }
    }
}

$smr = browseByDB($smr_pdo,'smr_chart');
foreach($smr as $entry){
    $artists = handleSMRArtists($entry['Artists']);
    foreach($artists as $artist) {
        if(!in_array($artist, $alreadyDone)){
            $alreadyDone[] = $artist;
            $check = read('users', 'LOWER(title)', strtolower($artist));
            if(!$check) {
                $items .= "<div class='tiny'>(SMR) {$artist} <button class='btn btn-sm tiny btn-outline-primary popup-toggle add-user' data-popup='addUserPopup' data-type='smr' data-title='{$artist}'>Add</button></div>";
            }
        }
    }
}




$pool = array_unique($pool);
$response['code'] = 200;
$response['success'] = true;
$response['message'] = 'Artists found';
$response['content'] = $items;
echo json_encode($response);