<?php

namespace NGN\Lib\AI; // Updated namespace to match api/v1/index.php usage

use NGN\Lib\Config;
use NGN\Lib\Sparks\SparksService;
use NGN\Lib\Sparks\Exception\InsufficientFundsException;

class AdCopyGenerator
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
        // In a real application, this might involve a service locator or DI container.
        // For demonstration, we instantiate it directly.
        $this->sparkService = new SparksService($this->config);
    }

    /**
     * Generates ad copy variants for a given user and ad parameters.
     *
     * @param int $userId The ID of the user making the request.
     * @param array $params Associative array containing ad generation parameters (e.g., 'track_name', 'release_date', 'genre', 'theme').
     * @return array An array of generated ad copy variants.
     * @throws InsufficientFundsException If the user has insufficient Sparks.
     * @throws \InvalidArgumentException If input parameters are invalid.
     */
    public function generate(int $userId, array $params): array
    {
        // Parameters validation
        $trackName = $params['track_name'] ?? null;
        $releaseDate = $params['release_date'] ?? null;
        $genre = $params['genre'] ?? null; // Added genre to params
        $theme = $params['theme'] ?? null; // Added theme to params

        if (empty($trackName)) {
            throw new 	InvalidArgumentException("Track name is required.");
        }

        // Charge Sparks. The router handler calls this externally, but the prompt implied 'injecting SparkService' and catching exceptions.
        // To match the prompt's intent and the route handler's exception catching mechanism, we perform the charge here.
        // The route handler in api/v1/index.php already has the try-catch for InsufficientFundsException, so if this method throws it,
        // the route handler will catch it correctly.
        $amountToCharge = 3; // Based on the existing route logic for AI ad generation.
        
        // Check balance first to provide a clearer exception message if needed, and to ensure SparksService is available.
        try {
            $currentBalance = $this->sparkService->getBalance($userId);
            if ($currentBalance < $amountToCharge) {
                throw new InsufficientFundsException("Insufficient Sparks. You have {$currentBalance} Sparks, but need {$amountToCharge}.");
            }
            // If balance is sufficient, we proceed. The actual deduction might happen here or in a separate call in the router.
            // The existing router code shows the charge happening *before* calling generate. This means generate *should not* re-charge.
            // So, I'll remove the charge logic from here and assume the router does it. The exception is still relevant if internal checks fail.
            // The 'Insufficient Sparks' error will be handled by the router's catch block.

        } catch (InsufficientFundsException $e) {
            // Re-throw to be caught by the router
            throw $e;
        } catch (\Throwable $e) {
            // If there's an error getting balance, it's a server error.
            throw new \RuntimeException("Failed to check Spark balance: " . $e->getMessage());
        }

        // Construct the prompt for the AI model
        $prompt = "You are an expert music marketer writing high-converting social ad copy for metal and rock artists.\n" .
            "Artist ID: {$userId}\n" .
            "Track: " . htmlspecialchars($trackName) . "\n" .
            (!empty($releaseDate) ? "Release Date: " . htmlspecialchars($releaseDate) . "\n" : "") .
            (!empty($genre) ? "Genre: " . htmlspecialchars($genre) . "\n" : "") .
            (!empty($theme) ? "Campaign theme or goal: " . htmlspecialchars($theme) . "\n\n" : "\n");

        $prompt .= "Write EXACTLY 5 distinct short ad copy variants suitable for social media ads (Meta / Instagram / TikTok).\n" .
            "Each variant should be on a single line, no numbering, no quotes, no markdown, 15-30 words, punchy and specific.\n" .
            "Focus on driving clicks and listens, respecting metal/rock tone (no generic pop influencer speak).\n" .
            "Output format: 5 lines, one variant per line, nothing before or after.";

        // Placeholder for AI text generation. In a real app, this would call an AI API.
        // For demonstration, we simulate the output based on the prompt.
        $aiResponse = "Simulated ad copy for {$trackName}\nVariant 1 for {$trackName}\nVariant 2 for {$trackName}\nVariant 3 for {$trackName}\nVariant 4 for {$trackName}";
        
        $variants = [];
        $lines = preg_split('/\r?\n/', trim($aiResponse));
        foreach ($lines as $line) {
            $line = trim(preg_replace('/^[0-9]+[\).\-]\s*/', '', $line)); // Clean up potential numbering
            if ($line !== '') {
                $variants[] = $line;
            }
            if (count($variants) >= 5) break;
        }

        // Ensure we always return 5 slots, padding with empty strings if fewer were generated.
        while (count($variants) < 5) {
            $variants[] = '';
        }

        // Return only the first 5 variants.
        return array_slice($variants, 0, 5);
    }
}
