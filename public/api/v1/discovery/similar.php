<?php

/**
 * Discovery Engine - Similar Artists Endpoint
 * GET /api/v1/discovery/similar/{artist_id}
 * Returns artists similar to a given artist
 */

require_once __DIR__ . '/../../../config/bootstrap.php';

use NGN\Config;
use NGN\Lib\Discovery\SimilarityService;
use NGN\Lib\API\Response;

header('Content-Type: application/json');

try {
    // Extract artist ID from URL
    $pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
    $artistId = end($pathParts);

    if (!$artistId || !is_numeric($artistId)) {
        Response::error('Invalid artist ID', 400);
        exit;
    }

    $artistId = (int) $artistId;
    $limit = isset($_GET['limit']) ? max(1, min((int) $_GET['limit'], 50)) : 10;

    $config = Config::getInstance();
    $similarityService = new SimilarityService($config);

    $similarArtists = $similarityService->getSimilarArtists($artistId, $limit);

    // Enrich with details
    $enriched = [];
    foreach ($similarArtists as $artist) {
        $enriched[] = [
            'artist_id' => (int) $artist['similar_artist_id'],
            'artist_name' => $artist['artist_name'] ?? 'Unknown',
            'similarity_score' => (float) $artist['similarity_score'],
            'shared_fans' => (int) ($artist['shared_fans_count'] ?? 0),
            'genre_match' => (float) $artist['genre_match_score'] ?? 0,
            'fanbase_overlap' => (float) $artist['fanbase_overlap_score'] ?? 0,
            'engagement_pattern' => (float) $artist['engagement_pattern_score'] ?? 0
        ];
    }

    Response::success([
        'artist_id' => $artistId,
        'similar_artists' => $enriched,
        'total' => count($enriched)
    ]);
} catch (Exception $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}
