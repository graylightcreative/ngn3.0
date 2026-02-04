<?php
namespace NGN\Lib\Rankings;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class RankingService
{
    private PDO $pdo; // Renamed for clarity, now directly points to rankings2025
    private bool $dbReady = false;
    private Config $config; // Store Config for consistent access

    public function __construct(Config $config)
    {
        $this->config = $config;
        try {
            // Prefer dedicated rankings_2025 shard
            $this->pdo = ConnectionFactory::named($config, 'rankings2025');
            $this->dbReady = true;
        } catch (\Throwable $e) {
            $this->pdo = null;
            $this->dbReady = false;
            // Log this error as it indicates a critical missing connection
            error_log("Failed to connect to rankings2025 database: " . $e->getMessage());
        }
    }

    /**
     * Return top artists rankings (DB-backed when available, fallback to mock).
     * @param int $top
     * @param string $interval one of daily|weekly|monthly
     * @return array{items: array<int, array{id:int,name:string,score:float,rank:int,delta:int}>, interval:string, top:int}
     */
    public function topArtists(int $top, string $interval): array
    {
        if ($this->dbReady) {
            $list = $this->list('artists', $interval, 1, $top, 'rank', 'asc');
            return ['items' => $list['items'], 'interval' => $interval, 'top' => $top];
        }
        $items = $this->mockItems('artist', $top, $interval);
        return ['items' => $items, 'interval' => $interval, 'top' => $top];
    }

    /**
     * @return array{items: array<int, array{id:int,name:string,score:float,rank:int,delta:int}>, interval:string, top:int}
     */
    public function topLabels(int $top, string $interval): array
    {
        if ($this->dbReady) {
            $list = $this->list('labels', $interval, 1, $top, 'rank', 'asc');
            return ['items' => $list['items'], 'interval' => $interval, 'top' => $top];
        }
        $items = $this->mockItems('label', $top, $interval);
        return ['items' => $items, 'interval' => $interval, 'top' => $top];
    }

    /**
     * @return array{items: array<int, array{id:int,name:string,score:float,rank:int,delta:int}>, interval:string, top:int}
     */
    public function topGenres(int $top, string $interval): array
    {
        if ($this->dbReady) {
            $list = $this->list('genres', $interval, 1, $top, 'rank', 'asc');
            return ['items' => $list['items'], 'interval' => $interval, 'top' => $top];
        }
        $items = $this->mockItems('genre', $top, $interval);
        return ['items' => $items, 'interval' => $interval, 'top' => $top];
    }

