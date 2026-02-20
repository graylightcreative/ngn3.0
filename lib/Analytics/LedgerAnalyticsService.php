<?php

namespace NGN\Lib\Analytics;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

/**
 * LedgerAnalyticsService
 * 
 * Aggregates data from the Content Ledger, SMR Logs, and Playback Events
 * to provide institutional-grade analytics for the VDR.
 */
class LedgerAnalyticsService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Calculate Artist Growth Velocity (WoW)
     * 
     * @param int $artistId CDM Artist ID
     * @param int $weeks Number of weeks to analyze
     * @return array Velocity metrics
     */
    public function getArtistVelocity(int $artistId, int $weeks = 12): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                week_date,
                SUM(spin_count) as total_spins,
                SUM(add_count) as total_adds
            FROM cdm_chart_entries
            WHERE artist_id = ?
            GROUP BY week_date
            ORDER BY week_date DESC
            LIMIT ?
        ");
        $stmt->execute([$artistId, $weeks]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC); // Newest first

        $velocity = [];
        $totalGrowth = 0;

        for ($i = 0; $i < count($data) - 1; $i++) {
            $current = $data[$i];
            $previous = $data[$i+1];
            
            $spinGrowth = ($previous['total_spins'] > 0) 
                ? (($current['total_spins'] - $previous['total_spins']) / $previous['total_spins']) * 100
                : 100; // Infinite growth if prev was 0

            $velocity[] = [
                'week' => $current['week_date'],
                'spins' => $current['total_spins'],
                'growth_pct' => round($spinGrowth, 2)
            ];
            
            $totalGrowth += $spinGrowth;
        }

        $avgGrowth = count($velocity) > 0 ? $totalGrowth / count($velocity) : 0;

        return [
            'artist_id' => $artistId,
            'avg_weekly_growth_pct' => round($avgGrowth, 2),
            'trend_data' => $velocity
        ];
    }

    /**
     * Generate Station Heat Map
     * 
     * @return array Territory-based listenership density
     */
    public function getStationHeatMap(): array
    {
        // Aggregate playback events by territory
        $stmt = $this->db->query("
            SELECT territory, COUNT(*) as heat_score
            FROM playback_events
            WHERE is_qualified_listen = 1
            GROUP BY territory
            ORDER BY heat_score DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate Total Ecosystem Reach
     * 
     * @return array Reach metrics
     */
    public function getEcosystemReach(): array
    {
        $stats = [];

        // 1. Total Unique Artists
        $stats['total_artists'] = $this->db->query("SELECT COUNT(*) FROM artists")->fetchColumn();

        // 2. Total Verified Rights
        $stats['verified_rights'] = $this->db->query("SELECT COUNT(*) FROM cdm_rights_ledger WHERE status = 'verified'")->fetchColumn();

        // 3. Total Playback Volume
        $stats['total_qualified_listens'] = $this->db->query("SELECT COUNT(*) FROM playback_events WHERE is_qualified_listen = 1")->fetchColumn();

        // 4. SMR Coverage
        $stats['smr_stations_monitored'] = $this->db->query("SELECT COUNT(DISTINCT station_id) FROM smr_records")->fetchColumn(); // Approx

        return $stats;
    }
}
