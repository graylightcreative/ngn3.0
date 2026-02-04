<?php

namespace NGN\Lib\Legal;

use Exception;

class PlnPlaybackService
{
    private PlnPlaybackRepository $plnPlaybackRepository;

    // Define "Qualified Listen" threshold as per Bible's LGL-001 requirements (30 seconds)
    private const QUALIFIED_LISTEN_THRESHOLD_SECONDS = 30;

    public function __construct(PlnPlaybackRepository $plnPlaybackRepository)
    {
        $this->plnPlaybackRepository = $plnPlaybackRepository;
    }

    /**
     * Logs a PLN playback event.
     *
     * @param int $trackId The ID of the track being played.
     * @param int|null $stationId The ID of the station (nullable for on-demand content).
     * @param int|null $userId The ID of the listener (nullable if unauthenticated).
     * @param int $durationSeconds The actual playback duration in seconds.
     * @param string $territory The ISO 3166-1 alpha-2 country code.
     * @param int $listeners The number of listeners (default 1 for individual playback).
     * @param string|null $ipAddress Listener's IP address for audit.
     * @param string|null $userAgent Listener's User-Agent string.
     * @param string $playedAt The datetime when the playback occurred (e.g., 'YYYY-MM-DD HH:MM:SS').
     * @return int The ID of the newly inserted log record.
     * @throws Exception If logging fails.
     */
    public function logPlayback(
        int $trackId,
        ?int $stationId,
        ?int $userId,
        int $durationSeconds,
        string $territory,
        int $listeners = 1,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $playedAt = null
    ): int {
        if ($trackId <= 0) {
            throw new \InvalidArgumentException("Track ID must be a positive integer.");
        }
        if ($durationSeconds < 0) {
            throw new \InvalidArgumentException("Duration in seconds cannot be negative.");
        }
        if (empty($territory)) {
            throw new \InvalidArgumentException("Territory is required.");
        }

        // Determine if it's a "Qualified Listen"
        $isQualified = ($durationSeconds >= self::QUALIFIED_LISTEN_THRESHOLD_SECONDS);

        // Use current time if playedAt is not provided
        $playedAt = $playedAt ?? date('Y-m-d H:i:s');

        try {
            return $this->plnPlaybackRepository->insertLog(
                $trackId,
                $stationId,
                $userId,
                $durationSeconds,
                $isQualified,
                $territory,
                $listeners,
                $ipAddress,
                $userAgent,
                $playedAt
            );
        } catch (Exception $e) {
            error_log("Error in PlnPlaybackService::logPlayback: " . $e->getMessage());
            throw new Exception("Failed to log PLN playback: " . $e->getMessage());
        }
    }
}
