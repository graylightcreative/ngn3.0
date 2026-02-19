<?php

namespace NGN\Lib\Services\Royalties;

use PDO;

/**
 * SettlementAuditService
 * 
 * Records immutable audit trails for financial policy execution.
 * Ensures every dollar split is traceable to a specific Governance Rule (Rule 5, Bounty, etc).
 */
class SettlementAuditService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Log a settlement event
     */
    public function log(
        string $transactionId,
        string $eventType,
        int $amountCents,
        int $beneficiaryId,
        array $logicSnapshot,
        string $policyVersion = '1.0.0'
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO `settlement_audit_log` (
                transaction_id, event_type, amount_processed, 
                beneficiary_user_id, logic_snapshot, policy_version, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, NOW()
            )
        ");

        $stmt->execute([
            $transactionId,
            $eventType,
            $amountCents,
            $beneficiaryId,
            json_encode($logicSnapshot),
            $policyVersion
        ]);
    }
}
