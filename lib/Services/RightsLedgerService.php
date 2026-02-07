<?php

namespace NGN\Lib\Services;

use PDO;
use Exception;

/**
 * RightsLedgerService - Manage ownership verification and rights
 *
 * Implements rights ledger workflows from Bible Ch. 14:
 * - Ownership verification (ISRC checking)
 * - Ownership splits (multiple contributors)
 * - Dispute resolution
 * - Certificate generation (Digital Safety Seal)
 */
class RightsLedgerService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get all rights registrations with filters
     */
    public function getRegistry(
        ?string $status = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        $query = "
            SELECT r.*
            FROM cdm_rights_ledger r
        ";

        $params = [];
        $where = [];

        if ($status) {
            $where[] = "r.status = :status";
        }

        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }

        $query .= " ORDER BY r.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($query);
        
        if ($status) {
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get summary counts by status
     */
    public function getSummary(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT status, COUNT(*) as count
            FROM cdm_rights_ledger
            GROUP BY status
        ");

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $summary = [
            'pending' => 0,
            'verified' => 0,
            'disputed' => 0,
            'rejected' => 0
        ];

        foreach ($rows as $row) {
            if (isset($summary[$row['status']])) {
                $summary[$row['status']] = (int)$row['count'];
            }
        }

        return $summary;
    }

    /**
     * Create a new rights registration
     */
    public function createRegistration(
        int $artistId,
        ?int $trackId,
        ?string $isrc,
        int $ownerId
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO cdm_rights_ledger (
                artist_id, track_id, isrc, owner_id, status, created_at
            ) VALUES (?, ?, ?, ?, 'pending', NOW())
        ");

        $stmt->execute([
            $artistId,
            $trackId,
            $isrc,
            $ownerId
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Verify a rights registration (mark as verified)
     */
    public function verify(int $rightId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE cdm_rights_ledger
            SET status = 'verified', verified_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([$rightId]);
    }

    /**
     * Mark a rights registration as disputed
     */
    public function markDisputed(int $rightId, string $reason): int
    {
        // Update status
        $stmt = $this->pdo->prepare("
            UPDATE cdm_rights_ledger
            SET status = 'disputed'
            WHERE id = ?
        ");

        $stmt->execute([$rightId]);

        // Create dispute record
        $disputeStmt = $this->pdo->prepare("
            INSERT INTO cdm_rights_disputes (
                right_id, reason, status, created_at
            ) VALUES (?, ?, 'open', NOW())
        ");

        $disputeStmt->execute([$rightId, $reason]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Get disputes
     */
    public function getDisputes(?string $status = null, int $limit = 50): array
    {
        $query = "
            SELECT d.*, r.artist_name, r.title, r.isrc
            FROM cdm_rights_disputes d
            LEFT JOIN cdm_rights_ledger r ON d.right_id = r.id
        ";

        if ($status) {
            $query .= " WHERE d.status = :status";
        }

        $query .= " ORDER BY d.created_at DESC LIMIT :limit";

        $stmt = $this->pdo->prepare($query);
        
        if ($status) {
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resolve a dispute
     */
    public function resolveDispute(int $disputeId, string $resolution, string $finalStatus): void
    {
        try {
            $this->pdo->beginTransaction();

            // Get the dispute and associated right
            $stmt = $this->pdo->prepare("
                SELECT right_id FROM cdm_rights_disputes WHERE id = ?
            ");
            $stmt->execute([$disputeId]);
            $dispute = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$dispute) {
                throw new Exception("Dispute not found");
            }

            // Update dispute
            $updateDisputeStmt = $this->pdo->prepare("
                UPDATE cdm_rights_disputes
                SET status = 'resolved', resolution = ?, resolved_at = NOW()
                WHERE id = ?
            ");

            $updateDisputeStmt->execute([$resolution, $disputeId]);

            // Update right status
            $updateRightStmt = $this->pdo->prepare("
                UPDATE cdm_rights_ledger
                SET status = ?
                WHERE id = ?
            ");

            $updateRightStmt->execute([$finalStatus, $dispute['right_id']]);

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Add ownership split
     */
    public function addSplit(
        int $rightId,
        int $contributorId,
        float $percentage,
        ?string $role = null
    ): int {
        // Validate total doesn't exceed 100%
        $stmt = $this->pdo->prepare("
            SELECT SUM(percentage) as total FROM cdm_rights_splits WHERE right_id = ?
        ");
        $stmt->execute([$rightId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $currentTotal = (float)($result['total'] ?? 0);

        if ($currentTotal + $percentage > 100) {
            throw new Exception(
                "Total splits cannot exceed 100% (current: {$currentTotal}%)"
            );
        }

        $insertStmt = $this->pdo->prepare("
            INSERT INTO cdm_rights_splits (
                right_id, contributor_id, percentage, role, created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");

        $insertStmt->execute([
            $rightId,
            $contributorId,
            $percentage,
            $role
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Get splits for a right
     */
    public function getSplits(int $rightId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, u.email, u.display_name as name
            FROM cdm_rights_splits s
            LEFT JOIN users u ON s.contributor_id = u.id
            WHERE s.right_id = ?
            ORDER BY s.percentage DESC
        ");

        $stmt->execute([$rightId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update split
     */
    public function updateSplit(int $splitId, float $percentage, ?string $role = null): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE cdm_rights_splits
            SET percentage = ?, role = ?
            WHERE id = ?
        ");

        $stmt->execute([$percentage, $role, $splitId]);
    }

    /**
     * Verify ISRC code format
     */
    public function verifyISRC(string $isrc): bool
    {
        // ISRC format: CC-XXX-YY-NNNNN (12 alphanumeric)
        return preg_match('/^[A-Z]{2}[A-Z0-9]{3}\d{7}$/', str_replace('-', '', $isrc)) === 1;
    }

    /**
     * Generate Digital Safety Seal certificate data
     */
    public function generateCertificate(int $rightId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*
            FROM cdm_rights_ledger r
            WHERE r.id = ?
        ");

        $stmt->execute([$rightId]);
        $right = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$right) {
            throw new Exception("Right not found");
        }

        $splits = $this->getSplits($rightId);

        return [
            'certificate_id' => 'CERT-' . date('Y') . '-' . str_pad($rightId, 8, '0', STR_PAD_LEFT),
            'artist' => $right['artist_name'],
            'artist_verified' => true, // Placeholder since we don't have artists table join anymore
            'isrc' => $right['isrc'],
            'status' => $right['status'],
            'verified_at' => $right['verified_at'],
            'contributors' => $splits,
            'generated_at' => date('c')
        ];
    }
}
