<?php
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

$root = $_SERVER['DOCUMENT_ROOT'] . '/';
require $root . 'lib/definitions/site-settings.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Only GET method is allowed']);
    exit;
}

$id = $_GET['id'] ?? null;
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;
$limit = $_GET['limit'] ?? null;
$sid = $_GET['sid'] ?? null;
$aid = $_GET['aid'] ?? null;
$lid = $_GET['lid'] ?? null;

// Initialize database connection
$config = new Config();
$pdo = ConnectionFactory::named($config, 'SPINS2025');

$type = 'Slides';
if ($id) {
    // get spins by id
    $item = readByDB($pdo, 'SpinData', 'Id', $id);
    echo json_encode($item, true);
} else if ($sid) {
    // get spins by station id
    $items = readMany('SpinData', 'station_id', $sid);
    $filtered = array_filter($items, function ($item) use ($start, $end) {
        $timestamp = strtotime($item['Timestamp']);
        return (!$start || $timestamp >= strtotime($start)) && (!$end || $timestamp <= strtotime($end));
    });
    if ($limit) {
        $filtered = array_slice($filtered, 0, (int)$limit);
    }
    echo json_encode(array_values($filtered));
} else if ($aid) {
    $artist = read('users', 'id', $aid);
    $items = searchByDB($pdo, 'SpinData', 'Artist', $artist['Title']);
    if ($start || $end || $limit) {
        $filtered = array_filter($items, function ($item) use ($start, $end) {
            $timestamp = strtotime($item['Timestamp']);
            return (!$start || $timestamp >= strtotime($start)) && (!$end || $timestamp <= strtotime($end));
        });
        if ($limit) {
            $filtered = array_slice($filtered, 0, (int)$limit);
        }
        echo json_encode(array_values($filtered));
    } else {
        echo json_encode($items, true);
    }
} else if ($lid) {
    $label = read('users', 'id', $lid);
    $collection = [];
    if ($label) {
        $artists = readMany('users', 'label_id', $lid);
        if ($artists) {
            foreach ($artists as $artist) {
                $items = searchByDB($pdo, 'SpinData', 'Artist', $artist['Title']);
                if ($items) $collection = array_merge($collection, $items);
            }
        }
    }
    if ($start || $end || $limit) {
        $filtered = array_filter($collection, function ($item) use ($start, $end) {
            $timestamp = strtotime($item['Timestamp']);
            return (!$start || $timestamp >= strtotime($start)) && (!$end || $timestamp <= strtotime($end));
        });
        if ($limit) {
            $filtered = array_slice($filtered, 0, (int)$limit);
        }
        echo json_encode(array_values($filtered));
    } else {
        echo json_encode($collection, true);
    }
} else {
    $items = browseByDB($GLOBALS['spins_pdo'], 'SpinData');
    if ($start || $end || $limit) {
        $filtered = array_filter($items, function ($item) use ($start, $end) {
            $timestamp = strtotime($item['Timestamp']);
            return (!$start || $timestamp >= strtotime($start)) && (!$end || $timestamp <= strtotime($end));
        });
        if ($limit) {
            $filtered = array_slice($filtered, 0, (int)$limit);
        }
        echo json_encode(array_values($filtered));
    } else {
        echo json_encode($items, true);
    }
}



