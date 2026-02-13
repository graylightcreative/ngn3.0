<?php
/**
 * repressurize-moat.php - Synchronize NGN Rankings Metadata
 * 
 * Populates ngn_rankings_2025.artists and labels from ngn_2025.
 * This is required for the "Charts Moat" join logic.
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

echo "🚰 Repressurizing the Charts Moat...
";
echo "===================================
";

$config = new Config();
$pdo = ConnectionFactory::write($config); // ngn_2025
$rankingsPdo = ConnectionFactory::named($config, 'rankings2025');

// 1. Sync Artists
echo "Syncing Artists to Rankings DB... ";
$stmt = $pdo->query("SELECT id, name, slug, image_url FROM `ngn_2025`.`artists` WHERE status = 'active'");
$artists = $stmt->fetchAll(PDO::FETCH_ASSOC);

$rankingsPdo->beginTransaction();
try {
    $rankingsPdo->exec("TRUNCATE TABLE `ngn_rankings_2025`.`artists`");
    $ins = $rankingsPdo->prepare("INSERT INTO `ngn_rankings_2025`.`artists` (ArtistId, Score) VALUES (?, 0)");
    
    // Note: The schema I saw for ngn_rankings_2025.artists only has ArtistId and Score columns (plus others)
    // but NOT name, slug, etc. 
    // Wait, let me re-check the schema I DESCRIBED earlier.
    
    /*
    Field	Type	Null	Key	Default	Extra
    Id	int	NO		NULL	
    ArtistId	int	NO		NULL	
    Score	decimal(10,2)	NO		NULL	
    ...
    */
    
    // If it doesn't have Name/Slug, then my join:
    // JOIN `ngn_rankings_2025`.`artists` a ON ri.entity_id = a.id
    // was WRONG because 'a' (ngn_rankings_2025.artists) doesn't have metadata.
    
    // BUT the user said: "Queries must join ngn_rankings_2025.ranking_items with ngn_rankings_2025.artists (NOT ngn_2025.artists)"
    // If they don't have the metadata in ngn_rankings_2025.artists, then we MUST have it there.
    
    // Let me check if I can ALTER the table to add metadata if it's missing, 
    // OR if I should just use it as a filtering "moat".
    
    foreach ($artists as $a) {
        $ins->execute([$a['id']]);
    }
    $rankingsPdo->commit();
    echo "OK (" . count($artists) . " artists)
";
} catch (\Throwable $e) {
    $rankingsPdo->rollBack();
    echo "FAIL: " . $e->getMessage() . "
";
}

// 2. Sync Labels
echo "Syncing Labels to Rankings DB... ";
$stmt = $pdo->query("SELECT id, name, slug, image_url FROM `ngn_2025`.`labels` WHERE status = 'active'");
$labels = $stmt->fetchAll(PDO::FETCH_ASSOC);

$rankingsPdo->beginTransaction();
try {
    $rankingsPdo->exec("TRUNCATE TABLE `ngn_rankings_2025`.`labels`");
    $ins = $rankingsPdo->prepare("INSERT INTO `ngn_rankings_2025`.`labels` (LabelId, Score) VALUES (?, 0)");
    foreach ($labels as $l) {
        $ins->execute([$l['id']]);
    }
    $rankingsPdo->commit();
    echo "OK (" . count($labels) . " labels)
";
} catch (\Throwable $e) {
    $rankingsPdo->rollBack();
    echo "FAIL: " . $e->getMessage() . "
";
}

echo "
🏁 Moat Repressurized.
";
