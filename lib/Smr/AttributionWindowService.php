<?php

namespace NGN\Lib\Smr;

use PDO;
use Exception;

/**
 * Attribution Window Service (Chapter 24 - Rule 2)
 *
 * Manages 90-day bounty eligibility windows.
 * When artist experiences heat spike, window opens and closes 90 days later.
 */
class AttributionWindowService
{
    private PDO $pdo;
    private int $windowDays;

    public function __construct(PDO $pdo, int $windowDays = 90)
    {
        $this->pdo = $pdo;
        $this->windowDays = $_ENV['SMR_ATTRIBUTION_WINDOW_DAYS'] ?? $windowDays;
    }

    /**
     * Create a new attribution window when heat spike detected
     *
     * @param int $artistId Artist ID
     * @param int $heatSpikeId Heat spike ID that triggered this window
     * @param string $spikeDate Date spike detected (Y-m-d format)
     * @return int Window ID
     * @throws Exception
     */
    public function createWindow(int $artistId, int $heatSpikeId, string $spikeDate): int
    {
        try {
            $windowStart = $spikeDate;
            $windowEnd = (new \DateTime($spikeDate))
                ->modify("+{$this->windowDays} days")
                ->format('Y-m-d');

            $stmt = $this->pdo->prepare("
                INSERT INTO smr_attribution_windows (
                    artist_id, heat_spike_id,
                    window_start, window_end, status,
                    total_bounties_triggered, total_bounty_amount,
                    created_at
                ) VALUES (
                    :artist_id, :heat_spike_id,
                    :window_start, :window_end, 'active',
                    0, 0.00,
                    NOW()
                )
            ");

            $stmt->execute([
                ':artist_id' => $artistId,
                ':heat_spike_id' => $heatSpikeId,
                ':window_start' => $windowStart,
                ':window_end' => $windowEnd,
            ]);

            return (int)$this->pdo->lastInsertId();
        } catch (\Throwable $e) {
            throw new Exception("Error creating attribution window: {$e->getMessage()}");
        }
    }

    /**
     * Check if artist has an active attribution window
     *
     * @param int $artistId Artist ID
     * @return bool True if active window exists
     */
    public function hasActiveWindow(int $artistId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM smr_attribution_windows
                WHERE artist_id = ?
                AND status = 'active'
                AND window_end >= CURDATE()
                LIMIT 1
            ");
            $stmt->execute([$artistId]);
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get the most recent active attribution window for an artist
     *
     * @param int $artistId Artist ID
     * @return array|null Window data or null if no active window
     */
    public function getActiveWindow(int $artistId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM smr_attribution_windows
                WHERE artist_id = ?
                AND status = 'active'
                AND window_end >= CURDATE()
                ORDER BY window_start DESC
                LIMIT 1
            ");
            $stmt->execute([$artistId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Record bounty triggered for an attribution window
     *
     * @param int $windowId Window ID
     * @param float $bountyAmount Bounty amount in USD
     * @throws Exception
     */
    public function recordBountyTriggered(int $windowId, float $bountyAmount): void
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE smr_attribution_windows
                SET total_bounties_triggered = total_bounties_triggered + 1,
                    total_bounty_amount = total_bounty_amount + ?,
                    last_bounty_triggered_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$bountyAmount, $windowId]);
        } catch (\Throwable $e) {
            throw new Exception("Error recording bounty triggered: {$e->getMessage()}");
        }
    }

    /**
     * Expire old attribution windows (> 90 days old)
     * Run as daily cron job
     *
     * @return int Number of windows expired
     */
    public function expireOldWindows(): int
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE smr_attribution_windows
                SET status = 'expired', updated_at = NOW()
                WHERE status = 'active'
                AND window_end < CURDATE()
            ");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get all active windows for a specific artist
     *
     * @param int $artistId Artist ID
     * @return array[] Array of active windows
     */
    public function getArtistActiveWindows(int $artistId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM smr_attribution_windows
                WHERE artist_id = ?
                AND status = 'active'
                AND window_end >= CURDATE()
                ORDER BY window_start DESC
            ");
            $stmt->execute([$artistId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get window by ID
     *
     * @param int $windowId Window ID
     * @return array|null Window data
     */
    public function getWindow(int $windowId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM smr_attribution_windows WHERE id = ?");
            $stmt->execute([$windowId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Mark window as claimed (all bounties paid)
     *
     * @param int $windowId Window ID
     * @throws Exception
     */
    public function markWindowClaimed(int $windowId): void
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE smr_attribution_windows
                SET status = 'claimed', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$windowId]);
        } catch (\Throwable $e) {
            throw new Exception("Error marking window as claimed: {$e->getMessage()}");
        }
    }

    /**
     * Get statistics for provider dashboard
     *
     * @param int $providerUserId Provider user ID (Erik Baker)
     * @return array Statistics including active windows count
     */
    public function getProviderStatistics(int $providerUserId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(DISTINCT aw.id) as active_windows,
                    COUNT(DISTINCT aw.artist_id) as unique_artists,
                    COALESCE(SUM(aw.total_bounties_triggered), 0) as total_bounties_triggered,
                    COALESCE(SUM(aw.total_bounty_amount), 0) as total_bounty_amount
                FROM smr_attribution_windows aw
                WHERE aw.status = 'active'
                AND aw.window_end >= CURDATE()
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'active_windows' => (int)($result['active_windows'] ?? 0),
                'unique_artists' => (int)($result['unique_artists'] ?? 0),
                'total_bounties_triggered' => (int)($result['total_bounties_triggered'] ?? 0),
                'total_bounty_amount' => (float)($result['total_bounty_amount'] ?? 0.00),
            ];
        } catch (\Throwable $e) {
            return [
                'active_windows' => 0,
                'unique_artists' => 0,
                'total_bounties_triggered' => 0,
                'total_bounty_amount' => 0.00,
            ];
        }
    }
}
