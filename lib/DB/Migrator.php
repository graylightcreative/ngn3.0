<?php
namespace NGN\Lib\DB;

use NGN\Lib\Config;
use PDO;

class Migrator
{
    private Config $config;
    // Primary connection for default DB DDL (tables without explicit DB prefix)
    protected PDO $db;
    // Separate connection dedicated to ngn_migrations ledger to avoid cursor conflicts
    protected PDO $meta;
    private string $dir;
    /** @var array<string, PDO> */
    private array $named = [];

    public function __construct(Config $config, ?string $dir = null)
    {
        $this->config = $config;
        // Use two independent connections to the primary DB so that
        // reading from the ngn_migrations ledger never leaves an
        // unbuffered cursor on the same connection used for DDL.
        $this->db = ConnectionFactory::write($config);   // default DDL / fallback
        $this->meta = ConnectionFactory::write($config); // ngn_migrations ledger
        $root = dirname(__DIR__, 2);
        $this->dir = $dir ?? ($root . '/migrations');
        $this->ensureTable();
    }

    public function ensureTable(): void
    {
        $this->meta->exec("CREATE TABLE IF NOT EXISTS ngn_migrations (
            Id INT AUTO_INCREMENT PRIMARY KEY,
            Filename VARCHAR(255) NOT NULL UNIQUE,
            AppliedAt DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function applied(): array
    {
        $stmt = $this->meta->query('SELECT Filename FROM ngn_migrations ORDER BY Id ASC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (method_exists($stmt, 'closeCursor')) {
            $stmt->closeCursor();
        }
        return array_map(fn($r) => $r['Filename'], $rows);
    }

    public function available(): array
    {
        if (!is_dir($this->dir)) return [];
        // Recursively collect all .sql and .php files under migrations/active/ and migrations/sql/
        // Exclude migrations/future/ and migrations/legacy_inactive/
        $base = rtrim($this->dir, '/');
        $list = [];
        $stack = [$base];
        while (!empty($stack)) {
            $d = array_pop($stack);
            $entries = @scandir($d) ?: [];
            foreach ($entries as $e) {
                if ($e === '.' || $e === '..') continue;
                $full = $d . '/' . $e;

                // Skip future/ and legacy_inactive/ directories (not for beta)
                if (is_dir($full)) {
                    if (basename($full) === 'future' || basename($full) === 'legacy_inactive') {
                        continue;
                    }
                    $stack[] = $full;
                    continue;
                }

                // Collect .sql and .php files
                if (is_file($full) && preg_match('/\.(sql|php)$/i', $e)) {
                    // store relative path from $base so apply() can join with dir
                    $rel = ltrim(substr($full, strlen($base)), '/');
                    $list[] = $rel;
                }
            }
        }
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);
        return $list;
    }

    public function getAvailableCategorized(): array
    {
        $all = $this->available();
        $categorized = ['active' => [], 'etl' => [], 'checks' => []];
        foreach ($all as $file) {
            // New structure: migrations/active/*
            if (str_starts_with($file, 'active/')) {
                $categorized['active'][] = $file;
            }
            // Legacy structure paths (for backward compatibility)
            elseif (str_starts_with($file, 'sql/schema/')) {
                $categorized['active'][] = $file;
            } elseif (str_starts_with($file, 'sql/etl/')) {
                $categorized['etl'][] = $file;
            } elseif (str_starts_with($file, 'sql/checks/')) {
                $categorized['checks'][] = $file;
            }
        }
        return $categorized;
    }

    public function status(): array
    {
        $applied = $this->applied();
        $available = $this->available();
        $pending = array_values(array_diff($available, $applied));
        return [
            'available' => $available,
            'applied' => $applied,
            'pending' => $pending,
            'dir' => $this->dir,
        ];
    }

    public function applyAll(): array
    {
        $status = $this->status();
        $applied = [];
        foreach ($status['pending'] as $file) {
            $this->apply($file);
            $applied[] = $file;
        }
        return $applied;
    }

    /**
     * Backward-compat alias for older callers (e.g., Upgrade Wizard) that expect applyPending().
     * Applies all pending migrations and returns the list of filenames applied.
     */
    public function applyPending(): array
    {
        return $this->applyAll();
    }

    public function apply(string $filename): void
    {
        // Special-case ETL migrations that need to read from the legacy DB and write
        // into ngn_2025, which cannot be expressed as a single cross-database SQL
        // statement on this hosting environment.
        if ($filename === 'sql/etl/10_seed_artists.sql') {
            $this->runEtlSeedArtists();
            $this->markApplied($filename);
            return;
        }
        if ($filename === 'sql/etl/11_seed_labels.sql') {
            $this->runEtlSeedLabels();
            $this->markApplied($filename);
            return;
        }
        if ($filename === 'sql/etl/12_seed_stations.sql') {
            $this->runEtlSeedStations();
            $this->markApplied($filename);
            return;
        }
        if ($filename === 'sql/etl/13_seed_venues.sql') {
            $this->runEtlSeedVenues();
            $this->markApplied($filename);
            return;
        }
        if ($filename === 'sql/etl/20_seed_releases_tracks.sql') {
            $this->runEtlSeedReleasesAndTracks();
            $this->markApplied($filename);
            return;
        }
        if ($filename === 'sql/etl/30_seed_posts.sql') {
            $this->runEtlSeedPosts();
            $this->markApplied($filename);
            return;
        }
        if ($filename === 'sql/etl/40_seed_station_spins.sql') {
            $this->runEtlSeedStationSpins();
            $this->markApplied($filename);
            return;
        }
        if ($filename === 'sql/etl/41_refresh_station_spins.sql') {
            $this->runEtlSeedStationSpins();
            $this->markApplied($filename);
            return;
        }
        if ($filename === 'sql/etl/50_seed_smr_chart.sql') {
            $this->runEtlSeedSmrChart();
            $this->markApplied($filename);
            return;
        }

        // Link entities to legacy Users and copy profile data
        if ($filename === 'sql/etl/14_link_entities_to_users.sql') {
            $this->runEtlLinkEntitiesToUsers();
            $this->markApplied($filename);
            return;
        }

        // Schema migration that adds columns - ignore duplicate column errors
        if ($filename === 'sql/schema/01_add_user_id_to_entities.sql') {
            $this->runSchemaAddUserIdToEntities();
            $this->markApplied($filename);
            return;
        }

        $path = $this->dir . '/' . ltrim($filename, '/');
        if (!is_file($path)) throw new \RuntimeException('Migration not found: ' . $filename);
        $sql = file_get_contents($path);
        if ($sql === false) throw new \RuntimeException('Unable to read migration: ' . $filename);
        // Split by ; while ignoring empty statements
        $stmts = array_values(array_filter(array_map('trim', preg_split('/;\s*\n/', $sql))));

        // Helper to route a statement to the correct connection based on DB prefix.
        $exec = function(string $s) use ($filename): void {
            if ($s === '') { return; }
            // Detect explicit database prefix (e.g., `ngn_rankings_2025`.`some_table`)
            $dbName = null;
            if (preg_match('/`([^`]+)`\.`[^`]+`/', $s, $m)) {
                $dbName = $m[1];
            }
            try {
                // SELECT-style statements (even if preceded by comment lines)
                if (preg_match('/^(?:\s*--.*\R)*\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $s)) {
                    // For "checks" migration files, skip read-only diagnostics during automated apply.
                    if (strpos($filename, 'sql/checks/') === 0) {
                        return;
                    }
                    $pdo = $this->selectConnectionForDatabase($dbName);
                    $stmt = $pdo->query($s);
                    if ($stmt !== false) {
                        $stmt->fetchAll(PDO::FETCH_ASSOC);
                        if (method_exists($stmt, 'closeCursor')) {
                            $stmt->closeCursor();
                        }
                    }
                    return;
                }
                // Everything else (DDL/DML) via exec on the appropriate connection.
                // ETL files that reach here should still run on the primary legacy DB
                // so unqualified tables like `Users` resolve correctly.
                if (strpos($filename, 'sql/etl/') === 0) {
                    $pdo = $this->db;
                } else {
                    $pdo = $dbName !== null ? $this->connectionForDatabase($dbName) : $this->db;
                }
                $pdo->exec($s);
            } catch (\PDOException $e) {
                $snippet = preg_replace('/\s+/', ' ', substr($s, 0, 200));
                throw new \RuntimeException($e->getMessage() . ' while executing: ' . $snippet, (int)$e->getCode(), $e);
            }
        };

        // Transactions across multiple databases are not supported; execute statements one-by-one.
        foreach ($stmts as $s) {
            if ($s === '') continue;
            $exec($s);
        }

        $this->markApplied($filename);
    }


    /**
     * Mark a migration file as applied in the ngn_migrations ledger.
     */
    private function markApplied(string $filename): void
    {
        $ins = $this->meta->prepare('INSERT INTO ngn_migrations (Filename, AppliedAt) VALUES (:f, :t)');
        $ins->execute([':f' => $filename, ':t' => date('Y-m-d H:i:s')]);
        if (method_exists($ins, 'closeCursor')) {
            $ins->closeCursor();
        }
    }

    /**
     * ETL: seed ngn_2025.artists and identity map from legacy Users.
     *
     * Implemented entirely at the application layer to avoid cross-database
     * INSERT..SELECT statements, given the isolated DB users on this host.
     */
    private function runEtlSeedArtists(): void
    {
        // 1) Read artists from legacy Users on the primary connection.
        $select = 'SELECT u.Id AS legacy_id, u.Slug AS slug, u.Title AS name, u.Body AS bio, ' .
                  "CASE WHEN u.Image IS NOT NULL AND u.Image<>'' THEN CONCAT('/uploads/users/', u.Slug, '/', u.Image) ELSE NULL END AS image_url, " .
                  'u.WebsiteUrl AS website_url, u.FacebookUrl AS facebook_url, u.InstagramUrl AS instagram_url, ' .
                  'u.YoutubeUrl AS youtube_url, u.TiktokUrl AS tiktok_url, ' .
                  'u.Claimed AS claimed, u.VerifiedEmail AS verified ' .
                  'FROM users u WHERE u.RoleId = 3';
        $stmt = $this->db->query($select);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if ($stmt && method_exists($stmt, 'closeCursor')) {
            $stmt->closeCursor();
        }
        if (!$rows) {
            return; // nothing to seed
        }

        // 2) First, ensure users exist in the central ngn_2025.users table
        $target = $this->connectionForDatabase('ngn_2025');
        $insUser = $target->prepare('INSERT IGNORE INTO `ngn_2025`.`users` (`id`, `display_name`, `username`, `status`, `claimed`, `verified`) VALUES (:id, :display_name, :username, :status, :claimed, :verified)');
        foreach ($rows as $row) {
            $insUser->execute([
                ':id' => $row['legacy_id'],
                ':display_name' => $row['name'],
                ':username' => $row['slug'],
                ':status' => 'active', // Default status for migrated users
                ':claimed' => $row['claimed'],
                ':verified' => $row['verified'],
            ]);
        }

        // 3) Insert into ngn_2025.artists on the dev connection.
        $insArtist = $target->prepare('INSERT IGNORE INTO `ngn_2025`.`artists` (`id`, `user_id`, `slug`, `name`, `bio`, `image_url`) VALUES (:id, :user_id, :slug, :name, :bio, :image_url)');
        foreach ($rows as $row) {
            $insArtist->execute([
                ':id' => $row['legacy_id'],
                ':user_id' => $row['legacy_id'], // Map legacy user ID to new user ID
                ':slug' => $row['slug'],
                ':name' => $row['name'],
                ':bio' => $row['bio'],
                ':image_url' => $row['image_url'],
            ]);
        }

        // 4) Populate identity map for artists (legacy→cdm) where missing.
        $selArtist = $target->prepare('SELECT id, slug FROM `ngn_2025`.`artists` WHERE slug = :slug LIMIT 1');
        $insMap = $target->prepare('INSERT IGNORE INTO `ngn_2025`.`cdm_identity_map` (`entity`,`legacy_id`,`legacy_slug`,`cdm_id`,`cdm_slug`,`source`) VALUES (:entity,:legacy_id,:legacy_slug,:cdm_id,:cdm_slug,:source)');
        foreach ($rows as $row) {
            $selArtist->execute([':slug' => $row['slug']]);
            $artist = $selArtist->fetch(PDO::FETCH_ASSOC) ?: null;
            if (method_exists($selArtist, 'closeCursor')) {
                $selArtist->closeCursor();
            }
            if (!$artist) {
                continue;
            }
            $insMap->execute([
                ':entity' => 'artist',
                ':legacy_id' => $row['legacy_id'],
                ':legacy_slug' => $row['slug'],
                ':cdm_id' => $artist['id'],
                ':cdm_slug' => $artist['slug'],
                ':source' => 'seed_2025',
            ]);
        }
        if (method_exists($insUser, 'closeCursor')) {
            $insUser->closeCursor();
        }
        if (method_exists($insArtist, 'closeCursor')) {
            $insArtist->closeCursor();
        }
        if (method_exists($insMap, 'closeCursor')) {
            $insMap->closeCursor();
        }
    }

    /**
     * ETL: seed ngn_2025.labels and identity map from legacy Users.
     */
    private function runEtlSeedLabels(): void
    {
        // 1) Read labels from legacy Users on the primary connection.
        $select = 'SELECT u.Id AS legacy_id, u.Slug AS slug, u.Title AS name, u.Body AS bio, ' .
                  "CASE WHEN u.Image IS NOT NULL AND u.Image<>'' THEN CONCAT('/uploads/users/', u.Slug, '/', u.Image) ELSE NULL END AS image_url, " .
                  'u.Claimed AS claimed, u.VerifiedEmail AS verified ' .
                  'FROM users u WHERE u.RoleId = 7';
        $stmt = $this->db->query($select);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if ($stmt && method_exists($stmt, 'closeCursor')) {
            $stmt->closeCursor();
        }
        if (!$rows) {
            return;
        }

        // 2) First, ensure users exist in the central ngn_2025.users table
        $target = $this->connectionForDatabase('ngn_2025');
        $insUser = $target->prepare('INSERT IGNORE INTO `ngn_2025`.`users` (`id`, `display_name`, `username`, `status`, `claimed`, `verified`) VALUES (:id, :display_name, :username, :status, :claimed, :verified)');
        foreach ($rows as $row) {
            $insUser->execute([
                ':id' => $row['legacy_id'],
                ':display_name' => $row['name'],
                ':username' => $row['slug'],
                ':status' => 'active', // Default status for migrated users
                ':claimed' => $row['claimed'],
                ':verified' => $row['verified'],
            ]);
        }

        // 3) Insert into ngn_2025.labels on the dev connection.
        $insLabel = $target->prepare('INSERT IGNORE INTO `ngn_2025`.`labels` (`id`, `user_id`, `slug`, `name`, `bio`, `image_url`) VALUES (:id, :user_id, :slug, :name, :bio, :image_url)');
        foreach ($rows as $row) {
            $insLabel->execute([
                ':id' => $row['legacy_id'],
                ':user_id' => $row['legacy_id'], // Map legacy user ID to new user ID
                ':slug' => $row['slug'],
                ':name' => $row['name'],
                ':bio' => $row['bio'],
                ':image_url' => $row['image_url'],
            ]);
        }

        // 4) Populate identity map for labels.
        $selLabel = $target->prepare('SELECT id, slug FROM `ngn_2025`.`labels` WHERE slug = :slug LIMIT 1');
        $insMap = $target->prepare('INSERT IGNORE INTO `ngn_2025`.`cdm_identity_map` (`entity`,`legacy_id`,`legacy_slug`,`cdm_id`,`cdm_slug`,`source`) VALUES (:entity,:legacy_id,:legacy_slug,:cdm_id,:cdm_slug,:source)');
        foreach ($rows as $row) {
            $selLabel->execute([':slug' => $row['slug']]);
            $label = $selLabel->fetch(PDO::FETCH_ASSOC) ?: null;
            if (method_exists($selLabel, 'closeCursor')) {
                $selLabel->closeCursor();
            }
            if (!$label) {
                continue;
            }
            $insMap->execute([
                ':entity' => 'label',
                ':legacy_id' => $row['legacy_id'],
                ':legacy_slug' => $row['slug'],
                ':cdm_id' => $label['id'],
                ':cdm_slug' => $label['slug'],
                ':source' => 'seed_2025',
            ]);
        }
        if (method_exists($insUser, 'closeCursor')) {
            $insUser->closeCursor();
        }
        if (method_exists($insLabel, 'closeCursor')) {
            $insLabel->closeCursor();
        }
        if (method_exists($insMap, 'closeCursor')) {
            $insMap->closeCursor();
        }
    }

    /**
     * ETL: seed ngn_2025.stations and identity map from legacy Users.
     */
    private function runEtlSeedStations(): void
    {
        // 1) Read stations from legacy Users on the primary connection.
        $select = 'SELECT u.Id AS legacy_id, u.Slug AS slug, u.Title AS name, u.Body AS bio, ' .
                  "CASE WHEN u.Image IS NOT NULL AND u.Image<>'' THEN CONCAT('/uploads/users/', u.Slug, '/', u.Image) ELSE NULL END AS image_url, " .
                  'u.CallSign AS call_sign, u.Region AS region, u.Format AS format, ' .
                  'u.Claimed AS claimed, u.VerifiedEmail AS verified ' .
                  'FROM users u WHERE u.RoleId = 9';
        $stmt = $this->db->query($select);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if ($stmt && method_exists($stmt, 'closeCursor')) {
            $stmt->closeCursor();
        }
        if (!$rows) {
            return;
        }

        // 2) First, ensure users exist in the central ngn_2025.users table
        $target = $this->connectionForDatabase('ngn_2025');
        $insUser = $target->prepare('INSERT IGNORE INTO `ngn_2025`.`users` (`id`, `display_name`, `username`, `status`, `claimed`, `verified`) VALUES (:id, :display_name, :username, :status, :claimed, :verified)');
        foreach ($rows as $row) {
            $insUser->execute([
                ':id' => $row['legacy_id'],
                ':display_name' => $row['name'],
                ':username' => $row['slug'],
                ':status' => 'active', // Default status for migrated users
                ':claimed' => $row['claimed'],
                ':verified' => $row['verified'],
            ]);
        }

        // 3) Insert into ngn_2025.stations on the dev connection.
        $insStation = $target->prepare('INSERT IGNORE INTO `ngn_2025`.`stations` (`id`, `user_id`, `slug`, `name`, `bio`, `call_sign`, `region`, `format`, `image_url`) VALUES (:id, :user_id, :slug, :name, :bio, :call_sign, :region, :format, :image_url)');
        foreach ($rows as $row) {
            $insStation->execute([
                ':id' => $row['legacy_id'],
                ':user_id' => $row['legacy_id'], // Map legacy user ID to new user ID
                ':slug' => $row['slug'],
                ':name' => $row['name'],
                ':bio' => $row['bio'],
                ':call_sign' => $row['call_sign'] ?? null,
                ':region' => $row['region'] ?? null,
                ':format' => $row['format'] ?? null,
                ':image_url' => $row['image_url'],
            ]);
        }

        // 4) Populate identity map for stations.
        $selStation = $target->prepare('SELECT id, slug FROM `ngn_2025`.`stations` WHERE slug = :slug LIMIT 1');
        $insMap = $target->prepare('INSERT IGNORE INTO `ngn_2025`.`cdm_identity_map` (`entity`,`legacy_id`,`legacy_slug`,`cdm_id`,`cdm_slug`,`source`) VALUES (:entity,:legacy_id,:legacy_slug,:cdm_id,:cdm_slug,:source)');
        foreach ($rows as $row) {
            $selStation->execute([':slug' => $row['slug']]);
            $station = $selStation->fetch(PDO::FETCH_ASSOC) ?: null;
            if (method_exists($selStation, 'closeCursor')) {
                $selStation->closeCursor();
            }
            if (!$station) {
                continue;
            }
            $insMap->execute([
                ':entity' => 'station',
                ':legacy_id' => $row['legacy_id'],
                ':legacy_slug' => $row['slug'],
                ':cdm_id' => $station['id'],
                ':cdm_slug' => $station['slug'],
                ':source' => 'seed_2025',
            ]);
        }
        if (method_exists($insUser, 'closeCursor')) {
            $insUser->closeCursor();
        }
        if (method_exists($insStation, 'closeCursor')) {
            $insStation->closeCursor();
        }
        if (method_exists($insMap, 'closeCursor')) {
            $insMap->closeCursor();
        }
    }

    /**
     * ETL: seed ngn_2025.venues and identity map from legacy Users (RoleId=11).
     */
    private function runEtlSeedVenues(): void
    {
        // 1) Read venues from legacy Users on the primary connection.
        $select = 'SELECT u.Id AS legacy_id, u.Slug AS slug, u.Title AS name, u.Body AS bio, ' .
                  'u.Address AS address, u.City AS city, u.State AS region, u.Country AS country, ' .
                  "CASE WHEN u.Image IS NOT NULL AND u.Image<>'' THEN CONCAT('/uploads/users/', u.Slug, '/', u.Image) ELSE NULL END AS image_url, " .
                  'u.Claimed AS claimed, u.VerifiedEmail AS verified ' .
                  'FROM users u WHERE u.RoleId = 11';
        $stmt = $this->db->query($select);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if ($stmt && method_exists($stmt, 'closeCursor')) {
            $stmt->closeCursor();
        }
        if (!$rows) {
            return;
        }

        // 2) First, ensure users exist in the central ngn_2025.users table
        $target = $this->connectionForDatabase('ngn_2025');
        $insUser = $target->prepare('INSERT IGNORE INTO `ngn_2025`.`users` (`id`, `display_name`, `username`, `status`, `claimed`, `verified`) VALUES (:id, :display_name, :username, :status, :claimed, :verified)');
        foreach ($rows as $row) {
            $insUser->execute([
                ':id' => $row['legacy_id'],
                ':display_name' => $row['name'],
                ':username' => $row['slug'],
                ':status' => 'active', // Default status for migrated users
                ':claimed' => $row['claimed'],
                ':verified' => $row['verified'],
            ]);
        }

        // 3) Insert into ngn_2025.venues on the dev connection.
        $insVenue = $target->prepare('INSERT IGNORE INTO `ngn_2025`.`venues` (`id`, `user_id`, `slug`, `name`, `bio`, `city`, `region`, `country`, `image_url`) VALUES (:id, :user_id, :slug, :name, :bio, :city, :region, :country, :image_url)');
        foreach ($rows as $row) {
            $insVenue->execute([
                ':id' => $row['legacy_id'],
                ':user_id' => $row['legacy_id'], // Map legacy user ID to new user ID
                ':slug' => $row['slug'],
                ':name' => $row['name'],
                ':bio' => $row['bio'],
                ':city' => $row['city'] ?? null,
                ':region' => $row['region'] ?? null,
                ':country' => $row['country'] ?? null,
                ':image_url' => $row['image_url'],
            ]);
        }

        // 4) Populate identity map for venues.
        $selVenue = $target->prepare('SELECT id, slug FROM `ngn_2025`.`venues` WHERE slug = :slug LIMIT 1');
        $insMap = $target->prepare('INSERT IGNORE INTO `ngn_2025`.`cdm_identity_map` (`entity`,`legacy_id`,`legacy_slug`,`cdm_id`,`cdm_slug`,`source`) VALUES (:entity,:legacy_id,:legacy_slug,:cdm_id,:cdm_slug,:source)');
        foreach ($rows as $row) {
            $selVenue->execute([':slug' => $row['slug']]);
            $venue = $selVenue->fetch(PDO::FETCH_ASSOC) ?: null;
            if (method_exists($selVenue, 'closeCursor')) {
                $selVenue->closeCursor();
            }
            if (!$venue) {
                continue;
            }
            $insMap->execute([
                ':entity' => 'venue',
                ':legacy_id' => $row['legacy_id'],
                ':legacy_slug' => $row['slug'],
                ':cdm_id' => $venue['id'],
                ':cdm_slug' => $venue['slug'],
                ':source' => 'seed_2025',
            ]);
        }
        if (method_exists($insUser, 'closeCursor')) {
            $insUser->closeCursor();
        }
        if (method_exists($insVenue, 'closeCursor')) {
            $insVenue->closeCursor();
        }
        if (method_exists($insMap, 'closeCursor')) {
            $insMap->closeCursor();
        }
    }

    /**
     * ETL: Link ngn_2025 entities to legacy Users and copy profile data.
     * Uses cdm_identity_map to find legacy_id, then updates entity with user data.
     */
    private function runEtlLinkEntitiesToUsers(): void
    {
        $primary = $this->db;
        $target = $this->connectionForDatabase('ngn_2025');

        // Get identity map entries for each entity type
        $entities = ['artist' => 'artists', 'label' => 'labels', 'venue' => 'venues', 'station' => 'stations'];

        foreach ($entities as $entityType => $tableName) {
            // Get all identity mappings for this entity type
            $mapSql = "SELECT cdm_id, legacy_id FROM `ngn_2025`.`cdm_identity_map` WHERE entity = :entity";
            $mapStmt = $target->prepare($mapSql);
            $mapStmt->execute([':entity' => $entityType]);
            $mappings = $mapStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (method_exists($mapStmt, 'closeCursor')) { $mapStmt->closeCursor(); }

            if (!$mappings) { continue; }

            // Prepare update statement based on entity type
            if ($entityType === 'artist') {
                $updateSql = "UPDATE `ngn_2025`.`artists` SET
                    user_id = :user_id, bio = :bio, email = :email, phone = :phone,
                    website = :website, facebook_url = :facebook_url, instagram_url = :instagram_url,
                    tiktok_url = :tiktok_url, youtube_url = :youtube_url, spotify_url = :spotify_url,
                    claimed = :claimed, verified = :verified
                    WHERE id = :cdm_id AND user_id IS NULL";
            } elseif ($entityType === 'label') {
                $updateSql = "UPDATE `ngn_2025`.`labels` SET
                    user_id = :user_id, bio = :bio, email = :email, phone = :phone,
                    website = :website, facebook_url = :facebook_url, instagram_url = :instagram_url,
                    tiktok_url = :tiktok_url, youtube_url = :youtube_url,
                    claimed = :claimed, verified = :verified
                    WHERE id = :cdm_id AND user_id IS NULL";
            } elseif ($entityType === 'venue') {
                $updateSql = "UPDATE `ngn_2025`.`venues` SET
                    user_id = :user_id, bio = :bio, email = :email, phone = :phone,
                    address = :address, website = :website, facebook_url = :facebook_url,
                    instagram_url = :instagram_url, tiktok_url = :tiktok_url,
                    claimed = :claimed, verified = :verified
                    WHERE id = :cdm_id AND user_id IS NULL";
            } else { // station
                $updateSql = "UPDATE `ngn_2025`.`stations` SET
                    user_id = :user_id, bio = :bio, email = :email, phone = :phone,
                    website = :website, facebook_url = :facebook_url, instagram_url = :instagram_url,
                    tiktok_url = :tiktok_url,
                    claimed = :claimed, verified = :verified
                    WHERE id = :cdm_id AND user_id IS NULL";
            }
            $updateStmt = $target->prepare($updateSql);

            foreach ($mappings as $map) {
                $legacyId = (int)$map['legacy_id'];
                $cdmId = (int)$map['cdm_id'];

                // Fetch user data from legacy Users table
                $userSql = "SELECT Id, Body, Email, Phone, Address, WebsiteUrl, FacebookUrl,
                            InstagramUrl, TiktokUrl, YoutubeUrl, SpotifyId, Claimed, VerifiedEmail
                            FROM users WHERE Id = :id LIMIT 1";
                $userStmt = $primary->prepare($userSql);
                $userStmt->execute([':id' => $legacyId]);
                $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                if (method_exists($userStmt, 'closeCursor')) { $userStmt->closeCursor(); }

                if (!$user) { continue; }

                $params = [
                    ':cdm_id' => $cdmId,
                    ':user_id' => $legacyId,
                    ':bio' => $user['Body'] ?: null,
                    ':email' => $user['Email'] ?: null,
                    ':phone' => $user['Phone'] ?: null,
                    ':website' => $user['WebsiteUrl'] ?: null,
                    ':facebook_url' => $user['FacebookUrl'] ?: null,
                    ':instagram_url' => $user['InstagramUrl'] ?: null,
                    ':tiktok_url' => $user['TiktokUrl'] ?: null,
                    ':claimed' => ($user['Claimed'] == 1) ? 1 : 0,
                    ':verified' => ($user['VerifiedEmail'] == 1) ? 1 : 0,
                ];

                if ($entityType === 'artist') {
                    $params[':youtube_url'] = $user['YoutubeUrl'] ?: null;
                    $spotifyId = $user['SpotifyId'] ?: null;
                    $params[':spotify_url'] = $spotifyId ? "https://open.spotify.com/artist/{$spotifyId}" : null;
                } elseif ($entityType === 'label') {
                    $params[':youtube_url'] = $user['YoutubeUrl'] ?: null;
                } elseif ($entityType === 'venue') {
                    $params[':address'] = $user['Address'] ?: null;
                }

                try {
                    $updateStmt->execute($params);
                } catch (\PDOException $e) {
                    // Ignore errors for individual updates
                }
            }
            if (method_exists($updateStmt, 'closeCursor')) { $updateStmt->closeCursor(); }
        }
    }

    /**
     * ETL: seed ngn_2025.releases + ngn_2025.tracks and identity map
     * from legacy releases/songs tables.
     */
    private function runEtlSeedReleasesAndTracks(): void
    {
        $primary = $this->db;
        $target = $this->connectionForDatabase('ngn_2025');

        // 1) Releases
        $relSql = 'SELECT r.Id AS legacy_id, r.Slug AS slug, r.Title AS title, r.ArtistId AS legacy_artist_id, ' .
                  'r.LabelId AS legacy_label_id, r.Description AS description, ' .
                  'DATE(r.ReleaseDate) AS released_at, r.ListeningURL AS listening_url, r.WatchURL AS watch_url, ' .
                  "CASE WHEN r.Image IS NOT NULL AND r.Image<>'' THEN CONCAT('/uploads/releases/', r.Slug, '/', r.Image) ELSE NULL END AS cover_url " .
                  'FROM releases r';
        $stmt = $primary->query($relSql);
        $releases = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if ($stmt && method_exists($stmt, 'closeCursor')) { $stmt->closeCursor(); }

        if ($releases) {
            $insRel = $target->prepare('INSERT IGNORE INTO `ngn_2025`.`releases` (`id`,`slug`,`artist_id`,`label_id`,`title`,`description`,`released_at`,`cover_url`,`listening_url`,`watch_url`) VALUES (:id,:slug,:artist_id,:label_id,:title,:description,:released_at,:cover_url,:listening_url,:watch_url)');
            $selArtistId = $target->prepare('SELECT `cdm_id` FROM `ngn_2025`.`cdm_identity_map` WHERE `entity`="artist" AND `legacy_id`=:legacy_id LIMIT 1');
            $selLabelId = $target->prepare('SELECT `cdm_id` FROM `ngn_2025`.`cdm_identity_map` WHERE `entity`="label" AND `legacy_id`=:legacy_id LIMIT 1');
            $selRelease = $target->prepare('SELECT `id`,`slug` FROM `ngn_2025`.`releases` WHERE `slug`=:slug LIMIT 1');
            $insMap = $target->prepare('INSERT IGNORE INTO `ngn_2025`.`cdm_identity_map` (`entity`,`legacy_id`,`legacy_slug`,`cdm_id`,`cdm_slug`,`source`) VALUES (:entity,:legacy_id,:legacy_slug,:cdm_id,:cdm_slug,:source)');

            foreach ($releases as $row) {
                $legacyArtistId = (int)($row['legacy_artist_id'] ?? 0);
                $legacyLabelId = (int)($row['legacy_label_id'] ?? 0);
                if ($legacyArtistId <= 0) { continue; }

                $artistRow = null;
                if ($legacyArtistId > 0) {
                    $selArtistId->execute([':legacy_id' => $legacyArtistId]);
                    $artistRow = $selArtistId->fetch(PDO::FETCH_ASSOC) ?: null;
                    if (method_exists($selArtistId, 'closeCursor')) { $selArtistId->closeCursor(); }
                }
                if (!$artistRow || empty($artistRow['cdm_id'])) { continue; }

                $labelRow = null;
                if ($legacyLabelId > 0) {
                    $selLabelId->execute([':legacy_id' => $legacyLabelId]);
                    $labelRow = $selLabelId->fetch(PDO::FETCH_ASSOC) ?: null;
                    if (method_exists($selLabelId, 'closeCursor')) { $selLabelId->closeCursor(); }
                }

                $insRel->execute([
                    ':id' => $row['legacy_id'],
                    ':slug' => $row['slug'],
                    ':artist_id' => (int)$artistRow['cdm_id'],
                    ':label_id' => (int)($labelRow['cdm_id'] ?? null),
                    ':title' => $row['title'],
                    ':description' => $row['description'] ?? null,
                    ':released_at' => $row['released_at'] ?: null,
                    ':cover_url' => $row['cover_url'] ?? null,
                    ':listening_url' => $row['listening_url'] ?? null,
                    ':watch_url' => $row['watch_url'] ?? null,
                ]);

                $selRelease->execute([':slug' => $row['slug']]);
                $rel = $selRelease->fetch(PDO::FETCH_ASSOC) ?: null;
                if (method_exists($selRelease, 'closeCursor')) { $selRelease->closeCursor(); }
                if ($rel) {
                    $insMap->execute([
                        ':entity' => 'release',
                        ':legacy_id' => $row['legacy_id'],
                        ':legacy_slug' => $row['slug'],
                        ':cdm_id' => $rel['id'],
                        ':cdm_slug' => $rel['slug'],
                        ':source' => 'seed_2025',
                    ]);
                }
            }
            if (method_exists($insRel, 'closeCursor')) { $insRel->closeCursor(); }
            if (method_exists($insMap, 'closeCursor')) { $insMap->closeCursor(); }
        }

        // 2) Tracks from legacy songs
        $songSql = 'SELECT s.id AS legacy_id, s.Slug AS slug, s.Title AS title, s.ArtistId AS legacy_artist_id, ' .
                   's.ReleaseId AS legacy_release_id, s.DurationMs AS duration_ms, s.ISRC AS isrc, ' .
                   's.Description AS description ' . // Assuming legacy Songs has Description
                   'FROM songs s';
        $stmt2 = $primary->query($songSql);
        $songs = $stmt2 ? $stmt2->fetchAll(PDO::FETCH_ASSOC) : [];
        if ($stmt2 && method_exists($stmt2, 'closeCursor')) { $stmt2->closeCursor(); }

        if ($songs) {
            $insTrack = $target->prepare('INSERT IGNORE INTO `ngn_2025`.`tracks` (`id`,`slug`,`release_id`,`artist_id`,`title`,`description`,`duration_ms`,`isrc`) VALUES (:id,:slug,:release_id,:artist_id,:title,:description,:duration_ms,:isrc)');
            $selArtistId2 = $target->prepare('SELECT `cdm_id` FROM `ngn_2025`.`cdm_identity_map` WHERE `entity`="artist" AND `legacy_id`=:legacy_id LIMIT 1');
            $selReleaseId = $target->prepare('SELECT `cdm_id` FROM `ngn_2025`.`cdm_identity_map` WHERE `entity`="release" AND `legacy_id`=:legacy_id LIMIT 1');
            $selTrack = $target->prepare('SELECT `id`,`slug` FROM `ngn_2025`.`tracks` WHERE `slug`=:slug LIMIT 1');
            $insMapTrack = $target->prepare('INSERT IGNORE INTO `ngn_2025`.`cdm_identity_map` (`entity`,`legacy_id`,`legacy_slug`,`cdm_id`,`cdm_slug`,`source`) VALUES (:entity,:legacy_id,:legacy_slug,:cdm_id,:cdm_slug,:source)');

            foreach ($songs as $row) {
                $legacyArtistId = (int)($row['legacy_artist_id'] ?? 0);
                $legacyReleaseId = (int)($row['legacy_release_id'] ?? 0);
                if ($legacyArtistId <= 0 || $legacyReleaseId <= 0) { continue; }

                $artistRow = null;
                if ($legacyArtistId > 0) {
                    $selArtistId2->execute([':legacy_id' => $legacyArtistId]);
                    $artistRow = $selArtistId2->fetch(PDO::FETCH_ASSOC) ?: null;
                    if (method_exists($selArtistId2, 'closeCursor')) { $selArtistId2->closeCursor(); }
                }
                if (!$artistRow || empty($artistRow['cdm_id'])) { continue; }

                $releaseRow = null;
                if ($legacyReleaseId > 0) {
                    $selReleaseId->execute([':legacy_id' => $legacyReleaseId]);
                    $releaseRow = $selReleaseId->fetch(PDO::FETCH_ASSOC) ?: null;
                    if (method_exists($selReleaseId, 'closeCursor')) { $selReleaseId->closeCursor(); }
                }
                if (!$releaseRow || empty($releaseRow['cdm_id'])) { continue; }

                $insTrack->execute([
                    ':id' => $row['legacy_id'],
                    ':slug' => $row['slug'],
                    ':release_id' => (int)$releaseRow['cdm_id'],
                    ':artist_id' => (int)$artistRow['cdm_id'],
                    ':title' => $row['title'],
                    ':description' => $row['description'] ?? null,
                    ':duration_ms' => $row['duration_ms'] ?? null,
                    ':isrc' => $row['isrc'] ?? null,
                ]);

                $selTrack->execute([':slug' => $row['slug']]);
                $track = $selTrack->fetch(PDO::FETCH_ASSOC) ?: null;
                if (method_exists($selTrack, 'closeCursor')) { $selTrack->closeCursor(); }
                if ($track) {
                    $insMapTrack->execute([
                        ':entity' => 'track',
                        ':legacy_id' => $row['legacy_id'],
                        ':legacy_slug' => $row['slug'],
                        ':cdm_id' => $track['id'],
                        ':cdm_slug' => $track['slug'],
                        ':source' => 'seed_2025',
                    ]);
                }
            }
            if (method_exists($insTrack, 'closeCursor')) { $insTrack->closeCursor(); }
            if (method_exists($insMapTrack, 'closeCursor')) { $insMapTrack->closeCursor(); }
        }
    }

    /**
     * ETL: seed ngn_2025.posts and identity map from legacy posts table.
     */
    private function runEtlSeedPosts(): void
    {
        $primary = $this->db;
        $target = $this->connectionForDatabase('ngn_2025');

        $sql = 'SELECT p.Id AS legacy_id, p.Slug AS slug, p.Title AS title, p.Summary AS teaser, p.Body AS body, p.Published AS published, ' .
               'p.PublishedDate AS published_at, p.Author AS legacy_author_id, p.EditorId AS legacy_editor_id, p.Tags AS tags, p.DeletedAt AS deleted_at, ' .
               "CASE WHEN p.Image IS NOT NULL AND p.Image<>'' THEN CONCAT('/uploads/posts/', p.Slug, '/', p.Image) ELSE NULL END AS image_url " .
               'FROM posts p';
        $stmt = $primary->query($sql);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if ($stmt && method_exists($stmt, 'closeCursor')) { $stmt->closeCursor(); }
        if (!$rows) { return; }

        $insPost = $target->prepare('INSERT IGNORE INTO `ngn_2025`.`posts` (`id`,`slug`,`title`,`teaser`,`body`,`status`,`published_at`,`author_id`,`editor_id`,`tags`,`image_url`,`deleted_at`) VALUES (:id,:slug,:title,:teaser,:body,:status,:published_at,:author_id,:editor_id,:tags,:image_url,:deleted_at)');
        $selAuthor = $target->prepare('SELECT `cdm_id` FROM `ngn_2025`.`cdm_identity_map` WHERE `legacy_id`=:legacy_id LIMIT 1');
        $selPost = $target->prepare('SELECT `id`,`slug` FROM `ngn_2025`.`posts` WHERE `slug`=:slug LIMIT 1');
        $insMap = $target->prepare('INSERT IGNORE INTO `ngn_2025`.`cdm_identity_map` (`entity`,`legacy_id`,`legacy_slug`,`cdm_id`,`cdm_slug`,`source`) VALUES (:entity,:legacy_id,:legacy_slug,:cdm_id,:cdm_slug,:source)');

        foreach ($rows as $row) {
            $status = ((int)($row['published'] ?? 0) === 1) ? 'published' : 'draft';
            $legacyAuthorId = (int)($row['legacy_author_id'] ?? 0);
            $legacyEditorId = (int)($row['legacy_editor_id'] ?? 0);
            $authorId = null;
            $editorId = null;

            if ($legacyAuthorId > 0) {
                $selAuthor->execute([':legacy_id' => $legacyAuthorId]);
                $a = $selAuthor->fetch(PDO::FETCH_ASSOC) ?: null;
                if (method_exists($selAuthor, 'closeCursor')) { $selAuthor->closeCursor(); }
                if ($a && !empty($a['cdm_id'])) {
                    $authorId = (int)$a['cdm_id'];
                }
            }

            // Need to resolve editor_id similarly if it maps to a user
            if ($legacyEditorId > 0) {
                // Assuming editor_id also maps to a cdm_id in the identity map
                $selEditor = $target->prepare('SELECT `cdm_id` FROM `ngn_2025`.`cdm_identity_map` WHERE `legacy_id`=:legacy_id LIMIT 1');
                $selEditor->execute([':legacy_id' => $legacyEditorId]);
                $e = $selEditor->fetch(PDO::FETCH_ASSOC) ?: null;
                if (method_exists($selEditor, 'closeCursor')) { $selEditor->closeCursor(); }
                if ($e && !empty($e['cdm_id'])) {
                    $editorId = (int)$e['cdm_id'];
                }
            }


            $insPost->execute([
                ':id' => $row['legacy_id'],
                ':slug' => $row['slug'],
                ':title' => $row['title'],
                ':teaser' => $row['teaser'] ?? null,
                ':body' => REPLACE($row['body'] ?? '', '/lib/images/posts/', '/uploads/posts/') ?? null, // Update embedded image paths
                ':status' => $status,
                ':published_at' => $row['published_at'] ?: null,
                ':author_id' => $authorId,
                ':editor_id' => $editorId,
                ':tags' => $row['tags'] ?? null,
                ':image_url' => $row['image_url'] ?? null,
                ':deleted_at' => $row['deleted_at'] ?? null,
            ]);

            $selPost->execute([':slug' => $row['slug']]);
            $post = $selPost->fetch(PDO::FETCH_ASSOC) ?: null;
            if (method_exists($selPost, 'closeCursor')) { $selPost->closeCursor(); }
            if ($post) {
                $insMap->execute([
                    ':entity' => 'post',
                    ':legacy_id' => $row['legacy_id'],
                    ':legacy_slug' => $row['slug'],
                    ':cdm_id' => $post['id'],
                    ':cdm_slug' => $post['slug'],
                    ':source' => 'seed_2025',
                ]);
            }
        }

        if (method_exists($insPost, 'closeCursor')) { $insPost->closeCursor(); }
        if (method_exists($insMap, 'closeCursor')) { $insMap->closeCursor(); }
    }

    /**
     * ETL: backfill ngn_smr_2025.smr_chart from legacy smrrankings.chartdata.
     */
    private function runEtlSeedSmrChart(): void
    {
        $src = $this->connectionForDatabase('smrrankings');
        $dst = $this->connectionForDatabase('ngn_smr_2025');

        // NOTE: avoid using reserved word "rank" as an alias; MySQL 8 treats it as a keyword.
        $sql = 'SELECT Date AS window_date, TWP AS smr_rank, Artists, Song, Label, TWS, LWS, WOC, Adds, Difference FROM chartdata';
        $stmt = $src->query($sql);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if ($stmt && method_exists($stmt, 'closeCursor')) { $stmt->closeCursor(); }
        if (!$rows) { return; }

        $ins = $dst->prepare('INSERT IGNORE INTO `ngn_smr_2025`.`smr_chart`
            (`window_date`,`rank`,`artist`,`track`,`label`,`tws`,`lws`,`woc`,`adds`,`delta`)
            VALUES (:window_date,:rank,:artist,:track,:label,:tws,:lws,:woc,:adds,:delta)');

        foreach ($rows as $row) {
            $ins->execute([
                ':window_date' => substr((string)($row['window_date'] ?? $row['Date'] ?? ''), 0, 10),
                ':rank' => (int)($row['smr_rank'] ?? $row['TWP'] ?? 0),
                ':artist' => (string)($row['Artists'] ?? ''),
                ':track' => $row['Song'] ?? null,
                ':label' => $row['Label'] ?? null,
                ':tws' => isset($row['TWS']) ? (int)$row['TWS'] : null,
                ':lws' => isset($row['LWS']) ? (int)$row['LWS'] : null,
                ':woc' => isset($row['WOC']) ? (int)$row['WOC'] : null,
                ':adds' => isset($row['Adds']) ? (int)$row['Adds'] : null,
                ':delta' => isset($row['Difference']) ? (int)$row['Difference'] : null,
            ]);
        }

        if (method_exists($ins, 'closeCursor')) { $ins->closeCursor(); }
    }

    /**
     * ETL: seed ngn_spins_2025.station_spins from authoritative spins DB (ngnspins.spindata).
     *
     * We deliberately allow null artist/track FKs when we cannot resolve them, and
     * preserve the original free-text fields in the meta JSON so charts can still
     * use/play these rows while we improve matching.
     */
    private function runEtlSeedStationSpins(): void
    {
        // Legacy spins live in the separate ngnspins database (spindata table)
        $spinsSource = $this->connectionForDatabase('ngnspins');
        $spinsDb = $this->connectionForDatabase('ngn_spins_2025');
        $target2025 = $this->connectionForDatabase('ngn_2025');

        $sql = 'SELECT s.Id AS legacy_id, s.StationId AS legacy_station_id, ' .
               's.Artist AS artist_name, s.Song AS track_name, s.Timestamp AS played_at, ' .
               's.TWS, s.Program, s.Hotlist ' .
               'FROM spindata s WHERE s.Approved = 1';
        $stmt = $spinsSource->query($sql);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if ($stmt && method_exists($stmt, 'closeCursor')) { $stmt->closeCursor(); }
        if (!$rows) { return; }

        $selStation = $target2025->prepare('SELECT `cdm_id` FROM `ngn_2025`.`cdm_identity_map` WHERE `entity`="station" AND `legacy_id`=:legacy_id LIMIT 1');
        $selTrack = $target2025->prepare('SELECT t.id, t.title FROM `ngn_2025`.`tracks` t WHERE t.title = :title LIMIT 1');
        $selArtist = $target2025->prepare('SELECT a.id, a.name FROM `ngn_2025`.`artists` a WHERE a.name = :name LIMIT 1');
        $insSpin = $spinsDb->prepare('INSERT INTO `ngn_spins_2025`.`station_spins` (`station_id`,`artist_id`,`track_id`,`played_at`,`meta`) VALUES (:station_id,:artist_id,:track_id,:played_at,:meta)');

        foreach ($rows as $row) {
            $legacyStationId = (int)($row['legacy_station_id'] ?? 0);
            if ($legacyStationId <= 0) { continue; }

            $selStation->execute([':legacy_id' => $legacyStationId]);
            $st = $selStation->fetch(PDO::FETCH_ASSOC) ?: null;
            if (method_exists($selStation, 'closeCursor')) { $selStation->closeCursor(); }
            if (!$st || empty($st['cdm_id'])) { continue; }

            $artistId = null;
            $trackId = null;

            $artistName = trim((string)($row['artist_name'] ?? ''));
            if ($artistName !== '') {
                $selArtist->execute([':name' => $artistName]);
                $a = $selArtist->fetch(PDO::FETCH_ASSOC) ?: null;
                if (method_exists($selArtist, 'closeCursor')) { $selArtist->closeCursor(); }
                if ($a && !empty($a['id'])) { $artistId = (int)$a['id']; }
            }

            $trackName = trim((string)($row['track_name'] ?? ''));
            if ($trackName !== '') {
                $selTrack->execute([':title' => $trackName]);
                $t = $selTrack->fetch(PDO::FETCH_ASSOC) ?: null;
                if (method_exists($selTrack, 'closeCursor')) { $selTrack->closeCursor(); }
                if ($t && !empty($t['id'])) { $trackId = (int)$t['id']; }
            }

            // Do NOT drop spins when artist/track could not be resolved; keep them with null FKs.
            $meta = [
                'legacy_id' => (int)($row['legacy_id'] ?? 0),
                'legacy_station_id' => $legacyStationId,
                'artist_name' => $artistName,
                'track_name' => $trackName,
                'tws' => isset($row['TWS']) ? (int)$row['TWS'] : null,
                'program' => $row['Program'] ?? null,
                'hotlist' => isset($row['Hotlist']) ? (int)$row['Hotlist'] : null,
            ];

            $insSpin->execute([
                ':station_id' => (int)$st['cdm_id'],
                ':artist_id' => $artistId,
                ':track_id' => $trackId,
                ':played_at' => $row['played_at'],
                ':meta' => json_encode($meta, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
            ]);
        }

        if (method_exists($insSpin, 'closeCursor')) { $insSpin->closeCursor(); }
    }

    /**
     * Map a physical database name to a named connection using .env credentials.
     */
    private function connectionForDatabase(string $dbName): PDO
    {
        // Normalize
        $dbName = trim($dbName);
        // Map physical names to named connection keys used in .env
        $map = [
            'ngn_2025'          => 'dev',
            'ngn_rankings_2025' => 'rankings2025',
            'ngn_smr_2025'      => 'smr2025',
            'ngn_spins_2025'    => 'spins2025',
            'ngnspins'          => 'ngnspins',
            'smrrankings'       => 'smrrankings',
            'smr_charts'        => 'smrrankings',
        ];
        $key = $map[$dbName] ?? null;
        if ($key === null) {
            // Anything else: use primary
            return $this->db;
        }
        if (!isset($this->named[$key])) {
            $this->named[$key] = ConnectionFactory::named($this->config, $key);
        }
        return $this->named[$key];
    }

    /**
     * For read-only SELECT-style checks, return a short-lived, isolated connection for the
     * appropriate database so that any unbuffered cursor on the main DDL connection cannot
     * block these queries. This avoids MySQL error 2014 while still allowing cross-db checks.
     */
    private function selectConnectionForDatabase(?string $dbName): PDO
    {
        // If the statement explicitly targets a known physical DB, open a fresh named
        // connection for that DB. Otherwise, open a fresh primary connection.
        if ($dbName !== null) {
            $dbName = trim($dbName);
        }
        $map = [
            'ngn_2025'          => 'dev',
            'ngn_rankings_2025' => 'rankings2025',
            'ngn_smr_2025'      => 'smr2025',
            'ngn_spins_2025'    => 'spins2025',
            'ngnspins'          => 'ngnspins',
        ];
        if ($dbName !== null && isset($map[$dbName])) {
            // Use a one-off named connection; do NOT reuse $this->named here so each
            // SELECT batch is isolated from any prior DDL.
            return ConnectionFactory::named($this->config, $map[$dbName]);
        }
        // Fallback: short-lived primary connection
        return ConnectionFactory::write($this->config);
    }


}

