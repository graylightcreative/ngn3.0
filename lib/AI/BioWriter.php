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
 * Service for generating artist bios using AI.
 */
class BioWriter
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
        // Assuming SparksService can be instantiated using Config.
        $this->sparkService = new SparksService($this->config);
    }

    /**
     * Generates bio variants for an artist.
     *
     * @param int $userId The ID of the user making the request.
     * @param array $params Associative array containing bio generation parameters:
     *                      - 'artist_name' (string, required)
     *                      - 'genre' (string, optional)
     *                      - 'tone' (string, e.g., 'Professional', 'Edgy', 'Fun', required)
     * @return array An array of 3 bio variants (short, medium, long).
     * @throws InsufficientFundsException If the user has insufficient Sparks.
     * @throws InvalidArgumentException If input parameters are invalid.
     * @throws RuntimeException If AI generation fails.
     */
    public function generate(int $userId, array $params): array
    {
        // --- Parameter Validation ---
        $artistName = $params['artist_name'] ?? null;
        $genre = $params['genre'] ?? null;
        $tone = $params['tone'] ?? null;

        if (empty($artistName)) {
            throw new \InvalidArgumentException("Artist name is required.");
        }
        if (empty($tone) || !in_array($tone, ['Professional', 'Edgy', 'Fun'])) {
            throw new \InvalidArgumentException("Valid tone ('Professional', 'Edgy', 'Fun') is required.");
        }

        // --- Spark Deduction ---
        $sparksCost = 5;
        try {
            // Check balance and deduct sparks. The `deduct` method should throw InsufficientFundsException.
            // Note: The API route handler catches this specific exception.
            $this->sparkService->deduct($userId, $sparksCost, [
                'reason' => 'ai_bio_writer',
                'artist_name' => $artistName
            ]);
        } catch (InsufficientFundsException $e) {
            // Re-throw to be caught by the API route handler for a 402 response.
            throw $e;
        } catch (\Throwable $e) {
            // Catch other potential errors during spark deduction (e.g., DB issues).
            throw new \RuntimeException("Failed to process Sparks for bio generation: " . $e->getMessage());
        }

        // --- AI Prompt Construction ---
        $prompt = "You are an expert music biographer tasked with creating compelling artist bios.\n";
        $prompt .= "Artist Name: " . htmlspecialchars($artistName) . "\n";
        if (!empty($genre)) {
            $prompt .= "Genre: " . htmlspecialchars($genre) . "\n";
        }
        $prompt .= "Tone: " . htmlspecialchars($tone) . "\n\n";
        $prompt .= "Generate EXACTLY three distinct artist bio variants:
1. Short bio (approx. 2-3 sentences, impactful opening).
2. Medium bio (approx. 4-5 sentences, covers origin, sound, and key achievements).
3. Long bio (approx. 6-8 sentences, more narrative, includes influences and future outlook).

Ensure each variant is concise, engaging, and tailored to the specified tone and genre. Output ONLY the three bio variants, each on a new line, with no additional text or formatting.";

        // --- AI Generation Simulation ---
        // In a real application, this would involve calling an external AI API (e.g., Gemini).
        // For this simulation, we'll create placeholder responses.
        $simulatedResponse = $this->simulateAiResponse($artistName, $genre, $tone, $sparksCost);

        return $simulatedResponse;
    }

    /**
     * Simulates the AI response for bio generation.
     *
     * @param string $artistName
     * @param string|null $genre
     * @param string $tone
     * @param int $sparksCost
     * @return array
     */
    private function simulateAiResponse(string $artistName, ?string $genre, string $tone, int $sparksCost): array
    {
        // Placeholder AI response logic
        $bios = [];

        // Short Bio
        $shortBio = "{$artistName} is a rising force in the {$genre ?? 'music'} scene, known for their distinct {$tone} sound.\n";
        if ($tone === 'Professional') $shortBio .= "They are quickly gaining recognition for their sophisticated artistry and unique sonic landscape.";
        elseif ($tone === 'Edgy') $shortBio .= "Breaking boundaries with raw energy, {$artistName} delivers a sound that's as fierce as it is unforgettable.";
        else $shortBio .= "Get ready to groove with {$artistName} – their music is pure sunshine and good vibes!";
        $bios[] = $shortBio;

        // Medium Bio
        $mediumBio = "Hailing from [City/Region], {$artistName} has carved a niche in the {$genre ?? 'music'} world with their unique blend of captivating melodies and powerful rhythms.\n";
        $mediumBio .= "Their music, often described as {$tone}, draws inspiration from [mention influences or a descriptive phrase).\n";
        $mediumBio .= "Having recently released [mention a recent release or achievement], {$artistName} continues to push sonic boundaries and connect with audiences worldwide.";
        $bios[] = $mediumBio;

        // Long Bio
        $longBio = "Born from the vibrant {$genre ?? 'music'} scene, {$artistName} emerged as a distinctive voice, captivating listeners with their [describe sound - e.g., atmospheric textures, driving beats).\n";
        $longBio .= "Their artistic journey, marked by a {$tone} approach to songwriting, has seen them evolve from local performances to a growing international fanbase.\n";
        $longBio .= "With influences ranging from [mention diverse influences], {$artistName} crafts sonic narratives that are both deeply personal and universally relatable.\n";
        $longBio .= "Their latest work, '[mention hypothetical release or project]', has been praised for its [mention specific qualities]. {$artistName} is currently working on new material and preparing for [mention upcoming plans].";
        $bios[] = $longBio;

        // Pad with empty strings if fewer than 3 variants were generated (shouldn't happen with this logic)
        while (count($bios) < 3) {
            $bios[] = '';
        }

        // Return only the first 3 variants.
        return array_slice($bios, 0, 3);
    }
}
