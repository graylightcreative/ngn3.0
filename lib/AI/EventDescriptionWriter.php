<?php

// Ensure necessary NGN bootstrap and configurations are loaded.
require_once __DIR__ . '/../../../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Env;
use NGN\Lib\Http\{Request, Response, Router, Json, Cors, RateLimiter};
use NGN\Lib\Logging\LoggerFactory;
use NGN\Lib\Auth\TokenService;
use NGN\Lib\Sparks\SparksService;
use NGN\Lib\Sparks\Exception\InsufficientFundsException;

/**
 * Service for generating event descriptions using AI.
 */
class EventDescriptionWriter
{
    private Config $config;
    private SparksService $sparkService;

    /**
     * Constructor.
     *
     * @param Config $config Application configuration.
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->sparkService = new SparksService($this->config);
    }

    /**
     * Generates event description variants for a given user.
     *
     * @param int $userId The ID of the user making the request.
     * @param array $params Associative array containing event description parameters:
     *                      - 'headliner' (string, required)
     *                      - 'date' (string, required, YYYY-MM-DD format)
     *                      - 'venue_name' (string, required)
     *                      - 'vibe' (string, e.g., 'Hype', 'Chill', 'Emotional', 'Dark', required)
     * @return array An array of 3 event description variants (Social Hype, Press Release, Short Blast).
     * @throws InsufficientFundsException If the user has insufficient Sparks.
     * @throws InvalidArgumentException If input parameters are invalid.
     * @throws RuntimeException If AI generation fails.
     */
    public function generate(int $userId, array $params): array
    {
        // --- Parameter Validation ---
        $headliner = $params['headliner'] ?? null;
        $date = $params['date'] ?? null;
        $venueName = $params['venue_name'] ?? null;
        $vibe = $params['vibe'] ?? null;

        if (empty($headliner)) {
            throw new \InvalidArgumentException("Headliner is required.");
        }
        if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \InvalidArgumentException("Date is required in YYYY-MM-DD format.");
        }
        if (empty($venueName)) {
            throw new \InvalidArgumentException("Venue name is required.");
        }
        if (empty($vibe) || !in_array($vibe, ['Hype', 'Chill', 'Emotional', 'Dark'])) {
            throw new \InvalidArgumentException("Valid vibe ('Hype', 'Chill', 'Emotional', 'Dark') is required.");
        }

        // --- Spark Deduction ---
        $sparksCost = 3;
        try {
            $this->sparkService->deduct($userId, $sparksCost, [
                'reason' => 'ai_event_writer',
                'headliner' => $headliner,
                'venue' => $venueName
            ]);
        } catch (InsufficientFundsException $e) {
            // Re-throw to be caught by the API route handler for a 402 response.
            throw $e;
        } catch (\Throwable $e) {
            // Catch other potential errors during spark deduction (e.g., DB issues).
            throw new \RuntimeException("Failed to process Sparks for event description generation: " . $e->getMessage());
        }

        // --- AI Prompt Construction ---
        $prompt = "You are an expert event promoter crafting engaging descriptions for live music events.\n";
        $prompt .= "Headliner: " . htmlspecialchars($headliner) . "\n";
        $prompt .= "Date: " . htmlspecialchars($date) . "\n";
        $prompt .= "Venue: " . htmlspecialchars($venueName) . "\n";
        $prompt .= "Vibe: " . htmlspecialchars($vibe) . "\n\n";
        $prompt .= "Generate EXACTLY three distinct event description variants:
1. Social Hype: Short, punchy, uses emojis, great for Instagram stories or TikTok.
2. Press Release: Formal, informative, suitable for media outlets or official announcements.
3. Short Blast: Concise, action-oriented, ideal for SMS alerts or brief social media posts.

Ensure each variant is tailored to the specified vibe, headliner, date, and venue. Output ONLY the three descriptions, each on a new line, with no extra text or formatting.";

        // --- AI Generation Simulation ---
        $simulatedResponse = $this->simulateAiResponse($headliner, $date, $venueName, $vibe, $sparksCost);

        return $simulatedResponse;
    }

    /**
     * Simulates the AI response for event description generation.
     *
     * @param string $headliner
     * @param string $date
     * @param string $venueName
     * @param string $vibe
     * @param int $sparksCost
     * @return array
     */
    private function simulateAiResponse(string $headliner, string $date, string $venueName, string $vibe, int $sparksCost): array
    {
        $formattedDate = date('F j, Y', strtotime($date));
        $vibeLower = strtolower($vibe);

        $variants = [];

        // Social Hype Bio
        $socialHype = "🔥 Get ready! {$headliner} is hitting {$venueName} on {$formattedDate}! 🎤 Expect {$vibeLower} vibes all night long. Don't miss out! #{$headliner} #LiveMusic #{$vibe} {$venueName}";
        $variants[] = $socialHype;

        // Press Release Bio
        $pressRelease = "FOR IMMEDIATE RELEASE: {$headliner} announces upcoming performance at {$venueName} on {$formattedDate}. Experience a night of {$vibeLower} energy as the acclaimed {$genre ?? 'artist'} takes the stage. Tickets available now.";
        $variants[] = $pressRelease;

        // Short Blast Bio
        $shortBlast = "Don't miss {$headliner} live at {$venueName}, {$formattedDate}! {$vibe} vibes guaranteed. Get tickets now!";
        $variants[] = $shortBlast;

        // Pad with empty strings if fewer than 3 variants were generated
        while (count($variants) < 3) {
            $variants[] = '';
        }

        return array_slice($variants, 0, 3);
    }
}
