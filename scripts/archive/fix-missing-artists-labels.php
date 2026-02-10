<?php
/**
 * Fix Missing Artists & Labels Migration
 * Migrates artists (RoleId 3) and labels (RoleId 7) from legacy nextgennoise to ngn_2025
 * According to bible Ch. 2 (Core Data Model) and Ch. 19 (Identity Migration)
 */

$pdo_new = new PDO(
    'mysql:host=server.starrship1.com;dbname=ngn_2025',
    'nextgennoise',
    'NextGenNoise!1',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo_legacy = new PDO(
    'mysql:host=server.starrship1.com;dbname=nextgennoise',
    'nextgennoise',
    'NextGenNoise!1',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "========================================\n";
echo "NGN 2.0 Artist & Label Migration Fix\n";
echo "========================================\n\n";

// Get legacy artists (RoleId 3)
$legacyArtists = $pdo_legacy->query("
    SELECT Id, Email, Slug, Title, Image, StatusId
    FROM users
    WHERE RoleId = 3
    ORDER BY Id
")->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($legacyArtists) . " legacy artists\n";

// Get artists already in ngn_2025
$existingArtists = $pdo_new->query("
    SELECT id FROM `ngn_2025`.`artists`
")->fetchAll(PDO::FETCH_COLUMN);

$existingSet = array_flip($existingArtists);
echo "Already have " . count($existingArtists) . " artists in ngn_2025\n\n";

// Migrate missing artists
$artistsMigrated = 0;
$artistsFailed = 0;

foreach ($legacyArtists as $legacyArtist) {
    // Skip if already migrated (use legacy Id as reference)
    if (isset($existingSet[$legacyArtist['Id']])) {
        continue;
    }

    try {
        $stmt = $pdo_new->prepare("
            INSERT INTO `ngn_2025`.`artists` (id, slug, name, image_url)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE slug = VALUES(slug), name = VALUES(name), image_url = VALUES(image_url)
        ");

        $stmt->execute([
            $legacyArtist['Id'],
            $legacyArtist['Slug'] ?? strtolower(str_replace(' ', '-', $legacyArtist['Title'])),
            $legacyArtist['Title'],
            !empty($legacyArtist['Image']) ? "/uploads/users/{$legacyArtist['Slug']}/{$legacyArtist['Image']}" : NULL
        ]);

        $artistsMigrated++;
    } catch (Throwable $e) {
        echo "✗ Failed to migrate artist {$legacyArtist['Id']}: " . $e->getMessage() . "\n";
        $artistsFailed++;
    }
}

echo "✓ Migrated $artistsMigrated artists\n";
if ($artistsFailed > 0) echo "✗ Failed: $artistsFailed artists\n";
echo "\n";

// Get legacy labels (RoleId 7)
$legacyLabels = $pdo_legacy->query("
    SELECT Id, Email, Slug, Title, Image, StatusId
    FROM users
    WHERE RoleId = 7
    ORDER BY Id
")->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($legacyLabels) . " legacy labels\n";

// Get labels already in ngn_2025
$existingLabels = $pdo_new->query("
    SELECT id FROM `ngn_2025`.`labels`
")->fetchAll(PDO::FETCH_COLUMN);

$existingLabelSet = array_flip($existingLabels);
echo "Already have " . count($existingLabels) . " labels in ngn_2025\n\n";

// Migrate missing labels
$labelsMigrated = 0;
$labelsFailed = 0;

foreach ($legacyLabels as $legacyLabel) {
    // Skip if already migrated
    if (isset($existingLabelSet[$legacyLabel['Id']])) {
        continue;
    }

    try {
        $stmt = $pdo_new->prepare("
            INSERT INTO `ngn_2025`.`labels` (id, slug, name, image_url)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE slug = VALUES(slug), name = VALUES(name), image_url = VALUES(image_url)
        ");

        $stmt->execute([
            $legacyLabel['Id'],
            $legacyLabel['Slug'] ?? strtolower(str_replace(' ', '-', $legacyLabel['Title'])),
            $legacyLabel['Title'],
            !empty($legacyLabel['Image']) ? "/uploads/users/{$legacyLabel['Slug']}/{$legacyLabel['Image']}" : NULL
        ]);

        $labelsMigrated++;
    } catch (Throwable $e) {
        echo "✗ Failed to migrate label {$legacyLabel['Id']}: " . $e->getMessage() . "\n";
        $labelsFailed++;
    }
}

echo "✓ Migrated $labelsMigrated labels\n";
if ($labelsFailed > 0) echo "✗ Failed: $labelsFailed labels\n";

// Verify final counts
echo "\n========================================\n";
echo "Verification\n";
echo "========================================\n\n";

$finalArtists = $pdo_new->query("SELECT COUNT(*) FROM `ngn_2025`.`artists`")->fetchColumn();
$finalLabels = $pdo_new->query("SELECT COUNT(*) FROM `ngn_2025`.`labels`")->fetchColumn();

echo "Final artist count: $finalArtists (expected: 911)\n";
echo "Final label count: $finalLabels (expected: 290)\n";

if ($finalArtists >= 900 && $finalLabels >= 280) {
    echo "\n✓ Migration successful!\n";
} else {
    echo "\n⚠ Migration incomplete - review the output above\n";
}
