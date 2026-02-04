<?php

/**
 * Discovery Engine - Digest Preview Endpoint
 * GET /api/v1/discovery/digest-preview
 * Preview of this week's Niko's Discovery digest (authenticated)
 */

require_once __DIR__ . '/../../../config/bootstrap.php';

use NGN\Config;
use NGN\Lib\Discovery\NikoDiscoveryService;
use NGN\Lib\API\Response;
use NGN\Lib\API\Auth;
use PDO;
use NGN\Lib\Database\ConnectionFactory;

header('Content-Type: application/json');

try {
    // Verify authentication
    $auth = Auth::verify();
    if (!$auth) {
        Response::error('Unauthorized', 401);
        exit;
    }

    $userId = $auth['user_id'];
    $currentWeek = date('Y-W');

    $config = Config::getInstance();
    $readConnection = ConnectionFactory::read();

    // Check if digest already sent this week
    $stmt = $readConnection->prepare(
        'SELECT id, featured_artists, status FROM niko_discovery_digests
         WHERE user_id = ? AND digest_week = ?
         LIMIT 1'
    );
    $stmt->execute([$userId, $currentWeek]);
    $existingDigest = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingDigest && $existingDigest['status'] === 'sent') {
        $artists = json_decode($existingDigest['featured_artists'], true) ?: [];
        Response::success([
            'week' => $currentWeek,
            'subject' => 'Niko\'s Discovery: 3 Artists You\'ll Love This Week',
            'featured_artists' => array_map(function ($artist) {
                return [
                    'artist_id' => $artist['artist_id'] ?? 0,
                    'artist_name' => $artist['artist_name'] ?? 'Unknown',
                    'reason' => $artist['reason'] ?? 'Personalized for you',
                    'genre' => $artist['genre'] ?? 'Music',
                    'ngn_score' => $artist['ngn_score'] ?? 0,
                    'affinity_score' => $artist['score'] ?? $artist['affinity_score'] ?? 0
                ];
            }, $artists),
            'sent' => true
        ]);
        exit;
    }

    // Generate preview
    $nikoService = new NikoDiscoveryService($config);
    $digest = $nikoService->generateWeeklyDigest($userId);

    if (empty($digest)) {
        Response::success([
            'week' => $currentWeek,
            'featured_artists' => [],
            'sent' => false,
            'message' => 'No recommendations available'
        ]);
        exit;
    }

    Response::success([
        'week' => $currentWeek,
        'subject' => $digest['subject_line'],
        'featured_artists' => array_map(function ($artist) {
            return [
                'artist_id' => $artist['artist_id'] ?? 0,
                'artist_name' => $artist['artist_name'] ?? 'Unknown',
                'reason' => $artist['reason'] ?? 'Personalized for you',
                'genre' => $artist['genre'] ?? 'Music',
                'ngn_score' => $artist['ngn_score'] ?? 0,
                'affinity_score' => $artist['score'] ?? $artist['affinity_score'] ?? 0
            ];
        }, $digest['featured_artists']),
        'sent' => false
    ]);
} catch (Exception $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}
