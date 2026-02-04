<?php

/**
 * SMR Cemetery Admin API
 * Chapter 24 - Flagged Data Review (Admin Only)
 *
 * GET /api/v1/smr/admin/cemetery
 * Query: ?status=flagged&failure_type=null&limit=50&offset=0
 *
 * Returns: List of flagged/suspicious data
 *
 * Authentication: Admin token only
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

    // Verify admin authentication
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($authHeader) || strpos($authHeader, 'Bearer ') !== 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Missing authentication']);
        exit;
    }

    // TODO: Verify admin token (integration with auth system)
    // For now, check if admin header provided
    $adminSecret = $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '';
    if ($adminSecret !== ($_ENV['SMR_ADMIN_SECRET'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access denied']);
        exit;
    }

    // Query parameters
    $status = $_GET['status'] ?? 'flagged';
    $failureType = $_GET['failure_type'] ?? null;
    $limit = min((int)($_GET['limit'] ?? 50), 100);
    $offset = (int)($_GET['offset'] ?? 0);

    // Validate status
    $validStatuses = ['flagged', 'reviewed', 'resolved', 'false_positive'];
    if (!in_array($status, $validStatuses)) {
        $status = 'flagged';
    }

    // Build query
    $sql = "
        SELECT
            sc.id,
            sc.upload_id,
            sc.failure_type,
            sc.expected_hash,
            sc.actual_hash,
            sc.row_number,
            sc.artist_name,
            sc.detected_by,
            sc.detected_at,
            sc.bounties_blocked,
            sc.status,
            su.uploaded_at,
            su.uploaded_by,
            COUNT(*) OVER () as total_count
        FROM smr_cemetery sc
        LEFT JOIN smr_uploads su ON sc.upload_id = su.id
        WHERE sc.status = ?
    ";

    $params = [$status];

    if ($failureType) {
        $sql .= " AND sc.failure_type = ?";
        $params[] = $failureType;
    }

    $sql .= " ORDER BY sc.detected_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalCount = !empty($records) ? (int)$records[0]['total_count'] : 0;

    // Get statistics
    $statsSQL = "
        SELECT
            COUNT(*) as total_flagged,
            SUM(bounties_blocked) as total_bounties_blocked,
            COUNT(CASE WHEN status = 'flagged' THEN 1 END) as pending_review,
            COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved,
            COUNT(CASE WHEN status = 'false_positive' THEN 1 END) as false_positives,
            COUNT(CASE WHEN status = 'reviewed' THEN 1 END) as reviewed
        FROM smr_cemetery
    ";

    $stmt = $pdo->prepare($statsSQL);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Format response
    $formattedRecords = [];
    foreach ($records as $record) {
        $flaggedData = null;
        if (!empty($record['flagged_data'])) {
            try {
                $flaggedData = json_decode($record['flagged_data'], true);
            } catch (\Throwable $e) {
                // Keep as null if JSON decode fails
            }
        }

        $formattedRecords[] = [
            'id' => (int)$record['id'],
            'upload' => [
                'id' => $record['upload_id'] ? (int)$record['upload_id'] : null,
                'uploaded_at' => $record['uploaded_at'],
                'uploaded_by' => $record['uploaded_by'],
            ],
            'failure' => [
                'type' => $record['failure_type'],
                'expected_hash' => $record['expected_hash'],
                'actual_hash' => $record['actual_hash'],
            ],
            'data' => [
                'row_number' => $record['row_number'],
                'artist_name' => $record['artist_name'],
                'flagged_data_snapshot' => $flaggedData,
            ],
            'detection' => [
                'detected_by' => $record['detected_by'],
                'detected_at' => $record['detected_at'],
            ],
            'impact' => [
                'bounties_blocked' => (int)$record['bounties_blocked'],
            ],
            'status' => $record['status'],
        ];
    }

    // Get failure type distribution
    $distSQL = "
        SELECT failure_type, COUNT(*) as count
        FROM smr_cemetery
        WHERE status = 'flagged'
        GROUP BY failure_type
        ORDER BY count DESC
    ";
    $stmt = $pdo->prepare($distSQL);
    $stmt->execute();
    $distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = [
        'pagination' => [
            'total' => $totalCount,
            'limit' => $limit,
            'offset' => $offset,
            'returned' => count($formattedRecords),
        ],
        'statistics' => [
            'total_flagged' => (int)($stats['total_flagged'] ?? 0),
            'pending_review' => (int)($stats['pending_review'] ?? 0),
            'resolved' => (int)($stats['resolved'] ?? 0),
            'false_positives' => (int)($stats['false_positives'] ?? 0),
            'reviewed' => (int)($stats['reviewed'] ?? 0),
            'total_bounties_blocked' => (int)($stats['total_bounties_blocked'] ?? 0),
        ],
        'failure_distribution' => $distribution,
        'records' => $formattedRecords,
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
