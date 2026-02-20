<?php
namespace NGN\Lib\Services\EPK;

/**
 * Professional EPK Service
 * Aggregates artist data, affinities, and booking stats for professional export.
 * Bible Ref: Chapter 18 (Discovery Profile)
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Discovery\AffinityService;
use PDO;

class EPKService
{
    private $config;
    private $pdo;
    private $affinity;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdo = ConnectionFactory::read($config);
        $this->affinity = new AffinityService($config);
    }

    /**
     * Aggregate all data required for a professional EPK
     */
    public function getEPKData(int $artistId): array
    {
        // 1. Core Profile
        $artist = $this->getArtistCore($artistId);
        if (!$artist) return [];

        // 2. High-Yield Metrics (The 'Money' View)
        $metrics = $this->getBookingMetrics($artistId);

        // 3. Affinity Heatmap (Regional Power)
        $heatmap = $this->getRegionalHeatmap($artistId);

        // 4. Content Highlights
        $tracks = $this->getTopTracks($artistId);
        $videos = $this->getFeaturedVideos($artistId);

        return [
            'artist' => $artist,
            'metrics' => $metrics,
            'heatmap' => $heatmap,
            'tracks' => $tracks,
            'videos' => $videos,
            'generated_at' => date('Y-m-d H:i:s'),
            'integrity_hash' => hash('sha256', $artistId . time())
        ];
    }

    private function getArtistCore(int $artistId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM artists WHERE id = ?");
        $stmt->execute([$artistId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getBookingMetrics(int $artistId): array
    {
        // Pull signals from engagements and sparks
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_signals,
                SUM(CASE WHEN type = 'spark' THEN 1 ELSE 0 END) as spark_count,
                COUNT(DISTINCT user_id) as unique_fans
            FROM cdm_engagements 
            WHERE artist_id = ?
        ");
        $stmt->execute([$artistId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'signal_strength' => $stats['total_signals'] ?? 0,
            'monetization_index' => $stats['spark_count'] ?? 0,
            'fan_base_reach' => $stats['unique_fans'] ?? 0,
            'engagement_velocity' => 'High' // Calculation logic placeholder
        ];
    }

    private function getRegionalHeatmap(int $artistId): array
    {
        // Simulate regional affinity data based on user IP/Profile data
        // Real implementation would join users table for location
        return [
            ['region' => 'Ohio (Home)', 'strength' => 0.95],
            ['region' => 'Texas', 'strength' => 0.72],
            ['region' => 'California', 'strength' => 0.45]
        ];
    }

    private function getTopTracks(int $artistId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tracks WHERE artist_id = ? ORDER BY id DESC LIMIT 5");
        $stmt->execute([$artistId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getFeaturedVideos(int $artistId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM videos WHERE artist_id = ? ORDER BY published_at DESC LIMIT 3");
        $stmt->execute([$artistId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
