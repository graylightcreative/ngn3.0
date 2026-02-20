<?php
namespace NGN\Lib\DB;

use NGN\Lib\Config;
use NGN\Lib\Env;
use PDO;
use PDOException;

class ConnectionFactory
{
    public static function write(Config $config): PDO
    {
        $db = $config->db();
        return self::connect($db['host'], $db['port'], $db['name'], $db['user'], $db['pass']);
    }

    public static function read(Config $config): PDO
    {
        $db = $config->dbRead();
        return self::connect($db['host'], $db['port'], $db['name'], $db['user'], $db['pass']);
    }

    public static function nexus(Config $config): PDO
    {
        $db = $config->dbNexus();
        return self::connect($db['host'], $db['port'], $db['name'], $db['user'], $db['pass']);
    }

    /**
     * Connect to a named database using env keys DB_{NAME}_{HOST,PORT,NAME,USER,PASS}.
     * Example: DB_LEGACY_HOST, DB_LEGACY_NAME, etc. Name is case-insensitive.
     */
    public static function named(Config $config, string $name): PDO
    {
        $n = strtoupper(trim($name));
        
        // Try new standard pattern first: DB_{NAME}_{HOST,PORT,NAME,USER,PASS}
        $host = Env::get('DB_' . $n . '_HOST', null);
        $port = Env::get('DB_' . $n . '_PORT', null);
        $dbn  = Env::get('DB_' . $n . '_NAME', null);
        $user = Env::get('DB_' . $n . '_USER', null);
        $pass = Env::get('DB_' . $n . '_PASS', Env::get('DB_' . $n . '_PASSWORD', null));

        // Fallback to legacy patterns if needed: {NAME}_DB_{SERVER,EXTERNAL_USER,EXTERNAL_PASS,EXTERNAL_NAME}
        $host = $host ?? Env::get($n . '_DB_SERVER', Env::get($n . '_DB_HOST', ''));
        $port = (int)($port ?? Env::get($n . '_DB_PORT', Env::get('DB_PORT', '3306') ?? '3306') ?? '3306');
        $dbn  = $dbn  ?? Env::get($n . '_DB_EXTERNAL_NAME', Env::get($n . '_DB_NAME', ''));
        $user = $user ?? Env::get($n . '_DB_EXTERNAL_USER', Env::get($n . '_DB_USER', ''));
        $pass = $pass ?? Env::get($n . '_DB_EXTERNAL_PASS', Env::get($n . '_DB_PASS', Env::get($n . '_DB_PASSWORD', '')));

        // Second fallback: parse .env files directly if some fields are still missing
        if ($host === '' || $dbn === '' || $user === '') {
            [$host2, $port2, $dbn2, $user2, $pass2] = self::lookupNamedFromFiles($n);
            $host = $host ?: $host2;
            $port = $port ?: $port2;
            $dbn  = $dbn  ?: $dbn2;
            $user = $user ?: $user2;
            $pass = $pass ?: $pass2;
        }
        
        return self::connect((string)$host, (int)$port, (string)$dbn, (string)$user, (string)$pass);
    }

