<?php
/**
 * identity-surgical-fix.php - NGN Identity Correction
 * 
 * Corrects misidentified Artists that were placed in the Labels table.
 * Strictly respects Legacy RoleIds (3=Artist, 7=Label).
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

echo "🔪 NGN Identity Surgical Fix (v1.1)\n";
echo "==================================\n";

$config = new Config();
$pdo = ConnectionFactory::write($config); // ngn_2025
$rankingsPdo = ConnectionFactory::named($config, 'rankings2025');

try {
    $legacyPdo = new PDO('mysql:host=server.starrship1.com;dbname=nextgennoise', 'root', 'Starr!1');
} catch (\Throwable $e) {
    die("❌ Could not connect to legacy DB for verification.\n");
}

// 1. Scan current labels for misidentification
echo "Scanning labels for incorrect identity...\n";
$labels = $pdo->query("SELECT id, name FROM labels")->fetchAll(PDO::FETCH_ASSOC);

$reassigned = 0;
$purged = 0;

foreach ($labels as $l) {
    $id = (int)$l['id'];
    
    // Check legacy role
    $stmt = $legacyPdo->prepare("SELECT Title, RoleId FROM users WHERE Id = ? LIMIT 1");
    $stmt->execute([$id]);
    $legacy = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($legacy) {
        $roleId = (int)$legacy['RoleId'];
        $realName = $legacy['Title'];
        
        if ($roleId === 3) {
            // THIS IS AN ARTIST!
            echo "   ⚠️  Found Artist in Label table: ID $id => '$realName'\n";
            
            // A. Remove from labels
            $pdo->prepare("DELETE FROM labels WHERE id = ?")->execute([$id]);
            
            // B. Ensure in artists
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $realName)) . '-' . $id;
            $ins = $pdo->prepare("INSERT INTO artists (id, name, slug, status) VALUES (?, ?, ?, 'ghost') ON DUPLICATE KEY UPDATE name = VALUES(name)");
            $ins->execute([$id, $realName, $slug]);
            
            // C. Correct ranking_items (HANDLE DUPLICATES)
            $stmt = $rankingsPdo->prepare("SELECT * FROM ranking_items WHERE entity_type = 'label' AND entity_id = ?");
            $stmt->execute([$id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($items as $item) {
                $wid = $item['window_id'];
                // Check if artist entry already exists for this ID in this window
                $check = $rankingsPdo->prepare("SELECT score FROM ranking_items WHERE window_id = ? AND entity_type = 'artist' AND entity_id = ?");
                $check->execute([$wid, $id]);
                $existingScore = $check->fetchColumn();
                
                if ($existingScore !== false) {
                    // SUM scores
                    $newScore = (float)$existingScore + (float)$item['score'];
                    $upd = $rankingsPdo->prepare("UPDATE ranking_items SET score = ? WHERE window_id = ? AND entity_type = 'artist' AND entity_id = ?");
                    $upd->execute([$newScore, $wid, $id]);
                    // Delete the label ranking entry
                    $del = $rankingsPdo->prepare("DELETE FROM ranking_items WHERE window_id = ? AND entity_type = 'label' AND entity_id = ?");
                    $del->execute([$wid, $id]);
                } else {
                    // No artist entry yet, just switch type
                    $upd = $rankingsPdo->prepare("UPDATE ranking_items SET entity_type = 'artist' WHERE window_id = ? AND entity_type = 'label' AND entity_id = ?");
                    $upd->execute([$wid, $id]);
                }
            }
            
            $reassigned++;
        } elseif ($roleId === 7) {
            // Correct label name if it was a placeholder
            if (str_contains($l['name'], 'Ghost Label')) {
                echo "   🏷️  Updating Label Name: ID $id => '$realName'\n";
                $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $realName)) . '-' . $id;
                $pdo->prepare("UPDATE labels SET name = ?, slug = ? WHERE id = ?")->execute([$realName, $slug, $id]);
            }
        }
    } else {
        // No legacy record found. If it's a ghost placeholder with no artists linked, maybe purge?
        $check = $pdo->prepare("SELECT COUNT(*) FROM artists WHERE label_id = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() == 0 && str_contains($l['name'], 'Ghost Label')) {
            echo "   🗑️  Purging orphaned Ghost Label: ID $id\n";
            $pdo->prepare("DELETE FROM labels WHERE id = ?")->execute([$id]);
            $purged++;
        }
    }
}

echo "\n🏁 Surgical Fix Complete.\n";
echo "   Reassigned: $reassigned artists.\n";
echo "   Purged:     $purged orphaned labels.\n";

echo "🔄 Repressurizing Moat...\n";
include __DIR__ . '/repressurize-moat.php';
