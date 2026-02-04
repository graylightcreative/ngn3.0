<?php

require_once __DIR__ . '/../../../../lib/bootstrap.php'; // Bootstrap NGN environment

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$pdo = ConnectionFactory::write($config); // Use ngn_2025 connection

$title = 'Top 10 Requested Songs of the Week';

$stmt = $pdo->prepare("SELECT data, week_of FROM `ngn_2025`.`promos` WHERE title = :title");
$stmt->execute([':title' => $title]);
$top10 = $stmt->fetchAll(PDO::FETCH_ASSOC);
if(empty($top10)) die('Could not find promo item');


$currentWeek = '2025-03-29';
    foreach ($top10 as $promo) {
        $weekOf = date('Y-m-d', strtotime($promo['week_of']));
        if ($weekOf == $currentWeek) {
            $songs = json_decode($promo['data'], true);
            $table = '<table border="1" style="border-collapse: collapse; width: 100%;">';
            $table .= '<thead><tr><th></th><th>Artist</th><th>Song</th></tr></thead><tbody>';
            foreach ($songs as $song) {
                $artist = readArtistByTitle($pdo, $song['artist']);            if ($artist) {
                $table .= '<tr>';
                $table .= '<td>' . htmlspecialchars($song['position']) . '</td>';
                $table .= '<td><a href="'.$baseurl.'artists/'.$artist['Slug'].'">' . htmlspecialchars(ucwords(strtolower($artist['Title']))) . '</a></td>';
                $table .= '<td>' . htmlspecialchars(ucwords(strtolower($song['song']))) . '</td>';
                $table .= '</tr>';
            }
        }
        $table .= '</tbody></table>';
        echo $table;
    }
}

function readArtistByTitle(PDO $pdo, $title){
    if(!is_string($title)) return false;
    $stmt = $pdo->prepare("SELECT a.id, a.slug, a.name AS title FROM `ngn_2025`.`artists` a JOIN `ngn_2025`.`users` u ON a.user_id = u.id WHERE LOWER(a.name) LIKE :title LIMIT 1");
    $stmt->bindValue(':title', '%'.strtolower($title).'%', PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: false;
}

