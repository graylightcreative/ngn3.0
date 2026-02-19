<?php
/**
 * scripts/repair-artist-ownership.php
 * 
 * Purpose: Map legacy Role 3 (Artist) users to ngn_2025.artists.user_id.
 * In the legacy system, Artist entities and User entities shared the same ID space.
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

echo "🛠️ Repairing Artist Ownership (1:1 ID Mapping)\n";
echo "============================================\n";

$config = new Config();
$pdo = ConnectionFactory::write($config);

// 1. Fetch artists who need a user_id
$stmt = $pdo->query("SELECT id FROM artists WHERE user_id IS NULL OR user_id = 0");
$artists = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($artists) . " artists needing ownership assignment.\n";

$repaired = 0;

// 2. Map user_id = id (1:1 mapping confirmed by legacy comparison)
$updateStmt = $pdo->prepare("UPDATE artists SET user_id = id WHERE id = ?");

foreach ($artists as $artist) {
    $id = $artist['id'];
    
    // Ensure the user actually exists in the users table
    $checkUser = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $checkUser->execute([$id]);
    
    if ($checkUser->fetch()) {
        $updateStmt->execute([$id]);
        $repaired++;
    }
}

echo "✅ Ownership Repaired.\n";
echo "   - Links Established: $repaired\n";
echo "============================================\n";
