<?php

$root = $_SERVER['DOCUMENT_ROOT'] . '/';

require $root.'lib/definitions/site-settings.php';
require $root.'lib/controllers/ResponseController.php';
require $root.'lib/controllers/NGNController.php';
require $root.'admin/lib/definitions/admin-settings.php';

$response = makeResponse();

// We search SMR Charts for labels
$smr = browse('smr_chart');
$allLabels = readMany('users','role_id',7);



$pool = [];
foreach ($smr as $entry) {
    $labels = handleSMRLabels($entry['Label']);
    foreach ($labels as $label) {
        $sanitizedLabel = sanitizeString($label);
        $labelInUsers = false;
        foreach ($allLabels as $userLabel) {
            if (sanitizeString(strtolower($userLabel['Title'])) == strtolower($sanitizedLabel)) {
                $labelInUsers = true;
                break;
            }
        }
        if (!$labelInUsers) $pool[] = $label;
    }
}


$checked = [];
$links = [];
foreach($pool as $key => $item)
{
    if(!in_array($item, $checked)){
        $links[] = "<a href='{$GLOBALS['Default']['Baseurl']}admin/lib/add-label.php?l={$item}' class='btn btn-sm btn-outline-primary d-block w-100'>Add {$item}</a>";
        $checked[] = $item;
    }
}
$response['message'] = 'Labels found';
$response['success'] = true;
$response['content'] = $links;
echo json_encode($response);