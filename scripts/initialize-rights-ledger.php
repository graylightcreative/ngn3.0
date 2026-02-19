<?php
/**
 * scripts/initialize-rights-ledger.php
 * 
 * Purpose: Create rights ledger entries for all reconstructed ghost tracks.
 * Default: 100% split to the Artist.
 * Required for royalty processing.
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

echo "⚖️  Initializing Rights Ledger (2025 Shard)
";
echo "========================================
";

$config = new Config();
$pdo = ConnectionFactory::write($config);

// 1. Fetch all tracks without rights
echo "Fetching tracks without rights...
";
$sql = "
    SELECT t.id, t.artist_id, t.title, a.user_id as owner_id
    FROM tracks t
    JOIN artists a ON t.artist_id = a.id
    LEFT JOIN cdm_rights_ledger rl ON t.id = rl.track_id
    WHERE rl.id IS NULL
";
$tracks = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($tracks) . " tracks requiring ledger initialization.
";

$ledgerCount = 0;
$splitCount = 0;

$insertLedgerStmt = $pdo->prepare("
    INSERT INTO cdm_rights_ledger (artist_id, track_id, owner_id, status, is_royalty_eligible, created_at)
    VALUES (:artist_id, :track_id, :owner_id, 'verified', 1, NOW())
");

$insertSplitStmt = $pdo->prepare("
    INSERT INTO cdm_rights_splits (right_id, contributor_id, percentage, role, verified, created_at)
    VALUES (:right_id, :contributor_id, 100.00, 'Primary Artist', 1, NOW())
");

foreach ($tracks as $track) {
    if (!$track['owner_id']) continue;

    $pdo->beginTransaction();
    try {
        $insertLedgerStmt->execute([
            ':artist_id' => $track['artist_id'],
            ':track_id' => $track['id'],
            ':owner_id' => $track['owner_id']
        ]);
        $rightId = $pdo->lastInsertId();
        
        $insertSplitStmt->execute([
            ':right_id' => $rightId,
            ':contributor_id' => $track['owner_id']
        ]);
        
        $pdo->commit();
        $ledgerCount++;
        $splitCount++;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Error on track {$track['id']}: " . $e->getMessage() . "
";
    }
}

echo "✅ Ledger Initialized.
";
echo "   - Rights Records: $ledgerCount
";
echo "   - 100% Splits: $splitCount
";
echo "========================================
";
