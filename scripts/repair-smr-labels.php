<?php

/**
 * repair-smr-labels.php - Backfill label_name in smr_records from archives
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

echo "🔧 SMR Label Repair Tool
";
echo "========================
";

$config = new Config();
$pdo = ConnectionFactory::write($config);

$archiveDir = __DIR__ . '/../storage/archives/smr';
$files = glob($archiveDir . '/* Top 200.csv');

foreach ($files as $filePath) {
    $filename = basename($filePath);
    echo "Processing $filename... ";
    
    // Get ingestion_id
    $stmt = $pdo->prepare("SELECT id FROM smr_ingestions WHERE filename = ?");
    $stmt->execute([$filename]);
    $ingestionId = $stmt->fetchColumn();
    
    if (!$ingestionId) {
        echo "Skip (Not in DB)
";
        continue;
    }

    $handle = fopen($filePath, 'r');
    $header = fgetcsv($handle, null, ',', '"', "");
    $headerTrimmed = array_map('trim', $header);
    $map = array_flip($headerTrimmed);
    
    if (!isset($map['LABEL'])) {
        echo "Skip (No LABEL col)
";
        fclose($handle);
        continue;
    }

    $count = 0;
    while (($row = fgetcsv($handle, null, ',', '"', "")) !== false) {
        if (empty(array_filter($row))) continue;
        
        $artist = $row[$map['ARTIST']] ?? '';
        $title = $row[$map['TITLE']] ?? '';
        $label = $row[$map['LABEL']] ?? '';
        
        $upd = $pdo->prepare("
            UPDATE smr_records 
            SET label_name = ? 
            WHERE ingestion_id = ? AND artist_name = ? AND track_title = ?
        ");
        $upd->execute([$label, $ingestionId, $artist, $title]);
        $count += $upd->rowCount();
    }
    fclose($handle);
    echo "Updated $count rows.
";
}

echo "✅ Repair complete.
";
