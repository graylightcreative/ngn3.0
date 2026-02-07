<?php
/**
 * Station Content Service
 * Manages BYOS (Bring Your Own Songs) upload, validation, and admin review workflow
 *
 * Patterns:
 * - File validation following lib/Smr/UploadService.php
 * - Database interaction via ConnectionFactory (separate read/write)
 * - Logging with structured context arrays
 * - Specific exceptions for different error types
 */

namespace NGN\Lib\Stations;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Logging\LoggerFactory;
use Monolog\Logger;
use PDO;

class StationContentService
{
    private PDO $read;
    private PDO $write;
    private Logger $logger;
    private Config $config;

    // Constants for file validation
    private const MAX_FILE_SIZE_BYTES = 52428800; // 50MB
    private const ALLOWED_MIME_TYPES = ['audio/mpeg', 'audio/wav', 'audio/flac', 'audio/aac', 'audio/x-m4a', 'audio/mp4'];
    private const ALLOWED_EXTENSIONS = ['mp3', 'wav', 'flac', 'aac', 'm4a'];

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);
        $this->logger = LoggerFactory::create($config, 'station_content');
    }

    /**
     * Upload BYOS content with validation
     *
     * @param int $stationId Station owner's station ID
     * @param array $file $_FILES array element
     * @param array $metadata Additional metadata (title, artist_name)
     * @param bool $indemnityAccepted Must be true to proceed
     * @return array Associative array with 'success', 'id', 'message'
     * @throws \InvalidArgumentException on validation failure
     * @throws \RuntimeException on file system failure
     */
    public function uploadContent(int $stationId, array $file, array $metadata, bool $indemnityAccepted): array
    {
        try {
            // Verify indemnity acceptance
            if (!$indemnityAccepted) {
                throw new \InvalidArgumentException('Indemnity clause must be accepted');
            }

            // Validate file structure
            if (!isset($file['error']) || !isset($file['tmp_name']) || !isset($file['size'])) {
                throw new \InvalidArgumentException('Invalid file upload structure');
            }

            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new \InvalidArgumentException('File upload error: ' . $this->getUploadErrorMessage($file['error']));
            }

            // Validate metadata
            if (empty($metadata['title'])) {
                throw new \InvalidArgumentException('Track title is required');
            }

            // Validate file content (size, type, extension)
            $validatedFile = $this->validateFile($file);

            // Check station tier limits
            $tierCheck = $this->checkStationTierLimit($stationId);
            if (!$tierCheck['allowed']) {
                throw new \RuntimeException($tierCheck['message']);
            }

            // Calculate SHA-256 hash for deduplication
            $fileHash = hash_file('sha256', $file['tmp_name']);

            // Check for duplicate content
            if ($this->isDuplicate($stationId, $fileHash)) {
                throw new \InvalidArgumentException('This file has already been uploaded to this station');
            }

            // Create storage directory
            $uploadDir = $this->getUploadDirectory();
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }

            // Generate unique filename
            $filename = $this->generateFilename($metadata['title'], $validatedFile['extension']);
            $storagePath = $uploadDir . '/' . $filename;

            // Move uploaded file
            if (!@move_uploaded_file($file['tmp_name'], $storagePath)) {
                throw new \RuntimeException('Failed to store uploaded file');
            }

            // Store metadata in database
            $contentId = $this->storeContent($stationId, $metadata, $validatedFile, $fileHash, $storagePath);

            $this->logger->info('byos_upload_stored', [
                'station_id' => $stationId,
                'content_id' => $contentId,
                'file_size' => $file['size'],
                'file_hash' => $fileHash,
                'filename' => $filename
            ]);

            // NGN 2.0.2: Register in content ledger (non-blocking)
            $certificateId = null;
            $certificateUrl = null;
            try {
                // Get station owner ID
                $ownerStmt = $this->read->prepare("SELECT user_id FROM stations WHERE id = :station_id LIMIT 1");
                $ownerStmt->execute([':station_id' => $stationId]);
                $stationOwner = $ownerStmt->fetch(PDO::FETCH_ASSOC);
                $ownerId = $stationOwner['user_id'] ?? 0;

                if ($ownerId > 0) {
                    $ledgerService = new \NGN\Lib\Legal\ContentLedgerService(
                        $this->write,
                        $this->config,
                        $this->logger
                    );

                    // Register content in ledger
                    $ledgerRecord = $ledgerService->registerContent(
                        ownerId: $ownerId,
                        contentHash: $fileHash,
                        uploadSource: 'station_content',
                        metadata: [
                            'title' => $metadata['title'] ?? 'Untitled',
                            'artist_name' => $metadata['artist_name'] ?? '',
                            'credits' => $metadata['credits'] ?? null,
                            'rights_split' => $metadata['rights_split'] ?? null
                        ],
                        fileInfo: [
                            'size_bytes' => $file['size'],
                            'mime_type' => $validatedFile['mime_type'],
                            'filename' => $filename
                        ],
                        sourceRecordId: $contentId
                    );

                    $certificateId = $ledgerRecord['certificate_id'] ?? null;

                    // Update station_content with certificate ID
                    if ($certificateId) {
                        $updateStmt = $this->write->prepare("UPDATE station_content SET certificate_id = :cert_id WHERE id = :id");
                        $updateStmt->execute([':cert_id' => $certificateId, ':id' => $contentId]);

                        // Generate certificate HTML
                        try {
                            $certService = new \NGN\Lib\Legal\DigitalCertificateService($this->config->baseUrl());
                            $ownerStmt = $this->read->prepare("SELECT id, name, email FROM users WHERE id = :user_id LIMIT 1");
                            $ownerStmt->execute([':user_id' => $ownerId]);
                            $ownerInfo = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: [];

                            $certificateHtml = $certService->generateCertificateHtml($ledgerRecord, $ownerInfo);
                            $certDir = __DIR__ . '/../../storage/certificates';
                            @mkdir($certDir, 0775, true);
                            $certPath = $certDir . '/' . $certificateId . '.html';
                            @file_put_contents($certPath, $certificateHtml);
                            $certificateUrl = '/storage/certificates/' . $certificateId . '.html';
                        } catch (\Throwable $e) {
                            $this->logger->warning('certificate_generation_failed', ['error' => $e->getMessage()]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Log error but don't fail the upload
                $this->logger->warning('content_ledger_registration_failed', [
                    'station_id' => $stationId,
                    'content_id' => $contentId,
                    'error' => $e->getMessage()
                ]);
            }

            $response = [
                'success' => true,
                'id' => $contentId,
                'message' => 'File uploaded successfully. Pending admin review.'
            ];

            // Add certificate info if available
            if ($certificateId && $certificateUrl) {
                $response['certificate_id'] = $certificateId;
                $response['certificate_url'] = $certificateUrl;
            }

            return $response;

        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('byos_upload_validation_failed', [
                'station_id' => $stationId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('byos_upload_failed', [
                'station_id' => $stationId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * List station content with filtering and pagination
     *
     * @param int $stationId Station ID
     * @param string|null $status Filter by status (pending, approved, rejected, taken_down)
     * @param int $page Pagination page (1-indexed)
     * @param int $perPage Items per page (max 50)
     * @return array Associative array with 'items', 'total', 'page', 'per_page'
     */
    public function listContent(int $stationId, ?string $status = null, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage)); // Cap at 50

        try {
            $where = ['station_id = :stationId'];
            $params = [':stationId' => $stationId];

            if ($status !== null) {
                $where[] = 'status = :status';
                $params[':status'] = $status;
            }

            $whereClause = implode(' AND ', $where);

            // Count total
            $countStmt = $this->write->prepare("
                SELECT COUNT(*) as total
                FROM `ngn_2025`.`station_content`
                WHERE {$whereClause}
            ");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            // Fetch paginated results
            $offset = ($page - 1) * $perPage;
            $listStmt = $this->write->prepare("
                SELECT
                    id, title, artist_name, file_size_bytes, mime_type, status,
                    indemnity_accepted_at, reviewed_at, review_notes,
                    metadata, created_at
                FROM `ngn_2025`.`station_content`
                WHERE {$whereClause}
                ORDER BY created_at DESC
                LIMIT :offset, :perPage
            ");
            $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $listStmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
            foreach ($params as $key => $value) {
                $listStmt->bindValue($key, $value);
            }
            $listStmt->execute();
            $items = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

            return [
                'success' => true,
                'items' => $items,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage
            ];

        } catch (\Throwable $e) {
            $this->logger->error('list_content_failed', ['station_id' => $stationId, 'error' => $e->getMessage()]);
            return ['success' => false, 'items' => [], 'total' => 0, 'message' => 'Failed to list content'];
        }
    }

    /**
     * Get single content item with details
     *
     * @param int $contentId Content ID
     * @param int|null $stationId Verify ownership (optional)
     * @return array|null Content details or null if not found
     */
    public function getContent(int $contentId, ?int $stationId = null): ?array
    {
        try {
            $sql = 'SELECT * FROM `ngn_2025`.`station_content` WHERE id = :id';
            $params = [':id' => $contentId];

            if ($stationId !== null) {
                $sql .= ' AND station_id = :stationId';
                $params[':stationId'] = $stationId;
            }

            $stmt = $this->write->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        } catch (\Throwable $e) {
            $this->logger->error('get_content_failed', ['content_id' => $contentId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Admin: Approve content
     *
     * @param int $contentId Content ID
     * @param int $adminId Admin user ID
     * @return bool Success
     */
    public function approveContent(int $contentId, int $adminId): bool
    {
        try {
            $stmt = $this->write->prepare("
                UPDATE station_content
                SET status = 'approved', reviewed_by = :adminId, reviewed_at = NOW()
                WHERE id = :id
            ");
            $success = $stmt->execute([':id' => $contentId, ':adminId' => $adminId]);

            if ($success) {
                $this->logger->info('byos_content_approved', [
                    'content_id' => $contentId,
                    'reviewed_by' => $adminId
                ]);
            }
            return $success;

        } catch (\Throwable $e) {
            $this->logger->error('approve_content_failed', ['content_id' => $contentId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Admin: Reject content
     *
     * @param int $contentId Content ID
     * @param int $adminId Admin user ID
     * @param string $reason Rejection reason
     * @return bool Success
     */
    public function rejectContent(int $contentId, int $adminId, string $reason): bool
    {
        try {
            $stmt = $this->write->prepare("
                UPDATE station_content
                SET status = 'rejected', reviewed_by = :adminId, reviewed_at = NOW(), review_notes = :reason
                WHERE id = :id
            ");
            $success = $stmt->execute([
                ':id' => $contentId,
                ':adminId' => $adminId,
                ':reason' => substr($reason, 0, 1000) // Cap at 1000 chars
            ]);

            if ($success) {
                $this->logger->info('byos_content_rejected', [
                    'content_id' => $contentId,
                    'reviewed_by' => $adminId,
                    'reason' => substr($reason, 0, 100)
                ]);
            }
            return $success;

        } catch (\Throwable $e) {
            $this->logger->error('reject_content_failed', ['content_id' => $contentId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Admin: Takedown content (DMCA, copyright, etc)
     *
     * @param int $contentId Content ID
     * @param int $adminId Admin user ID
     * @param string $reason Takedown reason
     * @return bool Success
     */
    public function takedownContent(int $contentId, int $adminId, string $reason): bool
    {
        try {
            $stmt = $this->write->prepare("
                UPDATE station_content
                SET status = 'taken_down', reviewed_by = :adminId, reviewed_at = NOW(), review_notes = :reason
                WHERE id = :id
            ");
            $success = $stmt->execute([
                ':id' => $contentId,
                ':adminId' => $adminId,
                ':reason' => substr($reason, 0, 1000)
            ]);

            if ($success) {
                $this->logger->info('byos_content_taken_down', [
                    'content_id' => $contentId,
                    'reviewed_by' => $adminId,
                    'reason' => substr($reason, 0, 100)
                ]);
            }
            return $success;

        } catch (\Throwable $e) {
            $this->logger->error('takedown_content_failed', ['content_id' => $contentId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Delete content and remove file
     *
     * @param int $contentId Content ID
     * @param int $stationId Station ID (for verification)
     * @return bool Success
     */
    public function deleteContent(int $contentId, int $stationId): bool
    {
        try {
            // Get file path for deletion
            $content = $this->getContent($contentId, $stationId);
            if (!$content) {
                throw new \RuntimeException('Content not found');
            }

            // Delete from database
            $stmt = $this->write->prepare("
                DELETE FROM station_content
                WHERE id = :id AND station_id = :stationId
            ");
            $success = $stmt->execute([':id' => $contentId, ':stationId' => $stationId]);

            if ($success && !empty($content['file_path'])) {
                // Attempt file deletion (don't fail if file doesn't exist)
                @unlink($content['file_path']);
                $this->logger->info('byos_content_deleted', [
                    'content_id' => $contentId,
                    'station_id' => $stationId
                ]);
            }

            return $success;

        } catch (\Throwable $e) {
            $this->logger->error('delete_content_failed', ['content_id' => $contentId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    // ========================================================================
    // PRIVATE HELPER METHODS
    // ========================================================================

    /**
     * Validate uploaded file (format, size, extension)
     *
     * @param array $file $_FILES element
     * @return array Validated file info with 'extension', 'mime_type', 'size'
     * @throws \InvalidArgumentException on validation failure
     */
    private function validateFile(array $file): array
    {
        $size = (int)($file['size'] ?? 0);

        // Check file size
        if ($size === 0) {
            throw new \InvalidArgumentException('File is empty');
        }
        if ($size > self::MAX_FILE_SIZE_BYTES) {
            throw new \InvalidArgumentException(
                'File size exceeds ' . round(self::MAX_FILE_SIZE_BYTES / 1024 / 1024) . 'MB limit'
            );
        }

        // Validate MIME type (basic check)
        $mimeType = mime_content_type($file['tmp_name']);
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new \InvalidArgumentException('File type not supported. Allowed: MP3, WAV, FLAC, AAC');
        }

        // Validate extension
        $filename = $file['name'] ?? '';
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            throw new \InvalidArgumentException('File extension not supported');
        }

        return [
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size' => $size
        ];
    }

    /**
     * Check if file is a duplicate (same hash, same station)
     *
     * @param int $stationId Station ID
     * @param string $fileHash SHA-256 hash
     * @return bool True if duplicate exists
     */
    private function isDuplicate(int $stationId, string $fileHash): bool
    {
        try {
            $stmt = $this->write->prepare("
                SELECT COUNT(*) as count
                FROM `ngn_2025`.`station_content`
                WHERE station_id = :stationId AND file_hash = :hash AND status != 'rejected'
            ");
            $stmt->execute([':stationId' => $stationId, ':hash' => $fileHash]);
            $count = (int)$stmt->fetchColumn();
            return $count > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check station tier limits for BYOS uploads
     *
     * @param int $stationId Station ID
     * @return array Array with 'allowed' bool and 'message'
     */
    private function checkStationTierLimit(int $stationId): array
    {
        try {
            $tierService = new StationTierService($this->config);
            $tier = $tierService->getStationTier($stationId);

            if (!$tier) {
                return ['allowed' => false, 'message' => 'Station tier not found'];
            }

            // Check if BYOS uploads are allowed for this tier
            if (!$tierService->hasFeature($stationId, 'byos_upload')) {
                return ['allowed' => false, 'message' => 'BYOS uploads not available on this tier. Please upgrade.'];
            }

            // Check upload limit
            $limits = $tier['limits'] ?? [];
            $maxTracks = $limits['max_byos_tracks'] ?? 0;

            // -1 means unlimited
            if ($maxTracks === -1) {
                return ['allowed' => true, 'message' => ''];
            }

            // Check current usage
            $stmt = $this->write->prepare("
                SELECT COUNT(*) as count
                FROM `ngn_2025`.`station_content`
                WHERE station_id = :stationId AND status != 'rejected'
            ");
            $stmt->execute([':stationId' => $stationId]);
            $currentCount = (int)$stmt->fetchColumn();

            if ($currentCount >= $maxTracks) {
                return ['allowed' => false, 'message' => "You have reached your limit of $maxTracks tracks. Upgrade to upload more."];
            }

            return ['allowed' => true, 'message' => ''];

        } catch (\Throwable $e) {
            $this->logger->warning('tier_limit_check_failed', ['station_id' => $stationId, 'error' => $e->getMessage()]);
            // Fail open - allow upload if tier check fails
            return ['allowed' => true, 'message' => ''];
        }
    }

    /**
     * Get upload directory for BYOS content
     *
     * @return string Full path to upload directory
     */
    private function getUploadDirectory(): string
    {
        $baseDir = $this->config->uploadDir() ?? './storage/uploads';
        return $baseDir . '/byos/' . date('Ymd');
    }

    /**
     * Generate unique filename for uploaded content
     *
     * @param string $title Track title
     * @param string $extension File extension
     * @return string Filename with timestamp and random component
     */
    private function generateFilename(string $title, string $extension): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_\-]/', '_', substr($title, 0, 50));
        $random = bin2hex(random_bytes(4));
        return date('YmdHis') . "_" . $sanitized . "_" . $random . "." . $extension;
    }

    /**
     * Store content metadata in database
     *
     * @param int $stationId Station ID
     * @param array $metadata User-provided metadata
     * @param array $file Validated file info
     * @param string $fileHash SHA-256 hash
     * @param string $storagePath Full storage path
     * @return int Content ID
     */
    private function storeContent(
        int $stationId,
        array $metadata,
        array $file,
        string $fileHash,
        string $storagePath
    ): int {
        $stmt = $this->write->prepare("
            INSERT INTO station_content
            (station_id, title, artist_name, file_path, file_hash, file_size_bytes, mime_type, indemnity_accepted_at)
            VALUES (:stationId, :title, :artist, :path, :hash, :size, :mime, NOW())
        ");

        $stmt->execute([
            ':stationId' => $stationId,
            ':title' => substr($metadata['title'] ?? '', 0, 255),
            ':artist' => substr($metadata['artist_name'] ?? '', 0, 255),
            ':path' => $storagePath,
            ':hash' => $fileHash,
            ':size' => $file['size'],
            ':mime' => $file['mime_type']
        ]);

        return (int)$this->write->lastInsertId();
    }

    /**
     * Get upload error message from PHP error code
     *
     * @param int $errorCode PHP UPLOAD_ERR_* constant
     * @return string Human-readable error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        return match($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds server maximum size',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form maximum size',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary directory',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by extension',
            default => 'Unknown upload error'
        };
    }
}
