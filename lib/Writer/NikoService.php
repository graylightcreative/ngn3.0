<?php
/**
 * Niko Service - Story Assignment & Routing
 * Routes anomalies to appropriate personas based on:
 * - Story value calculation (0-100)
 * - Genre-to-persona mapping
 * - Publishing pipeline determination
 */

namespace NGN\Lib\Writer;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Logging\LoggerFactory;
use Monolog\Logger;
use PDO;

class NikoService
{
    private PDO $read;
    private PDO $write;
    private Logger $logger;
    private Config $config;

    // Story value weights
    private const POPULARITY_WEIGHT = 0.4;
    private const MAGNITUDE_WEIGHT = 0.3;
    private const RECENCY_WEIGHT = 0.2;
    private const TIMELINESS_WEIGHT = 0.1;

    // Persona routing map (genre -> persona_id)
    private const PERSONA_ROUTING = [
        'metal' => 1,      // Alex Reynolds
        'rock' => 1,
        'data' => 2,       // Sam O'Donnel
        'analytical' => 2,
        'indie' => 3,      // Frankie Morale
        'alternative' => 3,
        'industry' => 4,   // Kat Blac
        'pop' => 4,
        'features' => 5,   // Max Thompson
        'hiphop' => 5,
        'rap' => 5,
    ];

