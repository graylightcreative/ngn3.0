<?php

namespace NGN\Lib\Legal;

use PDO;
use Exception;

class PlnPlaybackRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Inserts a PLN playback log record into the database.
     *
     * @param int $trackId The ID of the track being played.
     * @param int|null $stationId The ID of the station (nullable).
     * @param int|null $userId The ID of the listener (nullable).
     * @param int $durationSeconds The actual playback duration in seconds.
     * @param bool $isQualified Whether the playback constitutes a "Qualified Listen" (> 30s).
     * @param string $territory The ISO 3166-1 alpha-2 country code.
     * @param int $listeners The number of listeners (for broadcast).
     * @param string|null $ipAddress Listener's IP address.
     * @param string|null $userAgent Listener's User-Agent string.
     * @param string $playedAt The datetime when the playback occurred.
     * @return int The ID of the newly inserted log record.
     * @throws Exception If the insertion fails.
     */
    public function insertLog(
        int $trackId,
        ?int $stationId,
        ?int $userId,
        int $durationSeconds,
        bool $isQualified,
        string $territory,
        int $listeners,
        ?string $ipAddress,
        ?string $userAgent,
        string $playedAt // Passed as string to be inserted directly into DATETIME field
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO ngn_2025.pln_playback_log (
                track_id, station_id, user_id, played_at, duration_seconds,
                is_qualified, territory, listeners, ip_address, user_agent
            ) VALUES (
                :track_id, :station_id, :user_id, :played_at, :duration_seconds,
                :is_qualified, :territory, :listeners, :ip_address, :user_agent
            )
        ");

        $success = $stmt->execute([
            ':track_id' => $trackId,
            ':station_id' => $stationId,
            ':user_id' => $userId,
            ':played_at' => $playedAt,
            ':duration_seconds' => $durationSeconds,
            ':is_qualified' => (int)$isQualified, // Tinyint(1) expects 0 or 1
            ':territory' => $territory,
            ':listeners' => $listeners,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent
        ]);

        if (!$success) {
            throw new Exception("Failed to insert PLN playback log.");
        }

        return (int)$this->pdo->lastInsertId();
    }
}
