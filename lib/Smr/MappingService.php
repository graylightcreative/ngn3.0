<?php
namespace NGN\Lib\Smr;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class MappingService
{
    private Config $config;
    private ?PDO $read = null;
    private ?PDO $write = null;

    public function __construct(Config $config)
    {
        $this->config = $config;
        try { $this->read = ConnectionFactory::read($config); } catch (\Throwable $e) { $this->read = null; }
        try { $this->write = ConnectionFactory::write($config); } catch (\Throwable $e) { $this->write = null; }
    }

    /**
     * Persist a mapping for a given upload/station and return normalized result.
     * Input shape: { station_id, profile_name?, confidence?, fields: { artist, track, spins, date?, played_at? } }
     */
    public function trainMapping(string $uploadId, array $input): array
    {
        $stationId = isset($input['station_id']) ? (int)$input['station_id'] : null;
        $profile = isset($input['profile_name']) ? trim((string)$input['profile_name']) : null;
        $confidence = isset($input['confidence']) ? (int)$input['confidence'] : null;
        $fields = isset($input['fields']) && is_array($input['fields']) ? $input['fields'] : [];
        // Normalize to lowercase keys/values where applicable
        $normalized = [];
        foreach ($fields as $k => $v) {
            $nk = strtolower((string)$k);
            $normalized[$nk] = is_string($v) ? strtolower(trim($v)) : $v;
        }
        $saved = false;
        if ($this->write && $stationId) {
            try {
                // Upsert by (StationId, ProfileName) when provided; otherwise insert new active mapping for station
                $sql = "INSERT INTO smr_mappings (StationId, UploadId, ProfileName, Mapping, Confidence, IsActive, LastUsedAt, CreatedAt, UpdatedAt)
                        VALUES (:sid, :uid, :p, :m, :c, 1, NULL, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE Mapping=VALUES(Mapping), Confidence=VALUES(Confidence), IsActive=1, UpdatedAt=NOW()";
                $stmt = $this->write->prepare($sql);
                $stmt->execute([
                    ':sid' => $stationId,
                    ':uid' => $uploadId,
                    ':p' => $profile,
                    ':m' => json_encode($normalized),
                    ':c' => $confidence,
                ]);
                $saved = true;
            } catch (\Throwable $e) {
                // swallow and return accepted=false with error context
                return [
                    'upload_id' => $uploadId,
                    'station_id' => $stationId,
                    'accepted' => false,
                    'mapping' => $normalized,
                    'error' => $e->getMessage(),
                ];
            }
        }
        return [
            'upload_id' => $uploadId,
            'station_id' => $stationId,
            'accepted' => (bool)$saved,
            'mapping' => $normalized,
            'profile_name' => $profile,
            'confidence' => $confidence,
        ];
    }

    /**
     * Return the active mapping for a station, optionally including all profiles.
     */
    public function getMappings(?int $stationId = null, bool $allProfiles = false): array
    {
        if (!$this->read) return ['items' => [], 'count' => 0];
        $where = [];
        $params = [];
        if ($stationId) { $where[] = 'StationId = :sid'; $params[':sid'] = $stationId; }
        if (!$allProfiles) { $where[] = 'IsActive = 1'; }
        $sql = 'SELECT Id, StationId, UploadId, ProfileName, Mapping, Confidence, IsActive, LastUsedAt, CreatedAt, UpdatedAt FROM smr_mappings';
        if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
        $sql .= ' ORDER BY StationId ASC, (ProfileName IS NULL), ProfileName ASC, UpdatedAt DESC';
        $stmt = $this->read->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        // Decode JSON mapping
        foreach ($rows as &$r) {
            if (isset($r['Mapping']) && is_string($r['Mapping'])) {
                $dec = json_decode($r['Mapping'], true);
                if (is_array($dec)) $r['Mapping'] = $dec;
            }
            $r['Id'] = (int)($r['Id'] ?? 0);
            $r['StationId'] = isset($r['StationId']) ? (int)$r['StationId'] : null;
            $r['Confidence'] = isset($r['Confidence']) ? (int)$r['Confidence'] : null;
            $r['IsActive'] = (int)($r['IsActive'] ?? 0);
        }
        unset($r);
        return ['items' => $rows, 'count' => count($rows)];
    }

    /** Get best active mapping for a station (helper for workers). */
    public function getActiveForStation(int $stationId): ?array
    {
        if (!$this->read) return null;
        $stmt = $this->read->prepare('SELECT Mapping FROM smr_mappings WHERE StationId = :sid AND IsActive = 1 ORDER BY UpdatedAt DESC LIMIT 1');
        $stmt->execute([':sid' => $stationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$row) return null;
        $map = json_decode((string)$row['Mapping'], true);
        return is_array($map) ? $map : null;
    }

    /**
     * Update an existing mapping by Id. Accepts keys: IsActive, Confidence, fields (mapping JSON).
     * Returns the updated row (best-effort) or a status object on failure.
     */
    public function updateMapping(int $id, array $input): array
    {
        if (!$this->write) {
            return ['id' => $id, 'updated' => false, 'error' => 'db_unavailable'];
        }
        $fields = isset($input['fields']) && is_array($input['fields']) ? $input['fields'] : null;
        $normalized = null;
        if ($fields !== null) {
            $normalized = [];
            foreach ($fields as $k => $v) {
                $nk = strtolower((string)$k);
                $normalized[$nk] = is_string($v) ? strtolower(trim($v)) : $v;
            }
        }
        $isActive = array_key_exists('is_active', $input) ? (int)((bool)$input['is_active']) : null;
        $confidence = array_key_exists('confidence', $input) ? (int)$input['confidence'] : null;
        $sets = [];
        $params = [':id' => $id];
        if ($normalized !== null) { $sets[] = 'Mapping = :m'; $params[':m'] = json_encode($normalized); }
        if ($isActive !== null) { $sets[] = 'IsActive = :a'; $params[':a'] = $isActive; }
        if ($confidence !== null) { $sets[] = 'Confidence = :c'; $params[':c'] = $confidence; }
        if (empty($sets)) {
            return ['id' => $id, 'updated' => false, 'error' => 'no_changes'];
        }
        $sql = 'UPDATE smr_mappings SET ' . implode(', ', $sets) . ', UpdatedAt = NOW() WHERE Id = :id';
        try {
            $stmt = $this->write->prepare($sql);
            $stmt->execute($params);
        } catch (\Throwable $e) {
            return ['id' => $id, 'updated' => false, 'error' => $e->getMessage()];
        }
        // Return updated row if possible
        try {
            if ($this->read) {
                $stmt = $this->read->prepare('SELECT Id, StationId, UploadId, ProfileName, Mapping, Confidence, IsActive, LastUsedAt, CreatedAt, UpdatedAt FROM smr_mappings WHERE Id = :id');
                $stmt->execute([':id' => $id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($row) {
                    if (isset($row['Mapping']) && is_string($row['Mapping'])) {
                        $dec = json_decode($row['Mapping'], true);
                        if (is_array($dec)) $row['Mapping'] = $dec;
                    }
                    $row['Id'] = (int)($row['Id'] ?? 0);
                    $row['StationId'] = isset($row['StationId']) ? (int)$row['StationId'] : null;
                    $row['Confidence'] = isset($row['Confidence']) ? (int)$row['Confidence'] : null;
                    $row['IsActive'] = (int)($row['IsActive'] ?? 0);
                    return ['updated' => true, 'item' => $row];
                }
            }
        } catch (\Throwable $e) {
            // ignore readback errors
        }
        return ['id' => $id, 'updated' => true];
    }
}
