<?php

namespace NGN\Lib\Editorial;

use PDO;
use Exception;

/**
 * Content Reporting Service
 *
 * Handles submission and management of user-reported content.
 * Links to Public Integrity Protocols (Bible Ch. 21).
 */
class ContentReportingService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Submit a new content report
     */
    public function submitReport(int $reporterId, string $entityType, int $entityId, string $reason, ?string $details = null): int
    {
        $validTypes = ['post', 'video', 'comment', 'artist', 'label'];
        if (!in_array($entityType, $validTypes)) {
            throw new Exception("Invalid entity type: {$entityType}");
        }

        $validReasons = ['spam', 'harassment', 'copyright', 'explicit', 'misinformation', 'other'];
        if (!in_array($reason, $validReasons)) {
            throw new Exception("Invalid reporting reason: {$reason}");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO `ngn_2025`.`content_reports` (
                reporter_user_id, entity_type, entity_id, reason, details, status, created_at
            ) VALUES (
                :reporter_id, :entity_type, :entity_id, :reason, :details, 'pending', NOW()
            )
        ");

        $stmt->execute([
            ':reporter_id' => $reporterId,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':reason' => $reason,
            ':details' => $details
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Get pending reports for admin review
     */
    public function getPendingReports(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, u.display_name as reporter_name
            FROM `ngn_2025`.`content_reports` r
            JOIN `ngn_2025`.`users` u ON r.reporter_user_id = u.id
            WHERE r.status IN ('pending', 'reviewing')
            ORDER BY r.created_at ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Resolve a report
     */
    public function resolveReport(int $reportId, int $adminUserId, string $resolution, ?string $notes = null): bool
    {
        $status = ($resolution === 'action_taken') ? 'resolved' : 'dismissed';
        
        $stmt = $this->pdo->prepare("
            UPDATE `ngn_2025`.`content_reports`
            SET status = :status,
                admin_notes = :notes,
                resolved_by = :admin_id,
                resolved_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':status' => $status,
            ':notes' => $notes,
            ':admin_id' => $adminUserId,
            ':id' => $reportId
        ]);
    }
}
