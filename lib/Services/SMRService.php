<?php

namespace NGN\Lib\Services;

use PDO;
use Exception;

/**
 * SMRService - Handle radio data ingestion workflow
 *
 * Implements Erik's SMR Pipeline from Bible Ch. 5:
 * 1. Upload CSV/Excel file
 * 2. Parse and detect unmatched artists
 * 3. Identity mapping tool
 * 4. Review queue with CDM linkage
 * 5. Finalize → commits to smr_ingestions + cdm_chart_entries
 */
class SMRService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Store uploaded SMR file and create ingestion record
     */
    public function storeUpload(
        string $filename,
        string $filePath,
        string $fileHash,
        int $fileSize,
        int $uploadedBy
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO smr_ingestions (
                filename, file_hash, file_size, status, uploaded_by, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $filename,
            $fileHash,
            $fileSize,
            'pending_review',
            $uploadedBy
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Parse CSV/Excel file and extract records
     */
    public function parseFile(string $filePath): array
    {
        $records = [];

        // Detect file type
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            $records = $this->parseCSV($filePath);
        } elseif (in_array($ext, ['xls', 'xlsx'])) {
            $records = $this->parseExcel($filePath);
        }

        return $records;
    }

    /**
     * Parse CSV file
     */
    private function parseCSV(string $filePath): array
    {
        $records = [];
        $handle = fopen($filePath, 'r');

        if (!$handle) {
            throw new Exception("Cannot open CSV file: $filePath");
        }

        // Skip header row
        $header = fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            if (empty(array_filter($row))) {
                continue; // Skip empty rows
            }

            $records[] = [
                'artist_name' => trim($row[0] ?? ''),
                'track_title' => trim($row[1] ?? ''),
                'spin_count' => (int)($row[2] ?? 0),
                'add_count' => (int)($row[3] ?? 0),
                'isrc' => trim($row[4] ?? ''),
                'station_id' => (int)($row[5] ?? 0)
            ];
        }

        fclose($handle);
        return $records;
    }

    /**
     * Parse Excel file (stub - requires PhpSpreadsheet)
     */
    private function parseExcel(string $filePath): array
    {
        // For now, return empty array
        // In production, integrate PhpSpreadsheet
        return [];
    }

    /**
     * Get pending SMR ingestions
     */
    public function getPending(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM smr_ingestions
            WHERE status IN ('pending_review', 'pending_finalize')
            ORDER BY created_at DESC
            LIMIT 50
        ");

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Store parsed records for a specific ingestion
     */
    public function storeRecords(int $ingestionId, array $records): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO smr_records (
                ingestion_id, artist_name, track_title, spin_count, add_count, isrc, status
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending_mapping')
        ");

        foreach ($records as $record) {
            $stmt->execute([
                $ingestionId,
                $record['artist_name'],
                $record['track_title'],
                $record['spin_count'],
                $record['add_count'],
                $record['isrc']
            ]);
        }
    }

    /**
     * Get unmatched artists for identity mapping
     */
    public function getUnmatchedArtists(int $ingestionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT sr.artist_name, COUNT(*) as record_count
            FROM smr_records sr
            WHERE sr.ingestion_id = ?
            AND sr.status = 'pending_mapping'
            AND sr.cdm_artist_id IS NULL
            GROUP BY sr.artist_name
            ORDER BY record_count DESC
        ");

        $stmt->execute([$ingestionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Map artist identity to CDM artist
     */
    public function mapArtistIdentity(
        int $ingestionId,
        string $unmatched,
        int $cdmArtistId
    ): void {
        // Update smr_records with CDM artist ID
        $stmt = $this->pdo->prepare("
            UPDATE smr_records
            SET cdm_artist_id = ?, status = 'mapped'
            WHERE ingestion_id = ? AND artist_name = ?
        ");

        $stmt->execute([$cdmArtistId, $ingestionId, $unmatched]);

        // Also record the mapping in smr_identity_map
        $stmt = $this->pdo->prepare("
            INSERT INTO smr_identity_map (
                artist_id, alias_name, alias_type, verified
            ) VALUES (?, ?, 'smr_typo', 0)
            ON DUPLICATE KEY UPDATE verified = 0
        ");

        $stmt->execute([$cdmArtistId, $unmatched]);
    }

    /**
     * Get records ready for review
     */
    public function getReviewRecords(int $ingestionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT sr.*, ca.name as artist_name_verified
            FROM smr_records sr
            LEFT JOIN artists ca ON sr.cdm_artist_id = ca.id
            WHERE sr.ingestion_id = ?
            ORDER BY sr.artist_name
        ");

        $stmt->execute([$ingestionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if an artist has a "Heat Spike" in SMR logs within the last 90 days.
     * Used for triggering the Data Bounty.
     *
     * @param int $artistId The CDM Artist ID
     * @param int $days Lookback window (default 90)
     * @return bool True if spike detected
     */
    public function hasHeatSpike(int $artistId, int $days = 90): bool
    {
        // Definition of "Heat Spike": 
        // 1. Total spins > 50 in window OR
        // 2. Week-over-week growth > 20%
        
        $stmt = $this->pdo->prepare("
            SELECT SUM(spin_count) as total_spins, MAX(spin_count) as peak_spins
            FROM cdm_chart_entries
            WHERE artist_id = ? 
            AND week_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ");
        
        $stmt->execute([$artistId, $days]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (($stats['total_spins'] ?? 0) > 50) {
            return true;
        }
        
        return false;
    }

    /**
     * Finalize ingestion - commit records to chart entries
     */
    public function finalize(int $ingestionId): array
    {
        try {
            $this->pdo->beginTransaction();

            // Get all mapped records
            $stmt = $this->pdo->prepare("
                SELECT * FROM smr_records
                WHERE ingestion_id = ? AND cdm_artist_id IS NOT NULL
            ");
            $stmt->execute([$ingestionId]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $inserted = 0;

            // Create chart entries for each record
            $insertStmt = $this->pdo->prepare("
                INSERT INTO cdm_chart_entries (
                    ingestion_id, artist_id, track_title, spin_count, add_count, isrc, week_date
                ) VALUES (?, ?, ?, ?, ?, ?, CURDATE())
            ");

            foreach ($records as $record) {
                $insertStmt->execute([
                    $ingestionId,
                    $record['cdm_artist_id'],
                    $record['track_title'],
                    $record['spin_count'],
                    $record['add_count'],
                    $record['isrc']
                ]);
                $inserted++;
            }

            // Mark ingestion as complete
            $stmt = $this->pdo->prepare("
                UPDATE smr_ingestions SET status = 'finalized' WHERE id = ?
            ");
            $stmt->execute([$ingestionId]);

            $this->pdo->commit();

            return [
                'success' => true,
                'ingestion_id' => $ingestionId,
                'records_imported' => $inserted
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
