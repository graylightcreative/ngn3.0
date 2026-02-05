<?php
/**
 * MIGRATE_CONTENT_EXTRA.php
 * Migrates legacy releases and tracks to ngn_2025
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$db = ConnectionFactory::write($config);

echo "
" . str_repeat("=", 80) . "
";
echo "RELEASES & TRACKS MIGRATION
";
echo str_repeat("=", 80) . "

";

try {
    // 1. Migrate Releases
    echo "[STEP 1] Migrating Releases...
";
    $db->exec("DELETE FROM `ngn_2025`.`releases` WHERE 1=1");
    
    $sqlReleases = "INSERT INTO `ngn_2025`.`releases` 
        (id, artist_id, label_id, slug, title, type, release_date, genre, description, cover_url, created_at, updated_at)
        SELECT 
            Id, ArtistId, LabelId, Slug, Title, 
            LOWER(COALESCE(Type, 'album')), 
            ReleaseDate, Genre, Body, Image, Created, Updated
        FROM nextgennoise.releases";
    
    $migratedReleases = $db->exec($sqlReleases);
    echo "[OK] Migrated $migratedReleases releases.
";

    // 2. Migrate Tracks
    echo "[STEP 2] Migrating Tracks...
";
    $db->exec("DELETE FROM `ngn_2025`.`tracks` WHERE 1=1");
    
    // Some legacy systems use 'songs' some use 'tracks'. Check which one exists.
    $trackTable = 'songs';
    try {
        $db->query("SELECT 1 FROM nextgennoise.tracks LIMIT 1");
        $trackTable = 'tracks';
    } catch (Exception $e) {}

    $sqlTracks = "INSERT INTO `ngn_2025`.`tracks` 
        (id, release_id, artist_id, slug, title, track_number, duration_seconds, created_at, updated_at)
        SELECT 
            Id, ReleaseId, ArtistId, Slug, Title, TrackNumber, Duration, Created, Updated
        FROM nextgennoise.$trackTable";
    
    $migratedTracks = $db->exec($sqlTracks);
    echo "[OK] Migrated $migratedTracks tracks from nextgennoise.$trackTable.
";

} catch (Exception $e) {
    echo "[ERROR] Migration failed: " . $e->getMessage() . "
";
}

echo "
Done.
";
