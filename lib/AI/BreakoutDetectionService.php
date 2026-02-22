<?php
namespace NGN\Lib\AI;

/**
 * Breakout Detection Service - NGN 3.0 Empire Intelligence
 * Uses momentum analysis to identify "Breakout" artists and labels.
 * Logic: Velocity of NGN Score + SMR Airplay Acceleration.
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class BreakoutDetectionService
{
    private $pdo;
    private $rankingsPdo;
    private $spinsPdo;

    public function __construct(Config $config)
    {
        $this->pdo = ConnectionFactory::read($config);
        $this->rankingsPdo = ConnectionFactory::named($config, 'rankings2025');
        $this->spinsPdo = ConnectionFactory::named($config, 'spins2025');
    }

    /**
     * Detect Rising Stars (Breakout Artists)
     * @param int $limit Number of artists to return
     * @return array List of artists with momentum scores
     */
    public function detectBreakouts(int $limit = 10): array
    {
        // 1. Get entities with highest score delta over last 2 windows
        $stmt = $this->rankingsPdo->prepare("
            SELECT 
                curr.entity_id, 
                curr.entity_type,
                (curr.score - prev.score) as velocity,
                curr.score as current_score
            FROM ranking_items curr
            JOIN ranking_items prev ON curr.entity_id = prev.entity_id 
                AND curr.entity_type = prev.entity_type 
                AND prev.window_id = curr.window_id - 1
            WHERE curr.entity_type = 'artist'
            ORDER BY velocity DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit * 2, PDO::PARAM_INT);
        $stmt->execute();
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Further filter by SMR airplay acceleration (The "Signal" check)
        $breakouts = [];
        foreach ($candidates as $c) {
            $momentum = $this->calculateAirplayMomentum($c['entity_id']);
            if ($momentum > 1.2) { // 20% growth in airplay
                $c['momentum_multiplier'] = $momentum;
                $c['discovery_score'] = $c['velocity'] * $momentum;
                $breakouts[] = $this->enrichEntityData($c);
            }
        }

        // Sort by discovery score
        usort($breakouts, fn($a, $b) => $b['discovery_score'] <=> $a['discovery_score']);

        return array_slice($breakouts, 0, $limit);
    }

    private function calculateAirplayMomentum(int $artistId): float
    {
        // Compare spins in last 7 days vs previous 7 days
        $stmt = $this->spinsPdo->prepare("
            SELECT 
                SUM(CASE WHEN played_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as recent_spins,
                SUM(CASE WHEN played_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND played_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as previous_spins
            FROM station_spins
            WHERE artist_id = ?
        ");
        $stmt->execute([$artistId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        $recent = (int)($res['recent_spins'] ?? 0);
        $prev = (int)($res['previous_spins'] ?? 0);

        if ($prev === 0) return $recent > 0 ? 2.0 : 1.0;
        return $recent / $prev;
    }

    private function enrichEntityData(array $candidate): array
    {
        $stmt = $this->pdo->prepare("SELECT name, slug, image_url FROM artists WHERE id = ?");
        $stmt->execute([$candidate['entity_id']]);
        $meta = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return array_merge($candidate, $meta ?: ['name' => 'Unknown']);
    }
}
