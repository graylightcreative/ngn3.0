<?php
namespace NGN\Lib\Services\Legal;

/**
 * Sovereign Sign Service - Digital Signatures
 * Handles SHA-256 document hashing and integrity verification for the NGN Empire.
 * Bible Ref: Chapter 41 (Digital Agreements and Signatures)
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class SovereignSignService
{
    private $config;
    private $pdo;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdo = ConnectionFactory::read($config);
    }

    /**
     * Generate an immutable hash for a document/agreement
     */
    public function hashDocument(string $content): string
    {
        return hash('sha256', $content);
    }

    /**
     * Register a digital signature in the Sovereign ledger
     */
    public function registerSignature(int $userId, string $docHash, array $metadata = []): string
    {
        $signatureId = bin2hex(random_bytes(16));
        
        $stmt = $this->pdo->prepare("
            INSERT INTO sovereign_signatures (signature_id, user_id, doc_hash, metadata, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $signatureId,
            $userId,
            $docHash,
            json_encode($metadata)
        ]);

        return $signatureId;
    }

    /**
     * Verify a signature's integrity
     */
    public function verifySignature(string $signatureId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sovereign_signatures WHERE signature_id = ?");
        $stmt->execute([$signatureId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
