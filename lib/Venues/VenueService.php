<?php
namespace NGN\Lib\Venues;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class VenueService
{
    private PDO $read;

    public function __construct(Config $config)
    {
        $this->read = ConnectionFactory::read($config);
    }

    /**
     * List venues with search/filters/pagination/sort.
     * @return array{items: array<int, array<string,mixed>>, total: int}
     */
    public function list(string $search = '', ?string $city = null, ?string $state = null, int $page = 1, int $perPage = 10, string $sort = 'name', string $dir = 'asc'): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $allowedSort = ['name', 'city', 'state', 'created_at'];
        if (!in_array($sort, $allowedSort, true)) $sort = 'name';
        $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';

        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = '(v.name LIKE :search)';
            $params[':search'] = '%'.$search.'%';
        }
        if ($city !== null && $city !== '') {
            $where[] = 'v.city = :city';
            $params[':city'] = $city;
        }
        if ($state !== null && $state !== '') {
            $where[] = 'v.state = :state';
            $params[':state'] = $state;
        }
        $whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';
        $offset = ($page - 1) * $perPage;

        try {
            $sortCol = match($sort) {
                'city' => 'v.city',
                'state' => 'v.state',
                'created_at' => 'v.created_at',
                default => 'v.name',
            };
            $sql = "SELECT v.id, v.slug, v.name, v.image_url AS image, v.bio, v.website,
                           v.city, v.state, v.address, v.zip, v.capacity, v.phone
                    FROM `ngn_2025`.`venues` v
                    $whereSql
                    ORDER BY $sortCol $dir, v.name ASC
                    LIMIT :limit OFFSET :offset";
            $stmt = $this->read->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $countSql = "SELECT COUNT(*) AS c FROM `ngn_2025`.`venues` v $whereSql";
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
                'address' => $r['address'] ?? null,
                'zip' => $r['zip'] ?? null,
                'capacity' => isset($r['capacity']) ? (int)$r['capacity'] : null,
                'phone' => $r['phone'] ?? null,
            ];
        }
        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get venue detail by id
     * @return array<string,mixed>|null
     */
    public function get(int $id): ?array
    {
        try {
            $sql = "SELECT v.id, v.slug, v.name, v.image_url AS image, v.bio, v.website,
                           v.city, v.state, v.address, v.zip, v.capacity, v.phone
                    FROM `ngn_2025`.`venues` v WHERE v.id = :id";
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
                'address' => $row['address'] ?? null,
                'zip' => $row['zip'] ?? null,
                'capacity' => isset($row['capacity']) ? (int)$row['capacity'] : null,
                'phone' => $row['phone'] ?? null,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * List upcoming shows for a venue with pagination.
     * @return array{items: array<int, array<string,mixed>>, total: int}
     */
    public function shows(int $venueId, int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;
        try {
            $sql = "SELECT s.id, s.title, s.date, s.time, s.ticket_url,
                           a.id AS artist_id, a.name AS artist_name, a.slug AS artist_slug
                    FROM `ngn_2025`.`shows` s
                    LEFT JOIN `ngn_2025`.`artists` a ON a.id = s.artist_id
                    WHERE s.venue_id = :venueId AND s.date >= CURDATE()
                    ORDER BY s.date ASC, s.time ASC
                    LIMIT :limit OFFSET :offset";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':venueId', $venueId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $cStmt = $this->read->prepare("SELECT COUNT(*) AS c FROM `ngn_2025`.`shows` s WHERE s.venue_id = :venueId AND s.date >= CURDATE()");
            $cStmt->bindValue(':venueId', $venueId, PDO::PARAM_INT);
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
                'title' => $r['title'] ?? null,
                'date' => $r['date'] ?? null,
                'time' => $r['time'] ?? null,
                'ticket_url' => $r['ticket_url'] ?? null,
                'artist_id' => isset($r['artist_id']) ? (int)$r['artist_id'] : null,
                'artist_name' => $r['artist_name'] ?? null,
                'artist_slug' => $r['artist_slug'] ?? null,
            ];
        }
        return ['items' => $items, 'total' => $total];
    }
}
