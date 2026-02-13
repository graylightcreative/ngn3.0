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

echo "🚰 Repressurizing the Charts Moat...\n";
echo "===================================\n";

$config = new Config();
$pdo = ConnectionFactory::write($config); // ngn_2025
$rankingsPdo = ConnectionFactory::named($config, 'rankings2025');

// 1. Sync Artists
echo "Syncing Artists to Rankings DB... ";
$stmt = $pdo->query("SELECT id FROM `ngn_2025`.`artists` WHERE status = 'active'");
$artists = $stmt->fetchAll(PDO::FETCH_ASSOC);

try {
    $rankingsPdo->exec("DELETE FROM `ngn_rankings_2025`.`artists` WHERE 1=1");
    
    $rankingsPdo->beginTransaction();
    $ins = $rankingsPdo->prepare("INSERT INTO `ngn_rankings_2025`.`artists` (Id, ArtistId, Score) VALUES (?, ?, 0)");
    
    foreach ($artists as $a) {
        $ins->execute([$a['id'], $a['id']]);
    }
    $rankingsPdo->commit();
    echo "OK (" . count($artists) . " artists)\n";
} catch (\Throwable $e) {
    if ($rankingsPdo->inTransaction()) $rankingsPdo->rollBack();
    echo "FAIL: " . $e->getMessage() . "\n";
}

// 2. Sync Labels
echo "Syncing Labels to Rankings DB... ";
$stmt = $pdo->query("SELECT id FROM `ngn_2025`.`labels` WHERE status = 'active'");
$labels = $stmt->fetchAll(PDO::FETCH_ASSOC);

try {
    $rankingsPdo->exec("DELETE FROM `ngn_rankings_2025`.`labels` WHERE 1=1");
    
    $rankingsPdo->beginTransaction();
    $ins = $rankingsPdo->prepare("INSERT INTO `ngn_rankings_2025`.`labels` (Id, LabelId, Score) VALUES (?, ?, 0)");
    foreach ($labels as $l) {
        $ins->execute([$l['id'], $l['id']]);
    }
    $rankingsPdo->commit();
    echo "OK (" . count($labels) . " labels)\n";
} catch (\Throwable $e) {
    if ($rankingsPdo->inTransaction()) $rankingsPdo->rollBack();
    echo "FAIL: " . $e->getMessage() . "\n";
}

echo "\n🏁 Moat Repressurized.\n";
