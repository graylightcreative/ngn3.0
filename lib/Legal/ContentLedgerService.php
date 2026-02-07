<?php

namespace NGN\Lib\Legal;

use NGN\Lib\Config;
use Monolog\Logger;
use PDO;
use RuntimeException;
use InvalidArgumentException;

/**
 * ContentLedgerService
 *
 * Manages the immutable ledger of uploaded content with ownership proof.
 * Links file hashes to verified owners with timestamps for copyright protection.
 *
 * Patterns:
 * - PDO dependency injection via constructor
 * - Structured logging with LoggerFactory
 * - Prepared statements with named placeholders
 * - Metadata hashing for integrity verification
 * - Certificate generation and tracking
 */
class ContentLedgerService
{
    private PDO $pdo;
    private Logger $logger;
    private Config $config;
    private const HASH_ALGORITHM = 'sha256';
    private const CERTIFICATE_ID_FORMAT = 'CRT-%Y%m%d-%s';
    private const HASH_REGEX = '/^[a-f0-9]{64}$/i';

    public function __construct(PDO $pdo, Config $config, Logger $logger)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Register content in the ledger
     *
     * @param int $ownerId User ID of content owner
     * @param string $contentHash SHA-256 hash of uploaded file
     * @param string $uploadSource Source identifier (station_content, smr_ingestion, smr_assistant, etc.)
     * @param array $metadata Content metadata (title, artist_name, credits, rights_split)
     * @param array $fileInfo File information (size_bytes, mime_type, filename)
     * @param int|null $sourceRecordId Reference to source table record ID
     * @return array Registration result with id, certificate_id, content_hash, metadata_hash
     * @throws InvalidArgumentException on validation failure
     * @throws RuntimeException on database failure
     */
    public function registerContent(
        int $ownerId,
        string $contentHash,
        string $uploadSource,
        array $metadata,
        array $fileInfo,
        ?int $sourceRecordId = null
    ): array {
        try {
            // Validate inputs
            $this->validateContentHash($contentHash);
            $this->validateOwnerId($ownerId);
            $this->validateUploadSource($uploadSource);
            $this->validateFileInfo($fileInfo);

            // Check for duplicate content
            if ($this->isDuplicate($contentHash)) {
                throw new InvalidArgumentException(
                    "Content with hash {$contentHash} is already registered in the ledger"
                );
            }

            // Generate metadata hash for integrity verification
            $metadataHash = $this->generateMetadataHash($metadata);

            // Generate unique certificate ID
            $certificateId = $this->generateCertificateId();

            // Insert into ledger
            $stmt = $this->pdo->prepare("
                INSERT INTO content_ledger (
                    content_hash,
                    metadata_hash,
                    owner_id,
                    upload_source,
                    source_record_id,
                    title,
                    artist_name,
                    credits,
                    rights_split,
                    file_size_bytes,
                    mime_type,
                    original_filename,
                    certificate_id,
                    certificate_issued_at
                ) VALUES (
                    :content_hash,
                    :metadata_hash,
                    :owner_id,
                    :upload_source,
                    :source_record_id,
                    :title,
                    :artist_name,
                    :credits,
                    :rights_split,
                    :file_size_bytes,
                    :mime_type,
                    :original_filename,
                    :certificate_id,
                    NOW()
                )
            ");

            $stmt->execute([
                ':content_hash' => $contentHash,
                ':metadata_hash' => $metadataHash,
                ':owner_id' => $ownerId,
                ':upload_source' => $uploadSource,
                ':source_record_id' => $sourceRecordId,
                ':title' => $metadata['title'] ?? null,
                ':artist_name' => $metadata['artist_name'] ?? null,
                ':credits' => isset($metadata['credits']) ? json_encode($metadata['credits']) : null,
                ':rights_split' => isset($metadata['rights_split']) ? json_encode($metadata['rights_split']) : null,
                ':file_size_bytes' => $fileInfo['size_bytes'] ?? 0,
                ':mime_type' => $fileInfo['mime_type'] ?? 'application/octet-stream',
                ':original_filename' => $fileInfo['filename'] ?? 'unknown',
                ':certificate_id' => $certificateId
            ]);

            $ledgerId = (int)$this->pdo->lastInsertId();

            $this->logger->info('content_registered_in_ledger', [
                'ledger_id' => $ledgerId,
                'content_hash' => $contentHash,
                'certificate_id' => $certificateId,
                'owner_id' => $ownerId,
                'upload_source' => $uploadSource,
                'file_size_bytes' => $fileInfo['size_bytes'] ?? 0
            ]);

            return [
                'id' => $ledgerId,
                'certificate_id' => $certificateId,
                'content_hash' => $contentHash,
                'metadata_hash' => $metadataHash,
                'owner_id' => $ownerId,
                'certificate_issued_at' => date('c')
            ];

        } catch (InvalidArgumentException $e) {
            $this->logger->warning('content_ledger_validation_failed', [
                'owner_id' => $ownerId,
                'upload_source' => $uploadSource,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('content_ledger_registration_failed', [
                'owner_id' => $ownerId,
                'upload_source' => $uploadSource,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode()
            ]);
            throw new RuntimeException('Failed to register content in ledger: ' . $e->getMessage());
        }
    }

    /**
     * Lookup ledger entry by content hash
     *
     * @param string $contentHash SHA-256 hash
     * @return array|null Ledger record or null if not found
     */
    public function lookupByHash(string $contentHash): ?array
    {
        try {
            $this->validateContentHash($contentHash);

            $stmt = $this->pdo->prepare("
                SELECT * FROM content_ledger
                WHERE content_hash = :content_hash
                LIMIT 1
            ");

            $stmt->execute([':content_hash' => $contentHash]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $this->logger->debug('content_lookup_by_hash_found', [
                    'content_hash' => $contentHash
                ]);
            }

            return $result ?: null;

        } catch (\Exception $e) {
            $this->logger->error('content_lookup_by_hash_failed', [
                'content_hash' => $contentHash,
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException('Failed to lookup content by hash');
        }
    }

    /**
     * Lookup ledger entry by certificate ID
     *
     * @param string $certificateId Certificate identifier
     * @return array|null Ledger record or null if not found
     */
    public function lookupByCertificateId(string $certificateId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM content_ledger
                WHERE certificate_id = :certificate_id
                LIMIT 1
            ");

            $stmt->execute([':certificate_id' => $certificateId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $this->logger->debug('content_lookup_by_certificate_found', [
                    'certificate_id' => $certificateId
                ]);
            }

            return $result ?: null;

        } catch (\Exception $e) {
            $this->logger->error('content_lookup_by_certificate_failed', [
                'certificate_id' => $certificateId,
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException('Failed to lookup content by certificate ID');
        }
    }

    /**
     * Verify content integrity by comparing metadata
     *
     * @param string $contentHash Content file hash
     * @param array $metadata Metadata to verify against
     * @return array Verification result with verified=bool, ledger_record=array|null
     */
    public function verifyContent(string $contentHash, array $metadata): array
    {
        try {
            $ledgerRecord = $this->lookupByHash($contentHash);

            if (!$ledgerRecord) {
                return [
                    'verified' => false,
                    'status' => 'not_found',
                    'message' => 'Content not found in ledger'
                ];
            }

            $currentMetadataHash = $this->generateMetadataHash($metadata);
            $ledgerMetadataHash = $ledgerRecord['metadata_hash'];

            $matches = hash_equals($currentMetadataHash, $ledgerMetadataHash);

            return [
                'verified' => $matches,
                'status' => $matches ? 'match' : 'mismatch',
                'message' => $matches
                    ? 'Content metadata verified - no modifications detected'
                    : 'Content metadata mismatch - possible tampering detected',
                'ledger_record' => $matches ? $ledgerRecord : null
            ];

        } catch (\Exception $e) {
            $this->logger->error('content_verification_failed', [
                'content_hash' => $contentHash,
                'error' => $e->getMessage()
            ]);
            return [
                'verified' => false,
                'status' => 'error',
                'message' => 'Verification failed'
            ];
        }
    }

    /**
     * Generate canonical metadata hash for integrity verification
     *
     * Uses SHA-256 of JSON with sorted keys for consistency
     *
     * @param array $metadata Metadata array
     * @return string SHA-256 hash
     */
    public function generateMetadataHash(array $metadata): string
    {
        // Create canonical JSON with sorted keys
        $data = [
            'title' => $metadata['title'] ?? '',
            'artist_name' => $metadata['artist_name'] ?? '',
            'credits' => $metadata['credits'] ?? null,
            'rights_split' => $metadata['rights_split'] ?? null
        ];

        // Use numeric values: JSON_SORT_KEYS = 4, JSON_UNESCAPED_SLASHES = 64
        $canonical = json_encode($data, 4 | 64);

        return hash(self::HASH_ALGORITHM, $canonical);
    }

    /**
     * Increment verification counter and log the verification
     *
     * @param int $ledgerId Ledger entry ID
     * @param string $verificationType Type of verification (public_api, certificate_scan, etc.)
     * @param string $verificationResult Result (match, mismatch, not_found)
     * @param array $requestInfo Request context (ip, user_agent, referer, etc.)
     * @return void
     */
    public function incrementVerificationCount(
        int $ledgerId,
        string $verificationType,
        string $verificationResult,
        array $requestInfo = []
    ): void {
        try {
            // Update verification count and timestamp
            $stmt = $this->pdo->prepare("
                UPDATE content_ledger
                SET
                    verification_count = verification_count + 1,
                    last_verified_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $ledgerId]);

            // Log verification event
            $logStmt = $this->pdo->prepare("
                INSERT INTO content_ledger_verification_log (
                    ledger_id,
                    verified_by,
                    verification_type,
                    verification_result,
                    request_ip,
                    request_user_agent,
                    request_referer,
                    request_metadata
                ) VALUES (
                    :ledger_id,
                    :verified_by,
                    :verification_type,
                    :verification_result,
                    :request_ip,
                    :request_user_agent,
                    :request_referer,
                    :request_metadata
                )
            ");

            $logStmt->execute([
                ':ledger_id' => $ledgerId,
                ':verified_by' => $requestInfo['verified_by'] ?? null,
                ':verification_type' => $verificationType,
                ':verification_result' => $verificationResult,
                ':request_ip' => $requestInfo['request_ip'] ?? null,
                ':request_user_agent' => $requestInfo['request_user_agent'] ?? null,
                ':request_referer' => $requestInfo['request_referer'] ?? null,
                ':request_metadata' => isset($requestInfo['request_metadata'])
                    ? json_encode($requestInfo['request_metadata'])
                    : null
            ]);

            $this->logger->debug('verification_logged', [
                'ledger_id' => $ledgerId,
                'verification_type' => $verificationType
            ]);

        } catch (\Exception $e) {
            $this->logger->error('verification_log_failed', [
                'ledger_id' => $ledgerId,
                'error' => $e->getMessage()
            ]);
            // Don't throw - logging failure shouldn't break verification
        }
    }

    /**
     * Check if content with this hash is already registered
     *
     * @param string $contentHash SHA-256 hash
     * @return bool True if hash already exists in ledger
     */
    public function isDuplicate(string $contentHash): bool
    {
        try {
            $this->validateContentHash($contentHash);

            $stmt = $this->pdo->prepare("
                SELECT 1 FROM content_ledger
                WHERE content_hash = :content_hash
                LIMIT 1
            ");

            $stmt->execute([':content_hash' => $contentHash]);
            return $stmt->fetch() !== false;

        } catch (\Exception $e) {
            $this->logger->error('duplicate_check_failed', [
                'content_hash' => $contentHash,
                'error' => $e->getMessage()
            ]);
            // Assume not duplicate on error to avoid blocking uploads
            return false;
        }
    }

    /**
     * Get user's ledger history
     *
     * @param int $userId User ID
     * @param int $limit Maximum number of records (default 50)
     * @return array Array of ledger records
     */
    public function getUserLedgerHistory(int $userId, int $limit = 50): array
    {
        try {
            $limit = max(1, min(500, $limit)); // Cap at 500

            $stmt = $this->pdo->prepare("
                SELECT * FROM content_ledger
                WHERE owner_id = :owner_id
                ORDER BY created_at DESC
                LIMIT :limit
            ");

            $stmt->bindValue(':owner_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        } catch (\Exception $e) {
            $this->logger->error('user_ledger_history_failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Generate unique certificate ID
     *
     * Format: CRT-YYYYMMDD-XXXXXXXX (e.g., CRT-20260206-A3F8D91E)
     *
     * @return string Unique certificate identifier
     */
    private function generateCertificateId(): string
    {
        $datePrefix = date('Ymd');
        $randomPart = strtoupper(bin2hex(random_bytes(4)));
        return "CRT-{$datePrefix}-{$randomPart}";
    }

    /**
     * Validate SHA-256 content hash format
     *
     * @param string $contentHash Hash to validate
     * @throws InvalidArgumentException if invalid
     */
    private function validateContentHash(string $contentHash): void
    {
        if (!preg_match(self::HASH_REGEX, $contentHash)) {
            throw new InvalidArgumentException(
                "Invalid content hash format. Must be 64-character hexadecimal SHA-256."
            );
        }
    }

    /**
     * Validate owner user ID
     *
     * @param int $ownerId User ID to validate
     * @throws InvalidArgumentException if invalid
     */
    private function validateOwnerId(int $ownerId): void
    {
        if ($ownerId <= 0) {
            throw new InvalidArgumentException('Owner ID must be a positive integer');
        }
    }

    /**
     * Validate upload source identifier
     *
     * @param string $uploadSource Source identifier
     * @throws InvalidArgumentException if invalid
     */
    private function validateUploadSource(string $uploadSource): void
    {
        $validSources = [
            'station_content',
            'smr_ingestion',
            'smr_assistant',
            'api_upload',
            'admin_upload'
        ];

        if (!in_array($uploadSource, $validSources, true)) {
            throw new InvalidArgumentException(
                "Invalid upload source: {$uploadSource}. Must be one of: " . implode(', ', $validSources)
            );
        }
    }

    /**
     * Validate file information
     *
     * @param array $fileInfo File information array
     * @throws InvalidArgumentException if invalid
     */
    private function validateFileInfo(array $fileInfo): void
    {
        if (!isset($fileInfo['size_bytes']) || $fileInfo['size_bytes'] <= 0) {
            throw new InvalidArgumentException('File size must be positive');
        }

        if (!isset($fileInfo['mime_type']) || empty($fileInfo['mime_type'])) {
            throw new InvalidArgumentException('MIME type is required');
        }

        if (!isset($fileInfo['filename']) || empty($fileInfo['filename'])) {
            throw new InvalidArgumentException('Filename is required');
        }
    }

    /**
     * Get a list of ledger entries with filtering and pagination
     * 
     * @param int $limit Maximum number of records
     * @param int $offset Offset for pagination
     * @param int|null $ownerId Filter by owner
     * @param string|null $source Filter by upload source
     * @return array Result array with items and total count
     */
    public function getList(int $limit = 50, int $offset = 0, ?int $ownerId = null, ?string $source = null): array
    {
        try {
            $query = "SELECT l.*, u.name as owner_name FROM content_ledger l LEFT JOIN users u ON l.owner_id = u.id";
            $where = [];
            $params = [];

            if ($ownerId) {
                $where[] = "l.owner_id = :ownerId";
                $params[':ownerId'] = $ownerId;
            }

            if ($source) {
                $where[] = "l.upload_source = :source";
                $params[':source'] = $source;
            }

            if (!empty($where)) {
                $query .= " WHERE " . implode(" AND ", $where);
            }

            $query .= " ORDER BY l.created_at DESC LIMIT :limit OFFSET :offset";

            $stmt = $this->pdo->prepare($query);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count
            $countQuery = "SELECT COUNT(*) FROM content_ledger l";
            if (!empty($where)) {
                $countQuery .= " WHERE " . implode(" AND ", $where);
            }
            $countStmt = $this->pdo->prepare($countQuery);
            foreach ($params as $key => $val) {
                $countStmt->bindValue($key, $val);
            }
            $countStmt->execute();
            $total = (int)$countStmt->fetchColumn();

            return [
                'items' => $items,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ];
        } catch (\Exception $e) {
            error_log("ContentLedgerService::getList Error: " . $e->getMessage());
            $this->logger->error('ledger_list_failed', ['error' => $e->getMessage()]);
            return [
                'items' => [],
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'error' => $e->getMessage()
            ];
        }
    }
}