    /**
     * Paginated + sorted rankings (DB-backed when available; fallback to mock).
     * For NGN 2.0, prefer ngn_rankings_2025.ranking_windows + ranking_items.
     * @return array{items: array<int, array{id:int,name:string,score:float,rank:int,delta:int}>, total:int}
     */
    public function list(string $type, string $interval, int $page = 1, int $perPage = 10, string $sort = 'rank', string $dir = 'asc'): array
    {
        $type = strtolower($type);
        // Normalize supported inputs
        $resource = in_array($type, ['artists','labels','genres'], true) ? $type : 'artists';
        $interval = in_array(strtolower($interval), ['daily','weekly','monthly'], true) ? strtolower($interval) : 'daily';
        $perPage = max(1, min($perPage, 100));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $dirSql = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';

        if ($this->dbReady && $this->pdo) {
            try {
                // We are always on the 2025 rankings shard
                $is2025 = true; // No need to probe anymore, we connected directly to rankings2025

                if ($is2025) {
                    // New 2025 schema: ranking_windows + ranking_items
                    $entityType = $resource === 'artists' ? 'artist' : ($resource === 'labels' ? 'label' : 'genre');
                    if ($entityType === 'genre') {
                        // No genre rankings yet in 2025 schema; fall back to mock
                        throw new \RuntimeException('genres_not_supported_2025');
                    }

                    // Latest window for interval
                    $w = $this->pdo->prepare('SELECT id, window_start, window_end FROM `ngn_rankings_2025`.`ranking_windows` WHERE `interval` = :i ORDER BY window_start DESC LIMIT 1');
                    $w->execute([':i' => $interval]);
                    $win = $w->fetch(PDO::FETCH_ASSOC) ?: null;
                    if ($win) {
                        $wid = (int)$win['id'];
                        $orderCol = (strtolower($sort) === 'score') ? 'score' : 'rank';
                        $sql = "SELECT SQL_CALC_FOUND_ROWS entity_id AS id, score, rank, prev_rank
                                FROM `ngn_rankings_2025`.`ranking_items`
                                WHERE window_id = :wid AND entity_type = :et
                                ORDER BY `{$orderCol}` {$dirSql}, entity_id ASC
                                LIMIT :lim OFFSET :off";
                        $stmt = $this->pdo->prepare($sql);
                        $stmt->bindValue(':wid', $wid, PDO::PARAM_INT);
                        $stmt->bindValue(':et', $entityType, PDO::PARAM_STR);
                        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
                        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
                        $stmt->execute();
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        $total = (int)($this->pdo->query('SELECT FOUND_ROWS()')->fetchColumn() ?: 0);

                        // Best-effort join to ngn_2025 for display names
                        $nameMap = [];
                        if ($this->config) {
                            try {
                                $pdoPrimary = ConnectionFactory::read($this->config); // Use primary ngn_2025 connection
                                $ids = array_values(array_unique(array_map('intval', array_column($rows, 'id'))));
                                if (count($ids) > 0) {
                                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                                    if ($entityType === 'artist') {
                                        $nsql = "SELECT id, name FROM `ngn_2025`.`artists` WHERE id IN ($placeholders)";
                                    } else {
                                        $nsql = "SELECT id, name FROM `ngn_2025`.`labels` WHERE id IN ($placeholders)";
                                    }
                                    $nstmt = $pdoPrimary->prepare($nsql);
                                    foreach ($ids as $idx => $val) {
                                        $nstmt->bindValue($idx + 1, $val, PDO::PARAM_INT);
                                    }
                                    $nstmt->execute();
                                    $nameRows = $nstmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                                    foreach ($nameRows as $nr) {
                                        $nameMap[(int)$nr['id']] = (string)$nr['name'];
                                    }
                                }
                            } catch (\Throwable $eNames) {
                                // ignore name lookup errors; fall back to ID labels
                                error_log("Error in RankingService name lookup: " . $eNames->getMessage());
                            }
                        }

                        $items = [];
                        foreach ($rows as $r) {
                            $idVal = (int)($r['id'] ?? 0);
                            $name = $nameMap[$idVal] ?? ('ID '.$idVal);
                            $items[] = [
                                'id' => $idVal,
                                'name' => $name,
                                'score' => isset($r['score']) ? (float)$r['score'] : 0.0,
                                'rank' => (int)($r['rank'] ?? 0),
                                'delta' => isset($r['prev_rank']) && (int)$r['prev_rank'] > 0
                                    ? ((int)$r['prev_rank'] - (int)$r['rank'])
                                    : 0,
                            ];
                        }
                        return ['items' => $items, 'total' => $total];
                    }
                    // If no window exists yet, fall through to mock
                }
            } catch (\Throwable $e) {
                error_log("Error in RankingService list: " . $e->getMessage());
                // On any DB error, fall back to mock
            }
        }

        // Fallback: mock list of 100, sorted and sliced
        $all = $this->mockItems($resource === 'artists' ? 'artist' : ($resource === 'labels' ? 'label' : 'genre'), 100, $interval);
        $sorted = $this->sort($all, $sort, $dir);
        $items = array_slice($sorted, $offset, $perPage);
        return ['items' => $items, 'total' => count($all)];
    }

