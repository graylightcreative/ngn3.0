<?php

namespace NGN\Lib\Smr;

use PDO;
use Exception;

/**
 * Integrity Verification Service (Chapter 24 - Rule 3)
 *
 * Row-level SHA-256 verification for bot detection.
 * Flags duplicates/tampering to "Cemetery" table.
 * Blocks bounties on flagged data.
 */
class IntegrityVerificationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Verify row data integrity and generate SHA-256 hash
     *
     * @param array $rowData Row data to hash
     * @return string SHA-256 hash of canonical JSON
     */
    public function generateRowHash(array $rowData): string
    {
        // Sort keys alphabetically for canonical form
        ksort($rowData);

        // Remove null values and falsy non-zero values
        $cleaned = array_filter($rowData, fn($v) => !is_null($v) && $v !== '');

        // Encode as canonical JSON (no spaces, sorted keys)
        $canonical = json_encode($cleaned, JSON_UNESCAPED_SLASHES | JSON_SORT_KEYS);

        // SHA-256 hash
        return hash('sha256', $canonical);
    }

    /**
     * Verify row hash for SMR data entry
     *
     * @param int $chartEntryId Chart entry ID
     * @param array $expectedData Data to verify against
     * @return bool True if hash matches, false if mismatch (tampering detected)
     * @throws Exception
     */
    public function verifyRowHash(int $chartEntryId, array $expectedData): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT row_hash FROM cdm_chart_entries WHERE id = ?");
            $stmt->execute([$chartEntryId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result || empty($result['row_hash'])) {
                // No hash stored, cannot verify
                return false;
            }

            $storedHash = $result['row_hash'];
            $calculatedHash = $this->generateRowHash($expectedData);

            return $storedHash === $calculatedHash;
        } catch (\Throwable $e) {
            throw new Exception("Error verifying row hash: {$e->getMessage()}");
        }
    }

    /**
     * Store row hash in cdm_chart_entries
     *
     * @param int $chartEntryId Chart entry ID
     * @param array $rowData Row data to hash
     * @throws Exception
     */
    public function storeRowHash(int $chartEntryId, array $rowData): void
    {
        try {
            $hash = $this->generateRowHash($rowData);

            $stmt = $this->pdo->prepare("
                UPDATE cdm_chart_entries
                SET row_hash = ?, hash_verified = TRUE
                WHERE id = ?
            ");
            $stmt->execute([$hash, $chartEntryId]);
        } catch (\Throwable $e) {
            throw new Exception("Error storing row hash: {$e->getMessage()}");
        }
    }

    /**
     * Flag suspicious data to cemetery (bot detection)
     *
     * @param array $failureData Failure data
     * @return int Cemetery record ID
     * @throws Exception
     */
    public function flagToCemetery(array $failureData): int
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO smr_cemetery (
                    upload_id, failure_type,
                    expected_hash, actual_hash,
                    flagged_data, row_number, artist_name,
                    detected_by, detected_at, status
                ) VALUES (
                    :upload_id, :failure_type,
                    :expected_hash, :actual_hash,
                    :flagged_data, :row_number, :artist_name,
                    :detected_by, NOW(), 'flagged'
                )
            ");

            $stmt->execute([
                ':upload_id' => $failureData['upload_id'] ?? null,
                ':failure_type' => $failureData['failure_type'],
                ':expected_hash' => $failureData['expected_hash'] ?? null,
                ':actual_hash' => $failureData['actual_hash'] ?? null,
                ':flagged_data' => isset($failureData['data'])
                    ? json_encode($failureData['data'])
                    : null,
                ':row_number' => $failureData['row_number'] ?? null,
                ':artist_name' => $failureData['artist_name'] ?? null,
                ':detected_by' => $failureData['detected_by'] ?? 'automated_scan',
            ]);

            return (int)$this->pdo->lastInsertId();
        } catch (\Throwable $e) {
            throw new Exception("Error flagging to cemetery: {$e->getMessage()}");
        }
    }

    /**
     * Block bounties for flagged upload
     * Mark chart entries as flagged and cancel pending bounties
     *
     * @param int $uploadId SMR upload ID
     * @return int Number of bounties blocked
     * @throws Exception
     */
    public function blockBountiesForUpload(int $uploadId): int
    {
        try {
            // Mark chart entries as flagged
            $stmt = $this->pdo->prepare("
                UPDATE cdm_chart_entries
                SET flagged_in_cemetery = TRUE
                WHERE source_type = 'smr' AND source_id = ?
            ");
            $stmt->execute([$uploadId]);

            // Get artist IDs affected
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT artist_id
                FROM cdm_chart_entries
                WHERE source_type = 'smr' AND source_id = ?
            ");
            $stmt->execute([$uploadId]);
            $artistIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $blockedCount = 0;

            // Cancel pending bounties for affected artists
            foreach ($artistIds as $artistId) {
                $stmt = $this->pdo->prepare("
                    UPDATE smr_bounty_transactions
                    SET status = 'reversed'
                    WHERE artist_id = ? AND status = 'pending'
                ");
                $stmt->execute([$artistId]);
                $blockedCount += $stmt->rowCount();
            }

            return $blockedCount;
        } catch (\Throwable $e) {
            throw new Exception("Error blocking bounties: {$e->getMessage()}");
        }
    }

    /**
     * Scan for duplicate row hashes (bot indicator)
     * Returns entries with duplicate hashes
     *
     * @return array[] Array of duplicate entries
     */
    public function scanForDuplicateHashes(): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT row_hash, COUNT(*) as count, GROUP_CONCAT(id) as entry_ids
                FROM cdm_chart_entries
                WHERE row_hash IS NOT NULL
                AND flagged_in_cemetery = FALSE
                GROUP BY row_hash
                HAVING count > 1
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Flag duplicate hashes to cemetery
     * Run as part of daily integrity scan
     *
     * @return int Number of duplicates flagged
     */
    public function flagDuplicateHashes(): int
    {
        try {
            $duplicates = $this->scanForDuplicateHashes();
            $flaggedCount = 0;

            foreach ($duplicates as $dup) {
                $entryIds = explode(',', $dup['entry_ids']);

                foreach ($entryIds as $entryId) {
                    // Get entry data
                    $stmt = $this->pdo->prepare("SELECT * FROM cdm_chart_entries WHERE id = ?");
                    $stmt->execute([$entryId]);
                    $entry = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($entry) {
                        // Flag to cemetery
                        $this->flagToCemetery([
                            'upload_id' => $entry['source_id'] ?? null,
                            'failure_type' => 'duplicate_hash',
                            'expected_hash' => $dup['row_hash'],
                            'actual_hash' => $dup['row_hash'],
                            'data' => $entry,
                            'row_number' => $entry['id'],
                            'artist_name' => $entry['artist_name'] ?? null,
                            'detected_by' => 'automated_scan',
                        ]);

                        // Mark entry as flagged
                        $stmt = $this->pdo->prepare("
                            UPDATE cdm_chart_entries
                            SET flagged_in_cemetery = TRUE
                            WHERE id = ?
                        ");
                        $stmt->execute([$entryId]);

                        $flaggedCount++;
                    }
                }

                // Block bounties for affected artists
                $stmt = $this->pdo->prepare("
                    SELECT DISTINCT artist_id
                    FROM cdm_chart_entries
                    WHERE id IN ({$dup['entry_ids']})
                ");
                $stmt->execute();
                $artistIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($artistIds as $artistId) {
                    $this->blockBountiesForUpload((int)$artistId);
                }
            }

            return $flaggedCount;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get cemetery records for review
     *
     * @param array $filters Optional filters: ['status' => 'flagged', 'failure_type' => 'bot_detected']
     * @return array[] Cemetery records
     */
    public function getCemeteryRecords(array $filters = []): array
    {
        try {
            $sql = "SELECT * FROM smr_cemetery WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['failure_type'])) {
                $sql .= " AND failure_type = ?";
                $params[] = $filters['failure_type'];
            }

            $sql .= " ORDER BY detected_at DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Mark cemetery record as reviewed (resolve false positive)
     *
     * @param int $cemeteryId Cemetery record ID
     * @param string $newStatus New status ('resolved', 'false_positive', 'reviewed')
     * @throws Exception
     */
    public function reviewCemeteryRecord(int $cemeteryId, string $newStatus): void
    {
        try {
            if (!in_array($newStatus, ['resolved', 'false_positive', 'reviewed'])) {
                throw new Exception("Invalid status: {$newStatus}");
            }

            $stmt = $this->pdo->prepare("
                UPDATE smr_cemetery
                SET status = ?
                WHERE id = ?
            ");
            $stmt->execute([$newStatus, $cemeteryId]);
        } catch (\Throwable $e) {
            throw new Exception("Error reviewing cemetery record: {$e->getMessage()}");
        }
    }

    /**
     * Get bot detection statistics
     *
     * @return array Statistics including flagged count, bounties blocked, etc.
     */
    public function getBotDetectionStats(): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_flagged,
                    SUM(bounties_blocked) as total_bounties_blocked,
                    COUNT(CASE WHEN status = 'flagged' THEN 1 END) as pending_review,
                    COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved,
                    COUNT(CASE WHEN status = 'false_positive' THEN 1 END) as false_positives
                FROM smr_cemetery
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total_flagged' => (int)($result['total_flagged'] ?? 0),
                'total_bounties_blocked' => (int)($result['total_bounties_blocked'] ?? 0),
                'pending_review' => (int)($result['pending_review'] ?? 0),
                'resolved' => (int)($result['resolved'] ?? 0),
                'false_positives' => (int)($result['false_positives'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
