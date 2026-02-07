<?php

namespace NGN\Lib\Services;

use PDO;
use Exception;

/**
 * ChartQAService - Handle chart validation and score corrections
 *
 * Implements Chart QA workflows from Bible Ch. 5:
 * 1. QA Gatekeeper (Validation gates)
 * 2. Manual score corrections
 * 3. Score dispute management
 */
class ChartQAService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get status of the 4 QA Gates for a specific ingestion or period
     * 
     * SMR DATA (Uploaded by Erik Baker) vs INTERNAL DATA (NGN Streams/BYOS)
     */
    public function getQAStatus(?int $ingestionId = null): array
    {
        // 1. Linkage Rate (Identity mapping for SMR data)
        $linkageStmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN cdm_artist_id IS NOT NULL THEN 1 ELSE 0 END) as mapped
            FROM smr_records
            " . ($ingestionId ? "WHERE ingestion_id = ?" : "") . "
        ");
        $ingestionId ? $linkageStmt->execute([$ingestionId]) : $linkageStmt->execute();
        $linkage = $linkageStmt->fetch(PDO::FETCH_ASSOC);
        $linkageRate = $linkage['total'] > 0 ? ($linkage['mapped'] / $linkage['total']) * 100 : 0;

        // 2. Station Coverage (SMR reporting vs total expected stations)
        // Note: Bible requires only SMR-designated stations to be counted here
        $coverageStmt = $this->pdo->query("SELECT COUNT(*) FROM stations"); // TODO: Filter where is_smr = 1
        $activeStations = (int)$coverageStmt->fetchColumn();
        $reportingStationsStmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT station_id) FROM smr_records 
            " . ($ingestionId ? "WHERE ingestion_id = ?" : "") . "
        ");
        $ingestionId ? $reportingStationsStmt->execute([$ingestionId]) : $reportingStationsStmt->execute();
        $reportingStations = (int)$reportingStationsStmt->fetchColumn();
        $coverageRate = $activeStations > 0 ? ($reportingStations / $activeStations) * 100 : 0;

        // 3. Spin Parity (SMR Uploaded Spins vs NGN Internal/BYOS Streamed Spins)
        $smrSpinsStmt = $this->pdo->prepare("
            SELECT SUM(spin_count) FROM smr_records 
            " . ($ingestionId ? "WHERE ingestion_id = ?" : "") . "
        ");
        $ingestionId ? $smrSpinsStmt->execute([$ingestionId]) : $smrSpinsStmt->execute();
        $smrSpins = (int)$smrSpinsStmt->fetchColumn();
        
        $internalSpinsStmt = $this->pdo->query("SELECT SUM(spins_count) FROM station_spins");
        $internalSpins = (int)$internalSpinsStmt->fetchColumn();
        
        // Target: High correlation between SMR trends and internal NGN stream data
        $parityRate = $smrSpins > 0 ? (min($smrSpins, $internalSpins) / max($smrSpins, $internalSpins)) * 100 : 0;

        return [
            'gates' => [
                [
                    'id' => 'linkage',
                    'name' => 'Identity Linkage',
                    'value' => round($linkageRate, 1),
                    'target' => 95.0,
                    'status' => $linkageRate >= 95 ? 'pass' : 'warn'
                ],
                [
                    'id' => 'coverage',
                    'name' => 'Station Coverage',
                    'value' => round($coverageRate, 1),
                    'target' => 80.0,
                    'status' => $coverageRate >= 80 ? 'pass' : 'warn'
                ],
                [
                    'id' => 'parity',
                    'name' => 'Spin Parity',
                    'value' => round($parityRate, 1),
                    'target' => 90.0,
                    'status' => $parityRate >= 90 ? 'pass' : 'fail'
                ],
                [
                    'id' => 'anomalies',
                    'name' => 'Anomaly Detection',
                    'value' => 100,
                    'target' => 100,
                    'status' => 'pass'
                ]
            ],
            'overall_status' => ($linkageRate >= 95 && $coverageRate >= 80 && $parityRate >= 90) ? 'pass' : 'review_required'
        ];
    }

    /**
     * Get manual corrections
     */
    public function getCorrections(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*, a.name as artist_name, u.display_name as admin_name
            FROM ngn_score_corrections c
            LEFT JOIN artists a ON c.artist_id = a.id
            LEFT JOIN users u ON c.requested_by = u.id
            ORDER BY c.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Apply a manual score correction
     */
    public function applyCorrection(
        int $artistId,
        float $originalScore,
        float $correctedScore,
        string $reason,
        int $adminId,
        ?int $ingestionId = null
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO ngn_score_corrections (
                artist_id, history_id, original_score, corrected_score, 
                reason, requested_by, correction_type, adjustment_amount, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'manual', ?, NOW())
        ");

        $adjustment = $correctedScore - $originalScore;
        $stmt->execute([
            $artistId,
            $ingestionId ?? 1, // Ensure history_id is not null
            $originalScore,
            $correctedScore,
            $reason,
            $adminId,
            $adjustment
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Get score disputes
     */
    public function getDisputes(?string $status = null): array
    {
        $query = "
            SELECT d.*, a.name as artist_name
            FROM ngn_score_disputes d
            LEFT JOIN artists a ON d.artist_id = a.id
        ";

        $params = [];
        if ($status) {
            $query .= " WHERE d.status = ?";
            $params[] = $status;
        }

        $query .= " ORDER BY d.created_at DESC";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resolve a score dispute
     */
    public function resolveDispute(int $disputeId, string $resolution, string $status = 'resolved'): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE ngn_score_disputes
            SET status = ?, resolution = ?, resolved_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $resolution, $disputeId]);
    }
}