    /**
     * Return ranking item for a specific entity id in the latest window for the interval.
     * For NGN 2.0, prefer ngn_rankings_2025.ranking_items when available.
     * @return array{id:int,name:string,score:float,rank:int,delta:int}|null
     */
    public function itemFor(string $type, string $interval, int $id): ?array
    {
        $type = strtolower($type);
        $resource = in_array($type, ['artists','labels','genres'], true) ? $type : 'artists';
        $interval = in_array(strtolower($interval), ['daily','weekly','monthly'], true) ? strtolower($interval) : 'daily';
        $id = (int)$id;

        if ($this->dbReady && $this->pdo && $id > 0) {
            try {
                // We are always on the 2025 rankings shard
                $is2025 = true; // No need to probe anymore, we connected directly to rankings2025

                if ($is2025) {
                    $entityType = $resource === 'artists' ? 'artist' : ($resource === 'labels' ? 'label' : 'genre');
                    if ($entityType === 'genre') {
                        throw new \RuntimeException('genres_not_supported_2025');
                    }
                    // Latest window for interval
                    $w = $this->pdo->prepare('SELECT id FROM `ngn_rankings_2025`.`ranking_windows` WHERE `interval` = :i ORDER BY window_start DESC LIMIT 1');
                    $w->execute([':i' => $interval]);
                    $win = $w->fetch(PDO::FETCH_ASSOC) ?: null;
                    if ($win) {
                        $wid = (int)$win['id'];
                        $sql = "SELECT entity_id AS id, score, rank, prev_rank
                                FROM `ngn_rankings_2025`.`ranking_items`
                                WHERE window_id = :wid AND entity_type = :et AND entity_id = :id
                                LIMIT 1";
                        $stmt = $this->pdo->prepare($sql);
                        $stmt->bindValue(':wid', $wid, PDO::PARAM_INT);
                        $stmt->bindValue(':et', $entityType, PDO::PARAM_STR);
                        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                        $stmt->execute();
                        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                        if ($row) {
                            $name = 'ID '.(int)($row['id'] ?? 0);
                            if ($this->config) {
                                try {
                                    $pdoPrimary = ConnectionFactory::read($this->config); // Use primary ngn_2025 connection
                                    if ($entityType === 'artist') {
                                        $nsql = 'SELECT name FROM `ngn_2025`.`artists` WHERE id = :id LIMIT 1';
                                    } else {
                                        $nsql = 'SELECT name FROM `ngn_2025`.`labels` WHERE id = :id LIMIT 1';
                                    }
                                    $nstmt = $pdoPrimary->prepare($nsql);
                                    $nstmt->bindValue(':id', $id, PDO::PARAM_INT);
                                    $nstmt->execute();
                                    $nrow = $nstmt->fetch(PDO::FETCH_ASSOC) ?: null;
                                    if ($nrow && isset($nrow['name']) && $nrow['name'] !== '') {
                                        $name = (string)$nrow['name'];
                                    }
                                } catch (\Throwable $eNames) {
                                    // ignore name lookup errors
                                    error_log("Error in RankingService name lookup: " . $eNames->getMessage());
                                }
                            }
                            return [
                                'id' => (int)($row['id'] ?? 0),
                                'name' => $name,
                                'score' => isset($row['score']) ? (float)$row['score'] : 0.0,
                                'rank' => (int)($row['rank'] ?? 0),
                                'delta' => isset($row['prev_rank']) && (int)$row['prev_rank'] > 0
                                    ? ((int)$row['prev_rank'] - (int)$row['rank'])
                                    : 0,
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {
                error_log("Error in RankingService itemFor: " . $e->getMessage());
                // fall through to mock
            }
        }

    private function sort(array $items, string $sort, string $dir): array
    {
        $dirMul = strtolower($dir) === 'desc' ? -1 : 1;
        if (!in_array($sort, ['rank','score'], true)) $sort = 'rank';
        usort($items, function($a, $b) use ($sort, $dirMul) {
            $va = $a[$sort] ?? 0;
            $vb = $b[$sort] ?? 0;
            if ($va == $vb) return 0;
            return ($va < $vb ? -1 : 1) * $dirMul;
        });
        return $items;
    }

    private function mockItems(string $type, int $top, string $interval): array
    {
        $top = max(1, min($top, 100));
        $seed = crc32($type.'|'.$interval);
        mt_srand($seed);
        $items = [];
        for ($i = 1; $i <= $top; $i++) {
            $id = $i;
            $name = ucfirst($type).' '.$i;
            $score = round(1000.0 / $i + (mt_rand(0, 100) / 100.0), 2);
            $delta = mt_rand(-5, 5);
            $items[] = [
                'id' => (int)$id,
                'name' => (string)$name,
                'score' => (float)$score,
                'rank' => (int)$i,
                'delta' => (int)$delta,
            ];
        }
        usort($items, fn($a, $b) => $a['rank'] <=> $b['rank']);
        return $items;
    }
}
