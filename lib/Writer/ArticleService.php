<?php
/**
 * Article Service - Editorial Workflow
 * Handles editorial team interactions:
 * - Get editorial queue (unclaimed drafts)
 * - Claim articles for editing
 * - Approve articles (schedule publish)
 * - Reject articles (with reason)
 */

namespace NGN\Lib\Writer;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Logging\LoggerFactory;
use Monolog\Logger;
use PDO;

class ArticleService
{
    private PDO $read;
    private PDO $write;
    private Logger $logger;
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);
        $this->logger = LoggerFactory::create($config, 'writer_articles');
    }

    /**
     * Get editorial queue - unclaimed drafts awaiting review
     *
     * @param int $offset
     * @param int $limit
     * @return array Array of articles
     */
    public function getEditorialQueue(int $offset = 0, int $limit = 20): array
    {
        try {
            $sql = "
                SELECT wa.id, wa.title, wa.excerpt, wa.slug,
                       wa.persona_id, wp.name as persona_name, wp.specialty,
                       wa.safety_scan_status, wa.safety_score,
                       wa.created_at, wa.generation_time_ms, wa.generation_cost_usd,
                       a.name as artist_name,
                       wa_anomaly.detection_type, wa_anomaly.severity
                FROM writer_articles wa
                LEFT JOIN writer_personas wp ON wa.persona_id = wp.id
                LEFT JOIN artists a ON wa.author_id = a.id
                LEFT JOIN writer_anomalies wa_anomaly ON wa.anomaly_id = wa_anomaly.id
                WHERE wa.editor_id IS NULL
                  AND wa.status IN ('draft', 'pending_review')
                  AND wa.safety_scan_status IN ('approved', 'flagged')
                ORDER BY wa.safety_score DESC, wa.created_at ASC
                LIMIT :offset, :limit
            ";

            $stmt = $this->read->prepare($sql);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $articles = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $articles[] = [
                    'id' => (int)$row['id'],
                    'title' => $row['title'],
                    'excerpt' => $row['excerpt'],
                    'slug' => $row['slug'],
                    'persona_name' => $row['persona_name'],
                    'persona_specialty' => $row['specialty'],
                    'safety_status' => $row['safety_scan_status'],
                    'safety_score' => (float)$row['safety_score'],
                    'artist_name' => $row['artist_name'],
                    'detection_type' => $row['detection_type'],
                    'severity' => $row['severity'],
                    'created_at' => $row['created_at'],
                    'generation_cost_usd' => (float)$row['generation_cost_usd'],
                ];
            }

            return $articles;

        } catch (\Throwable $e) {
            $this->logger->error("Failed to fetch editorial queue", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get editor's workspace - claimed articles
     *
     * @param int $editorId
     * @param int $offset
     * @param int $limit
     * @return array Array of articles
     */
    public function getEditorWorkspace(int $editorId, int $offset = 0, int $limit = 20): array
    {
        try {
            $sql = "
                SELECT wa.id, wa.title, wa.excerpt, wa.slug,
                       wa.persona_id, wp.name as persona_name,
                       wa.status, wa.safety_scan_status, wa.safety_score,
                       wa.claimed_at, wa.created_at,
                       COUNT(CASE WHEN wpc.id IS NOT NULL THEN 1 END) as comment_count
                FROM writer_articles wa
                LEFT JOIN writer_personas wp ON wa.persona_id = wp.id
                LEFT JOIN writer_persona_comments wpc ON wa.id = wpc.article_id
                WHERE wa.editor_id = :editor_id
                ORDER BY wa.status ASC, wa.claimed_at DESC
                LIMIT :offset, :limit
            ";

            $stmt = $this->read->prepare($sql);
            $stmt->bindParam(':editor_id', $editorId, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $articles = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $articles[] = [
                    'id' => (int)$row['id'],
                    'title' => $row['title'],
                    'excerpt' => $row['excerpt'],
                    'slug' => $row['slug'],
                    'persona_name' => $row['persona_name'],
                    'status' => $row['status'],
                    'safety_status' => $row['safety_scan_status'],
                    'safety_score' => (float)$row['safety_score'],
                    'claimed_at' => $row['claimed_at'],
                    'comment_count' => (int)$row['comment_count'],
                ];
            }

            return $articles;

        } catch (\Throwable $e) {
            $this->logger->error("Failed to fetch editor workspace", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Claim article for editing
     *
     * @param int $articleId
     * @param int $editorId
     * @return bool
     */
    public function claimArticle(int $articleId, int $editorId): bool
    {
        try {
            $sql = "
                UPDATE writer_articles
                SET editor_id = :editor_id,
                    claimed_at = NOW(),
                    status = 'pending_review'
                WHERE id = :id AND editor_id IS NULL
            ";

            $stmt = $this->write->prepare($sql);
            $result = $stmt->execute([
                ':editor_id' => $editorId,
                ':id' => $articleId,
            ]);

            if ($stmt->rowCount() > 0) {
                $this->logger->info("Article claimed", [
                    'article_id' => $articleId,
                    'editor_id' => $editorId,
                ]);
                return true;
            }

            return false;

        } catch (\Throwable $e) {
            $this->logger->error("Failed to claim article", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get full article for editing
     *
     * @param int $articleId
     * @return array|null
     */
    public function getArticleForEdit(int $articleId): ?array
    {
        try {
            $sql = "
                SELECT wa.id, wa.title, wa.slug, wa.excerpt, wa.content,
                       wa.persona_id, wp.name as persona_name, wp.system_prompt as persona_prompt,
                       wa.status, wa.safety_scan_status, wa.safety_score, wa.safety_flags,
                       wa.editor_id, wa.claimed_at, wa.review_notes,
                       wa.anomaly_id, wa_anomaly.detection_type, wa_anomaly.severity,
                       wa_anomaly.magnitude, wa_anomaly.change_percentage,
                       a.name as artist_name
                FROM writer_articles wa
                LEFT JOIN writer_personas wp ON wa.persona_id = wp.id
                LEFT JOIN writer_anomalies wa_anomaly ON wa.anomaly_id = wa_anomaly.id
                LEFT JOIN artists a ON wa_anomaly.artist_id = a.id
                WHERE wa.id = :id
            ";

            $stmt = $this->read->prepare($sql);
            $stmt->execute([':id' => $articleId]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        } catch (\Throwable $e) {
            $this->logger->error("Failed to fetch article for edit", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Update article content during editing
     *
     * @param int $articleId
     * @param int $editorId
     * @param array $changes
     * @return bool
     */
    public function updateArticle(int $articleId, int $editorId, array $changes): bool
    {
        try {
            $updateFields = [];
            $params = [':id' => $articleId, ':editor_id' => $editorId];

            if (isset($changes['title'])) {
                $updateFields[] = "title = :title";
                $params[':title'] = $changes['title'];
            }

            if (isset($changes['excerpt'])) {
                $updateFields[] = "excerpt = :excerpt";
                $params[':excerpt'] = $changes['excerpt'];
            }

            if (isset($changes['content'])) {
                $updateFields[] = "content = :content";
                $params[':content'] = $changes['content'];
            }

            if (isset($changes['review_notes'])) {
                $updateFields[] = "review_notes = :review_notes";
                $params[':review_notes'] = $changes['review_notes'];
            }

            if (empty($updateFields)) {
                return false;
            }

            $updateFields[] = "updated_at = NOW()";

            $sql = "UPDATE writer_articles SET " . implode(', ', $updateFields) . " WHERE id = :id AND editor_id = :editor_id";

            $stmt = $this->write->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() > 0) {
                $this->logger->info("Article updated", [
                    'article_id' => $articleId,
                    'editor_id' => $editorId,
                    'fields' => count($updateFields) - 1,
                ]);
                return true;
            }

            return false;

        } catch (\Throwable $e) {
            $this->logger->error("Failed to update article", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Approve article and schedule publish
     *
     * @param int $articleId
     * @param int $editorId
     * @param string|null $scheduledFor ISO datetime or null for immediate
     * @return bool
     */
    public function approveArticle(int $articleId, int $editorId, ?string $scheduledFor = null): bool
    {
        try {
            // Determine pipeline and publish time
            $article = $this->getArticleForEdit($articleId);
            if (!$article || $article['editor_id'] !== $editorId) {
                return false;
            }

            // Default: publish immediately unless scheduled
            $publishTime = $scheduledFor ?? 'NOW()';
            if ($publishTime !== 'NOW()') {
                $publishTime = "'" . date('Y-m-d H:i:s', strtotime($scheduledFor)) . "'";
            }

            $sql = "
                UPDATE writer_articles
                SET status = 'approved',
                    reviewed_by_id = :editor_id,
                    reviewed_at = NOW()
                WHERE id = :id AND editor_id = :editor_id
            ";

            $stmt = $this->write->prepare($sql);
            $stmt->execute([
                ':editor_id' => $editorId,
                ':id' => $articleId,
            ]);

            if ($stmt->rowCount() > 0) {
                // If auto-hype pipeline, create publish schedule
                if ($article['publishing_pipeline'] === 'auto_hype') {
                    $this->createPublishSchedule($articleId, $scheduledFor ?? date('Y-m-d H:i:s'));
                }

                $this->logger->info("Article approved", [
                    'article_id' => $articleId,
                    'editor_id' => $editorId,
                    'scheduled_for' => $scheduledFor,
                ]);

                return true;
            }

            return false;

        } catch (\Throwable $e) {
            $this->logger->error("Failed to approve article", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Reject article with reason
     *
     * @param int $articleId
     * @param int $editorId
     * @param string $reason
     * @param array|null $categories Rejection categories
     * @return bool
     */
    public function rejectArticle(int $articleId, int $editorId, string $reason, ?array $categories = null): bool
    {
        try {
            $sql = "
                UPDATE writer_articles
                SET status = 'rejected',
                    rejection_reason = :reason,
                    rejection_categories = :categories,
                    reviewed_by_id = :editor_id,
                    reviewed_at = NOW()
                WHERE id = :id AND editor_id = :editor_id
            ";

            $stmt = $this->write->prepare($sql);
            $stmt->execute([
                ':reason' => $reason,
                ':categories' => json_encode($categories ?? ['admin_decision']),
                ':editor_id' => $editorId,
                ':id' => $articleId,
            ]);

            if ($stmt->rowCount() > 0) {
                $this->logger->info("Article rejected", [
                    'article_id' => $articleId,
                    'editor_id' => $editorId,
                    'reason' => $reason,
                ]);

                // Increment admin rejection metric
                $this->updateMetrics($this->getArticlePersona($articleId), 'admin_rejection');

                return true;
            }

            return false;

        } catch (\Throwable $e) {
            $this->logger->error("Failed to reject article", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Create publish schedule for auto-hype article
     */
    private function createPublishSchedule(int $articleId, string $scheduledFor): void
    {
        try {
            // Add random variance (dopamine dealer logic)
            $windowMinutes = 30;
            $variance = rand(-$windowMinutes, $windowMinutes);
            $actualSchedule = date('Y-m-d H:i:s', strtotime($scheduledFor) + ($variance * 60));

            $sql = "
                INSERT INTO writer_publish_schedule (
                    article_id, scheduled_for, scheduled_timezone,
                    publish_window_minutes, publish_status,
                    schedule_reason
                ) VALUES (
                    :article_id, :scheduled_for, 'UTC',
                    :window_minutes, 'scheduled',
                    'Auto-hype approval'
                )
                ON DUPLICATE KEY UPDATE
                    scheduled_for = :scheduled_for,
                    publish_status = 'scheduled'
            ";

            $stmt = $this->write->prepare($sql);
            $stmt->execute([
                ':article_id' => $articleId,
                ':scheduled_for' => $actualSchedule,
                ':window_minutes' => $windowMinutes,
            ]);

        } catch (\Throwable $e) {
            $this->logger->error("Failed to create publish schedule", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get article persona for metrics
     */
    private function getArticlePersona(int $articleId): ?int
    {
        try {
            $sql = "SELECT persona_id FROM writer_articles WHERE id = :id";
            $stmt = $this->read->prepare($sql);
            $stmt->execute([':id' => $articleId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? (int)$result['persona_id'] : null;

        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Update metrics for persona
     */
    private function updateMetrics(int $personaId, string $metricType): void
    {
        try {
            $today = date('Y-m-d');

            if ($metricType === 'admin_rejection') {
                $sql = "
                    INSERT INTO writer_generation_metrics (
                        metric_date, persona_id, articles_rejected
                    ) VALUES (
                        :date, :persona_id, 1
                    )
                    ON DUPLICATE KEY UPDATE
                        articles_rejected = articles_rejected + 1
                ";

                $stmt = $this->write->prepare($sql);
                $stmt->execute([':date' => $today, ':persona_id' => $personaId]);
            }

        } catch (\Throwable $e) {
            $this->logger->error("Failed to update metrics", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get article stats for dashboard
     */
    public function getStats(): array
    {
        try {
            $sql = "
                SELECT
                    COUNT(CASE WHEN editor_id IS NULL AND status IN ('draft', 'pending_review') THEN 1 END) as pending_claimed,
                    COUNT(CASE WHEN status = 'published' AND published_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as published_today,
                    COUNT(CASE WHEN status = 'rejected' AND reviewed_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as rejected_today,
                    COUNT(CASE WHEN safety_scan_status = 'flagged' THEN 1 END) as flagged_for_safety
                FROM writer_articles
            ";

            $stmt = $this->read->prepare($sql);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        } catch (\Throwable $e) {
            $this->logger->error("Failed to fetch stats", ['error' => $e->getMessage()]);
            return [];
        }
    }
}
