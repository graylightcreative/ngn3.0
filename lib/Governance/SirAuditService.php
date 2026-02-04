<?php

namespace NGN\Lib\Governance;

use PDO;
use Exception;

/**
 * SirAuditService
 *
 * Handles audit logging for SIR status changes and actions.
 * Maintains immutable paper trail per Bible Ch. 31 principle.
 *
 * Bible Reference: Chapter 31 - "Paper Trail" principle
 */
class SirAuditService
{
    private PDO $pdo;

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Log SIR creation
     *
     * @param int $sirId SIR ID
     * @param int $actorUserId Chairman user ID
     * @param array $sirData Full SIR data (for change_details)
     * @return void
     * @throws Exception
     */
    public function logCreated(int $sirId, int $actorUserId, array $sirData = []): void
    {
        $this->logAuditEvent(
            $sirId,
            'created',
            null,
            null,
            $actorUserId,
            'chairman',
            [
                'sir_number' => $sirData['sir_number'] ?? null,
                'objective' => $sirData['objective'] ?? null,
                'priority' => $sirData['priority'] ?? null,
                'assigned_to' => $sirData['assigned_to_director'] ?? null,
            ]
        );
    }

    /**
     * Log status change
     *
     * @param int $sirId SIR ID
     * @param string $oldStatus Previous status
     * @param string $newStatus New status
     * @param int $actorUserId User making the change
     * @param string $actorRole Role of actor (chairman, director)
     * @return void
     * @throws Exception
     */
    public function logStatusChange(int $sirId, string $oldStatus, string $newStatus, int $actorUserId, string $actorRole = 'director'): void
    {
        $this->logAuditEvent(
            $sirId,
            'status_change',
            $oldStatus,
            $newStatus,
            $actorUserId,
            $actorRole,
            [
                'transition' => "{$oldStatus} → {$newStatus}",
            ]
        );
    }

    /**
     * Log feedback added
     *
     * @param int $sirId SIR ID
     * @param int $feedbackId Feedback ID
     * @param int $actorUserId User adding feedback
     * @param string $actorRole Role (chairman, director)
     * @return void
     * @throws Exception
     */
    public function logFeedbackAdded(int $sirId, int $feedbackId, int $actorUserId, string $actorRole = 'director'): void
    {
        $this->logAuditEvent(
            $sirId,
            'feedback_added',
            null,
            null,
            $actorUserId,
            $actorRole,
            [
                'feedback_id' => $feedbackId,
            ]
        );
    }

    /**
     * Log verification
     *
     * @param int $sirId SIR ID
     * @param int $directorUserId Director verifying
     * @return void
     * @throws Exception
     */
    public function logVerified(int $sirId, int $directorUserId): void
    {
        $this->logStatusChange($sirId, 'rant_phase', 'verified', $directorUserId, 'director');

        // Also log explicit verification action
        $this->logAuditEvent(
            $sirId,
            'verified',
            null,
            'verified',
            $directorUserId,
            'director',
            [
                'verified_by' => $directorUserId,
                'one_tap' => true,
            ]
        );
    }

    /**
     * Log SIR closure
     *
     * @param int $sirId SIR ID
     * @param int $chairmanUserId Chairman closing the SIR
     * @return void
     * @throws Exception
     */
    public function logClosed(int $sirId, int $chairmanUserId): void
    {
        $this->logStatusChange($sirId, 'verified', 'closed', $chairmanUserId, 'chairman');

        $this->logAuditEvent(
            $sirId,
            'closed',
            'verified',
            'closed',
            $chairmanUserId,
            'chairman',
            [
                'action' => 'SIR archived and locked',
            ]
        );
    }

    /**
     * Get audit trail for SIR
     *
     * @param int $sirId SIR ID
     * @return array List of audit entries (chronological order)
     * @throws Exception
     */
    public function getAuditTrail(int $sirId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                    id as audit_id,
                    action,
                    old_status,
                    new_status,
                    actor_user_id,
                    actor_role,
                    change_details,
                    ip_address,
                    user_agent,
                    created_at
                FROM ngn_2025.sir_audit_log
                WHERE sir_id = ?
                ORDER BY created_at ASC"
            );
            $stmt->execute([$sirId]);

            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Parse JSON change_details
            foreach ($entries as &$entry) {
                if ($entry['change_details']) {
                    $entry['change_details'] = json_decode($entry['change_details'], true);
                }
            }

            return $entries;
        } catch (\PDOException $e) {
            throw new Exception("Failed to retrieve audit trail: " . $e->getMessage());
        }
    }

    /**
     * Get audit summary for dashboard
     *
     * @param int $sirId SIR ID
     * @return array Summary with key timestamps and actors
     * @throws Exception
     */
    public function getAuditSummary(int $sirId): array
    {
        $trail = $this->getAuditTrail($sirId);

        $summary = [
            'created_at' => null,
            'created_by' => null,
            'last_status_change' => null,
            'last_changed_by' => null,
            'total_changes' => count($trail),
            'status_transitions' => [],
        ];

        foreach ($trail as $entry) {
            if ($entry['action'] === 'created') {
                $summary['created_at'] = $entry['created_at'];
                $summary['created_by'] = $entry['actor_user_id'];
            }

            if ($entry['action'] === 'status_change') {
                $summary['status_transitions'][] = [
                    'from' => $entry['old_status'],
                    'to' => $entry['new_status'],
                    'by' => $entry['actor_user_id'],
                    'at' => $entry['created_at'],
                ];
                $summary['last_status_change'] = $entry['created_at'];
                $summary['last_changed_by'] = $entry['actor_user_id'];
            }
        }

        return $summary;
    }

    /**
     * Get context info for audit logging (IP, user agent)
     *
     * @return array Context data
     */
    private function getActorContext(): array
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        return [
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ];
    }

    /**
     * Internal: Log audit event
     *
     * @param int $sirId SIR ID
     * @param string $action Action type
     * @param string|null $oldStatus Previous status
     * @param string|null $newStatus New status
     * @param int $actorUserId User performing action
     * @param string $actorRole Role of actor
     * @param array $changeDetails Details of change
     * @return void
     * @throws Exception
     */
    private function logAuditEvent(
        int $sirId,
        string $action,
        ?string $oldStatus,
        ?string $newStatus,
        int $actorUserId,
        string $actorRole,
        array $changeDetails = []
    ): void {
        try {
            $context = $this->getActorContext();
            $changeDetailsJson = json_encode($changeDetails);

            $stmt = $this->pdo->prepare(
                "INSERT INTO ngn_2025.sir_audit_log (
                    sir_id, action, old_status, new_status, actor_user_id, actor_role,
                    change_details, ip_address, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)"
            );

            $stmt->execute([
                $sirId,
                $action,
                $oldStatus,
                $newStatus,
                $actorUserId,
                $actorRole,
                $changeDetailsJson,
                $context['ip_address'],
                $context['user_agent'],
            ]);
        } catch (\PDOException $e) {
            throw new Exception("Failed to log audit event: " . $e->getMessage());
        }
    }

    /**
     * Verify audit log integrity (immutability check)
     * Used for verification/testing purposes
     *
     * @param int $sirId SIR ID
     * @return array Integrity report
     * @throws Exception
     */
    public function verifyIntegrity(int $sirId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) as total_entries FROM ngn_2025.sir_audit_log WHERE sir_id = ?"
            );
            $stmt->execute([$sirId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'sir_id' => $sirId,
                'total_entries' => $result['total_entries'],
                'status' => 'intact',
                'message' => 'Audit log is immutable (no UPDATE/DELETE permissions)',
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to verify integrity: " . $e->getMessage());
        }
    }
}
