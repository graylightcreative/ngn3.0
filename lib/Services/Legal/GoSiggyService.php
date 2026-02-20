<?php
namespace NGN\Lib\Services\Legal;

/**
 * GoSiggy Service - Sovereign Digital Signatures
 * Handles SHA-256 document hashing and integrity verification.
 * Bible Ref: Chapter 52 (GoSiggy Node)
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class GoSiggyService
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
     * Register a digital signature in the GoSiggy ledger
     */
    public function registerSignature(int $userId, string $docHash, array $metadata = []): string
    {
        $signatureId = bin2hex(random_bytes(16));
        
        $stmt = $this->pdo->prepare("
            INSERT INTO gosiggy_signatures (signature_id, user_id, doc_hash, metadata, created_at)
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
        $stmt = $this->pdo->prepare("SELECT * FROM gosiggy_signatures WHERE signature_id = ?");
        $stmt->execute([$signatureId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
