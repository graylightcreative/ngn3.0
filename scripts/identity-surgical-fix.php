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

echo "🔪 NGN Identity Surgical Fix (v1.0)
";
echo "==================================
";

$config = new Config();
$pdo = ConnectionFactory::write($config); // ngn_2025
$rankingsPdo = ConnectionFactory::named($config, 'rankings2025');

try {
    $legacyPdo = new PDO('mysql:host=server.starrship1.com;dbname=nextgennoise', 'root', 'Starr!1');
} catch (\Throwable $e) {
    die("❌ Could not connect to legacy DB for verification.
");
}

// 1. Scan current labels for misidentification
echo "Scanning labels for incorrect identity...
";
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
            echo "   ⚠️  Found Artist in Label table: ID $id => '$realName'
";
            
            // A. Remove from labels
            $pdo->prepare("DELETE FROM labels WHERE id = ?")->execute([$id]);
            
            // B. Ensure in artists
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $realName)) . '-' . $id;
            $ins = $pdo->prepare("INSERT INTO artists (id, name, slug, status) VALUES (?, ?, ?, 'ghost') ON DUPLICATE KEY UPDATE name = VALUES(name)");
            $ins->execute([$id, $realName, $slug]);
            
            // C. Correct ranking_items
            $upd = $rankingsPdo->prepare("UPDATE ranking_items SET entity_type = 'artist' WHERE entity_id = ? AND entity_type = 'label'");
            $upd->execute([$id]);
            
            $reassigned++;
        } elseif ($roleId === 7) {
            // Correct label name if it was a placeholder
            if (str_contains($l['name'], 'Ghost Label')) {
                echo "   🏷️  Updating Label Name: ID $id => '$realName'
";
                $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $realName)) . '-' . $id;
                $pdo->prepare("UPDATE labels SET name = ?, slug = ? WHERE id = ?")->execute([$realName, $slug, $id]);
            }
        }
    } else {
        // No legacy record found. If it's a ghost placeholder with no artists linked, maybe purge?
        // Let's check if any artists in ngn_2025 use this label_id
        $check = $pdo->prepare("SELECT COUNT(*) FROM artists WHERE label_id = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() == 0 && str_contains($l['name'], 'Ghost Label')) {
            echo "   🗑️  Purging orphaned Ghost Label: ID $id
";
            $pdo->prepare("DELETE FROM labels WHERE id = ?")->execute([$id]);
            $purged++;
        }
    }
}

echo "
🏁 Surgical Fix Complete.
";
echo "   Reassigned: $reassigned artists.
";
echo "   Purged:     $purged orphaned labels.
";

echo "🔄 Repressurizing Moat...
";
include __DIR__ . '/repressurize-moat.php';
