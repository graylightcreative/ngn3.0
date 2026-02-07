<?php

namespace NGN\Lib\Services;

use PDO;
use Exception;

/**
 * EntityService - General purpose management for core entities
 * 
 * Handles:
 * - Artists
 * - Labels
 * - Users
 * - Stations
 */
class EntityService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get paginated list of entities
     */
    public function getList(string $type, int $limit = 50, int $offset = 0, ?string $search = null): array
    {
        $table = $this->mapTypeToTable($type);
        $searchField = $this->getSearchField($type);

        $sql = "SELECT * FROM $table";
        
        if ($search) {
            $sql .= " WHERE $searchField LIKE :search";
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        
        if ($search) {
            $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get total count for pagination
        $countSql = "SELECT COUNT(*) FROM $table";
        if ($search) {
            $countSql .= " WHERE $searchField LIKE ?";
            $countParams = ["%$search%"];
        } else {
            $countParams = [];
        }
        
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $total = (int)$countStmt->fetchColumn();

        return [
            'items' => $items,
            'total' => $total,
            'page' => floor($offset / $limit) + 1,
            'limit' => $limit
        ];
    }

    /**
     * Get single entity by ID
     */
    public function get(string $type, int $id): ?array
    {
        $table = $this->mapTypeToTable($type);
        $stmt = $this->pdo->prepare("SELECT * FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        return $item ?: null;
    }

    /**
     * Update entity status or fields
     */
    public function update(string $type, int $id, array $data): bool
    {
        $table = $this->mapTypeToTable($type);
        
        // Allowed fields for update (whitelist to prevent overwriting critical data)
        $allowed = ['status', 'claimed', 'verified', 'name', 'bio', 'display_name'];
        
        $updates = [];
        $params = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $updates[] = "$key = ?";
                $params[] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $id;
        $sql = "UPDATE $table SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    private function mapTypeToTable(string $type): string
    {
        return match ($type) {
            'artists' => 'artists',
            'users' => 'users',
            'labels' => 'labels',
            'stations' => 'stations',
            default => throw new Exception("Invalid entity type: $type")
        };
    }

    private function getSearchField(string $type): string
    {
        return match ($type) {
            'artists', 'labels', 'stations' => 'name',
            'users' => 'email',
            default => 'id'
        };
    }
}
