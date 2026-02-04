<?php
namespace NGN\Lib\Labels;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class LabelService
{
    private PDO $read;

    public function __construct(Config $config)
    {
        $this->read = ConnectionFactory::read($config);
    }

    /**
     * List labels with simple search/pagination/sort.
     * @return array{items: array<int, array<string,mixed>>, total: int}
     */
    public function list(string $search = '', int $page = 1, int $perPage = 10, string $sort = 'name', string $dir = 'asc'): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $allowedSort = ['name'];
        if (!in_array($sort, $allowedSort, true)) $sort = 'name';
        $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';

        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = '(l.name LIKE :search)';
            $params[':search'] = '%'.$search.'%';
        }
        $whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';
        $offset = ($page - 1) * $perPage;

        try {
            $sql = "SELECT l.id, l.slug, l.name, l.image_url AS image, l.bio, l.website,
                           l.city, l.state
                    FROM `ngn_2025`.`labels` l
                    $whereSql
                    ORDER BY l.name $dir
                    LIMIT :limit OFFSET :offset";
            $stmt = $this->read->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $countSql = "SELECT COUNT(*) AS c FROM `ngn_2025`.`labels` l $whereSql";
            $cStmt = $this->read->prepare($countSql);
            foreach ($params as $k => $v) $cStmt->bindValue($k, $v);
            $cStmt->execute();
            $total = (int)($cStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
        } catch (\Throwable $e) {
            $rows = [];
            $total = 0;
        }

        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'id' => isset($r['id']) ? (int)$r['id'] : null,
                'slug' => $r['slug'] ?? null,
                'name' => $r['name'] ?? null,
                'image' => $r['image'] ?? null,
                'bio' => $r['bio'] ?? null,
                'website' => $r['website'] ?? null,
                'city' => $r['city'] ?? null,
                'state' => $r['state'] ?? null,
            ];
        }
        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get a single label by id
     * @return array<string,mixed>|null
     */
    public function get(int $id): ?array
    {
        try {
            $sql = "SELECT l.id, l.slug, l.name, l.image_url AS image, l.bio, l.website,
                           l.city, l.state
                    FROM `ngn_2025`.`labels` l WHERE l.id = :id";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$row) return null;
            return [
                'id' => isset($row['id']) ? (int)$row['id'] : null,
                'slug' => $row['slug'] ?? null,
                'name' => $row['name'] ?? null,
                'image' => $row['image'] ?? null,
                'bio' => $row['bio'] ?? null,
                'website' => $row['website'] ?? null,
                'city' => $row['city'] ?? null,
                'state' => $row['state'] ?? null,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * List artists for a label with pagination.
     * @return array{items: array<int, array<string,mixed>>, total: int}
     */
    public function artists(int $labelId, int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;
        try {
            $sql = "SELECT a.id, a.slug, a.name, a.image_url AS image, a.genre, a.city, a.state
                    FROM `ngn_2025`.`artists` a WHERE a.label_id = :labelId
                    ORDER BY a.name ASC LIMIT :limit OFFSET :offset";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':labelId', $labelId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $cStmt = $this->read->prepare("SELECT COUNT(*) AS c FROM `ngn_2025`.`artists` a WHERE a.label_id = :labelId");
            $cStmt->bindValue(':labelId', $labelId, PDO::PARAM_INT);
            $cStmt->execute();
            $total = (int)($cStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
        } catch (\Throwable $e) {
            $rows = [];
            $total = 0;
        }
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'id' => isset($r['id']) ? (int)$r['id'] : null,
                'slug' => $r['slug'] ?? null,
                'name' => $r['name'] ?? null,
                'image' => $r['image'] ?? null,
                'genre' => $r['genre'] ?? null,
                'city' => $r['city'] ?? null,
                'state' => $r['state'] ?? null,
            ];
        }
        return ['items' => $items, 'total' => $total];
    }
}
