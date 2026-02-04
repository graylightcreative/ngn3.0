<?php
namespace NGN\Lib\Backup;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class DbDumper
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Dump a database connection ("world") to a gzipped SQL file without using exec().
     * Returns array with path, bytes, duration, tables, rows.
     *
     * @param string $world Named connection: primary|dev|ngnrankings|smrrankings|ngnspins|ngnnotes
     * @param array $options [schema_only(bool), data_only(bool), include_tables(array), exclude_tables(array), batch(int), cap(int), dry_run(bool)]
     */
    public function dump(string $world, array $options = []): array
    {
        $t0 = microtime(true);
        $schemaOnly = (bool)($options['schema_only'] ?? false);
        $dataOnly   = (bool)($options['data_only'] ?? false);
        $include    = $options['include_tables'] ?? null; // null → all
        $exclude    = $options['exclude_tables'] ?? [];
        $batch      = (int)($options['batch'] ?? 1000);
        $cap        = (int)($options['cap'] ?? 0); // 0 → no cap
        $dryRun     = (bool)($options['dry_run'] ?? false);

        // Resolve connection
        $pdo = $this->resolvePdo($world);
        if (!$pdo) {
            return [ 'ok' => false, 'error' => 'incomplete_config', 'message' => 'One or more DB config vars missing for world '.$world ];
        }

        // Identify database name
        $dbName = $this->currentDatabase($pdo);
        $ts = date('YmdHis');
        // Determine project root without relying on Config::root()
        $root = realpath(__DIR__ . '/../../') ?: dirname(__DIR__, 2);
        $dir = $this->ensureDir($root.'/storage/backups/db');
        $filename = sprintf('%s/%s-%s.sql.gz', $dir, $world, $ts);

        $tables = $this->listTables($pdo, $include, $exclude);
        $meta = [ 'world' => $world, 'database' => $dbName, 'tables' => [], 'rows_total' => 0 ];

        if ($dryRun) {
            // Estimate row counts quickly (best effort)
            foreach ($tables as $t) {
                $cnt = $this->safeCount($pdo, $t);
                $meta['tables'][] = ['name'=>$t, 'rows'=>$cnt];
                $meta['rows_total'] += $cnt;
            }
            return [ 'ok' => true, 'dry_run' => true, 'estimate' => $meta, 'path' => null, 'bytes' => 0, 'duration_ms' => (int)((microtime(true)-$t0)*1000) ];
        }

        $gz = @gzopen($filename, 'w9');
        if (!$gz) {
            return [ 'ok' => false, 'error' => 'file_open_failed', 'message' => 'Unable to open output file for writing: '.$filename ];
        }

        $write = function(string $s) use ($gz) { @gzwrite($gz, $s); };

        $write("-- NGN DB Dump\n");
        $write("-- world: {$world}\n");
        $write("-- database: {$dbName}\n");
        $write("-- created_at: ".date('c')."\n\n");
        $write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach ($tables as $t) {
            if (!$dataOnly) {
                $create = $this->showCreate($pdo, $t);
                $write("--\n-- Table structure for table `{$t}`\n--\n\n");
                $write("DROP TABLE IF EXISTS `{$t}`;\n");
                $write($create.";\n\n");
            }
            if ($schemaOnly) { continue; }

            // Data rows in batches
            $total = $this->safeCount($pdo, $t);
            $remainingCap = $cap > 0 ? $cap : PHP_INT_MAX;
            $offset = 0;
            $columns = $this->columns($pdo, $t);
            $colList = implode('`, `', array_map(fn($c)=>str_replace('`','``',$c), $columns));
            while ($offset < $total && $remainingCap > 0) {
                $lim = (int)min($batch, $remainingCap);
                $rows = $this->selectBatch($pdo, $t, $offset, $lim);
                if (!$rows) break;
                $vals = [];
                foreach ($rows as $row) {
                    $vals[] = '(' . $this->rowValues($pdo, $columns, $row) . ')';
                }
                $write("INSERT INTO `{$t}` (`{$colList}`) VALUES\n" . implode(",\n", $vals) . ";\n");
                $offset += count($rows);
                $remainingCap -= count($rows);
            }
            $write("\n");
            $meta['tables'][] = ['name'=>$t, 'rows'=>min($total, $cap>0? $cap : $total)];
            $meta['rows_total'] += min($total, $cap>0? $cap : $total);
        }

        $write("SET FOREIGN_KEY_CHECKS=1;\n");
        @gzclose($gz);

        $bytes = @filesize($filename) ?: 0;
        // Metadata sidecar
        @file_put_contents($filename.'.json', json_encode([
            'meta' => $meta,
            'bytes' => $bytes,
            'sha256' => @hash_file('sha256', $filename) ?: null,
            'created_at' => date('c'),
        ], JSON_PRETTY_PRINT));

        return [
            'ok' => true,
            'path' => $filename,
            'bytes' => $bytes,
            'duration_ms' => (int)((microtime(true)-$t0)*1000),
            'meta' => $meta,
        ];
    }

    private function resolvePdo(string $world): ?PDO
    {
        try {
            if ($world === 'primary') return ConnectionFactory::write($this->config);
            return ConnectionFactory::named($this->config, $world);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function currentDatabase(PDO $pdo): string
    {
        try { return (string)$pdo->query('SELECT DATABASE()')->fetchColumn(); } catch (\Throwable $e) { return ''; }
    }

    private function ensureDir(string $dir): string
    {
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        return $dir;
    }

    private function listTables(PDO $pdo, ?array $include, array $exclude): array
    {
        $tables = [];
        try {
            $stmt = $pdo->query('SHOW TABLES');
            foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $row) {
                $tables[] = $row[0];
            }
        } catch (\Throwable $e) { $tables = []; }

        if ($include && count($include)) {
            $tables = array_values(array_intersect($tables, $include));
        }
        if ($exclude && count($exclude)) {
            $tables = array_values(array_diff($tables, $exclude));
        }
        sort($tables, SORT_NATURAL|SORT_FLAG_CASE);
        return $tables;
    }

    private function showCreate(PDO $pdo, string $table): string
    {
        try {
            $stmt = $pdo->query('SHOW CREATE TABLE `'.str_replace('`','``',$table).'`');
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $keys = array_keys($row);
            $create = $row[$keys[1] ?? 'Create Table'] ?? '';
            return $create;
        } catch (\Throwable $e) {
            return 'CREATE TABLE `'.str_replace('`','``',$table).'` (/* failed to fetch CREATE TABLE: '.addslashes($e->getMessage()).' */)';
        }
    }

    private function columns(PDO $pdo, string $table): array
    {
        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM `'.str_replace('`','``',$table).'`');
            $cols = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $cols[] = $r['Field']; }
            return $cols;
        } catch (\Throwable $e) { return []; }
    }

    private function selectBatch(PDO $pdo, string $table, int $offset, int $limit): array
    {
        try {
            $sql = 'SELECT * FROM `'.str_replace('`','``',$table).'` LIMIT :lim OFFSET :off';
            $st = $pdo->prepare($sql);
            $st->bindValue(':lim', $limit, PDO::PARAM_INT);
            $st->bindValue(':off', $offset, PDO::PARAM_INT);
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) { return []; }
    }

    private function rowValues(PDO $pdo, array $columns, array $row): string
    {
        $vals = [];
        foreach ($columns as $c) {
            if (!array_key_exists($c, $row) || $row[$c] === null) { $vals[] = 'NULL'; continue; }
            $v = $row[$c];
            if (is_int($v) || is_float($v)) { $vals[] = (string)$v; continue; }
            // Treat everything else as string and quote via PDO::quote
            $vals[] = $pdo->quote((string)$v);
        }
        return implode(',', $vals);
    }

    private function safeCount(PDO $pdo, string $table): int
    {
        try {
            $stmt = $pdo->query('SELECT COUNT(*) FROM `'.str_replace('`','``',$table).'`');
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) { return 0; }
    }
}
