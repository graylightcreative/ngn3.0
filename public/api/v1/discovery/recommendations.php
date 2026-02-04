<?php

/**
 * Discovery Engine - Recommendations Endpoint
 * GET /api/v1/discovery/recommendations
 * Returns personalized artist recommendations for authenticated user
 */

require_once __DIR__ . '/../../../config/bootstrap.php';

use NGN\Config;
use NGN\Lib\Discovery\DiscoveryEngineService;
use NGN\Lib\API\Response;
use NGN\Lib\API\Auth;

header('Content-Type: application/json');

try {
    // Verify authentication
    $auth = Auth::verify();
    if (!$auth) {
        Response::error('Unauthorized', 401);
        exit;
    }

    $userId = $auth['user_id'];
    $limit = isset($_GET['limit']) ? max(1, min((int) $_GET['limit'], 50)) : 10;
    $genre = $_GET['genre'] ?? null;
    $minScore = isset($_GET['min_score']) ? (float) $_GET['min_score'] : null;

    $config = Config::getInstance();
    $discoveryEngine = new DiscoveryEngineService($config);

    $recommendations = [];

    if ($genre) {
        $recommendations = $discoveryEngine->getGenreBasedRecommendations($userId, $genre, $limit);
    } else {
        $recommendations = $discoveryEngine->getRecommendedArtists($userId, $limit);
    }

    // Apply min score filter
    if ($minScore !== null) {
        $recommendations = array_filter($recommendations, function ($rec) use ($minScore) {
            return ($rec['score'] ?? $rec['affinity_score'] ?? 0) >= $minScore;
        });
    }

    // Enrich with artist details
    $enriched = [];
    foreach ($recommendations as $rec) {
        $enriched[] = [
            'artist_id' => $rec['artist_id'],
            'artist_name' => $rec['artist_name'] ?? 'Unknown',
            'genre' => $rec['genre'] ?? 'Music',
            'ngn_score' => $rec['ngn_score'] ?? 0,
            'affinity_score' => $rec['score'] ?? $rec['affinity_score'] ?? 0,
            'reason' => $rec['reason'] ?? 'Recommended for you',
            'is_emerging' => $rec['is_emerging'] ?? false
        ];
    }

    Response::success([
        'recommendations' => $enriched,
        'total' => count($enriched),
        'generated_at' => date('c'),
        'expires_at' => date('c', strtotime('+24 hours'))
    ]);
} catch (Exception $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}
