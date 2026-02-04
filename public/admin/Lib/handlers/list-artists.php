<?php
// JSON bridge: list artists from NGN 1.0 DB for NGN 2.0 preview
$root = $_SERVER['DOCUMENT_ROOT'] . '/';
require_once $root . 'lib/bootstrap.php'; // Ensure NGN\Lib\Config and NGN\Lib\DB\ConnectionFactory are available

header('Content-Type: application/json');

$q = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 24;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$config = new NGN\Lib\Config();
$pdo = NGN\Lib\DB\ConnectionFactory::write($config); // Use ngn_2025 connection

$params = [];
$where = 'WHERE 1=1'; // Start with a generic WHERE clause
if ($q !== '') { $where .= ' AND name LIKE :q'; $params['q'] = '%'.$q.'%'; }

$sql = "SELECT id, slug, name, image_url FROM `ngn_2025`.`artists` {$where} ORDER BY name ASC LIMIT :lim OFFSET :off";

try {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue(':'.$k, $v, $type);
    }
    $stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([ 'items' => [], 'meta' => ['error' => 'db_error', 'message' => $e->getMessage()] ]);
    exit;
}

$items = array_map(function($r){
    return [
        'id' => (int)$r['id'],
        'slug' => (string)$r['slug'],
        'name' => (string)$r['name'],
        'image_url' => (string)$r['image_url'],
        'type' => 'artist',
    ];
}, $rows);

echo json_encode([ 'items' => $items, 'meta' => [ 'page' => $page, 'limit' => $limit ] ]);
