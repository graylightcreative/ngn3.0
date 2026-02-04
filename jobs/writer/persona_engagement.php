<?php
/**
 * Persona Engagement - Inter-Persona Debates
 * Generates debate comments from personas on top articles
 * Runs every 4 hours for reader engagement (Dopamine Dealer)
 *
 * Schedule: Every 4 hours (cron: 0 \*\/4 \* \* \*)
 * Command: php /path/to/jobs/writer/persona_engagement.php
 */

require_once __DIR__ . '/../../lib/autoload.php';
require_once __DIR__ . '/../../lib/config/config.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$logFile = __DIR__ . '/../../storage/logs/writer_engagement.log';

function logMessage(string $message, string $logFile): void
{
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);
    echo $logLine;
}

try {
    logMessage("=== Persona Engagement Starting ===", $logFile);

    $config = new Config();
    $read = ConnectionFactory::read($config);
    $write = ConnectionFactory::write($config);

    // Get top 5 published articles from last 7 days by engagement
    $sql = "
        SELECT wa.id, wa.title, wa.content, wa.persona_id, wp.name as author_persona,
               wa.total_engagement
        FROM writer_articles wa
        LEFT JOIN writer_personas wp ON wa.persona_id = wp.id
        WHERE wa.status = 'published'
          AND wa.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY wa.total_engagement DESC
        LIMIT 5
    ";

    $stmt = $read->prepare($sql);
    $stmt->execute();

    $commentsGenerated = 0;
    $commentsCost = 0.0;

    while ($article = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Skip if already has comments from other personas
        $checkSql = "SELECT COUNT(*) as count FROM writer_persona_comments WHERE article_id = :id";
        $checkStmt = $read->prepare($checkSql);
        $checkStmt->execute([':id' => $article['id']]);
        $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($checkResult['count'] >= 3) {
            logMessage("Article {$article['id']} already has sufficient comments", $logFile);
            continue;
        }

        // Get other personas to comment
        $personaSql = "SELECT id, name FROM writer_personas WHERE id != :current_id LIMIT 2";
        $personaStmt = $read->prepare($personaSql);
        $personaStmt->execute([':current_id' => $article['persona_id']]);

        while ($persona = $personaStmt->fetch(PDO::FETCH_ASSOC)) {
            // Simulate comment generation
            $comment = $this->generatePersonaComment($article, $persona);

            // Estimate cost (mock)
            $estimatedCost = 0.001; // Mock cost

            // Insert comment
            $insertSql = "
                INSERT INTO writer_persona_comments (
                    article_id, persona_id, comment_text, comment_type,
                    generation_time_ms, generation_cost_usd, is_published
                ) VALUES (
                    :article_id, :persona_id, :comment_text, :comment_type,
                    :generation_time_ms, :generation_cost_usd, 1
                )
            ";

            $insertStmt = $write->prepare($insertSql);
            $insertStmt->execute([
                ':article_id' => $article['id'],
                ':persona_id' => $persona['id'],
                ':comment_text' => $comment,
                ':comment_type' => $this->getCommentType(),
                ':generation_time_ms' => rand(100, 500),
                ':generation_cost_usd' => $estimatedCost,
            ]);

            $commentsGenerated++;
            $commentsCost += $estimatedCost;

            logMessage("Generated comment on article {$article['id']} from {$persona['name']}", $logFile);
        }
    }

    logMessage("Generated $commentsGenerated comments | Cost: \$" . number_format($commentsCost, 4), $logFile);
    logMessage("=== Persona Engagement Complete ===\n", $logFile);
    exit(0);

} catch (\Throwable $e) {
    $errorMessage = "CRITICAL ERROR in Engagement: " . $e->getMessage();
    logMessage($errorMessage, $logFile);
    error_log($errorMessage);
    exit(1);
}

/**
 * Generate persona-specific comment (mock)
 */
function generatePersonaComment(array $article, array $persona): string
{
    $templates = [
        'reply' => [
            "I see where you're going with this, but I'd argue the production quality isn't nearly as important as the raw energy.",
            "Interesting take, but you're missing the emotional weight of this release.",
            "Data supports your point, but the narrative here is what really matters.",
            "This is exactly the kind of critical thinking we need in music journalism.",
        ],
        'counter_opinion' => [
            "I have to respectfully disagree with your analysis here.",
            "While your points are valid, I think you've overlooked a crucial element.",
            "The data tells a different story than what you're proposing.",
            "I'd challenge that perspective based on what I'm seeing.",
        ],
        'additional_insight' => [
            "What's also worth noting is the broader industry context here.",
            "If we zoom out a bit, we can see this fits into a larger pattern.",
            "There's an economic angle nobody's talking about yet.",
            "The cultural implications are even more significant than the music itself.",
        ],
    ];

    $types = array_keys($templates);
    $type = $types[array_rand($types)];
    $comments = $templates[$type];

    return $comments[array_rand($comments)];
}

/**
 * Get comment type
 */
function getCommentType(): string
{
    $types = ['reply', 'counter_opinion', 'additional_insight', 'joke'];
    return $types[array_rand($types)];
}
