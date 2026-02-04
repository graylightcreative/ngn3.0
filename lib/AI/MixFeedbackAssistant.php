<?php

namespace NGN\Lib\AI;

use NGN\Lib\Config;
use NGN\Lib\Sparks\SparkService;
use NGN\Lib\Sparks\Exception\InsufficientFundsException;

/**
 * Service for providing AI-powered mix feedback analysis.
 */
class MixFeedbackAssistant
{
    private Config $config;
    private SparkService $sparkService;

    /**
     * Constructor.
     *
     * @param Config $config Application configuration.
     * @param SparkService $sparkService The Spark service instance.
     */
    public function __construct(Config $config, SparkService $sparkService)
    {
        $this->config = $config;
        $this->sparkService = $sparkService;
    }

    /**
     * Checks if a user is an investor by querying the database.
     *
     * @param int $userId
     * @return bool True if the user is an investor, false otherwise.
     */
    private function isInvestor(int $userId): bool
    {
        try {
            $pdo = \NGN\Lib\DB\ConnectionFactory::read($this->config);
            $stmt = $pdo->prepare("SELECT IsInvestor FROM `Users` WHERE Id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch();

            return ($userData && isset($userData['IsInvestor']) && (bool)$userData['IsInvestor']);
        } catch (\Throwable $e) {
            // Log error and return false if database query fails.
            // In a real application, you might want more robust error handling or logging.
            error_log("Database error checking investor status for user {$userId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Analyzes a music mix and provides technical feedback.
     *
     * @param int $userId The ID of the user making the request.
     * @param array $params Associative array containing analysis parameters:
     *                      - 'genre' (string, required)
     *                      - 'file_url' (string, required, URL to the mix file)
     *                      - 'legal_disclaimer_accepted' (bool, required, must be true)
     * @return array An array containing simulated technical analysis results, including 'is_free_perk' if applicable.
     * @throws InvalidArgumentException If input parameters are invalid.
     * @throws \Exception If the legal disclaimer is not accepted.
     * @throws InsufficientFundsException If the user has insufficient Sparks (and is not an investor).
     * @throws \RuntimeException If AI generation or other processes fail.
     */
    public function analyze(int $userId, array $params): array
    {
        // --- Step 1: Validate Inputs & Legal Disclaimer ---
        $genre = $params['genre'] ?? null;
        $fileUrl = $params['file_url'] ?? null;
        $legalDisclaimerAccepted = $params['legal_disclaimer_accepted'] ?? false;

        if (empty($genre)) {
            throw new \InvalidArgumentException("Genre is required.");
        }
        if (empty($fileUrl)) {
            throw new \InvalidArgumentException("File URL is required.");
        }
        if (!$legalDisclaimerAccepted) {
            // As per requirement, throw an Exception that can be mapped to 400 Bad Request.
            throw new \InvalidArgumentException("Legal disclaimer must be accepted to proceed.");
        }

        // --- Step 2: Payment (Spark Deduction) ---
        $sparksCost = 15;
        $isFreePerk = false;

        if ($this->isInvestor($userId)) {
            $sparksCost = 0; // Investor perk: analysis is free
            $isFreePerk = true;
        }

        if ($sparksCost > 0) {
            try {
                $this->sparkService->deduct($userId, $sparksCost, [
                    'reason' => 'ai_mix_feedback',
                    'user_id' => $userId,
                    'genre' => $genre
                ]);
            } catch (InsufficientFundsException $e) {
                // Re-throw to be caught by the API route handler for a 402 response.
                throw $e;
            } catch (\Throwable $e) {
                // Catch other potential errors during spark deduction (e.g., DB issues).
                throw new \RuntimeException("Failed to process Sparks for mix analysis: " . $e->getMessage());
            }
        }

        // --- Step 3: Simulated Technical Analysis ---
        $analysis = $this->simulateTechnicalAnalysis($genre);

        // Add perk status to the analysis result
        $analysis['is_free_perk'] = $isFreePerk;

        return $analysis;
    }

    /**
     * Simulates the AI response for mix analysis.
     *
     * @param string $genre
     * @return array
     */
    private function simulateTechnicalAnalysis(string $genre): array
    {
        $analysis = [
            "overall_message" => "Mix analysis complete. Details below.",
            "technical_report" => [
                "low_end_mud" => "N/A",
                "vocal_sibilance" => "N/A",
                "stereo_width" => "N/A",
                "dynamics" => "N/A",
                "eq_balance" => "N/A"
            ]
        ];

        // Simulate analysis based on genre
        switch (strtolower($genre)) {
            case 'hype':
            case 'electronic':
            case 'dance':
                $analysis["technical_report"]["low_end_mud"] = "Slight muddiness detected around 150-250Hz. Consider carving out a small dip.";
                $analysis["technical_report"]["dynamics"] = "Punchy overall, but sidechain compression might enhance perceived loudness.";
                $analysis["technical_report"]["stereo_width"] = "Good stereo width, particularly in hi-hats and synth layers.";
                break;
            case 'chill':
            case 'lo-fi':
            case 'ambient':
                $analysis["technical_report"]["vocal_sibilance"] = "Slight harshness on vocals above 6kHz. Consider a de-esser.";
                $analysis["technical_report"]["eq_balance"] = "Warm low-mids, clear highs. Ensure vocals sit well without being overpowering.";
                break;
            case 'emotional':
            case 'ballad':
            case 'acoustic':
                $analysis["technical_report"]["vocal_sibilance"] = "Vocal sibilance is noticeable, especially on 's' and 't' sounds.";
                $analysis["technical_report"]["eq_balance"] = "Mid-range frequencies are well-balanced, supporting the emotional delivery.";
                break;
            case 'dark':
            case 'metal':
            case 'rock':
                $analysis["technical_report"]["low_end_mud"] = "Heavy low-end present, but could benefit from tighter kick and bass definition below 100Hz.";
                $analysis["technical_report"]["dynamics"] = "Aggressive compression used, ensure transient details aren't lost.";
                break;
            default:
                $analysis["technical_report"]["eq_balance"] = "General EQ balance seems good, but check for masking in the mid-high frequencies.";
                break;
        }

        return $analysis;
    }
}