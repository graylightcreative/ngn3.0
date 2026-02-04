<?php

namespace NGN\Lib\Services\Media;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;
use RuntimeException;

/**
 * StreamingService - Secure audio streaming with signed URLs
 *
 * Provides:
 * - Token generation for secure streaming (15-minute expiry)
 * - Token validation and one-time use tracking
 * - IP binding for optional security
 * - Range request support for seeking
 * - Rights verification and disputed track blocking
 */
class StreamingService
{
    private PDO $db;
    private Config $config;
    private string $audioStorageRoot;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->db = ConnectionFactory::write($config);
        $this->audioStorageRoot = dirname(__DIR__, 3) . '/storage/audio';
    }

    /**
     * Generate a secure stream token for a track
     *
     * @param int $trackId Track ID to stream
     * @param ?int $userId User ID (nullable for guests)
     * @param ?string $ipAddress Client IP address for optional binding
     * @return array {token, url, expires_at, track} or throw on error
     * @throws RuntimeException if track not found or has no audio file
     */
    public function generateStreamToken(
        int $trackId,
        ?int $userId = null,
        ?string $ipAddress = null
    ): array {
        // 1. Verify track exists and has audio file
        $stmt = $this->db->prepare("
            SELECT id, slug, title, audio_path, audio_hash, audio_size_bytes
            FROM tracks
            WHERE id = :track_id
        ");
        $stmt->execute([':track_id' => $trackId]);
        $track = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$track) {
            throw new RuntimeException("Track not found: {$trackId}");
        }

        if (!$track['audio_path']) {
            throw new RuntimeException("Track has no audio file: {$trackId}");
        }

        // 2. Check rights_ledger status - block disputed tracks
        // (Assuming rights_ledger table exists with is_royalty_eligible flag)
        $stmt = $this->db->prepare("
            SELECT is_royalty_eligible
            FROM cdm_rights_ledger
            WHERE track_id = :track_id
            LIMIT 1
        ");
        $stmt->execute([':track_id' => $trackId]);
        $rights = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($rights && !$rights['is_royalty_eligible']) {
            throw new RuntimeException("Track rights disputed or unavailable: {$trackId}");
        }

        // 3. Generate SHA-256 token
        $tokenData = sprintf(
            '%d:%d:%s:%d:%s',
            $trackId,
            $userId ?? 0,
            $ipAddress ?? 'any',
            time(),
            bin2hex(random_bytes(16))
        );
        $token = hash('sha256', $tokenData);

        // 4. Calculate expiry (15 minutes)
        $expiresAt = new \DateTime();
        $expiresAt->add(new \DateInterval('PT15M'));

        // 5. Store token in stream_tokens table
        $stmt = $this->db->prepare("
            INSERT INTO stream_tokens (track_id, user_id, token, ip_address, created_at, expires_at)
            VALUES (:track_id, :user_id, :token, :ip_address, NOW(), :expires_at)
        ");
        $stmt->execute([
            ':track_id' => $trackId,
            ':user_id' => $userId,
            ':token' => $token,
            ':ip_address' => $ipAddress,
            ':expires_at' => $expiresAt->format('Y-m-d H:i:s')
        ]);

        // 6. Return token with signed URL
        $baseUrl = $this->config->get('app.base_url', 'https://nextgennoise.com');
        $streamUrl = "{$baseUrl}/api/v1/tracks/{$trackId}/stream?token={$token}";

        return [
            'token' => $token,
            'url' => $streamUrl,
            'expires_at' => $expiresAt->format('c'),
            'expires_in_seconds' => 900,
            'track' => [
                'id' => (int)$track['id'],
                'slug' => $track['slug'],
                'title' => $track['title'],
                'duration_bytes' => (int)$track['audio_size_bytes']
            ]
        ];
    }

    /**
     * Validate and stream a track using token
     *
     * @param int $trackId Track ID to stream
     * @param string $token Signed token from generateStreamToken()
     * @param string $ipAddress Client IP for binding verification
     * @param int $rangeStart Optional byte range start for seeking
     * @param int $rangeEnd Optional byte range end
     * @return void (outputs audio stream)
     * @throws RuntimeException on token validation failure
     */
    public function streamTrack(
        int $trackId,
        string $token,
        string $ipAddress,
        ?int $rangeStart = null,
        ?int $rangeEnd = null
    ): void {
        // 1. Validate token and expiry
        $stmt = $this->db->prepare("
            SELECT id, track_id, ip_address, expires_at, is_used
            FROM stream_tokens
            WHERE token = :token
            AND track_id = :track_id
        ");
        $stmt->execute([
            ':token' => $token,
            ':track_id' => $trackId
        ]);
        $tokenRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tokenRecord) {
            throw new RuntimeException('Invalid or expired token', 401);
        }

        // Check expiry
        if (strtotime($tokenRecord['expires_at']) < time()) {
            throw new RuntimeException('Token expired', 401);
        }

        // Check one-time use
        if ($tokenRecord['is_used']) {
            throw new RuntimeException('Token already used', 401);
        }

        // Check IP binding if specified
        if ($tokenRecord['ip_address'] && $tokenRecord['ip_address'] !== 'any') {
            if ($tokenRecord['ip_address'] !== $ipAddress) {
                throw new RuntimeException('IP mismatch', 403);
            }
        }

        // 2. Get track audio file
        $stmt = $this->db->prepare("
            SELECT audio_path, audio_size_bytes, audio_format
            FROM tracks
            WHERE id = :track_id
        ");
        $stmt->execute([':track_id' => $trackId]);
        $track = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$track || !$track['audio_path']) {
            throw new RuntimeException('Audio file not found', 404);
        }

        $filePath = $this->audioStorageRoot . '/' . ltrim($track['audio_path'], '/');

        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new RuntimeException('Audio file inaccessible', 404);
        }

        // 3. Mark token as used
        $stmt = $this->db->prepare("
            UPDATE stream_tokens
            SET is_used = TRUE, used_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':id' => $tokenRecord['id']]);

        // 4. Stream file with Range support
        $this->streamFileWithRange(
            $filePath,
            $track['audio_format'] ?? 'mp3',
            (int)$track['audio_size_bytes'],
            $rangeStart,
            $rangeEnd
        );

        // 5. Log playback event (first use)
        $this->logPlaybackEvent($trackId, $ipAddress);
    }

    /**
     * Stream file with HTTP Range request support
     *
     * @param string $filePath Full path to audio file
     * @param string $format Audio format (mp3, aac, flac)
     * @param int $fileSize File size in bytes
     * @param ?int $rangeStart Byte range start
     * @param ?int $rangeEnd Byte range end
     * @return void (outputs audio with proper headers)
     */
    private function streamFileWithRange(
        string $filePath,
        string $format,
        int $fileSize,
        ?int $rangeStart = null,
        ?int $rangeEnd = null
    ): void {
        // Determine MIME type
        $mimeTypes = [
            'mp3' => 'audio/mpeg',
            'aac' => 'audio/aac',
            'flac' => 'audio/flac',
            'wav' => 'audio/wav',
            'm4a' => 'audio/mp4'
        ];
        $mimeType = $mimeTypes[strtolower($format)] ?? 'audio/mpeg';

        // Set response headers
        header('Content-Type: ' . $mimeType);
        header('Accept-Ranges: bytes');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Content-Disposition: inline; filename="audio.' . $format . '"');

        // Handle Range requests
        if ($rangeStart !== null || $rangeEnd !== null) {
            $start = $rangeStart ?? 0;
            $end = $rangeEnd ?? ($fileSize - 1);

            if ($start > $end || $start >= $fileSize) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header('Content-Range: bytes */' . $fileSize);
                exit();
            }

            header('HTTP/1.1 206 Partial Content');
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
            header('Content-Length: ' . ($end - $start + 1));

            // Stream partial content
            $fp = fopen($filePath, 'rb');
            if ($fp) {
                fseek($fp, $start);
                echo fread($fp, $end - $start + 1);
                fclose($fp);
            }
        } else {
            // Stream entire file
            header('Content-Length: ' . $fileSize);
            readfile($filePath);
        }
    }

    /**
     * Log playback event for qualified listen tracking
     *
     * @param int $trackId Track being played
     * @param string $ipAddress Client IP address
     * @return void
     */
    private function logPlaybackEvent(int $trackId, string $ipAddress): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO playback_events (
                    track_id, started_at, session_id, source_type,
                    ip_address, user_agent
                ) VALUES (
                    :track_id, NOW(), :session_id, :source_type,
                    :ip_address, :user_agent
                )
            ");

            $sessionId = isset($_COOKIE['ngn_session_id'])
                ? $_COOKIE['ngn_session_id']
                : bin2hex(random_bytes(32));

            $stmt->execute([
                ':track_id' => $trackId,
                ':session_id' => $sessionId,
                ':source_type' => 'on_demand',
                ':ip_address' => $ipAddress,
                ':user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512)
            ]);
        } catch (\Throwable $e) {
            // Log error but don't break the stream
            error_log("Failed to log playback event: " . $e->getMessage());
        }
    }

    /**
     * Clean up expired tokens (should be called daily via cron)
     *
     * @return int Number of tokens deleted
     */
    public function deleteExpiredTokens(): int
    {
        $stmt = $this->db->prepare("
            DELETE FROM stream_tokens
            WHERE expires_at < NOW()
            AND is_used = TRUE
            LIMIT 10000
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Get token status for debugging
     *
     * @param string $token Token to check
     * @return array Token status or null
     */
    public function getTokenStatus(string $token): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id, track_id, user_id, token, ip_address,
                created_at, expires_at, used_at, is_used,
                NOW() as current_time
            FROM stream_tokens
            WHERE token = :token
        ");
        $stmt->execute([':token' => $token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
