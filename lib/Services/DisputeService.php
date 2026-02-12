<?php

namespace NGN\Lib\Services;

use PDO;
use Exception;
use NGN\Lib\Config;
use NGN\Lib\Logging\LoggerFactory;

/**
 * DisputeService
 * 
 * Handles profile ownership disputes and challenges.
 */
class DisputeService
{
    private PDO $pdo;
    private Config $config;
    private $logger;

    public function __construct(PDO $pdo, Config $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->logger = LoggerFactory::getLogger('disputes');
    }

    /**
     * File a new profile dispute
     */
    public function fileDispute(array $data): int
    {
        $this->validateDisputeData($data);

        $stmt = $this->pdo->prepare("
            INSERT INTO `ngn_2025`.`profile_disputes` (
                entity_type, entity_id, claim_id, disputant_user_id,
                disputant_name, disputant_email, disputant_phone,
                relationship, reason, evidence_url, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");

        $stmt->execute([
            $data['entity_type'],
            $data['entity_id'],
            $data['claim_id'] ?? null,
            $data['disputant_user_id'] ?? null,
            $data['disputant_name'],
            $data['disputant_email'],
            $data['disputant_phone'] ?? null,
            $data['relationship'],
            $data['reason'],
            $data['evidence_url'] ?? null
        ]);

        $disputeId = (int)$this->pdo->lastInsertId();
        $this->logger->info("Profile dispute filed", ['id' => $disputeId, 'entity' => "{$data['entity_type']}/{$data['entity_id']}"]);

        return $disputeId;
    }

    /**
     * Get disputes with optional filtering
     */
    public function getDisputes(?string $status = null, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT d.*, c.status as claim_status 
                FROM `ngn_2025`.`profile_disputes` d
                LEFT JOIN `ngn_2025`.`pending_claims` c ON d.claim_id = c.id
                WHERE 1=1";
        $params = [];

        if ($status) {
            $sql .= " AND d.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY d.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        // Bind limit/offset as INT
        $stmt->bindValue(count($params) - 1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(count($params), $offset, PDO::PARAM_INT);
        
        // Redoing binding for safety with named params or indexed
        $stmt = $this->pdo->prepare($sql);
        $idx = 1;
        if ($status) {
            $stmt->bindValue($idx++, $status);
        }
        $stmt->bindValue($idx++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($idx++, $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resolve a dispute
     */
    public function resolveDispute(int $disputeId, int $adminUserId, string $resolution, string $notes): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE `ngn_2025`.`profile_disputes`
            SET status = ?, resolution_notes = ?, reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$resolution, $notes, $adminUserId, $disputeId]);

        $this->logger->info("Dispute resolved", ['id' => $disputeId, 'resolution' => $resolution, 'admin' => $adminUserId]);
    }

    private function validateDisputeData(array $data): void
    {
        $required = ['entity_type', 'entity_id', 'disputant_name', 'disputant_email', 'relationship', 'reason'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        if (!in_array($data['entity_type'], ['artist', 'label', 'venue', 'station'])) {
            throw new Exception("Invalid entity type: {$data['entity_type']}");
        }

        if (!filter_var($data['disputant_email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address");
        }
    }
}
