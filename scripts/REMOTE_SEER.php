<?php
/**
 * REMOTE SEER SCRIPT (v8) - THE "NO USERS" EDITION
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();

try {
    $pdo = ConnectionFactory::write($config);
    echo "--- REMOTE SEER START (No-User Mode) ---\n";

    // 1. Seed Roles
    echo "Seeding default roles...\n";
    $pdo->exec("INSERT IGNORE INTO `ngn_2025`.`roles` (`id`, `name`) VALUES
        (1, 'Administrator'), (2, 'User'), (3, 'Artist'), (4, 'Station'),
        (5, 'Venue'), (6, 'Writer'), (7, 'Label'), (8, 'Editor'),
        (9, 'Contributor'), (10, 'Moderator'), (11, 'Advertiser'), (12, 'Fan')");

    // 2. Seed Admin User
    echo "Ensuring Admin user exists...\n";
    // Use single quotes for the hash to prevent PHP variable interpolation
    $hash = '$2y$10$ozXlINpTSf42Uw6Ax/IJi.fdKOZIHkUfzV6tw/MvhcH8b/KNxDynC';
    $stmt = $pdo->prepare("INSERT IGNORE INTO `ngn_2025`.`users` 
        (id, email, password_hash, username, display_name, role_id, status, created_at) 
        VALUES (1, 'brock@graylightcreative.com', :hash, 'brock-lamb', 'Brock Lamb', 1, 'active', NOW())");
    $stmt->execute([':hash' => $hash]);

    // 3. Update Artists
    echo "Updating artists from legacy data...\n";
    $sql = "SELECT Id, Slug, Title, Body, Image, WebsiteUrl, FacebookUrl, InstagramUrl, YoutubeUrl, SpotifyId, TiktokUrl FROM nextgennoise.users WHERE RoleId = 3";
    $artists = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
    $updArtist = $pdo->prepare("UPDATE `ngn_2025`.`artists` SET
        name = :name,
        bio = :bio,
        image_url = :image,
        website = :website,
        facebook_url = :facebook,
        instagram_url = :instagram,
        youtube_url = :youtube,
        spotify_url = :spotify,
        tiktok_url = :tiktok
        WHERE slug = :slug");

    foreach ($artists as $a) {
        $imageUrl = $a['Image'] ? "/uploads/users/" . $a['Slug'] . "/" . $a['Image'] : null;
        $spotifyUrl = $a['SpotifyId'] ? "https://open.spotify.com/artist/" . $a['SpotifyId'] : null;
        
        $updArtist->execute([
            ':name' => $a['Title'],
            ':bio' => $a['Body'],
            ':image' => $imageUrl,
            ':website' => $a['WebsiteUrl'],
            ':facebook' => $a['FacebookUrl'],
            ':instagram' => $a['InstagramUrl'],
            ':youtube' => $a['YoutubeUrl'],
            ':spotify' => $spotifyUrl,
            ':tiktok' => $a['TiktokUrl'],
            ':slug' => $a['Slug']
        ]);
    }
    echo "✓ Updated " . count($artists) . " artists.\n";

    // 4. Update Labels
    echo "Updating labels from legacy data...\n";
    $sql = "SELECT Id, Slug, Title, Body, Image, WebsiteUrl FROM nextgennoise.users WHERE RoleId = 7";
    $labels = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
    $updLabel = $pdo->prepare("UPDATE `ngn_2025`.`labels` SET
        name = :name,
        bio = :bio,
        image_url = :image,
        website = :website
        WHERE slug = :slug");

    foreach ($labels as $l) {
        $imageUrl = $l['Image'] ? "/uploads/users/" . $l['Slug'] . "/" . $l['Image'] : null;
        $updLabel->execute([
            ':name' => $l['Title'],
            ':bio' => $l['Body'],
            ':image' => $imageUrl,
            ':website' => $l['WebsiteUrl'],
            ':slug' => $l['Slug']
        ]);
    }
    echo "✓ Updated " . count($labels) . " labels.\n";

    // 5. Update Stations
    echo "Updating stations from legacy data...\n";
    $sql = "SELECT Id, Slug, Title, Body, Image FROM nextgennoise.users WHERE RoleId = 9";
    $stations = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
    $updStation = $pdo->prepare("UPDATE `ngn_2025`.`stations` SET
        name = :name,
        bio = :bio,
        image_url = :image
        WHERE slug = :slug");

    foreach ($stations as $s) {
        $imageUrl = $s['Image'] ? "/uploads/users/" . $s['Slug'] . "/" . $s['Image'] : null;
        $updStation->execute([
            ':name' => $s['Title'],
            ':bio' => $s['Body'],
            ':image' => $imageUrl,
            ':slug' => $s['Slug']
        ]);
    }
    echo "✓ Updated " . count($stations) . " stations.\n";

    // 6. Identity Map
    echo "Refreshing identity map...\n";
    $pdo->exec("INSERT IGNORE INTO `ngn_2025`.`cdm_identity_map` (entity, legacy_id, legacy_slug, cdm_id, cdm_slug, source)
        SELECT 'artist', id, slug, id, slug, 'seed_preserved' FROM `ngn_2025`.`artists` WHERE id IN (SELECT Id FROM nextgennoise.users WHERE RoleId=3)");
    $pdo->exec("INSERT IGNORE INTO `ngn_2025`.`cdm_identity_map` (entity, legacy_id, legacy_slug, cdm_id, cdm_slug, source)
        SELECT 'label', id, slug, id, slug, 'seed_preserved' FROM `ngn_2025`.`labels` WHERE id IN (SELECT Id FROM nextgennoise.users WHERE RoleId=7)");
    $pdo->exec("INSERT IGNORE INTO `ngn_2025`.`cdm_identity_map` (entity, legacy_id, legacy_slug, cdm_id, cdm_slug, source)
        SELECT 'station', u.Id, u.Slug, s.id, s.slug, 'seed_slug_match' 
        FROM nextgennoise.users u 
        JOIN `ngn_2025`.`stations` s ON u.Slug = s.slug 
        WHERE u.RoleId = 9");

    echo "✓ Identity map refreshed.\n";

    echo "--- REMOTE SEER COMPLETE ---\n";

} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}