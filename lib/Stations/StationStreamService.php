<?php

namespace NGN\Lib\Stations;

use PDO;
use NGN\Lib\Config;

/**
 * StationStreamService - Handle station radio streaming
 *
 * Features:
 * - Token generation for station streams (24-hour expiry)
 * - Session tracking with heartbeats
 * - Concurrent listener counts
 * - Now-playing metadata retrieval
 */
class StationStreamService
{
    private PDO $pdo;
    private Config $config;

    public function __construct(PDO $pdo, Config $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    /**
     * Generate streaming token for station
     *
     * @param int $stationId Station ID
     * @param int|null $userId User ID (optional for anonymous)
     * @param string|null $ipAddress IP address
     * @return array Token data with URL
     */
    public function generateStreamToken(
        int $stationId,
        ?int $userId,
        ?string $ipAddress
    ): array {
        // Fetch station info
        $stmt = $this->pdo->prepare("
            SELECT id, name, stream_url, stream_type
            FROM `ngn_2025`.`stations`
            WHERE id = :station_id
            LIMIT 1
        ");
        $stmt->execute([':station_id' => $stationId]);
        $station = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$station) {
            throw new \Exception("Station not found");
        }

        if (empty($station['stream_url'])) {
            throw new \Exception("Station has no stream URL configured");
        }

        // Generate secure token (256 bits)
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Insert token
        $stmt = $this->pdo->prepare("
            INSERT INTO `ngn_2025`.`stream_tokens` (
                station_id,
                user_id,
                token,
                token_type,
                ip_address,
                expires_at,
                created_at
            ) VALUES (
                :station_id,
                :user_id,
                :token,
                'station',
                :ip_address,
                :expires_at,
                NOW()
            )
        ");

        $stmt->execute([
            ':station_id' => $stationId,
            ':user_id' => $userId,
            ':token' => $token,
            ':ip_address' => $ipAddress,
            ':expires_at' => $expiresAt
        ]);

        return [
            'token' => $token,
            'url' => $station['stream_url'], // Direct to Shoutcast/Icecast
            'stream_type' => $station['stream_type'],
            'expires_at' => $expiresAt,
            'station' => [
                'id' => $station['id'],
                'name' => $station['name']
            ]
        ];
    }

    /**
     * Get now-playing metadata for station
     *
     * @param int $stationId Station ID
     * @return array|null Metadata or null if none
     */
    public function getNowPlaying(int $stationId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                current_track_id,
                current_track_title,
                current_track_artist,
                current_track_album,
                current_track_artwork,
                current_track_started_at,
                last_metadata_update
            FROM `ngn_2025`.`stations`
            WHERE id = :station_id
            LIMIT 1
        ");

        $stmt->execute([':station_id' => $stationId]);
        $metadata = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$metadata || !$metadata['current_track_title']) {
            return null;
        }

        return [
            'track_id' => $metadata['current_track_id'],
            'title' => $metadata['current_track_title'],
            'artist' => $metadata['current_track_artist'],
            'album' => $metadata['current_track_album'],
            'artwork' => $metadata['current_track_artwork'],
            'started_at' => $metadata['current_track_started_at'],
            'updated_at' => $metadata['last_metadata_update']
        ];
    }

    /**
     * Update now-playing metadata for station
     *
     * @param int $stationId Station ID
     * @param array $metadata Track metadata
     */
    public function updateNowPlaying(int $stationId, array $metadata): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE `ngn_2025`.`stations`
            SET
                current_track_id = :track_id,
                current_track_title = :title,
                current_track_artist = :artist,
                current_track_album = :album,
                current_track_artwork = :artwork,
                current_track_started_at = :started_at,
                last_metadata_update = NOW()
            WHERE id = :station_id
        ");

        $stmt->execute([
            ':station_id' => $stationId,
            ':track_id' => $metadata['track_id'] ?? null,
            ':title' => $metadata['title'] ?? null,
            ':artist' => $metadata['artist'] ?? null,
            ':album' => $metadata['album'] ?? null,
            ':artwork' => $metadata['artwork'] ?? null,
            ':started_at' => $metadata['started_at'] ?? date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Start listening session
     *
     * @param int $stationId Station ID
     * @param string $sessionId Client session ID
     * @param int|null $userId User ID (optional)
     * @param string|null $ipAddress IP address
     * @param string|null $userAgent User agent
     * @param string $territory ISO country code
     * @return int Session ID
     */
    public function startSession(
        int $stationId,
        string $sessionId,
        ?int $userId,
        ?string $ipAddress,
        ?string $userAgent,
        string $territory = 'XX'
    ): int {
        // Check for existing active session
        $stmt = $this->pdo->prepare("
            SELECT id FROM `ngn_2025`.`station_sessions`
            WHERE station_id = :station_id
              AND session_id = :session_id
              AND ended_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            ':station_id' => $stationId,
            ':session_id' => $sessionId
        ]);

        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update heartbeat
            $this->heartbeat($stationId, $sessionId);
            return $existing['id'];
        }

        // Create new session
        $stmt = $this->pdo->prepare("
            INSERT INTO `ngn_2025`.`station_sessions` (
                station_id,
                session_id,
                user_id,
                ip_address,
                user_agent,
                territory,
                started_at,
                last_heartbeat_at
            ) VALUES (
                :station_id,
                :session_id,
                :user_id,
                :ip_address,
                :user_agent,
                :territory,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            ':station_id' => $stationId,
            ':session_id' => $sessionId,
            ':user_id' => $userId,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent,
            ':territory' => $territory
        ]);

        $this->updateListenerCount($stationId);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Send heartbeat to keep session alive
     *
     * @param int $stationId Station ID
     * @param string $sessionId Session ID
     */
    public function heartbeat(int $stationId, string $sessionId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE `ngn_2025`.`station_sessions`
            SET last_heartbeat_at = NOW()
            WHERE station_id = :station_id
              AND session_id = :session_id
              AND ended_at IS NULL
        ");

        $stmt->execute([
            ':station_id' => $stationId,
            ':session_id' => $sessionId
        ]);
    }

    /**
     * End listening session
     *
     * @param int $stationId Station ID
     * @param string $sessionId Session ID
     */
    public function endSession(int $stationId, string $sessionId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE `ngn_2025`.`station_sessions`
            SET
                ended_at = NOW(),
                duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW())
            WHERE station_id = :station_id
              AND session_id = :session_id
              AND ended_at IS NULL
        ");

        $stmt->execute([
            ':station_id' => $stationId,
            ':session_id' => $sessionId
        ]);

        $this->updateListenerCount($stationId);
    }

    /**
     * Clean up stale sessions (no heartbeat in 5 minutes)
     *
     * @param int $stationId Station ID
     */
    public function cleanupStaleSessions(int $stationId): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE `ngn_2025`.`station_sessions`
            SET
                ended_at = last_heartbeat_at,
                duration_seconds = TIMESTAMPDIFF(SECOND, started_at, last_heartbeat_at)
            WHERE station_id = :station_id
              AND ended_at IS NULL
              AND last_heartbeat_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");

        $stmt->execute([':station_id' => $stationId]);
        $cleaned = $stmt->rowCount();

        if ($cleaned > 0) {
            $this->updateListenerCount($stationId);
        }

        return $cleaned;
    }

    /**
     * Update concurrent listener count
     *
     * @param int $stationId Station ID
     */
    private function updateListenerCount(int $stationId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE `ngn_2025`.`stations`
            SET listener_count = (
                SELECT COUNT(*)
                FROM `ngn_2025`.`station_sessions`
                WHERE station_id = :station_id
                  AND ended_at IS NULL
            )
            WHERE id = :station_id
        ");

        $stmt->execute([':station_id' => $stationId]);
    }

    /**
     * Get station info with listener count
     *
     * @param int $stationId Station ID
     * @return array Station data
     */
    public function getStationInfo(int $stationId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                name,
                slug,
                call_sign,
                format,
                region,
                image_url,
                listener_count,
                stream_type
            FROM `ngn_2025`.`stations`
            WHERE id = :station_id
            LIMIT 1
        ");

        $stmt->execute([':station_id' => $stationId]);
        $station = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$station) {
            throw new \Exception("Station not found");
        }

        return $station;
    }
}
