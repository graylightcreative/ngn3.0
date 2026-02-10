<?php

$root = $_SERVER['DOCUMENT_ROOT'] .'/';
require $root.'lib/definitions/site-settings.php';

$smrdata = browse('smr_chart');

foreach($smrdata as $smr) {
    $newDate = date('Y-m-d H:i:s', strtotime($smr['Date']));
    edit('smr_chart', $smr['Id'], ['Timestamp' => $newDate]);
}