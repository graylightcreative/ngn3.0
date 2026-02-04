<?php

namespace NGN\Lib\AI;

use NGN\Lib\Config;
use NGN\Lib\Sparks\SparksService;
use NGN\Lib\Sparks\Exception\InsufficientFundsException;

/**
 * Service for optimizing release timing using AI.
 */
class ReleaseTimingOptimizer
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
     * Checks if a user has an 'Elite' subscription. Mocks this check for demonstration.
     * In a real scenario, this would query the user_subscriptions table.
     *
     * @param int $userId
     * @return bool
     */
    private function isEliteSubscriber(int $userId): bool
    {
        // Mock check: For demonstration, let's say user ID 1 and 10 are Elite subscribers.
        // In production, query: SELECT t.slug FROM user_subscriptions s JOIN subscription_tiers t ON s.tier_id = t.id WHERE s.user_id = ? AND s.status = 'active' AND t.slug = 'elite'
        return in_array($userId, [1, 10]);
    }

    /**
     * Optimizes release timing suggestions for an artist.
     *
     * @param int $userId The ID of the user making the request.
     * @param array $params Associative array containing timing optimization parameters:
     *                      - 'artist_name' (string, optional, for context)
     *                      - 'genre' (string, required)
     * @return array An array containing suggested release date and reasoning.
     * @throws ForbiddenException If the user does not have an Elite subscription.
     * @throws InsufficientFundsException If the user has insufficient Sparks.
     * @throws InvalidArgumentException If input parameters are invalid.
     * @throws RuntimeException If AI generation or other processes fail.
     */
    public function optimize(int $userId, array $params): array
    {
        // --- Step 1: Gating (Elite Subscription Check) ---
        if (!$this->isEliteSubscriber($userId)) {
            throw new ForbiddenException("This feature is only available for Elite subscribers.");
        }

        // --- Step 2: Payment (Spark Deduction) ---
        $sparksCost = 5;
        try {
            $this->sparkService->deduct($userId, $sparksCost, [
                'reason' => 'ai_timing_optimizer',
                'user_id' => $userId
            ]);
        } catch (InsufficientFundsException $e) {
            // Re-throw to be caught by the API route handler for a 402 response.
            throw $e;
        } catch (\Throwable $e) {
            // Catch other potential errors during spark deduction (e.g., DB issues).
            throw new \RuntimeException("Failed to process Sparks for timing optimization: " . $e->getMessage());
        }

        // --- Step 3: Result Generation ---
        $artistName = $params['artist_name'] ?? 'Your Artist';
        $genre = $params['genre'] ?? 'Unknown Genre';

        // Simple logic to suggest a release date based on genre and vibe
        $suggestedDate = '';
        $reasoning = '';

        switch (strtolower($genre)) {
            case 'hype':
            case 'electronic':
            case 'dance':
                $suggestedDate = date('Y-m-d', strtotime('next friday'));
                $reasoning = "Releasing on a Friday often maximizes weekend listening for {$genre} tracks, capitalizing on peak user activity.";
                break;
            case 'chill':
            case 'lo-fi':
            case 'ambient':
                $suggestedDate = date('Y-m-d', strtotime('next sunday'));
                $reasoning = "Sundays can be ideal for {$genre} music, offering a relaxed listening experience as the week winds down.";
                break;
            case 'emotional':
            case 'ballad':
            case 'acoustic':
                $suggestedDate = date('Y-m-d', strtotime('next wednesday'));
                $reasoning = "Mid-week releases for {$genre} tracks can build anticipation for the weekend and provide a reflective moment.";
                break;
            case 'dark':
            case 'metal':
            case 'rock':
                $suggestedDate = date('Y-m-d', strtotime('next tuesday'));
                $reasoning = "Tuesdays can be effective for {$genre} releases, allowing initial buzz to build before the weekend crowds arrive.";
                break;
            default:
                $suggestedDate = date('Y-m-d', strtotime('next friday'));
                $reasoning = "A Friday release is generally a strong strategy across most genres, leveraging peak listener engagement.";
                break;
        }

        return [
            'suggested_date' => $suggestedDate,
            'reasoning' => $reasoning
        ];
    }
}