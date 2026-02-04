<?php

/**
 * SMR Attribution Windows API
 * Chapter 24 - Active Attribution Windows List
 *
 * GET /api/v1/smr/provider/attribution-windows
 * Query: ?status=active&limit=50&offset=0
 *
 * Returns: List of active attribution windows with bounty data
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
    $status = $_GET['status'] ?? 'active';
    $limit = min((int)($_GET['limit'] ?? 50), 100);
    $offset = (int)($_GET['offset'] ?? 0);

    // Validate status
    $validStatuses = ['active', 'expired', 'claimed'];
    if (!in_array($status, $validStatuses)) {
        $status = 'active';
    }

    // Query windows
    $sql = "
        SELECT
            aw.id,
            aw.artist_id,
            a.name as artist_name,
            a.slug as artist_slug,
            aw.window_start,
            aw.window_end,
            DATEDIFF(aw.window_end, CURDATE()) as days_remaining,
            aw.status,
            aw.total_bounties_triggered,
            aw.total_bounty_amount,
            aw.last_bounty_triggered_at,
            aw.created_at,
            sh.spike_multiplier,
            sh.stations_count,
            sh.baseline_spins,
            sh.spike_spins
        FROM smr_attribution_windows aw
        LEFT JOIN cdm_artists a ON aw.artist_id = a.id
        LEFT JOIN smr_heat_spikes sh ON aw.heat_spike_id = sh.id
        WHERE aw.status = ?
    ";

    $params = [$status];

    // Filter by active/current only
    if ($status === 'active') {
        $sql .= " AND aw.window_end >= CURDATE()";
    }

    $sql .= " ORDER BY aw.window_start DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $windows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $countSql = "
        SELECT COUNT(*) as total
        FROM smr_attribution_windows aw
        WHERE aw.status = ?
    ";
    $countParams = [$status];

    if ($status === 'active') {
        $countSql .= " AND aw.window_end >= CURDATE()";
    }

    $stmt = $pdo->prepare($countSql);
    $stmt->execute($countParams);
    $countResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalCount = (int)$countResult['total'];

    // Format response
    $formattedWindows = [];
    foreach ($windows as $window) {
        $formattedWindows[] = [
            'id' => (int)$window['id'],
            'artist_id' => (int)$window['artist_id'],
            'artist_name' => $window['artist_name'],
            'artist_slug' => $window['artist_slug'],
            'window_start' => $window['window_start'],
            'window_end' => $window['window_end'],
            'days_remaining' => (int)$window['days_remaining'],
            'status' => $window['status'],
            'bounty_stats' => [
                'bounties_triggered' => (int)$window['total_bounties_triggered'],
                'total_bounty_amount' => (float)$window['total_bounty_amount'],
                'last_bounty_triggered_at' => $window['last_bounty_triggered_at'],
            ],
            'heat_spike' => [
                'spike_multiplier' => (float)$window['spike_multiplier'],
                'stations_count' => (int)$window['stations_count'],
                'baseline_spins' => (int)$window['baseline_spins'],
                'spike_spins' => (int)$window['spike_spins'],
            ],
            'created_at' => $window['created_at'],
        ];
    }

    $response = [
        'pagination' => [
            'total' => $totalCount,
            'limit' => $limit,
            'offset' => $offset,
            'returned' => count($formattedWindows),
        ],
        'windows' => $formattedWindows,
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
