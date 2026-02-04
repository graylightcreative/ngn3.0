<?php

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

$root = $_SERVER['DOCUMENT_ROOT'] . '/';

require $root.'lib/definitions/site-settings.php';
require $root.'lib/controllers/ResponseController.php';
require $root.'admin/lib/definitions/admin-settings.php';

$config = new Config();
$smr_pdo = ConnectionFactory::named($config, 'SMR2025');

//$_POST = json_decode(file_get_contents("php://input"), true);

$startDate = !isset($_REQUEST['start_date']) ? convertDate('Today - 30 days') : convertDate($_REQUEST['start_date']);
$endDate = !isset($_REQUEST['end_date']) ? convertDate('Today') : convertDate($_REQUEST['end_date']);

$response = makeResponse();

function getMoversAndShakers($startDate, $endDate, $limit = 5) {
    $sql = "
        SELECT 
            Artists, 
            Song, 
            Label, 
            MIN(LWP) AS BestLWP,
            MIN(TWP) AS BestTWP,
            SUM(LWP - TWP) AS TotalPositionChange,
            SUM(TWS) AS TotalTWS,
            SUM(LWS) AS TotalLWS,
            SUM(TWS) - SUM(LWS) AS SpinsChange,
            MAX(WOC) AS MaxWOC,
            MIN(Peak) AS Peak
        FROM 
            ChartData
        WHERE 
            `Timestamp` BETWEEN ? AND ? 
        GROUP BY 
            Artists, Song, Label
        ORDER BY 
            TotalPositionChange DESC
        LIMIT " . (int)$limit;

    $stmt = $smr_pdo->prepare($sql);
    $stmt->execute([$startDate, $endDate]);

    $moversAndShakers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($moversAndShakers) {
        return $moversAndShakers;
    } else {
        return [];
    }
}

function convertDate($date){
    return date('Y-m-d H:i:s', strtotime($date));
}


$moversAndShakers = getMoversAndShakers($startDate, $endDate);
echo json_encode($moversAndShakers);