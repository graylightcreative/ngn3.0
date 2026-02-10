<?php
/**
 * MIGRATE_VIDEOS.php
 * Migrates legacy videos to ngn_2025.videos
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$db = ConnectionFactory::write($config);

echo "
" . str_repeat("=", 80) . "
";
echo "VIDEO MIGRATION: nextgennoise.videos → ngn_2025.videos
";
echo str_repeat("=", 80) . "

";

try {
    // Check for source table
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM nextgennoise.videos");
        $stmt->execute();
        $legacyCount = $stmt->fetch()['cnt'];
    } catch (Exception $e) {
        die("[ERROR] Legacy videos table not found. Make sure nextgennoise database is loaded.
");
    }

    echo "[INFO] Legacy records found: $legacyCount
";

    // Step 1: Clear current videos (optional, but good for fresh seed)
    echo "[STEP 1] Clearing existing videos in ngn_2025...
";
    $db->exec("DELETE FROM `ngn_2025`.`videos` WHERE 1=1");

    // Step 2: Migrate
    echo "[STEP 2] Migrating records...
";
    
    // We map ArtistId to artist_id in the new table
    // We also map VideoId to external_id
    $sql = "INSERT INTO `ngn_2025`.`videos` 
            (id, artist_id, platform, external_id, title, slug, description, published_at, created_at, updated_at, status)
            SELECT 
                Id, 
                ArtistId, 
                LOWER(Platform), 
                VideoId, 
                Title, 
                Slug, 
                Summary, 
                COALESCE(ReleaseDate, Created), 
                Created, 
                Updated,
                'published'
            FROM nextgennoise.videos";
    
    $migrated = $db->exec($sql);
    echo "[OK] Migrated $migrated videos successfully.
";

    // Step 3: Map user_id if column exists and we can link them
    // In ngn_2025, videos often have user_id instead of artist_id or both
    try {
        echo "[STEP 3] Linking videos to user accounts via artists table...
";
        $updateSql = "UPDATE `ngn_2025`.`videos` v
                      JOIN `ngn_2025`.`artists` a ON v.artist_id = a.id
                      SET v.user_id = a.user_id
                      WHERE a.user_id IS NOT NULL";
        $linked = $db->exec($updateSql);
        echo "[OK] Linked $linked videos to user accounts.
";
    } catch (Exception $e) {
        echo "[WARN] Could not link users: " . $e->getMessage() . "
";
    }

} catch (Exception $e) {
    echo "[ERROR] Video migration failed: " . $e->getMessage() . "
";
    exit(1);
}

echo "
Done.
";
