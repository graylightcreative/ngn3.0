<?php

/**
 * Discovery Engine - Affinity Endpoint
 * GET /api/v1/discovery/affinity
 * Returns user's affinity scores for artists and genres (authenticated)
 */

require_once __DIR__ . '/../../../config/bootstrap.php';

use NGN\Config;
use NGN\Lib\Discovery\AffinityService;
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
    $type = $_GET['type'] ?? 'artist';
    $limit = isset($_GET['limit']) ? max(1, min((int) $_GET['limit'], 100)) : 20;

    if (!in_array($type, ['artist', 'genre'])) {
        Response::error('Invalid type. Must be "artist" or "genre"', 400);
        exit;
    }

    $config = Config::getInstance();
    $affinityService = new AffinityService($config);

    $affinities = [];

    if ($type === 'artist') {
        $affinities = $affinityService->getUserTopArtistAffinities($userId, $limit);

        $enriched = array_map(function ($affinity) {
            return [
                'artist_id' => (int) $affinity['artist_id'],
                'artist_name' => $affinity['artist_name'] ?? 'Unknown',
                'affinity_score' => (float) $affinity['affinity_score'],
                'total_sparks' => (float) $affinity['total_sparks'],
                'total_engagements' => (int) $affinity['total_engagements'],
                'is_following' => (bool) $affinity['is_following'],
                'last_engagement_at' => $affinity['last_engagement_at'] ?? null
            ];
        }, $affinities);
    } else {
        $affinities = $affinityService->getUserGenreAffinities($userId);
        $affinities = array_slice($affinities, 0, $limit);

        $enriched = array_map(function ($affinity) {
            return [
                'genre_slug' => $affinity['genre_slug'],
                'genre_name' => $affinity['genre_name'],
                'affinity_score' => (float) $affinity['affinity_score'],
                'artist_count' => (int) $affinity['artist_count'],
                'total_engagements' => (int) $affinity['total_engagements'],
                'total_sparks' => (float) $affinity['total_sparks'],
                'last_engagement_at' => $affinity['last_engagement_at'] ?? null
            ];
        }, $affinities);
    }

    Response::success([
        'type' => $type,
        'affinities' => $enriched,
        'total' => count($enriched)
    ]);
} catch (Exception $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}
