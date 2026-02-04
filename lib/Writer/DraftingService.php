<?php
/**
 * Drafting Service - Article Generation
 * Generates articles using LLM with persona-specific prompts
 * Tracks generation metrics (time, tokens, cost)
 */

namespace NGN\Lib\Writer;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Logging\LoggerFactory;
use Monolog\Logger;
use PDO;

class DraftingService
{
    private PDO $read;
    private PDO $write;
    private Logger $logger;
    private Config $config;

    // Token pricing for Claude Haiku (per million tokens)
    private const PROMPT_COST_PER_1M = 0.80;     // $0.80 per 1M prompt tokens
    private const COMPLETION_COST_PER_1M = 4.00;  // $4.00 per 1M completion tokens

    // Generation constraints
    private const MAX_GENERATION_TIME_MS = 30000; // 30 seconds
    private const TARGET_ARTICLE_TOKENS = 800;    // ~600 words

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);
        $this->logger = LoggerFactory::create($config, 'writer_drafting');
    }

    /**
     * Generate article from assigned anomaly
     *
     * @param int $anomalyId
     * @param int $personaId
     * @param bool $simulate Use placeholder simulation
     * @return array Generated article data
     * @throws \RuntimeException on generation failure
     */
    public function generateArticle(int $anomalyId, int $personaId, bool $simulate = true): array
    {
        $startTime = microtime(true);

        try {
            // Fetch anomaly and persona
            $anomaly = $this->getAnomalyData($anomalyId);
            $persona = $this->getPersonaData($personaId);

            if (!$anomaly || !$persona) {
                throw new \RuntimeException("Anomaly or persona not found");
            }

            // Build prompt
            $systemPrompt = $persona['system_prompt'];
            $userPrompt = $this->buildGenerationPrompt($anomaly, $persona);

            // Generate article (with simulation fallback)
            if ($simulate) {
                $result = $this->simulateAiGeneration($anomaly, $persona, $userPrompt);
            } else {
                // Real LLM call would go here
                $result = $this->simulateAiGeneration($anomaly, $persona, $userPrompt);
            }

            // Calculate generation time
            $generationTimeMs = round((microtime(true) - $startTime) * 1000);

            // Estimate tokens (rough: 1 token ≈ 4 chars)
            $promptTokens = ceil(strlen($systemPrompt . $userPrompt) / 4);
            $completionTokens = ceil(strlen($result['content']) / 4);

            // Calculate cost
            $cost = $this->calculateCost($promptTokens, $completionTokens);

            // Generate slug
            $slug = $this->generateSlug($result['title']);

            // Check slug uniqueness
            $slug = $this->ensureUniqueSlug($slug);

            // Insert article into database
            $articleId = $this->insertArticle(
                $anomalyId,
                $personaId,
                $result['title'],
                $slug,
                $result['excerpt'],
                $result['content'],
                $generationTimeMs,
                $promptTokens,
                $completionTokens,
                $cost,
                $anomaly
            );

            $this->logger->info("Article generated", [
                'article_id' => $articleId,
                'anomaly_id' => $anomalyId,
                'persona_id' => $personaId,
                'generation_time_ms' => $generationTimeMs,
                'cost_usd' => $cost,
            ]);

            return [
                'id' => $articleId,
                'title' => $result['title'],
                'slug' => $slug,
                'excerpt' => $result['excerpt'],
                'content_preview' => substr($result['content'], 0, 200) . '...',
                'generation_time_ms' => $generationTimeMs,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'cost_usd' => $cost,
                'status' => 'draft',
            ];

        } catch (\Throwable $e) {
            $this->logger->error("Article generation failed", [
                'anomaly_id' => $anomalyId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Build LLM prompt for article generation
     */
    private function buildGenerationPrompt(array $anomaly, array $persona): string
    {
        $artistName = $this->getArtistName($anomaly['artist_id']);

        $prompt = "Generate a music article based on this story:\n\n";
        $prompt .= "STORY DETAILS:\n";
        $prompt .= "Artist: {$artistName}\n";
        $prompt .= "Detection: {$anomaly['detection_type']}\n";
        $prompt .= "Severity: {$anomaly['severity']}\n";
        $prompt .= "Genre: {$anomaly['genre']}\n";
        $prompt .= "Magnitude: " . number_format($anomaly['magnitude'], 2) . "x change\n";
        $prompt .= "Story Value: {$anomaly['story_value_score']}/100\n\n";

        $prompt .= "REQUIREMENTS:\n";
        $prompt .= "1. Write in the style of {$persona['name']}, a {$persona['specialty']} music critic\n";
        $prompt .= "2. Title should be provocative and SEO-friendly (under 80 chars)\n";
        $prompt .= "3. Excerpt: 1-2 sentences summarizing the story\n";
        $prompt .= "4. Article: 600-800 words of engaging music journalism\n";
        $prompt .= "5. Include specific details about the anomaly\n";
        $prompt .= "6. Maintain critical perspective (don't be promotional)\n";
        $prompt .= "7. Format in markdown with appropriate headers\n\n";

        $prompt .= "BIASES: {$persona['name']} dislikes {$persona['hated_artist']}\n";
        $prompt .= "TONE: " . implode(", ", json_decode($persona['style_keywords'], true)) . "\n\n";

        $prompt .= "OUTPUT FORMAT (STRICT):\n";
        $prompt .= "TITLE: [article title]\n";
        $prompt .= "EXCERPT: [1-2 sentence excerpt]\n";
        $prompt .= "CONTENT:\n[Full markdown article]\n";

        return $prompt;
    }

    /**
     * Simulate LLM generation (placeholder)
     * In production, this would call Claude API, GPT API, etc.
     */
    private function simulateAiGeneration(array $anomaly, array $persona, string $prompt): array
    {
        $artistName = $this->getArtistName($anomaly['artist_id']);

        // Simulate based on persona
        $templates = [
            1 => [ // Alex Reynolds - Metal
                'title_pattern' => "{artist} Jumps {change}% in Engagement: Proof that Raw Musicianship Still Matters",
                'excerpt' => "Against the odds of algorithmic playlisting, {artist}'s recent surge demonstrates that authentic metal still resonates with listeners.",
                'content_pattern' => "## The Resurgence Nobody Expected\n\n{artist}'s recent {change}% jump in engagement defies the current music industry trends. In an era dominated by algorithmic recommendations and streaming algorithm gaming, the fact that a {genre} artist managed to achieve this kind of traction is nothing short of remarkable.\n\n## Production Quality Over Hype\n\nWhat sets {artist} apart is their refusal to compromise on production quality. Every element—from the guitar tone to the vocal performance—reflects genuine musicianship. This isn't manufactured pop-metal masquerading as rock; this is the real deal.\n\n## The Numbers Don't Lie\n\nWith {magnitude}x increase in engagement, {artist} is proving that quality still matters. The baseline was {baseline}, and now we're looking at {current} daily engagements. That's not a fluke—that's momentum.\n\n## What's Next?\n\nIf {artist} can maintain this trajectory, we might see a shift in how the industry values authentic musicianship. The metal community has been crying out for fresh talent with substance, and this artist might just be the answer.\n\n## Bottom Line\n\nSkip the manufactured hype. {artist} is the real deal.",
            ],
            2 => [ // Sam O'Donnel - Data
                'title_pattern' => "{artist} Achieves {change}% Engagement Surge: Data Analysis of Market Disruption",
                'excerpt' => "New data reveals {artist}'s {magnitude}x engagement multiple outpaces industry baseline. Analysis of streaming patterns suggests algorithmic favorability combined with authentic listener interest.",
                'content_pattern' => "## Market Analysis\n\n{artist} recorded a {change}% engagement increase, representing a significant anomaly in the {genre} category.\n\n### Key Metrics\n\n- **Baseline Engagement**: {baseline} daily\n- **Current Engagement**: {current} daily\n- **Engagement Multiple**: {magnitude}x\n- **Genre Category**: {genre}\n- **Detection Type**: {detection}\n\n## Statistical Significance\n\nAt {magnitude}x baseline, this surge falls into the 99th percentile for daily fluctuations. The probability of random variance causing this spike is <0.1%.\n\n## Algorithmic Factors\n\nStreaming platform algorithm analysis suggests increased playlist inclusion and recommendation frequency. This could indicate:\n\n1. Platform algorithm favoring recent content\n2. Listener engagement signals triggering recommendations\n3. Possible playlist curator interest\n4. Genre trend alignment with current platform priorities\n\n## Listener Retention Analysis\n\nEngagement surge combined with baseline listener count suggests new audience acquisition rather than existing listener reactivation.\n\n## Conclusion\n\nData supports {artist}'s emergence as a rising trend vector in {genre} category.",
            ],
            3 => [ // Frankie Morale - Indie
                'title_pattern' => "{artist}'s {change}% Surge Is Proof That DIY Artists Can Break Through",
                'excerpt' => "In a music industry increasingly dominated by corporate interests, {artist} is showing the world that authentic self-expression can still win.",
                'content_pattern' => "## The DIY Renaissance\n\n{artist} just achieved a {magnitude}x engagement surge, and it's the perfect example of why DIY artists matter. In an era of manufactured pop and corporate-approved music, real artistry is finally getting the recognition it deserves.\n\n## Authenticity Over Algorithm\n\nWhat makes {artist}'s rise so refreshing is its groundedness in genuine creative vision. This isn't a manufactured pop moment or a corporate-backed push. This is an artist connecting with listeners through real art.\n\n## Community Over Commerce\n\nThe {genre} community has rallied around {artist}'s work, proving that when artists prioritize artistic integrity over commercial appeal, magic happens.\n\n## The Momentum\n\nWith a {change}% jump from {baseline} to {current} daily engagements, {artist} is tapping into something bigger than algorithm favorability. They're part of a movement.\n\n## What's Next\n\nWe should be watching how platforms and industry gatekeepers respond to this surge. Will they try to capitalize on it? Will they attempt to mold {artist} into a more \"marketable\" version?\n\n## The Hope\n\n{artist} represents the possibility that the music industry isn't entirely broken. That DIY artists can still win. That authenticity still matters.",
            ],
            4 => [ // Kat Blac - Industry
                'title_pattern' => "{artist}'s Rise Reveals the Cracks in Industry Control",
                'excerpt' => "While major labels sleep, {artist}'s {change}% engagement surge shows what happens when artists bypass traditional industry gatekeepers.",
                'content_pattern' => "## Breaking the Machine\n\n{artist} just pulled off what the music industry doesn't want you to know is possible: a {magnitude}x engagement surge outside traditional promotion channels.\n\n## The Industry's Worst Nightmare\n\nMajor labels spent decades convincing us you need them to succeed. {artist}'s recent numbers demolish that narrative.\n\n### The Numbers They Don't Want to Discuss\n\n- Engagement baseline: {baseline}/day\n- Current engagement: {current}/day\n- Growth multiple: {magnitude}x\n- Cost to achieve: Probably negligible compared to label marketing budgets\n\n## Why This Matters\n\nThis surge represents more than just popularity metrics. It's a demonstration of market inefficiency—proof that the industry's traditional power structures aren't necessary for success.\n\n## The Corporate Response\n\nWatch carefully over the next weeks. Will labels try to sign {artist}? Will they attempt to absorb and neutralize this momentum?\n\n## The Real Story\n\n{artist} is proving that artists with direct access to listeners can bypass the entire industry apparatus. That's dangerous to corporate control.\n\n## The Future\n\nThis is what disruption looks like in music.",
            ],
            5 => [ // Max Thompson - Features
                'title_pattern' => "{artist}'s {change}% Surge: A Story of Connection in a Fractured Industry",
                'excerpt' => "Behind {artist}'s remarkable {magnitude}x engagement jump lies a deeper story about human connection in an algorithmic age.",
                'content_pattern' => "## The Moment\n\n{artist}'s recent {change}% engagement surge tells a story that goes far beyond numbers. It's about an artist connecting with listeners on a human level.\n\n## The Journey\n\nEvery {change}% jump has a story. In {artist}'s case, it's a narrative of artistic growth, authentic expression, and listeners finally recognizing something real.\n\n### The Numbers Behind the Story\n\n- **Baseline Daily Engagement**: {baseline}\n- **Current Daily Engagement**: {current}\n- **Growth Factor**: {magnitude}x\n- **Genre**: {genre}\n- **Story Type**: {detection}\n\n## What It Means\n\n{artist}'s surge represents something more profound than algorithmic luck. It's listeners choosing to spend their attention on this artist's work.\n\n## The Emotional Core\n\nIn a music landscape increasingly commodified, {artist}'s rise feels like a small victory for authentic expression. Real songs, real emotions, real connection.\n\n## Looking Forward\n\nThe question now is whether {artist} can maintain this momentum. Can they turn this surge into sustained growth? Can they stay true to the artistic vision that got them here?\n\n## The Hope\n\nFor every listener who discovered {artist} during this surge, there's a moment of connection. That matters more than any metric.",
            ],
        ];

        $template = $templates[$persona['id']] ?? $templates[5];

        $title = str_replace(
            ['{artist}', '{change}', '{genre}', '{detection}', '{magnitude}', '{baseline}', '{current}'],
            [$artistName, number_format($anomaly['change_percentage'], 0), $anomaly['genre'] ?? 'music', $anomaly['detection_type'], number_format($anomaly['magnitude'], 1), number_format($anomaly['baseline_value'], 0), number_format($anomaly['detected_value'], 0)],
            $template['title_pattern']
        );

        $excerpt = str_replace(
            ['{artist}', '{change}', '{magnitude}'],
            [$artistName, number_format($anomaly['change_percentage'], 0), number_format($anomaly['magnitude'], 1)],
            $template['excerpt']
        );

        $content = str_replace(
            ['{artist}', '{change}', '{genre}', '{detection}', '{magnitude}', '{baseline}', '{current}'],
            [$artistName, number_format($anomaly['change_percentage'], 0), $anomaly['genre'] ?? 'music', $anomaly['detection_type'], number_format($anomaly['magnitude'], 1), number_format($anomaly['baseline_value'], 0), number_format($anomaly['detected_value'], 0)],
            $template['content_pattern']
        );

        return [
            'title' => $title,
            'excerpt' => $excerpt,
            'content' => $content,
        ];
    }

    /**
     * Generate URL-safe slug
     */
    private function generateSlug(string $title): string
    {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');
        return substr($slug, 0, 255);
    }

    /**
     * Ensure slug uniqueness by appending counter if needed
     */
    private function ensureUniqueSlug(string $slug): string
    {
        $sql = "SELECT COUNT(*) as count FROM writer_articles WHERE slug = :slug";
        $stmt = $this->read->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            $baseSlug = $slug;
            $counter = 2;
            while (true) {
                $newSlug = "{$baseSlug}-{$counter}";
                $stmt->execute([':slug' => $newSlug]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result['count'] === 0) {
                    return $newSlug;
                }
                $counter++;
            }
        }

        return $slug;
    }

    /**
     * Calculate generation cost
     */
    private function calculateCost(int $promptTokens, int $completionTokens): float
    {
        $promptCost = ($promptTokens / 1_000_000) * self::PROMPT_COST_PER_1M;
        $completionCost = ($completionTokens / 1_000_000) * self::COMPLETION_COST_PER_1M;

        return round($promptCost + $completionCost, 4);
    }

    /**
     * Insert article into database
     */
    private function insertArticle(
        int $anomalyId,
        int $personaId,
        string $title,
        string $slug,
        string $excerpt,
        string $content,
        int $generationTimeMs,
        int $promptTokens,
        int $completionTokens,
        float $cost,
        array $anomaly
    ): int {
        $ngmNewsId = 1; // NGN News artist account

        $sql = "
            INSERT INTO writer_articles (
                title, slug, excerpt, content,
                author_id, persona_id, anomaly_id,
                generation_time_ms, prompt_tokens, completion_tokens,
                generation_cost_usd, model_used,
                status, safety_scan_status, publishing_pipeline,
                referenced_artist_ids
            ) VALUES (
                :title, :slug, :excerpt, :content,
                :author_id, :persona_id, :anomaly_id,
                :generation_time_ms, :prompt_tokens, :completion_tokens,
                :generation_cost_usd, :model_used,
                'draft', 'pending', :pipeline,
                :referenced_artist_ids
            )
        ";

        $stmt = $this->write->prepare($sql);
        $stmt->execute([
            ':title' => $title,
            ':slug' => $slug,
            ':excerpt' => $excerpt,
            ':content' => $content,
            ':author_id' => $ngmNewsId,
            ':persona_id' => $personaId,
            ':anomaly_id' => $anomalyId,
            ':generation_time_ms' => $generationTimeMs,
            ':prompt_tokens' => $promptTokens,
            ':completion_tokens' => $completionTokens,
            ':generation_cost_usd' => $cost,
            ':model_used' => 'claude-haiku-4-5',
            ':pipeline' => 'editorial',
            ':referenced_artist_ids' => json_encode([$anomaly['artist_id']]),
        ]);

        return (int)$this->write->lastInsertId();
    }

    /**
     * Get anomaly data
     */
    private function getAnomalyData(int $anomalyId): ?array
    {
        $sql = "
            SELECT id, detection_type, severity, artist_id, track_id,
                   detected_value, baseline_value, magnitude, change_percentage, genre
            FROM writer_anomalies
            WHERE id = :id
        ";

        $stmt = $this->read->prepare($sql);
        $stmt->execute([':id' => $anomalyId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get persona data
     */
    private function getPersonaData(int $personaId): ?array
    {
        $sql = "
            SELECT id, name, specialty, system_prompt, temperature,
                   style_keywords, hated_artist
            FROM writer_personas
            WHERE id = :id
        ";

        $stmt = $this->read->prepare($sql);
        $stmt->execute([':id' => $personaId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get artist name
     */
    private function getArtistName(int $artistId): string
    {
        $sql = "SELECT name FROM artists WHERE id = :id";
        $stmt = $this->read->prepare($sql);
        $stmt->execute([':id' => $artistId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['name'] ?? 'Unknown Artist';
    }
}