    /**
     * Enumerate available named DB connections from environment and .env files (best-effort).
     * Returns array of connection names, excluding the default primary/read entries.
     */
    public static function availableNamed(): array
    {
        $candidates = [];
        // 1) Superglobals and process env (DB_{NAME}_HOST and legacy {NAME}_DB_SERVER)
        $bag = array_change_key_case(array_merge($_ENV ?? [], $_SERVER ?? []), CASE_UPPER);
        foreach ($bag as $k => $v) {
            if (preg_match('/^DB_([A-Z0-9_]+)_HOST$/', $k, $m)) {
                $name = $m[1];
                if (!in_array($name, ['HOST','READ','PORT','NAME','USER','PASS'], true)) { $candidates[$name] = true; }
            }
            if (preg_match('/^([A-Z0-9_]+)_DB_SERVER$/', $k, $m)) {
                $name = $m[1];
                // Skip obvious primary aliases
                if (!in_array($name, ['DB','DEV_DB','NGNRANKINGS','SMRRANKINGS','NGNSPINS','NGNNOTES'], true)) { /* include all, we will normalize later */ }
                $candidates[$name] = true;
            }
        }
        foreach (getenv() ?: [] as $k => $v) {
            $ku = strtoupper($k);
            if (preg_match('/^DB_([A-Z0-9_]+)_HOST$/', $ku, $m)) {
                $name = $m[1];
                if (!in_array($name, ['HOST','READ','PORT','NAME','USER','PASS'], true)) { $candidates[$name] = true; }
            }
            if (preg_match('/^([A-Z0-9_]+)_DB_SERVER$/', $ku, $m)) {
                $name = $m[1];
                $candidates[$name] = true;
            }
        }
        // 2) Parse .env files on disk to catch values not injected into process env
        $root = dirname(__DIR__, 2);
        $paths = [ $root.'/.env', $root.'/lib/definitions/.env' ];
        foreach ($paths as $p) {
            if (!is_file($p)) continue;
            $lines = @file($p, FILE_IGNORE_NEW_LINES);
            if ($lines === false) continue;
            foreach ($lines as $line) {
                $t = trim($line);
                if ($t === '' || $t[0] === '#') continue;
                if (stripos($t, 'export ') === 0) { $t = trim(substr($t, 7)); }
                $parts = explode('=', $t, 2);
                if (count($parts) !== 2) continue;
                $key = strtoupper(trim($parts[0]));
                if (preg_match('/^DB_([A-Z0-9_]+)_HOST$/', $key, $m)) {
                    $name = $m[1];
                    if (!in_array($name, ['HOST','READ','PORT','NAME','USER','PASS'], true)) { $candidates[$name] = true; }
                }
                if (preg_match('/^([A-Z0-9_]+)_DB_SERVER$/', $key, $m)) {
                    $name = $m[1];
                    $candidates[$name] = true;
                }
            }
        }
        // Normalize: map legacy NGNRANKINGS -> DB_NGNRANKINGS style names by just lowercasing
        $names = array_values(array_keys($candidates));
        $names = array_map('strtolower', $names);
        // Filter out primary/read aliases if they slipped in
        $names = array_values(array_filter($names, function($n){ return !in_array($n, ['host','read','port','name','user','pass','dev_db','db'], true); }));
        $names = array_values(array_unique($names));
        return $names;
    }

    private static function connect(string $host, int $port, string $name, string $user, string $pass): PDO
    {
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        // Ensure MySQL uses buffered queries so we can safely run multiple statements sequentially
        // PHP 8.5+ uses Pdo\Mysql::ATTR_USE_BUFFERED_QUERY
        if (class_exists('Pdo\Mysql') && defined('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY')) {
            $options[\Pdo\Mysql::ATTR_USE_BUFFERED_QUERY] = true;
        } elseif (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
        }
        try {
            return new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            throw $e; // Let upper layer convert to JSON error; no echoing secrets
        }
    }

    /**
     * Parse .env files to lookup a named DB connection values.
     * Returns array [$host, $port, $name, $user, $pass].
     */
    private static function lookupNamedFromFiles(string $upperName): array
    {
        $host = '';
        $port = (int)(Env::get('DB_PORT', '3306') ?? '3306');
        $dbn = '';
        $user = '';
        $pass = '';
        $root = dirname(__DIR__, 2);
        $paths = [ $root.'/.env', $root.'/lib/definitions/.env' ];
        foreach ($paths as $p) {
            if (!is_file($p)) continue;
            $lines = @file($p, FILE_IGNORE_NEW_LINES);
            if ($lines === false) continue;
            foreach ($lines as $line) {
                $t = trim($line);
                if ($t === '' || $t[0] === '#') continue;
                if (stripos($t, 'export ') === 0) { $t = trim(substr($t, 7)); }
                $parts = explode('=', $t, 2);
                if (count($parts) !== 2) continue;
                $key = strtoupper(trim($parts[0]));
                $val = trim((string)$parts[1], " \t\n\r\0\x0B'\"");
                if ($key === 'DB_'.$upperName.'_HOST') $host = $host ?: $val;
                if ($key === 'DB_'.$upperName.'_PORT') $port = (int)$val;
                if ($key === 'DB_'.$upperName.'_NAME') $dbn = $dbn ?: $val;
                if ($key === 'DB_'.$upperName.'_USER') $user = $user ?: $val;
                if ($key === 'DB_'.$upperName.'_PASS' || $key === 'DB_'.$upperName.'_PASSWORD') $pass = $pass ?: $val;
            }
        }
        return [$host, $port, $dbn, $user, $pass];
    }


}
