<?php
/**
 * Scout Service - Anomaly Detection
 * Detects music anomalies from CDM data:
 * - Chart jumps (>20 rank change)
 * - Engagement spikes (10x average)
 * - Spin surges (5x increase)
 * - Genre trends (location-specific)
 */

namespace NGN\Lib\Writer;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Logging\LoggerFactory;
use Monolog\Logger;
use PDO;

class ScoutService
{
    private PDO $read;
    private PDO $write;
    private Logger $logger;
    private Config $config;

    // Thresholds for detection
    private const CHART_JUMP_THRESHOLD = 20;       // >20 rank change
    private const ENGAGEMENT_SPIKE_MULTIPLE = 10;  // 10x average
    private const SPIN_SURGE_MULTIPLE = 5;         // 5x previous period
    private const MIN_STORY_VALUE = 30;            // Minimum score to consider

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);
        $this->logger = LoggerFactory::create($config, 'writer_scout');
    }

    /**
     * Detect chart jumps in rankings
     * @param int $daysLookback Number of days to analyze (default 7)
     * @return array Array of anomalies detected
     */
    public function detectChartJumps(int $daysLookback = 7): array
    {
        $anomalies = [];

        try {
            $sql = "
                SELECT
                    cce_current.artist_id,
                    cce_current.track_id,
                    cce_current.chart_position as current_rank,
                    cce_prev.chart_position as previous_rank,
                    ABS(cce_current.chart_position - COALESCE(cce_prev.chart_position, 999)) as rank_change,
                    'chart_jump' as detection_type,
                    CASE
                        WHEN ABS(cce_current.chart_position - COALESCE(cce_prev.chart_position, 999)) > 50 THEN 'critical'
                        WHEN ABS(cce_current.chart_position - COALESCE(cce_prev.chart_position, 999)) > 35 THEN 'high'
                        ELSE 'medium'
                    END as severity,
                    cce_current.genre,
                    (ABS(cce_current.chart_position - COALESCE(cce_prev.chart_position, 999)) * 1.0) / NULLIF(COALESCE(cce_prev.chart_position, 1), 0) as magnitude
                FROM cdm_chart_entries cce_current
                LEFT JOIN cdm_chart_entries cce_prev ON
                    cce_current.artist_id = cce_prev.artist_id
                    AND cce_current.track_id = cce_prev.track_id
                    AND cce_prev.chart_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                WHERE
                    cce_current.chart_date = CURDATE()
                    AND ABS(cce_current.chart_position - COALESCE(cce_prev.chart_position, 999)) > :threshold
                ORDER BY rank_change DESC
            ";

            $stmt = $this->read->prepare($sql);
            $stmt->bindParam(':threshold', $threshold = self::CHART_JUMP_THRESHOLD);
            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $anomalies[] = $this->createAnomalyRecord(
                    'chart_jump',
                    $row['severity'],
                    $row['artist_id'],
                    $row['track_id'],
                    $row['current_rank'],
                    $row['previous_rank'] ?? 999,
                    $row['magnitude'],
                    $row['genre'],
                    null
                );
            }

            $this->logger->info("Chart jump detection completed", ['anomalies_found' => count($anomalies)]);

        } catch (\Throwable $e) {
            $this->logger->error("Chart jump detection failed", ['error' => $e->getMessage()]);
        }

        return $anomalies;
    }

    /**
     * Detect engagement spikes on riffs/content
     * @return array Array of anomalies detected
     */
    public function detectEngagementSpikes(): array
    {
        $anomalies = [];

        try {
            $sql = "
                SELECT
                    ce.artist_id,
                    ce.track_id,
                    ce.total_engagement as current_engagement,
                    cde.avg_daily_engagement,
                    (ce.total_engagement / NULLIF(cde.avg_daily_engagement, 0)) as engagement_multiple,
                    'engagement_spike' as detection_type,
                    CASE
                        WHEN (ce.total_engagement / NULLIF(cde.avg_daily_engagement, 0)) > 20 THEN 'critical'
                        WHEN (ce.total_engagement / NULLIF(cde.avg_daily_engagement, 0)) > 15 THEN 'high'
                        ELSE 'medium'
                    END as severity,
                    a.genre
                FROM cdm_engagements ce
                LEFT JOIN (
                    SELECT artist_id, track_id, AVG(total_engagement) as avg_daily_engagement
                    FROM cdm_engagements
                    WHERE engagement_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                      AND engagement_date < DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                    GROUP BY artist_id, track_id
                ) cde ON ce.artist_id = cde.artist_id AND ce.track_id = cde.track_id
                LEFT JOIN artists a ON ce.artist_id = a.id
                WHERE
                    ce.engagement_date = CURDATE()
                    AND cde.avg_daily_engagement > 0
                    AND (ce.total_engagement / NULLIF(cde.avg_daily_engagement, 0)) > :multiple
                ORDER BY engagement_multiple DESC
            ";

            $stmt = $this->read->prepare($sql);
            $stmt->bindParam(':multiple', $multiple = self::ENGAGEMENT_SPIKE_MULTIPLE);
            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $anomalies[] = $this->createAnomalyRecord(
                    'engagement_spike',
                    $row['severity'],
                    $row['artist_id'],
                    $row['track_id'],
                    $row['current_engagement'],
                    $row['avg_daily_engagement'] ?? 0,
                    $row['engagement_multiple'],
                    $row['genre'],
                    null
                );
            }

            $this->logger->info("Engagement spike detection completed", ['anomalies_found' => count($anomalies)]);

        } catch (\Throwable $e) {
            $this->logger->error("Engagement spike detection failed", ['error' => $e->getMessage()]);
        }

        return $anomalies;
    }

    /**
     * Detect spin surges on tracks
     * @return array Array of anomalies detected
     */
    public function detectSpinSurges(): array
    {
        $anomalies = [];

        try {
            $sql = "
                SELECT
                    cs_current.artist_id,
                    cs_current.track_id,
                    cs_current.spin_count as current_spins,
                    cs_prev.spin_count as baseline_spins,
                    (cs_current.spin_count / NULLIF(cs_prev.spin_count, 0)) as surge_multiple,
                    'spin_surge' as detection_type,
                    CASE
                        WHEN (cs_current.spin_count / NULLIF(cs_prev.spin_count, 0)) > 10 THEN 'critical'
                        WHEN (cs_current.spin_count / NULLIF(cs_prev.spin_count, 0)) > 7 THEN 'high'
                        ELSE 'medium'
                    END as severity,
                    a.genre
                FROM cdm_spins cs_current
                LEFT JOIN (
                    SELECT artist_id, track_id, AVG(spin_count) as spin_count
                    FROM cdm_spins
                    WHERE spin_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                      AND spin_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    GROUP BY artist_id, track_id
                ) cs_prev ON cs_current.artist_id = cs_prev.artist_id AND cs_current.track_id = cs_prev.track_id
                LEFT JOIN artists a ON cs_current.artist_id = a.id
                WHERE
                    cs_current.spin_date = CURDATE()
                    AND cs_prev.spin_count > 0
                    AND (cs_current.spin_count / NULLIF(cs_prev.spin_count, 0)) > :multiple
                ORDER BY surge_multiple DESC
            ";

            $stmt = $this->read->prepare($sql);
            $stmt->bindParam(':multiple', $multiple = self::SPIN_SURGE_MULTIPLE);
            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $anomalies[] = $this->createAnomalyRecord(
                    'spin_surge',
                    $row['severity'],
                    $row['artist_id'],
                    $row['track_id'],
                    $row['current_spins'],
                    $row['baseline_spins'] ?? 0,
                    $row['surge_multiple'],
                    $row['genre'],
                    null
                );
            }

            $this->logger->info("Spin surge detection completed", ['anomalies_found' => count($anomalies)]);

        } catch (\Throwable $e) {
            $this->logger->error("Spin surge detection failed", ['error' => $e->getMessage()]);
        }

        return $anomalies;
    }

    /**
     * Detect location-specific genre trends
     * @return array Array of anomalies detected
     */
    public function detectGenreTrends(): array
    {
        $anomalies = [];

        try {
            $sql = "
                SELECT
                    cge.artist_id,
                    cge.genre,
                    cge.city_code,
                    cge.current_listeners,
                    cge.baseline_listeners,
                    (cge.current_listeners / NULLIF(cge.baseline_listeners, 0)) as trend_multiple,
                    'genre_trend' as detection_type,
                    CASE
                        WHEN (cge.current_listeners / NULLIF(cge.baseline_listeners, 0)) > 8 THEN 'high'
                        ELSE 'medium'
                    END as severity
                FROM (
                    SELECT
                        cs.artist_id,
                        a.genre,
                        cs.city_code,
                        SUM(cs.daily_listeners) as current_listeners,
                        AVG(cs_prev.weekly_avg) as baseline_listeners
                    FROM cdm_genre_by_city cs
                    LEFT JOIN (
                        SELECT artist_id, city_code, AVG(daily_listeners) as weekly_avg
                        FROM cdm_genre_by_city
                        WHERE date BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND DATE_SUB(CURDATE(), INTERVAL 8 DAY)
                        GROUP BY artist_id, city_code
                    ) cs_prev ON cs.artist_id = cs_prev.artist_id AND cs.city_code = cs_prev.city_code
                    LEFT JOIN artists a ON cs.artist_id = a.id
                    WHERE cs.date = CURDATE()
                    GROUP BY cs.artist_id, cs.city_code
                ) cge
                WHERE baseline_listeners > 0
                  AND (cge.current_listeners / NULLIF(cge.baseline_listeners, 0)) > 3
                ORDER BY trend_multiple DESC
            ";

            $stmt = $this->read->prepare($sql);
            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $anomalies[] = $this->createAnomalyRecord(
                    'genre_trend',
                    $row['severity'],
                    $row['artist_id'],
                    null,
                    $row['current_listeners'],
                    $row['baseline_listeners'] ?? 0,
                    $row['trend_multiple'],
                    $row['genre'],
                    $row['city_code']
                );
            }

            $this->logger->info("Genre trend detection completed", ['anomalies_found' => count($anomalies)]);

        } catch (\Throwable $e) {
            $this->logger->error("Genre trend detection failed", ['error' => $e->getMessage()]);
        }

        return $anomalies;
    }

    /**
     * Create anomaly record in database
     *
     * @param string $detection_type
     * @param string $severity
     * @param int $artist_id
     * @param int|null $track_id
     * @param float $detected_value
     * @param float $baseline_value
     * @param float $magnitude
     * @param string|null $genre
     * @param string|null $city_code
     * @return array Inserted anomaly data
     */
    public function createAnomaly(
        string $detection_type,
        string $severity,
        int $artist_id,
        ?int $track_id,
        float $detected_value,
        float $baseline_value,
        float $magnitude,
        ?string $genre,
        ?string $city_code
    ): array {
        try {
            $changePercentage = ($baseline_value > 0)
                ? (($detected_value - $baseline_value) / $baseline_value) * 100
                : 0;

            $sql = "
                INSERT INTO writer_anomalies (
                    detection_type, severity, artist_id, track_id,
                    detected_value, baseline_value, magnitude, change_percentage,
                    genre, city_code, status, detected_at
                ) VALUES (
                    :detection_type, :severity, :artist_id, :track_id,
                    :detected_value, :baseline_value, :magnitude, :change_percentage,
                    :genre, :city_code, 'detected', NOW()
                )
            ";

            $stmt = $this->write->prepare($sql);
            $stmt->execute([
                ':detection_type' => $detection_type,
                ':severity' => $severity,
                ':artist_id' => $artist_id,
                ':track_id' => $track_id,
                ':detected_value' => $detected_value,
                ':baseline_value' => $baseline_value,
                ':magnitude' => $magnitude,
                ':change_percentage' => $changePercentage,
                ':genre' => $genre,
                ':city_code' => $city_code,
            ]);

            $anomalyId = (int)$this->write->lastInsertId();

            $this->logger->info("Anomaly created", [
                'anomaly_id' => $anomalyId,
                'detection_type' => $detection_type,
                'artist_id' => $artist_id,
                'severity' => $severity,
            ]);

            return [
                'id' => $anomalyId,
                'detection_type' => $detection_type,
                'severity' => $severity,
                'artist_id' => $artist_id,
                'track_id' => $track_id,
                'detected_value' => $detected_value,
                'baseline_value' => $baseline_value,
                'magnitude' => $magnitude,
            ];

        } catch (\Throwable $e) {
            $this->logger->error("Failed to create anomaly", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Helper to format anomaly record for insertion
     */
    private function createAnomalyRecord(
        string $type,
        string $severity,
        int $artist_id,
        ?int $track_id,
        float $current,
        float $baseline,
        float $magnitude,
        ?string $genre,
        ?string $city_code
    ): array {
        return [
            'detection_type' => $type,
            'severity' => $severity,
            'artist_id' => $artist_id,
            'track_id' => $track_id,
            'detected_value' => $current,
            'baseline_value' => $baseline,
            'magnitude' => $magnitude,
            'genre' => $genre,
            'city_code' => $city_code,
        ];
    }

    /**
     * Get configuration threshold (can be overridden via rules table)
     */
    public function getThreshold(string $thresholdType): float
    {
        $thresholds = [
            'chart_jump' => self::CHART_JUMP_THRESHOLD,
            'engagement_spike' => self::ENGAGEMENT_SPIKE_MULTIPLE,
            'spin_surge' => self::SPIN_SURGE_MULTIPLE,
        ];

        return $thresholds[$thresholdType] ?? 0;
    }
}
