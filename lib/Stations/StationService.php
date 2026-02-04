<?php
namespace NGN\Lib\Stations;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class StationService
{
    private PDO $read;

    public function __construct(Config $config)
    {
        $this->read = ConnectionFactory::read($config);
    }

    /**
     * List stations with search/filters/pagination/sort.
     * @return array{items: array<int, array<string,mixed>>, total: int}
     */
    public function list(string $search = '', ?string $region = null, ?string $format = null, int $page = 1, int $perPage = 10, string $sort = 'name', string $dir = 'asc'): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $allowedSort = ['name','region','format'];
        if (!in_array($sort, $allowedSort, true)) $sort = 'name';
        $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';

        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = '(s.name LIKE :search)';
            $params[':search'] = '%'.$search.'%';
        }
        if ($region !== null && $region !== '') {
            $where[] = 's.region = :region';
            $params[':region'] = $region;
        }
        if ($format !== null && $format !== '') {
            $where[] = 's.format = :format';
            $params[':format'] = $format;
        }
        $whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';
        $offset = ($page - 1) * $perPage;

        try {
            $sortCol = $sort === 'region' ? 's.region' : ($sort === 'format' ? 's.format' : 's.name');
            $sql = "SELECT s.id, s.slug, s.name, s.image_url AS image, s.region, s.format,
                           s.bio, s.website, s.city, s.state
                    FROM `ngn_2025`.`stations` s
                    $whereSql
                    ORDER BY $sortCol $dir, s.name ASC
                    LIMIT :limit OFFSET :offset";
            $stmt = $this->read->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $countSql = "SELECT COUNT(*) AS c FROM `ngn_2025`.`stations` s $whereSql";
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
                'region' => $r['region'] ?? null,
                'format' => $r['format'] ?? null,
                'bio' => $r['bio'] ?? null,
                'website' => $r['website'] ?? null,
                'city' => $r['city'] ?? null,
                'state' => $r['state'] ?? null,
            ];
        }
        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get station detail by id
     * @return array<string,mixed>|null
     */
    public function get(int $id): ?array
    {
        try {
            $sql = "SELECT s.id, s.slug, s.name, s.image_url AS image, s.region, s.format,
                           s.bio, s.website, s.city, s.state
                    FROM `ngn_2025`.`stations` s WHERE s.id = :id";
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
                'region' => $row['region'] ?? null,
                'format' => $row['format'] ?? null,
                'bio' => $row['bio'] ?? null,
                'website' => $row['website'] ?? null,
                'city' => $row['city'] ?? null,
                'state' => $row['state'] ?? null,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
