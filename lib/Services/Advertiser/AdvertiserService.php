<?php

namespace NGN\Lib\Services\Advertiser;

use PDO;
use Exception;

/**
 * Advertiser Service
 *
 * Handles campaign intake, management, and AI-assisted drafting for advertisers.
 *
 * Bible Ch. 18.5: Ads Platform
 */
class AdvertiserService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Submit a new campaign request.
     */
    public function submitRequest(int $userId, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `ngn_2025`.`campaign_requests` 
            (user_id, campaign_type, title, objective, target_audience, budget_cents, start_date, end_date, status, ai_suggestions)
            VALUES (:user_id, :type, :title, :objective, :audience, :budget, :start, :end, 'pending', :ai)
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':type' => $data['campaign_type'] ?? 'display',
            ':title' => $data['title'] ?? 'Untitled Campaign',
            ':objective' => $data['objective'] ?? '',
            ':audience' => isset($data['target_audience']) ? json_encode($data['target_audience']) : null,
            ':budget' => (int)($data['budget_cents'] ?? 0),
            ':start' => $data['start_date'] ?? null,
            ':end' => $data['end_date'] ?? null,
            ':ai' => isset($data['ai_suggestions']) ? json_encode($data['ai_suggestions']) : null
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Get campaign requests for a specific advertiser.
     */
    public function getRequests(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `ngn_2025`.`campaign_requests` WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as &$row) {
            $row['target_audience'] = json_decode($row['target_audience'], true);
            $row['ai_suggestions'] = json_decode($row['ai_suggestions'], true);
        }

        return $results;
    }

    /**
     * Get a specific campaign request.
     */
    public function getRequest(int $requestId, ?int $userId = null): ?array
    {
        $sql = "SELECT * FROM `ngn_2025`.`campaign_requests` WHERE id = ?";
        $params = [$requestId];

        if ($userId) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $row['target_audience'] = json_decode($row['target_audience'], true);
            $row['ai_suggestions'] = json_decode($row['ai_suggestions'], true);
            return $row;
        }

        return null;
    }

    /**
     * Update campaign request status (Admin tool).
     */
    public function updateStatus(int $requestId, string $status, ?string $adminNotes = null): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE `ngn_2025`.`campaign_requests`
            SET status = :status, admin_notes = :notes, updated_at = NOW()
            WHERE id = :id
        ");
        return $stmt->execute([
            ':status' => $status,
            ':notes' => $adminNotes,
            ':id' => $requestId
        ]);
    }

    /**
     * AI Campaign Drafting Assistant.
     * Generates suggestions based on objective and NGN data.
     */
    public function generateSuggestions(string $objective, string $campaignType): array
    {
        // In a real implementation, this would call an LLM (OpenAI/Gemini)
        // For now, we simulate AI logic that suggests targeting and copy.
        
        $suggestions = [
            'targeting' => [
                'genres' => ['Metalcore', 'Hard Rock', 'Alternative'],
                'locations' => ['Top 10 US Cities (Streaming)', 'London', 'Berlin'],
                'devices' => ['Mobile (90%)', 'Desktop (10%)']
            ],
            'copy' => [
                'headline' => 'Reach Your Fans Where They Listen',
                'body' => "Launch your next {$campaignType} campaign on NextGenNoise. Target high-intent listeners based on real streaming data.",
                'cta' => 'Learn More'
            ],
            'strategy' => "Based on your objective to '{$objective}', we recommend a 30-day flight with a focus on peak listening hours (4 PM - 10 PM)."
        ];

        return $suggestions;
    }
}
