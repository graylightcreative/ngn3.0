<?php

namespace NGN\Lib\Smr;

use PDO;
use Exception;

/**
 * Geofence Matching Service (Chapter 24 - Rule 4)
 *
 * Matches venue location to radio coverage areas.
 * When artist performs at venue matching radio station zip codes,
 * triggers +2% bounty bonus.
 */
class GeofenceMatchingService
{
    private PDO $pdo;
    private float $bonusPercentage;

    public function __construct(PDO $pdo, float $bonusPercentage = 2.00)
    {
        $this->pdo = $pdo;
        $this->bonusPercentage = $_ENV['SMR_GEOFENCE_BONUS_PERCENTAGE'] ?? $bonusPercentage;
    }

    /**
     * Check if venue location matches radio heat coverage
     *
     * @param int $artistId Artist ID
     * @param int $venueId Venue ID
     * @param int $heatSpikeId Heat spike ID (contains zip codes)
     * @return array ['matched' => bool, 'bonus_percentage' => float, 'matched_zip' => ?string]
     */
    public function checkGeofenceMatch(int $artistId, int $venueId, int $heatSpikeId): array
    {
        try {
            $venueZip = $this->getVenueZipCode($venueId);

            if (!$venueZip) {
                return [
                    'matched' => false,
                    'bonus_percentage' => 0.00,
                    'matched_zip' => null,
                ];
            }

            $heatZips = $this->getHeatSpikeZipCodes($heatSpikeId);

            if (empty($heatZips)) {
                return [
                    'matched' => false,
                    'bonus_percentage' => 0.00,
                    'matched_zip' => null,
                ];
            }

            // Check if venue zip in heat spike coverage
            $matched = in_array($venueZip, $heatZips);

            return [
                'matched' => $matched,
                'bonus_percentage' => $matched ? $this->bonusPercentage : 0.00,
                'matched_zip' => $matched ? $venueZip : null,
            ];
        } catch (\Throwable $e) {
            return [
                'matched' => false,
                'bonus_percentage' => 0.00,
                'matched_zip' => null,
            ];
        }
    }

    /**
     * Get venue zip code
     *
     * @param int $venueId Venue ID
     * @return string|null Zip code or null if not found
     */
    private function getVenueZipCode(int $venueId): ?string
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT zip_code
                FROM cdm_venues
                WHERE id = ?
            ");
            $stmt->execute([$venueId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return ($result && !empty($result['zip_code']))
                ? $result['zip_code']
                : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get zip codes from heat spike
     *
     * @param int $heatSpikeId Heat spike ID
     * @return array[] Array of zip codes from coverage area
     */
    private function getHeatSpikeZipCodes(int $heatSpikeId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT zip_codes
                FROM smr_heat_spikes
                WHERE id = ?
            ");
            $stmt->execute([$heatSpikeId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result || empty($result['zip_codes'])) {
                return [];
            }

            $decoded = json_decode($result['zip_codes'], true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Calculate geofence bonus percentage
     *
     * @param bool $matched True if venue matches radio coverage
     * @return float Bonus percentage (0.00 or configured bonus)
     */
    public function calculateGeofenceBonus(bool $matched): float
    {
        return $matched ? $this->bonusPercentage : 0.00;
    }

    /**
     * Get all venues within a set of zip codes
     *
     * @param array $zipCodes Array of zip codes
     * @return array[] Venues in those zip codes
     */
    public function getVenuesInZipCodes(array $zipCodes): array
    {
        if (empty($zipCodes)) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($zipCodes), '?'));
            $stmt = $this->pdo->prepare("
                SELECT id, name, zip_code
                FROM cdm_venues
                WHERE zip_code IN ({$placeholders})
            ");
            $stmt->execute($zipCodes);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Update venue zip code
     *
     * @param int $venueId Venue ID
     * @param string $zipCode Zip code to set
     * @throws Exception
     */
    public function updateVenueZipCode(int $venueId, string $zipCode): void
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE cdm_venues
                SET zip_code = ?
                WHERE id = ?
            ");
            $stmt->execute([$zipCode, $venueId]);
        } catch (\Throwable $e) {
            throw new Exception("Error updating venue zip code: {$e->getMessage()}");
        }
    }

    /**
     * Get statistics on geofence matches for a heat spike
     *
     * @param int $heatSpikeId Heat spike ID
     * @return array Statistics about coverage
     */
    public function getHeatSpikeCoverageStats(int $heatSpikeId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    stations_count,
                    zip_codes
                FROM smr_heat_spikes
                WHERE id = ?
            ");
            $stmt->execute([$heatSpikeId]);
            $spike = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$spike) {
                return [];
            }

            $zipCodes = json_decode($spike['zip_codes'] ?? '[]', true) ?: [];

            // Get count of venues in coverage area
            $venues = $this->getVenuesInZipCodes($zipCodes);

            return [
                'stations_in_spike' => (int)($spike['stations_count'] ?? 0),
                'coverage_zip_codes' => $zipCodes,
                'zip_count' => count($zipCodes),
                'venues_in_coverage' => count($venues),
                'bonus_percentage' => $this->bonusPercentage,
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
