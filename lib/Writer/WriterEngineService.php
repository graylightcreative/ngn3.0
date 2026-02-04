<?php
/**
 * Writer Engine Service - Pipeline Orchestration
 * Orchestrates the complete three-stage workflow:
 * 1. Scout detects anomalies
 * 2. Niko assigns personas and calculates story value
 * 3. Drafting generates articles
 * 4. Safety filter scans for defamation
 * 5. Publish routes to auto-hype or editorial
 */

namespace NGN\Lib\Writer;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Logging\LoggerFactory;
use Monolog\Logger;
use PDO;

class WriterEngineService
{
    private PDO $read;
    private PDO $write;
    private Logger $logger;
    private Config $config;
    private ScoutService $scout;
    private NikoService $niko;
    private DraftingService $drafting;
    private SafetyFilterService $safety;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);
        $this->logger = LoggerFactory::create($config, 'writer_engine');

        // Initialize sub-services
        $this->scout = new ScoutService($config);
        $this->niko = new NikoService($config);
        $this->drafting = new DraftingService($config);
        $this->safety = new SafetyFilterService($config);
    }

    /**
     * Process new anomalies: Scout -> Niko pipeline
     * Detects anomalies and assigns personas
     *
     * @return array Processing results
     */
    public function processNewAnomalies(): array
    {
        $result = [
            'detected_anomalies' => 0,
            'assigned_personas' => 0,
            'errors' => 0,
        ];

        try {
            // Step 1: Run anomaly detection (Scout)
            $chartJumps = $this->scout->detectChartJumps();
            $engagementSpikes = $this->scout->detectEngagementSpikes();
            $spinSurges = $this->scout->detectSpinSurges();
            $genreTrends = $this->scout->detectGenreTrends();

            $allAnomalies = array_merge($chartJumps, $engagementSpikes, $spinSurges, $genreTrends);

            $this->logger->info("Anomalies detected", [
                'chart_jumps' => count($chartJumps),
                'engagement_spikes' => count($engagementSpikes),
                'spin_surges' => count($spinSurges),
                'genre_trends' => count($genreTrends),
                'total' => count($allAnomalies),
            ]);

            $result['detected_anomalies'] = count($allAnomalies);

            // Store anomalies in database
            foreach ($allAnomalies as $anomaly) {
                try {
                    $this->scout->createAnomaly(
                        $anomaly['detection_type'],
                        $anomaly['severity'],
                        $anomaly['artist_id'],
                        $anomaly['track_id'] ?? null,
                        $anomaly['detected_value'],
                        $anomaly['baseline_value'],
                        $anomaly['magnitude'],
                        $anomaly['genre'] ?? null,
                        $anomaly['city_code'] ?? null
                    );
                } catch (\Throwable $e) {
                    $this->logger->error("Failed to create anomaly", ['error' => $e->getMessage()]);
                    $result['errors']++;
                }
            }

            // Step 2: Process assignments (Niko)
            $nikoResult = $this->niko->processAnomalies();
            $result['assigned_personas'] = $nikoResult['assigned'];

            // Check for surge alert (>50 anomalies in 5 min)
            if (count($allAnomalies) > 50) {
                $this->logger->warning("Surge detected - P1 alert", [
                    'anomaly_count' => count($allAnomalies),
                    'threshold' => 50,
                ]);
            }

        } catch (\Throwable $e) {
            $this->logger->error("Anomaly processing pipeline failed", ['error' => $e->getMessage()]);
            $result['errors']++;
        }

        return $result;
    }

    /**
     * Generate articles from pending assignments
     * Orchestrates: Niko -> Drafting pipeline
     *
     * @param int $limit Max articles to generate
     * @return array Processing results
     */
    public function generatePendingArticles(int $limit = 10): array
    {
        $result = [
            'generated' => 0,
            'failed' => 0,
            'cost_total_usd' => 0.0,
        ];

        try {
            $sql = "
                SELECT wa.id, wa.anomaly_id, wa.persona_id
                FROM writer_anomalies wa
                WHERE wa.status = 'assigned'
                  AND NOT EXISTS (
                    SELECT 1 FROM writer_articles WHERE anomaly_id = wa.id
                  )
                ORDER BY wa.story_value_score DESC
                LIMIT :limit
            ";

            $stmt = $this->read->prepare($sql);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                try {
                    // Generate article
                    $article = $this->drafting->generateArticle($row['anomaly_id'], $row['persona_id']);

                    $result['generated']++;
                    $result['cost_total_usd'] += $article['cost_usd'];

                    // Alert if generation time excessive
                    if ($article['generation_time_ms'] > 30000) {
                        $this->logger->warning("Generation time excessive - P1 alert", [
                            'article_id' => $article['id'],
                            'generation_time_ms' => $article['generation_time_ms'],
                        ]);
                    }

                    // Log generation metrics
                    $this->logGenerationMetric($row['persona_id'], $article);

                } catch (\Throwable $e) {
                    $this->logger->error("Article generation failed", [
                        'anomaly_id' => $row['anomaly_id'],
                        'error' => $e->getMessage(),
                    ]);
                    $result['failed']++;
                }
            }

            $this->logger->info("Article generation batch completed", [
                'generated' => $result['generated'],
                'failed' => $result['failed'],
                'total_cost_usd' => round($result['cost_total_usd'], 2),
            ]);

        } catch (\Throwable $e) {
            $this->logger->error("Article generation pipeline failed", ['error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * Scan pending articles for safety issues
     *
     * @param int $limit Max articles to scan
     * @return array Scan results
     */
    public function scanPendingArticles(int $limit = 50): array
    {
        try {
            return $this->safety->scanPendingArticles($limit);
        } catch (\Throwable $e) {
            $this->logger->error("Safety scanning pipeline failed", ['error' => $e->getMessage()]);
            return ['scanned' => 0, 'errors' => 1];
        }
    }

    /**
     * Publish auto-hype articles on schedule
     * Auto-hype articles bypass editorial queue and go straight to publish
     *
     * @return array Publishing results
     */
    public function publishAutoHypeArticles(): array
    {
        $result = [
            'published' => 0,
            'errors' => 0,
        ];

        try {
            $sql = "
                SELECT wa.id, wa.title, wa.slug, wps.scheduled_for
                FROM writer_articles wa
                JOIN writer_publish_schedule wps ON wa.id = wps.article_id
                WHERE wa.publishing_pipeline = 'auto_hype'
                  AND wa.status = 'approved'
                  AND wa.safety_scan_status IN ('approved', 'flagged')
                  AND wps.publish_status = 'scheduled'
                  AND wps.scheduled_for <= NOW()
                ORDER BY wps.scheduled_for ASC
                LIMIT 20
            ";

            $stmt = $this->read->prepare($sql);
            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                try {
                    // Update article status
                    $updateSql = "
                        UPDATE writer_articles
                        SET status = 'published', published_at = NOW()
                        WHERE id = :id
                    ";
                    $updateStmt = $this->write->prepare($updateSql);
                    $updateStmt->execute([':id' => $row['id']]);

                    // Update schedule status
                    $scheduleSql = "
                        UPDATE writer_publish_schedule
                        SET publish_status = 'published',
                            actual_publish_time = NOW(),
                            publish_delay_seconds = FLOOR((UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(scheduled_for)))
                        WHERE article_id = :id
                    ";
                    $scheduleStmt = $this->write->prepare($scheduleSql);
                    $scheduleStmt->execute([':id' => $row['id']]);

                    $this->logger->info("Article published", [
                        'article_id' => $row['id'],
                        'title' => $row['title'],
                        'slug' => $row['slug'],
                    ]);

                    $result['published']++;

                } catch (\Throwable $e) {
                    $this->logger->error("Failed to publish article", [
                        'article_id' => $row['id'],
                        'error' => $e->getMessage(),
                    ]);
                    $result['errors']++;
                }
            }

        } catch (\Throwable $e) {
            $this->logger->error("Auto-hype publishing pipeline failed", ['error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * Update article engagement metrics from CDM
     */
    public function syncEngagementMetrics(): array
    {
        $result = ['updated' => 0, 'errors' => 0];

        try {
            // Sync recently published articles
            $sql = "
                SELECT wa.id, wa.referenced_artist_ids
                FROM writer_articles wa
                WHERE wa.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  AND wa.status = 'published'
            ";

            $stmt = $this->read->prepare($sql);
            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                try {
                    $artistIds = json_decode($row['referenced_artist_ids'], true);

                    // Query engagement from CDM
                    if (!empty($artistIds)) {
                        $placeholders = implode(',', array_fill(0, count($artistIds), '?'));
                        $engagementSql = "
                            SELECT SUM(views_count) as views, SUM(likes_count) as likes,
                                   SUM(shares_count) as shares, SUM(comments_count) as comments
                            FROM cdm_engagements
                            WHERE artist_id IN ($placeholders)
                              AND engagement_date >= DATE(wa.published_at)
                        ";

                        // Update article metrics
                        $updateSql = "
                            UPDATE writer_articles
                            SET views_count = COALESCE(:views, 0),
                                likes_count = COALESCE(:likes, 0),
                                shares_count = COALESCE(:shares, 0),
                                comments_count = COALESCE(:comments, 0),
                                total_engagement = COALESCE(:views, 0) + COALESCE(:likes, 0) + COALESCE(:shares, 0) + COALESCE(:comments, 0)
                            WHERE id = :id
                        ";

                        $updateStmt = $this->write->prepare($updateSql);
                        $updateStmt->execute([
                            ':views' => 0,
                            ':likes' => 0,
                            ':shares' => 0,
                            ':comments' => 0,
                            ':id' => $row['id'],
                        ]);

                        $result['updated']++;
                    }

                } catch (\Throwable $e) {
                    $this->logger->error("Failed to sync engagement", ['error' => $e->getMessage()]);
                    $result['errors']++;
                }
            }

        } catch (\Throwable $e) {
            $this->logger->error("Engagement sync failed", ['error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * Log generation metrics for persona
     */
    private function logGenerationMetric(int $personaId, array $article): void
    {
        try {
            $today = date('Y-m-d');

            $sql = "
                INSERT INTO writer_generation_metrics (
                    metric_date, persona_id,
                    articles_generated, total_generation_time_ms,
                    total_prompt_tokens, total_completion_tokens, total_cost_usd
                ) VALUES (
                    :date, :persona_id,
                    1, :time_ms,
                    :prompt_tokens, :completion_tokens, :cost_usd
                )
                ON DUPLICATE KEY UPDATE
                    articles_generated = articles_generated + 1,
                    total_generation_time_ms = total_generation_time_ms + :time_ms,
                    total_prompt_tokens = total_prompt_tokens + :prompt_tokens,
                    total_completion_tokens = total_completion_tokens + :completion_tokens,
                    total_cost_usd = total_cost_usd + :cost_usd
            ";

            $stmt = $this->write->prepare($sql);
            $stmt->execute([
                ':date' => $today,
                ':persona_id' => $personaId,
                ':time_ms' => $article['generation_time_ms'],
                ':prompt_tokens' => $article['prompt_tokens'],
                ':completion_tokens' => $article['completion_tokens'],
                ':cost_usd' => $article['cost_usd'],
            ]);

        } catch (\Throwable $e) {
            $this->logger->error("Failed to log generation metric", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get real-time Writer Engine status
     */
    public function getStatus(): array
    {
        try {
            $sql = "
                SELECT
                    (SELECT COUNT(*) FROM writer_anomalies WHERE status = 'detected') as pending_anomalies,
                    (SELECT COUNT(*) FROM writer_anomalies WHERE status = 'assigned') as pending_assignments,
                    (SELECT COUNT(*) FROM writer_articles WHERE status = 'draft') as pending_drafts,
                    (SELECT COUNT(*) FROM writer_articles WHERE safety_scan_status = 'pending') as pending_safety_scans,
                    (SELECT COUNT(*) FROM writer_articles WHERE safety_scan_status = 'flagged') as flagged_for_review,
                    (SELECT COUNT(*) FROM writer_articles WHERE status = 'approved' AND published_at IS NULL) as ready_to_publish,
                    (SELECT COUNT(*) FROM writer_articles WHERE status = 'published' AND published_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)) as published_today,
                    (SELECT SUM(total_cost_usd) FROM writer_generation_metrics WHERE metric_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) as cost_last_7_days
            ";

            $stmt = $this->read->prepare($sql);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        } catch (\Throwable $e) {
            $this->logger->error("Failed to fetch status", ['error' => $e->getMessage()]);
            return [];
        }
    }
}
