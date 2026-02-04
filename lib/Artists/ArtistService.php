<?php
namespace NGN\Lib\Artists;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class ArtistService
{
    private PDO $read;

    public function __construct(Config $config)
    {
        $this->read = ConnectionFactory::read($config);
    }

    /**
     * List artists with simple search/pagination/sort.
     * NOTE: This uses best-guess column names. If tables/columns differ in your DB, this will gracefully return empty sets.
     *
     * @return array{items: array<int, array<string,mixed>>, total: int}
     */
    public function list(
        string $search = '',
        ?int $labelId = null,
        int $page = 1,
        int $perPage = 10,
        string $sort = 'name',
        string $dir = 'asc'
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $allowedSort = ['name','latest_release_date','popularity'];
        if (!in_array($sort, $allowedSort, true)) $sort = 'name';
        $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';

        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = '(a.name LIKE :search)';
            $params[':search'] = '%'.$search.'%';
        }
        if ($labelId !== null) {
            $where[] = 'a.label_id = :labelId';
            $params[':labelId'] = $labelId;
        }
        $whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

        // Map sort to columns (best-guess schema)
        $sortCol = $sort;
        if ($sort === 'latest_release_date') $sortCol = 'a.latest_release_date';
        elseif ($sort === 'popularity') $sortCol = 'a.popularity';
        else $sortCol = 'a.name';

        $offset = ($page - 1) * $perPage;

        try {
            // Basic select from ngn_2025 tables
            $sql = "SELECT a.id, a.slug, a.name, a.image_url AS image, a.bio, a.website,
                           a.city, a.state, a.genre, a.label_id,
                           l.name AS label_name
                    FROM `ngn_2025`.`artists` a
                    LEFT JOIN `ngn_2025`.`labels` l ON l.id = a.label_id
                    $whereSql
                    ORDER BY $sortCol $dir
                    LIMIT :limit OFFSET :offset";
            $stmt = $this->read->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Total
            $countSql = "SELECT COUNT(*) AS c FROM `ngn_2025`.`artists` a
                         ".($whereSql ? $whereSql : '');
            $cStmt = $this->read->prepare($countSql);
            foreach ($params as $k => $v) {
                $cStmt->bindValue($k, $v);
            }
            $cStmt->execute();
            $total = (int)($cStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
        } catch (\Throwable $e) {
            // If table/columns don't exist yet, fail soft with empty payload
            $items = [];
            $total = 0;
        }

        // Normalize fields to expected keys
        $norm = [];
        foreach ($items as $r) {
            $norm[] = [
                'id' => isset($r['id']) ? (int)$r['id'] : null,
                'slug' => $r['slug'] ?? null,
                'name' => $r['name'] ?? null,
                'image' => $r['image'] ?? null,
                'bio' => $r['bio'] ?? null,
                'website' => $r['website'] ?? null,
                'city' => $r['city'] ?? null,
                'state' => $r['state'] ?? null,
                'genre' => $r['genre'] ?? null,
                'label_id' => isset($r['label_id']) ? (int)$r['label_id'] : null,
                'label_name' => $r['label_name'] ?? null,
            ];
        }

        return ['items' => $norm, 'total' => $total];
    }

    /**
     * Get a single artist by id
     * @return array<string,mixed>|null
     */
    public function get(int $id): ?array
    {
        try {
            $sql = "SELECT a.id, a.slug, a.name, a.bio, a.image_url AS image, a.website,
                           a.city, a.state, a.genre, a.label_id,
                           l.name AS label_name
                    FROM `ngn_2025`.`artists` a
                    LEFT JOIN `ngn_2025`.`labels` l ON l.id = a.label_id
                    WHERE a.id = :id";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$row) return null;
            return [
                'id' => isset($row['id']) ? (int)$row['id'] : null,
                'slug' => $row['slug'] ?? null,
                'name' => $row['name'] ?? null,
                'bio' => $row['bio'] ?? null,
                'image' => $row['image'] ?? null,
                'website' => $row['website'] ?? null,
                'city' => $row['city'] ?? null,
                'state' => $row['state'] ?? null,
                'genre' => $row['genre'] ?? null,
                'label_id' => isset($row['label_id']) ? (int)$row['label_id'] : null,
                'label_name' => $row['label_name'] ?? null,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get emerging artists based on NGN Score momentum (Discovery Engine integration)
     *
     * @param int $limit Number of artists to return
     * @return array List of emerging artists
     */
    public function getEmergingArtists(int $limit = 10): array
    {
        try {
            $sql = "SELECT a.id, a.name, a.image_url AS image, a.primary_genre AS genre, ais.ngn_score, ais.ngn_momentum
                    FROM `ngn_2025`.`artists` a
                    LEFT JOIN `ngn_2025`.`artist_intelligence_scores` ais ON a.id = ais.artist_id
                    WHERE ais.ngn_momentum > 0
                    AND a.status = 'active'
                    ORDER BY ais.ngn_momentum DESC, ais.ngn_score DESC
                    LIMIT :limit";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get artists by genre (Discovery Engine integration)
     *
     * @param string $genre Genre slug or name
     * @param int $limit Number of artists to return
     * @return array List of artists in genre
     */
    public function getArtistsByGenre(string $genre, int $limit = 20): array
    {
        try {
            $sql = "SELECT a.id, a.name, a.image_url AS image, a.primary_genre AS genre, ais.ngn_score
                    FROM `ngn_2025`.`artists` a
                    LEFT JOIN `ngn_2025`.`artist_intelligence_scores` ais ON a.id = ais.artist_id
                    WHERE (a.primary_genre LIKE :genre OR a.primary_genre = :genre_exact)
                    AND a.status = 'active'
                    ORDER BY ais.ngn_score DESC
                    LIMIT :limit";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':genre', '%'.$genre.'%');
            $stmt->bindValue(':genre_exact', $genre);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get similar artists (Discovery Engine integration)
     *
     * @param int $artistId Artist ID
     * @param int $limit Number of similar artists to return
     * @return array List of similar artists
     */
    public function getSimilarArtists(int $artistId, int $limit = 10): array
    {
        try {
            $sql = "SELECT a.id, a.name, a.image_url AS image, a.primary_genre AS genre, ais.ngn_score, asim.similarity_score
                    FROM `ngn_2025`.`artist_similarity` asim
                    JOIN `ngn_2025`.`artists` a ON asim.similar_artist_id = a.id
                    LEFT JOIN `ngn_2025`.`artist_intelligence_scores` ais ON a.id = ais.artist_id
                    WHERE asim.artist_id = :artist_id
                    ORDER BY asim.similarity_score DESC
                    LIMIT :limit";
            $stmt = $this->read->prepare($sql);
            $stmt->bindValue(':artist_id', $artistId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
