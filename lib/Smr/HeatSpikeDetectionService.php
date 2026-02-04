<?php

namespace NGN\Lib\Smr;

use PDO;
use Exception;

/**
 * Heat Spike Detection Service (Chapter 24 - Rule 2)
 *
 * Detects momentum events when artist experiences 2x baseline spike in SMR spins.
 * Triggers 90-day attribution window for bounty eligibility.
 */
class HeatSpikeDetectionService
{
    private PDO $pdo;
    private float $spikeThreshold;

    public function __construct(PDO $pdo, float $spikeThreshold = 2.00)
    {
        $this->pdo = $pdo;
        $this->spikeThreshold = $_ENV['SMR_HEAT_SPIKE_THRESHOLD'] ?? $spikeThreshold;
    }

    /**
     * Detect heat spikes from a recent SMR upload
     * Analyzes each artist in the upload for 2x baseline momentum
     *
     * @param int $uploadId SMR upload ID
     * @return array[] Array of detected spikes with spike IDs
     * @throws Exception
     */
    public function detectSpikesFromUpload(int $uploadId): array
    {
        try {
            // Get upload metadata
            $stmt = $this->pdo->prepare("SELECT id, uploaded_at FROM smr_uploads WHERE id = ?");
            $stmt->execute([$uploadId]);
            $upload = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$upload) {
                throw new Exception("Upload {$uploadId} not found");
            }

            // Get all unique artists from this upload
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT artist_id
                FROM smr_staging
                WHERE upload_id = ? AND artist_id IS NOT NULL
            ");
            $stmt->execute([$uploadId]);
            $artists = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $detectedSpikes = [];

            foreach ($artists as $artistId) {
                $spikeData = $this->detectSpikeForArtist(
                    (int)$artistId,
                    $uploadId,
                    $upload['uploaded_at']
                );

                if ($spikeData) {
                    $spikeId = $this->recordHeatSpike($spikeData);
                    $detectedSpikes[] = array_merge($spikeData, ['spike_id' => $spikeId]);
                }
            }

            return $detectedSpikes;
        } catch (\Throwable $e) {
            throw new Exception("Error detecting spikes: {$e->getMessage()}");
        }
    }

    /**
     * Detect spike for a single artist
     *
     * @param int $artistId Artist ID
     * @param int $uploadId Upload ID
     * @param string $uploadDate Date of upload
     * @return array|null Spike data if spike detected, null otherwise
     */
    private function detectSpikeForArtist(int $artistId, int $uploadId, string $uploadDate): ?array
    {
        $uploadDateObj = new \DateTime($uploadDate);
        $spikeStartDate = (clone $uploadDateObj)->format('Y-m-d');

        // Calculate 7-day baseline (spins from week before upload)
        $baselineEndDate = (clone $uploadDateObj)->modify('-8 days')->format('Y-m-d');
        $baselineStartDate = (clone $uploadDateObj)->modify('-15 days')->format('Y-m-d');

        $baseline = $this->calculateBaseline($artistId, $baselineStartDate, $baselineEndDate);

        if ($baseline <= 0) {
            // No baseline data, can't determine spike
            return null;
        }

        // Calculate spike window (upload date + 7 days)
        $spikeEndDate = (clone $uploadDateObj)->modify('+7 days')->format('Y-m-d');

        $spikeSpins = $this->getSpinsInWindow($artistId, $spikeStartDate, $spikeEndDate);

        // Check if spike threshold met (2x baseline)
        $multiplier = $baseline > 0 ? round($spikeSpins / $baseline, 2) : 0;

        if ($multiplier < $this->spikeThreshold) {
            // Threshold not met
            return null;
        }

        // Extract station coverage zip codes
        $zipCodes = $this->extractZipCodes($artistId, $spikeStartDate, $spikeEndDate);
        $stationsCount = count(array_unique($zipCodes));

        return [
            'artist_id' => $artistId,
            'upload_id' => $uploadId,
            'detection_date' => $uploadDate,
            'baseline_spins' => $baseline,
            'spike_spins' => $spikeSpins,
            'spike_multiplier' => $multiplier,
            'threshold_met' => true,
            'spike_start_date' => $spikeStartDate,
            'spike_end_date' => $spikeEndDate,
            'stations_count' => $stationsCount,
            'zip_codes' => json_encode($zipCodes),
        ];
    }

    /**
     * Calculate 7-day baseline spins before spike window
     *
     * @param int $artistId Artist ID
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return int Average spins during baseline period
     */
    private function calculateBaseline(int $artistId, string $startDate, string $endDate): int
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(spins), 0) as total_spins
                FROM cdm_chart_entries
                WHERE artist_id = ?
                AND chart_date >= ?
                AND chart_date <= ?
            ");
            $stmt->execute([$artistId, $startDate, $endDate]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int)($result['total_spins'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get total spins during spike window
     *
     * @param int $artistId Artist ID
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return int Total spins during window
     */
    private function getSpinsInWindow(int $artistId, string $startDate, string $endDate): int
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(spins), 0) as total_spins
                FROM cdm_chart_entries
                WHERE artist_id = ?
                AND chart_date >= ?
                AND chart_date <= ?
            ");
            $stmt->execute([$artistId, $startDate, $endDate]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int)($result['total_spins'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Extract station zip codes from spike window
     *
     * @param int $artistId Artist ID
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array List of unique zip codes
     */
    private function extractZipCodes(int $artistId, string $startDate, string $endDate): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT s.zip_code
                FROM cdm_chart_entries ce
                JOIN cdm_stations s ON ce.station_id = s.id
                WHERE ce.artist_id = ?
                AND ce.chart_date >= ?
                AND ce.chart_date <= ?
                AND s.zip_code IS NOT NULL
            ");
            $stmt->execute([$artistId, $startDate, $endDate]);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);

            return array_filter($results, fn($z) => !empty($z));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Record heat spike to database
     *
     * @param array $spikeData Spike data array
     * @return int Heat spike ID
     * @throws Exception
     */
    public function recordHeatSpike(array $spikeData): int
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO smr_heat_spikes (
                    artist_id, upload_id, detection_date,
                    baseline_spins, spike_spins, spike_multiplier,
                    threshold_met, spike_start_date, spike_end_date,
                    stations_count, zip_codes, created_at
                ) VALUES (
                    :artist_id, :upload_id, :detection_date,
                    :baseline_spins, :spike_spins, :spike_multiplier,
                    :threshold_met, :spike_start_date, :spike_end_date,
                    :stations_count, :zip_codes, NOW()
                )
            ");

            $stmt->execute([
                ':artist_id' => $spikeData['artist_id'],
                ':upload_id' => $spikeData['upload_id'],
                ':detection_date' => $spikeData['detection_date'],
                ':baseline_spins' => $spikeData['baseline_spins'],
                ':spike_spins' => $spikeData['spike_spins'],
                ':spike_multiplier' => $spikeData['spike_multiplier'],
                ':threshold_met' => $spikeData['threshold_met'] ? 1 : 0,
                ':spike_start_date' => $spikeData['spike_start_date'],
                ':spike_end_date' => $spikeData['spike_end_date'],
                ':stations_count' => $spikeData['stations_count'],
                ':zip_codes' => $spikeData['zip_codes'],
            ]);

            return (int)$this->pdo->lastInsertId();
        } catch (\Throwable $e) {
            throw new Exception("Error recording heat spike: {$e->getMessage()}");
        }
    }
}
