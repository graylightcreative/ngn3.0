<?php
namespace NGN\Lib\Services\Media;

/**
 * Venue PPV Livestream Service (Node 48 Integration)
 * Handles secure RTMP ingestion orchestration and token-gated HLS playback.
 * Bible Ref: Chapter 48 (Venues & Tours)
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class LiveStreamService
{
    private $config;
    private $pdo;
    private $streamSecret;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdo = ConnectionFactory::read($config);
        $this->streamSecret = getenv('LIVESTREAM_SECRET_KEY') ?: 'FOUNDRY_STREAM_SECURE_2026';
    }

    /**
     * Generate or fetch an RTMP Stream Key for a venue/event
     */
    public function getStreamKey(string $eventId): string
    {
        // Simple deterministic key based on event and secret
        return hash_hmac('sha256', $eventId, $this->streamSecret);
    }

    /**
     * Get the RTMP Ingestion URL for hardware encoders
     */
    public function getIngestUrl(string $eventId): string
    {
        $baseUrl = getenv('LIVESTREAM_INGEST_URL') ?: 'rtmp://shredder.nextgennoise.com/live';
        $key = $this->getStreamKey($eventId);
        return "{$baseUrl}/{$key}";
    }

    /**
     * Generate a token-gated HLS playback URL
     * 
     * @param string $eventId
     * @param int $userId
     * @return array{url: string, token: string}
     */
    public function getPlaybackDetails(string $eventId, int $userId): array
    {
        // 1. Verify PPV Access (Ticket check)
        if (!$this->hasPPVAccess($eventId, $userId)) {
            throw new \Exception("Unauthorized: No valid PPV ticket found for this event.");
        }

        // 2. Generate signed HLS token
        $expires = time() + 3600; // 1 hour window
        $token = hash_hmac('sha256', "{$eventId}:{$userId}:{$expires}", $this->streamSecret);
        
        $baseUrl = getenv('LIVESTREAM_HLS_URL') ?: 'https://shredder.nextgennoise.com/hls';
        
        return [
            'url' => "{$baseUrl}/{$eventId}.m3u8",
            'token' => $token,
            'expires' => $expires,
            'viewer_count' => $this->getViewerCount($eventId)
        ];
    }

    /**
     * Check if a user has a valid PPV ticket for an event
     */
    private function hasPPVAccess(string $eventId, int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT id FROM tickets 
            WHERE event_id = ? AND user_id = ? 
            AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$eventId, $userId]);
        return (bool)$stmt->fetchColumn();
    }

    private function getViewerCount(string $eventId): int
    {
        // Simulated real-time count
        return rand(100, 500);
    }
}
