<?php

/**
 * SMR Bounty Transactions API
 * Chapter 24 - Bounty Transaction History
 *
 * GET /api/v1/smr/provider/bounty-transactions
 * Query: ?status=settled&limit=50&offset=0&artist_id=null
 *
 * Returns: List of bounty transactions
 *
 * Authentication: Bearer token (provider must be Erik Baker)
 */

require_once __DIR__ . '/../../../../vendor/autoload.php';

header('Content-Type: application/json');

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

try {
    // Get database connection
    $config = new Config();
    $pdo = ConnectionFactory::read($config);

    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }

    // Verify authentication
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($authHeader) || strpos($authHeader, 'Bearer ') !== 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Missing authentication']);
        exit;
    }

    // Get provider user ID
    $providerUserId = (int)($_ENV['SMR_PROVIDER_USER_ID'] ?? 0);

    if ($providerUserId <= 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Provider not configured']);
        exit;
    }

    // Query parameters
    $status = $_GET['status'] ?? 'settled';
    $limit = min((int)($_GET['limit'] ?? 50), 100);
    $offset = (int)($_GET['offset'] ?? 0);
    $artistId = !empty($_GET['artist_id']) ? (int)$_GET['artist_id'] : null;

    // Validate status
    $validStatuses = ['pending', 'settled', 'disputed', 'reversed'];
    if (!in_array($status, $validStatuses)) {
        $status = 'settled';
    }

    // Build query
    $sql = "
        SELECT
            sbt.id,
            sbt.transaction_id,
            sbt.artist_id,
            a.name as artist_name,
            a.slug as artist_slug,
            sbt.platform_fee_gross,
            sbt.bounty_percentage,
            sbt.bounty_amount,
            sbt.ngn_operations_amount,
            sbt.geofence_matched,
            sbt.geofence_bonus_percentage,
            sbt.venue_id,
            v.name as venue_name,
            sbt.matched_zip_code,
            sbt.status,
            sbt.settled_at,
            sbt.created_at,
            rt.from_user_id,
            rt.entity_type,
            rt.entity_id
        FROM smr_bounty_transactions sbt
        LEFT JOIN cdm_artists a ON sbt.artist_id = a.id
        LEFT JOIN cdm_venues v ON sbt.venue_id = v.id
        LEFT JOIN cdm_royalty_transactions rt ON sbt.royalty_transaction_id = rt.id
        WHERE sbt.provider_user_id = ? AND sbt.status = ?
    ";

    $params = [$providerUserId, $status];

    if ($artistId) {
        $sql .= " AND sbt.artist_id = ?";
        $params[] = $artistId;
    }

    $sql .= " ORDER BY sbt.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $countSql = "
        SELECT COUNT(*) as total
        FROM smr_bounty_transactions
        WHERE provider_user_id = ? AND status = ?
    ";
    $countParams = [$providerUserId, $status];

    if ($artistId) {
        $countSql .= " AND artist_id = ?";
        $countParams[] = $artistId;
    }

    $stmt = $pdo->prepare($countSql);
    $stmt->execute($countParams);
    $countResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalCount = (int)$countResult['total'];

    // Format response
    $formattedTransactions = [];
    foreach ($transactions as $tx) {
        $formattedTransactions[] = [
            'id' => (int)$tx['id'],
            'transaction_id' => $tx['transaction_id'],
            'artist' => [
                'id' => (int)$tx['artist_id'],
                'name' => $tx['artist_name'],
                'slug' => $tx['artist_slug'],
            ],
            'source' => [
                'entity_type' => $tx['entity_type'],
                'entity_id' => (int)$tx['entity_id'],
                'from_user_id' => (int)$tx['from_user_id'],
            ],
            'amounts' => [
                'platform_fee_gross' => (float)$tx['platform_fee_gross'],
                'bounty_percentage' => (float)$tx['bounty_percentage'],
                'bounty_amount' => (float)$tx['bounty_amount'],
                'ngn_operations_amount' => (float)$tx['ngn_operations_amount'],
            ],
            'geofence' => [
                'matched' => (bool)$tx['geofence_matched'],
                'bonus_percentage' => (float)$tx['geofence_bonus_percentage'],
                'venue_name' => $tx['venue_name'],
                'matched_zip_code' => $tx['matched_zip_code'],
            ],
            'status' => $tx['status'],
            'settled_at' => $tx['settled_at'],
            'created_at' => $tx['created_at'],
        ];
    }

    // Calculate summary
    $summarySQL = "
        SELECT
            COUNT(*) as count,
            SUM(bounty_amount) as total_amount,
            AVG(bounty_amount) as average_amount,
            MIN(bounty_amount) as min_amount,
            MAX(bounty_amount) as max_amount,
            COUNT(CASE WHEN geofence_matched = TRUE THEN 1 END) as geofence_matches
        FROM smr_bounty_transactions
        WHERE provider_user_id = ? AND status = ?
    ";
    $summarParams = [$providerUserId, $status];

    if ($artistId) {
        $summarySQL .= " AND artist_id = ?";
        $summarParams[] = $artistId;
    }

    $stmt = $pdo->prepare($summarySQL);
    $stmt->execute($summarParams);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    $response = [
        'pagination' => [
            'total' => $totalCount,
            'limit' => $limit,
            'offset' => $offset,
            'returned' => count($formattedTransactions),
        ],
        'summary' => [
            'total_transactions' => (int)($summary['count'] ?? 0),
            'total_bounty_amount' => (float)($summary['total_amount'] ?? 0.00),
            'average_bounty' => (float)($summary['average_amount'] ?? 0.00),
            'min_bounty' => (float)($summary['min_amount'] ?? 0.00),
            'max_bounty' => (float)($summary['max_amount'] ?? 0.00),
            'geofence_matches' => (int)($summary['geofence_matches'] ?? 0),
        ],
        'transactions' => $formattedTransactions,
        'timestamp' => date('c'),
    ];

    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $_ENV['APP_ENV'] === 'development' ? $e->getMessage() : null,
    ]);
}
