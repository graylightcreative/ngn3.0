<?php

namespace NGN\Lib\Services\Legal;

use PDO;
use Exception;
use RuntimeException;

/**
 * AgreementService
 * 
 * Handles presenting, signing, and auditing digital agreements on the NGN platform.
 */
class AgreementService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get an active agreement template by its slug.
     */
    public function getTemplate(string $slug): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM agreement_templates 
            WHERE slug = :slug AND is_active = 1 
            LIMIT 1
        ");
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Record a user's signature for an agreement.
     */
    public function signAgreement(int $userId, string $templateSlug, string $ipAddress, ?string $userAgent = null): array
    {
        $template = $this->getTemplate($templateSlug);
        if (!$template) {
            throw new RuntimeException("Agreement template '{$templateSlug}' not found or inactive.");
        }

        // Generate SHA-256 hash of the agreement body to prove what was signed
        $agreementHash = hash('sha256', $template['body']);

        $stmt = $this->db->prepare("
            INSERT INTO agreement_signatures (
                template_id, user_id, ip_address, user_agent, agreement_hash, signed_at
            ) VALUES (
                :template_id, :user_id, :ip_address, :user_agent, :agreement_hash, NOW()
            )
        ");

        $stmt->execute([
            'template_id' => $template['id'],
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'agreement_hash' => $agreementHash
        ]);

        return [
            'signature_id' => $this->db->lastInsertId(),
            'signed_at' => date('Y-m-d H:i:s'),
            'version' => $template['version']
        ];
    }

    /**
     * Check if a user has signed the latest version of an agreement.
     */
    public function hasSigned(int $userId, string $templateSlug): bool
    {
        $template = $this->getTemplate($templateSlug);
        if (!$template) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM agreement_signatures 
            WHERE user_id = :user_id AND template_id = :template_id
        ");
        $stmt->execute([
            'user_id' => $userId,
            'template_id' => $template['id']
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Create or update an agreement template (Admin only).
     */
    public function upsertTemplate(string $slug, string $title, string $body, string $version = '1.0.0'): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO agreement_templates (slug, title, body, version, is_active)
            VALUES (:slug, :title, :body, :version, 1)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                body = VALUES(body),
                version = VALUES(version),
                is_active = 1
        ");

        $stmt->execute([
            'slug' => $slug,
            'title' => $title,
            'body' => $body,
            'version' => $version
        ]);

        return (int)$this->db->lastInsertId();
    }
}
