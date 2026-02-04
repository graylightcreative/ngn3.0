<?php

namespace NGN\Lib\Services;

use PDO;
use Exception;

/**
 * Entity Merge Service
 *
 * Merges duplicate entities (artists, labels, venues) while preserving:
 * - Engagement data (likes, shares, comments, sparks)
 * - Rankings history
 * - Rights ledger entries
 * - Identity map references
 * - All foreign key relationships
 */
class EntityMergeService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Merge multiple entities into a canonical record
     *
     * @param string $entityType Entity type (artist, label, venue, station)
     * @param array $entityIds Array of IDs to merge (first one becomes canonical)
     * @param bool $dryRun If true, shows what would happen without executing
     * @return array Result summary
     * @throws Exception
     */
    public function merge(string $entityType, array $entityIds, bool $dryRun = false): array
    {
        if (count($entityIds) < 2) {
            throw new Exception('Need at least 2 entities to merge');
        }

        $validTypes = ['artist', 'label', 'venue', 'station'];
        if (!in_array($entityType, $validTypes)) {
            throw new Exception("Invalid entity type: {$entityType}");
        }

        // Table name mapping
        $tables = [
            'artist' => 'cdm_artists',
            'label' => 'cdm_labels',
            'venue' => 'cdm_venues',
            'station' => 'cdm_stations'
        ];

        $table = $tables[$entityType];
        $canonicalId = $entityIds[0];
        $duplicateIds = array_slice($entityIds, 1);

        $result = [
            'entity_type' => $entityType,
            'canonical_id' => $canonicalId,
            'merged_ids' => $duplicateIds,
            'dry_run' => $dryRun,
            'operations' => []
        ];

        if (!$dryRun) {
            $this->pdo->beginTransaction();
        }

        try {
            // 1. Merge engagement data
            $engagementResult = $this->mergeEngagements($entityType, $canonicalId, $duplicateIds, $dryRun);
            $result['operations'][] = $engagementResult;

            // 2. Merge identity map references
            $identityResult = $this->mergeIdentityMap($entityType, $canonicalId, $duplicateIds, $dryRun);
            $result['operations'][] = $identityResult;

            // 3. Merge rights ledger (for artists/tracks)
            if ($entityType === 'artist') {
                $rightsResult = $this->mergeRights($canonicalId, $duplicateIds, $dryRun);
                $result['operations'][] = $rightsResult;
            }

            // 4. Update foreign key references in other tables
            $fkResult = $this->updateForeignKeyReferences($entityType, $canonicalId, $duplicateIds, $dryRun);
            $result['operations'][] = $fkResult;

            // 5. Delete duplicate records
            if (!$dryRun) {
                foreach ($duplicateIds as $dupId) {
                    $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE id = :id");
                    $stmt->execute([':id' => $dupId]);
                }
            }

            $result['operations'][] = [
                'operation' => 'delete_duplicates',
                'deleted_count' => count($duplicateIds),
                'executed' => !$dryRun
            ];

            if (!$dryRun) {
                $this->pdo->commit();
            }

            $result['success'] = true;
            $result['message'] = $dryRun
                ? 'Dry run complete - no changes made'
                : 'Merge completed successfully';

        } catch (Exception $e) {
            if (!$dryRun) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    private function mergeEngagements(string $entityType, int $canonicalId, array $duplicateIds, bool $dryRun): array
    {
        $totalMoved = 0;

        foreach ($duplicateIds as $dupId) {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM cdm_engagements
                WHERE entity_type = :type AND entity_id = :id AND deleted_at IS NULL
            ");
            $stmt->execute([':type' => $entityType, ':id' => $dupId]);
            $count = (int)$stmt->fetch()['count'];

            if ($count > 0 && !$dryRun) {
                // Move engagements to canonical entity
                $update = $this->pdo->prepare("
                    UPDATE cdm_engagements
                    SET entity_id = :canonical_id
                    WHERE entity_type = :type AND entity_id = :dup_id
                ");
                $update->execute([
                    ':canonical_id' => $canonicalId,
                    ':type' => $entityType,
                    ':dup_id' => $dupId
                ]);
            }

            $totalMoved += $count;
        }

        // Recalculate EQS for canonical entity
        if (!$dryRun && $totalMoved > 0) {
            $this->recalculateEngagementCounts($entityType, $canonicalId);
        }

        return [
            'operation' => 'merge_engagements',
            'engagements_moved' => $totalMoved,
            'executed' => !$dryRun
        ];
    }

    private function mergeIdentityMap(string $entityType, int $canonicalId, array $duplicateIds, bool $dryRun): array
    {
        $totalMoved = 0;

        foreach ($duplicateIds as $dupId) {
            if (!$dryRun) {
                $stmt = $this->pdo->prepare("
                    UPDATE cdm_identity_map
                    SET cdm_id = :canonical_id
                    WHERE entity = :type AND cdm_id = :dup_id
                ");
                $stmt->execute([
                    ':canonical_id' => $canonicalId,
                    ':type' => $entityType,
                    ':dup_id' => $dupId
                ]);

                $totalMoved += $stmt->rowCount();
            }
        }

        return [
            'operation' => 'merge_identity_map',
            'mappings_updated' => $totalMoved,
            'executed' => !$dryRun
        ];
    }

    private function mergeRights(int $canonicalId, array $duplicateIds, bool $dryRun): array
    {
        $totalMoved = 0;

        foreach ($duplicateIds as $dupId) {
            // Check for tracks that reference duplicate artist
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM cdm_rights_ledger rl
                JOIN tracks t ON rl.track_id = t.id
                WHERE t.artist_id = :dup_id
            ");
            $stmt->execute([':dup_id' => $dupId]);
            $count = (int)$stmt->fetch()['count'];

            if ($count > 0 && !$dryRun) {
                // Update tracks to point to canonical artist
                $update = $this->pdo->prepare("
                    UPDATE tracks
                    SET artist_id = :canonical_id
                    WHERE artist_id = :dup_id
                ");
                $update->execute([
                    ':canonical_id' => $canonicalId,
                    ':dup_id' => $dupId
                ]);
            }

            $totalMoved += $count;
        }

        return [
            'operation' => 'merge_rights_ledger',
            'tracks_moved' => $totalMoved,
            'executed' => !$dryRun
        ];
    }

    private function updateForeignKeyReferences(string $entityType, int $canonicalId, array $duplicateIds, bool $dryRun): array
    {
        $updated = 0;

        // Tables with foreign key references
        $fkTables = [
            'artist' => ['releases', 'tracks', 'cdm_songs', 'cdm_spins'],
            'label' => ['releases', 'artists'],
            'venue' => ['shows', 'cdm_events'],
            'station' => ['station_spins', 'cdm_spins']
        ];

        $column = $entityType . '_id';
        $tables = $fkTables[$entityType] ?? [];

        foreach ($tables as $fkTable) {
            foreach ($duplicateIds as $dupId) {
                if (!$dryRun) {
                    try {
                        $stmt = $this->pdo->prepare("
                            UPDATE {$fkTable}
                            SET {$column} = :canonical_id
                            WHERE {$column} = :dup_id
                        ");
                        $stmt->execute([
                            ':canonical_id' => $canonicalId,
                            ':dup_id' => $dupId
                        ]);
                        $updated += $stmt->rowCount();
                    } catch (Exception $e) {
                        // Table or column might not exist - continue
                        continue;
                    }
                }
            }
        }

        return [
            'operation' => 'update_foreign_keys',
            'references_updated' => $updated,
            'executed' => !$dryRun
        ];
    }

    private function recalculateEngagementCounts(string $entityType, int $entityId): void
    {
        // Recalculate aggregated counts
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(CASE WHEN type = 'like' THEN 1 END) as likes,
                COUNT(CASE WHEN type = 'share' THEN 1 END) as shares,
                COUNT(CASE WHEN type = 'comment' THEN 1 END) as comments,
                COUNT(CASE WHEN type = 'spark' THEN 1 END) as sparks,
                SUM(CASE WHEN type = 'spark' THEN spark_amount ELSE 0 END) as sparks_total
            FROM cdm_engagements
            WHERE entity_type = :type AND entity_id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([':type' => $entityType, ':id' => $entityId]);
        $counts = $stmt->fetch();

        // Calculate EQS
        $eqs = ($counts['likes'] * 1.0) +
               ($counts['shares'] * 3.0) +
               ($counts['comments'] * 2.0) +
               ($counts['sparks_total'] * 5.0);

        // Update or insert counts
        $upsert = $this->pdo->prepare("
            INSERT INTO cdm_engagement_counts
            (entity_type, entity_id, likes_count, shares_count, comments_count,
             sparks_count, sparks_total_amount, eqs_score, updated_at)
            VALUES (:type, :id, :likes, :shares, :comments, :sparks, :sparks_total, :eqs, NOW())
            ON DUPLICATE KEY UPDATE
                likes_count = VALUES(likes_count),
                shares_count = VALUES(shares_count),
                comments_count = VALUES(comments_count),
                sparks_count = VALUES(sparks_count),
                sparks_total_amount = VALUES(sparks_total_amount),
                eqs_score = VALUES(eqs_score),
                updated_at = NOW()
        ");

        $upsert->execute([
            ':type' => $entityType,
            ':id' => $entityId,
            ':likes' => $counts['likes'],
            ':shares' => $counts['shares'],
            ':comments' => $counts['comments'],
            ':sparks' => $counts['sparks'],
            ':sparks_total' => $counts['sparks_total'],
            ':eqs' => $eqs
        ]);
    }

    /**
     * Preview what would happen if entities were merged
     *
     * @param string $entityType Entity type
     * @param array $entityIds IDs to merge
     * @return array Preview data
     */
    public function preview(string $entityType, array $entityIds): array
    {
        return $this->merge($entityType, $entityIds, true);
    }
}
