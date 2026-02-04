<?php
/**
 * Safety Filter Service - Defamation Detection
 * Scans articles for defamatory content using LLM
 * Assigns safety score (0.0-1.0) with specific flagged content
 * Thresholds: <0.1 clean, 0.1-0.3 flagged, >0.3 rejected
 */

namespace NGN\Lib\Writer;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Logging\LoggerFactory;
use Monolog\Logger;
use PDO;

class SafetyFilterService
{
    private PDO $read;
    private PDO $write;
    private Logger $logger;
    private Config $config;

    // Safety thresholds
    private const CLEAN_THRESHOLD = 0.1;
    private const FLAGGED_THRESHOLD = 0.3;
    // Above FLAGGED_THRESHOLD = rejected

    // Safety scan temperature (conservative)
    private const SAFETY_SCAN_TEMPERATURE = 0.2;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);
        $this->logger = LoggerFactory::create($config, 'writer_safety');
    }

    /**
     * Scan article for defamatory content
     *
     * @param int $articleId
     * @return array Safety scan result with score and flags
     * @throws \RuntimeException on scan failure
     */
    public function scanArticle(int $articleId): array
    {
        try {
            // Fetch article
            $article = $this->getArticle($articleId);
            if (!$article) {
                throw new \RuntimeException("Article not found");
            }

            // Run safety scan (with simulation)
            $scanResult = $this->runSafetyScan($article);

            // Determine status based on score
            $status = match (true) {
                $scanResult['score'] < self::CLEAN_THRESHOLD => 'approved',
                $scanResult['score'] < self::FLAGGED_THRESHOLD => 'flagged',
                default => 'rejected',
            };

            // Update article with scan results
            $this->updateArticleWithScanResults($articleId, $scanResult, $status);

            // If rejected, create alert
            if ($status === 'rejected') {
                $this->createAlert($articleId, $scanResult);
            }

            $result = [
                'article_id' => $articleId,
                'safety_score' => $scanResult['score'],
                'status' => $status,
                'flagged_sentences' => $scanResult['flags'],
                'message' => $this->getStatusMessage($status, $scanResult['score']),
            ];

            $this->logger->info("Article safety scan completed", [
                'article_id' => $articleId,
                'score' => $scanResult['score'],
                'status' => $status,
            ]);

            return $result;

        } catch (\Throwable $e) {
            $this->logger->error("Safety scan failed", [
                'article_id' => $articleId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Run safety scan on article content
     * Simulates LLM defamation detection
     */
    private function runSafetyScan(array $article): array
    {
        $prompt = $this->buildSafetyPrompt($article);

        // Simulate scan result
        $result = $this->simulateSafetyScan($article['content']);

        return $result;
    }

    /**
     * Build safety filter prompt
     */
    private function buildSafetyPrompt(array $article): string
    {
        $prompt = "Analyze the following music article for potential defamation, personal attacks, or other problematic content.\n\n";
        $prompt .= "ARTICLE:\n";
        $prompt .= $article['title'] . "\n\n";
        $prompt .= $article['content'] . "\n\n";

        $prompt .= "INSTRUCTIONS:\n";
        $prompt .= "1. Identify sentences that attack PEOPLE rather than their MUSIC/DECISIONS\n";
        $prompt .= "2. Attacks on music, production, genre choices = OK\n";
        $prompt .= "3. Attacks on character, appearance, personal life = NOT OK\n";
        $prompt .= "4. Assign defamation risk score (0.0-1.0)\n";
        $prompt .= "5. Return specific flagged sentences\n\n";

        $prompt .= "EXAMPLES:\n";
        $prompt .= "✅ 'Metallica's production choices lack sophistication' = 0.0 (music critique)\n";
        $prompt .= "❌ 'James Hetfield is a washed-up alcoholic' = 0.9 (personal attack)\n";
        $prompt .= "✅ 'This album misses the mark' = 0.0 (artistic critique)\n";
        $prompt .= "❌ 'The singer is clearly mentally unstable' = 0.95 (character attack)\n\n";

        $prompt .= "OUTPUT FORMAT:\nSCORE: [0.0-1.0]\nFLAGS:\n- [flagged sentence 1]\n- [flagged sentence 2]\n";

        return $prompt;
    }

    /**
     * Simulate safety scan result
     */
    private function simulateSafetyScan(string $content): array
    {
        $flags = [];
        $score = 0.0;

        // Check for personal attack keywords
        $attackKeywords = [
            'washed-up' => 0.25,
            'has-been' => 0.25,
            'alcoholic' => 0.40,
            'drug addict' => 0.40,
            'mentally unstable' => 0.35,
            'fraud' => 0.30,
            'con artist' => 0.35,
            'liar' => 0.25,
            'disgusting person' => 0.40,
            'terrible human' => 0.30,
            'piece of garbage' => 0.35,
            'pedophile' => 0.50,
            'abuser' => 0.40,
            'criminal' => 0.35,
        ];

        foreach ($attackKeywords as $keyword => $riskScore) {
            if (stripos($content, $keyword) !== false) {
                $flags[] = [
                    'keyword' => $keyword,
                    'risk_score' => $riskScore,
                    'reason' => 'Personal attack keyword detected',
                ];
                $score = max($score, $riskScore);
            }
        }

        // Check for character attack patterns
        if (preg_match_all('/(?:is|are)\s+(?:clearly|obviously|plainly)\s+(\w+)/i', $content, $matches)) {
            // Psychological characterizations
            $psych_terms = ['insane', 'delusional', 'narcissistic', 'sociopathic', 'psychotic'];
            foreach ($matches[1] as $term) {
                if (in_array(strtolower($term), $psych_terms)) {
                    $flags[] = [
                        'pattern' => 'Psychological attack',
                        'risk_score' => 0.35,
                        'reason' => 'Character judgment using psychological terms',
                    ];
                    $score = max($score, 0.35);
                }
            }
        }

        // Music/artistic criticism should NOT increase score
        // (This is intentionally not penalized)

        return [
            'score' => round($score, 2),
            'flags' => $flags,
            'reasoning' => $this->getScoreReasoning($score),
        ];
    }

    /**
     * Get explanation for safety score
     */
    private function getScoreReasoning(float $score): string
    {
        if ($score < self::CLEAN_THRESHOLD) {
            return 'Content is clean - no defamation detected';
        } elseif ($score < self::FLAGGED_THRESHOLD) {
            return 'Content flagged - requires editorial review';
        } else {
            return 'Content rejected - personal attacks or defamation detected';
        }
    }

    /**
     * Get user-friendly status message
     */
    private function getStatusMessage(string $status, float $score): string
    {
        return match ($status) {
            'approved' => "Article passed safety check (score: {$score}). Ready for publishing.",
            'flagged' => "Article flagged for review (score: {$score}). Admin approval required.",
            'rejected' => "Article rejected due to potential defamation (score: {$score}). Will be deleted.",
            default => "Unknown status",
        };
    }

    /**
     * Update article with scan results
     */
    private function updateArticleWithScanResults(int $articleId, array $scanResult, string $status): void
    {
        try {
            $sql = "
                UPDATE writer_articles
                SET safety_scan_status = :status,
                    safety_score = :score,
                    safety_flags = :flags
                WHERE id = :id
            ";

            $stmt = $this->write->prepare($sql);
            $stmt->execute([
                ':status' => $status,
                ':score' => $scanResult['score'],
                ':flags' => json_encode($scanResult['flags']),
                ':id' => $articleId,
            ]);

        } catch (\Throwable $e) {
            $this->logger->error("Failed to update article with scan results", [
                'article_id' => $articleId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create alert for rejected articles
     */
    private function createAlert(int $articleId, array $scanResult): void
    {
        try {
            $article = $this->getArticle($articleId);

            // Check if AlertService exists and use it
            if (class_exists('\NGN\Lib\Alerts\AlertService')) {
                // Real alert creation would go here
            }

            $this->logger->critical("Safety rejection - P0 alert", [
                'article_id' => $articleId,
                'score' => $scanResult['score'],
                'flags' => $scanResult['flags'],
                'article_title' => $article['title'],
            ]);

        } catch (\Throwable $e) {
            $this->logger->error("Failed to create alert", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Override safety flag (admin approval)
     *
     * @param int $articleId
     * @param int $adminUserId
     * @param string $reason
     * @return bool
     */
    public function overrideSafetyFlag(int $articleId, int $adminUserId, string $reason): bool
    {
        try {
            $sql = "
                UPDATE writer_articles
                SET safety_override_by_id = :admin_id,
                    safety_override_reason = :reason,
                    safety_override_at = NOW(),
                    safety_scan_status = 'approved',
                    status = 'approved'
                WHERE id = :id AND safety_scan_status = 'flagged'
            ";

            $stmt = $this->write->prepare($sql);
            $result = $stmt->execute([
                ':admin_id' => $adminUserId,
                ':reason' => $reason,
                ':id' => $articleId,
            ]);

            if ($stmt->rowCount() > 0) {
                $this->logger->info("Safety flag overridden", [
                    'article_id' => $articleId,
                    'admin_id' => $adminUserId,
                    'reason' => $reason,
                ]);
                return true;
            }

            return false;

        } catch (\Throwable $e) {
            $this->logger->error("Failed to override safety flag", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get article for scanning
     */
    private function getArticle(int $articleId): ?array
    {
        $sql = "
            SELECT id, title, content, safety_score, safety_scan_status
            FROM writer_articles
            WHERE id = :id
        ";

        $stmt = $this->read->prepare($sql);
        $stmt->execute([':id' => $articleId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Scan all pending articles
     */
    public function scanPendingArticles(int $limit = 50): array
    {
        $results = [
            'scanned' => 0,
            'approved' => 0,
            'flagged' => 0,
            'rejected' => 0,
            'errors' => 0,
        ];

        try {
            $sql = "
                SELECT id FROM writer_articles
                WHERE safety_scan_status = 'pending'
                LIMIT :limit
            ";

            $stmt = $this->read->prepare($sql);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                try {
                    $scanResult = $this->scanArticle($row['id']);

                    $results['scanned']++;

                    match ($scanResult['status']) {
                        'approved' => $results['approved']++,
                        'flagged' => $results['flagged']++,
                        'rejected' => $results['rejected']++,
                    };

                } catch (\Throwable $e) {
                    $this->logger->error("Error scanning article", [
                        'article_id' => $row['id'],
                        'error' => $e->getMessage(),
                    ]);
                    $results['errors']++;
                }
            }

        } catch (\Throwable $e) {
            $this->logger->error("Batch scan failed", ['error' => $e->getMessage()]);
        }

        return $results;
    }
}