    // Publishing pipeline routing
    private const AUTO_HYPE_KEYWORDS = ['tour', 'merch', 'milestone', 'milestone', 'chart topper', 'debut'];
    private const EDITORIAL_KEYWORDS = ['review', 'op-ed', 'analysis', 'chart watch', 'trend'];

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);
        $this->logger = LoggerFactory::create($config, 'writer_niko');
    }

    /**
     * Process pending anomalies: evaluate story value and assign personas
     * @return array Results of processing
     */
    public function processAnomalies(): array
    {
        $result = [
            'processed' => 0,
            'assigned' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        // 1. Check AI Policy & Killswitch
        $policy = new \NGN\Lib\AI\SovereignAIPolicy($this->config);
        if (!$policy->isAIEnabled()) {
            $this->logger->warning("AI Activation Restricted: Killswitch is ACTIVE. Anomalies will remain in queue.");
            return $result;
        }

        try {
            // Get unassigned anomalies
            $sql = "
                SELECT id, detection_type, artist_id, track_id, severity,
                       detected_value, baseline_value, magnitude, genre
                FROM writer_anomalies
                WHERE status = 'detected'
                ORDER BY severity DESC, magnitude DESC
                LIMIT 100
            ";

            $stmt = $this->read->prepare($sql);
            $stmt->execute();

            while ($anomaly = $stmt->fetch(PDO::FETCH_ASSOC)) {
                try {
                    // Calculate story value
                    $storyValue = $this->evaluateStoryValue($anomaly);

                    if ($storyValue < 30) {
                        // Skip low-value stories
                        $this->skipAnomaly($anomaly['id'], 'low_story_value');
                        $result['skipped']++;
                        continue;
                    }

                    // Determine persona
                    $personaId = $this->assignPersona($anomaly['genre']);

                    // Determine publishing pipeline
                    $pipeline = $this->determinePublishingPipeline($anomaly);

                    // Update anomaly with assignment
                    $updateSql = "
                        UPDATE writer_anomalies
                        SET status = 'assigned',
                            story_value_score = :story_value,
                            assigned_persona_id = :persona_id,
                            assigned_at = NOW(),
                            context_json = :context_json
                        WHERE id = :id
                    ";

                    $contextJson = json_encode([
                        'pipeline' => $pipeline,
                        'original_detection' => $anomaly['detection_type'],
                        'story_value_components' => $this->getStoryValueComponents($anomaly),
                    ]);

                    $updateStmt = $this->write->prepare($updateSql);
                    $updateStmt->execute([
                        ':story_value' => $storyValue,
                        ':persona_id' => $personaId,
                        ':context_json' => $contextJson,
                        ':id' => $anomaly['id'],
                    ]);

                    $this->logger->info("Anomaly assigned", [
                        'anomaly_id' => $anomaly['id'],
                        'story_value' => $storyValue,
                        'persona_id' => $personaId,
                        'pipeline' => $pipeline,
                    ]);

                    $result['assigned']++;

                } catch (\Throwable $e) {
                    $this->logger->error("Failed to process anomaly", [
                        'anomaly_id' => $anomaly['id'],
                        'error' => $e->getMessage(),
                    ]);
                    $result['errors']++;
                }

                $result['processed']++;
            }

        } catch (\Throwable $e) {
            $this->logger->error("Anomaly processing failed", ['error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * Evaluate story value on 0-100 scale
     * Considers: artist popularity, magnitude of event, recency, timeliness
     *
     * @param array $anomaly
     * @return float Story value score (0-100)
     */
    public function evaluateStoryValue(array $anomaly): float
    {
        try {
            $score = 0;

            // 1. Popularity component (40%): artist stream count
            $popularityScore = $this->calculatePopularityScore($anomaly['artist_id']);
            $score += $popularityScore * self::POPULARITY_WEIGHT;

            // 2. Magnitude component (30%): how big is the change
            $magnitudeScore = min(100, $anomaly['magnitude'] * 10);
            $score += $magnitudeScore * self::MAGNITUDE_WEIGHT;

            // 3. Recency component (20%): how fresh is it
            $recencyScore = 100; // Fresh detection = full points
            $score += $recencyScore * self::RECENCY_WEIGHT;

            // 4. Timeliness component (10%): does it align with news cycles
            $timelinessScore = $this->calculateTimelinessScore($anomaly);
            $score += $timelinessScore * self::TIMELINESS_WEIGHT;

            // Apply severity multiplier
            $severityMultiplier = match ($anomaly['severity']) {
                'critical' => 1.3,
                'high' => 1.15,
                'medium' => 1.0,
                'low' => 0.8,
                default => 1.0,
            };

            $finalScore = min(100, $score * $severityMultiplier);

            return round($finalScore, 2);

        } catch (\Throwable $e) {
            $this->logger->error("Story value calculation failed", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Get popularity score for artist (0-100)
     */
    private function calculatePopularityScore(int $artistId): float
    {
        try {
            // Query artist stream count and rank
            $sql = "
                SELECT COALESCE(total_streams, 0) as streams
                FROM artists
                WHERE id = :artist_id
            ";

            $stmt = $this->read->prepare($sql);
            $stmt->execute([':artist_id' => $artistId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return 30; // Default score for unknown artists
            }

            // Normalize streams to 0-100 scale
            // Assume max notable artist = 10M streams
            $normalizedStreams = min(100, ($result['streams'] / 10_000_000) * 100);

            return $normalizedStreams;

        } catch (\Throwable $e) {
            $this->logger->error("Popularity score calculation failed", ['error' => $e->getMessage()]);
            return 50;
        }
    }

    /**
     * Get timeliness score (0-100)
     * Higher score if event aligns with music calendar (Fridays, major release days)
     */
    private function calculateTimelinessScore(array $anomaly): float
    {
        $dayOfWeek = date('N'); // 1=Mon, 5=Fri, 7=Sun

        // Fridays and Sundays are high-engagement days
        if ($dayOfWeek === '5' || $dayOfWeek === '7') {
            return 100;
        }

        // Weekdays are moderate
        if ($dayOfWeek >= '1' && $dayOfWeek <= '4') {
            return 70;
        }

        return 50;
    }

    /**
     * Get story value components breakdown (for logging)
     */
    private function getStoryValueComponents(array $anomaly): array
    {
        return [
            'popularity' => $this->calculatePopularityScore($anomaly['artist_id']),
            'magnitude' => min(100, $anomaly['magnitude'] * 10),
            'recency' => 100,
            'timeliness' => $this->calculateTimelinessScore($anomaly),
        ];
    }

    /**
     * Assign persona based on genre
     * Falls back to features writer (5) for unmapped genres
     *
     * @param string|null $genre
     * @return int persona_id (1-5)
     */
    public function assignPersona(?string $genre): int
    {
        if (!$genre) {
            // No genre: use features writer (most flexible)
            return 5;
        }

        $genreLower = strtolower($genre);

        // Exact match
        if (isset(self::PERSONA_ROUTING[$genreLower])) {
            return self::PERSONA_ROUTING[$genreLower];
        }

        // Partial match
        foreach (self::PERSONA_ROUTING as $mappedGenre => $personaId) {
            if (strpos($genreLower, $mappedGenre) !== false || strpos($mappedGenre, $genreLower) !== false) {
                return $personaId;
            }
        }

        // Default to Features writer
        return 5;
    }

    /**
     * Determine publishing pipeline (auto_hype vs editorial)
     *
     * @param array $anomaly
     * @return string 'auto_hype' or 'editorial'
     */
    public function determinePublishingPipeline(array $anomaly): string
    {
        // High-velocity, urgent stories: Auto-Hype
        // (Tours, merch drops, milestone announcements)
        if ($anomaly['severity'] === 'critical') {
            return 'auto_hype';
        }

        // Check detection type
        $detectionType = strtolower($anomaly['detection_type']);

        // Chart jumps and spin surges: Editorial review
        if (in_array($detectionType, ['chart_jump', 'spin_surge', 'engagement_spike'])) {
            return 'editorial';
        }

        // Genre trends: Likely features/analysis: Editorial
        if ($detectionType === 'genre_trend') {
            return 'editorial';
        }

        // Default to editorial for quality control
        return 'editorial';
    }

    /**
     * Skip an anomaly with reason
     */
    private function skipAnomaly(int $anomalyId, string $reason): void
    {
        try {
            $sql = "
                UPDATE writer_anomalies
                SET status = 'skipped', context_json = JSON_SET(COALESCE(context_json, '{}'), '$.skip_reason', :reason)
                WHERE id = :id
            ";

            $stmt = $this->write->prepare($sql);
            $stmt->execute([
                ':reason' => $reason,
                ':id' => $anomalyId,
            ]);

        } catch (\Throwable $e) {
            $this->logger->error("Failed to skip anomaly", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get a specific anomaly with full context
     */
    public function getAnomaly(int $anomalyId): ?array
    {
        try {
            $sql = "
                SELECT id, detection_type, severity, artist_id, track_id,
                       detected_value, baseline_value, magnitude, genre, city_code,
                       status, story_value_score, assigned_persona_id,
                       context_json, detected_at
                FROM writer_anomalies
                WHERE id = :id
            ";

            $stmt = $this->read->prepare($sql);
            $stmt->execute([':id' => $anomalyId]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        } catch (\Throwable $e) {
            $this->logger->error("Failed to fetch anomaly", ['error' => $e->getMessage()]);
            return null;
        }
    }
}
