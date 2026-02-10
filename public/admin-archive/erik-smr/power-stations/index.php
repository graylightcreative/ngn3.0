<?php
/**
 * Power Station Ingestion Management
 *
 * Admin interface for Erik Baker to manage automated terrestrial station feeds.
 */

$root = dirname(__DIR__, 4);
require_once $root . '/lib/bootstrap.php';

use NGN\Lib\DB\ConnectionFactory;

$pdo = ConnectionFactory::write($config);

// Handle status toggle
if (isset($_GET['toggle_id'])) {
    $id = (int)$_GET['toggle_id'];
    $pdo->prepare("UPDATE `ngn_2025`.`power_station_profiles` SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
    header("Location: index.php?msg=status_updated");
    exit;
}

// Fetch profiles with station names and last log status
$sql = "
    SELECT p.*, s.name as station_name,
           (SELECT status FROM `ngn_2025`.`power_station_ingestion_logs` 
            WHERE profile_id = p.id ORDER BY created_at DESC LIMIT 1) as last_status,
           (SELECT records_count FROM `ngn_2025`.`power_station_ingestion_logs` 
            WHERE profile_id = p.id ORDER BY created_at DESC LIMIT 1) as last_count
    FROM `ngn_2025`.`power_station_profiles` p
    JOIN `ngn_2025`.`stations` s ON p.station_id = s.id
    ORDER BY s.name ASC
";
$profiles = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Power Station Management | Erik SMR Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7f6; font-family: 'Inter', sans-serif; }
        .dashboard-card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 2rem; }
        .status-pill { padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.85rem; }
        .status-success { background: #e6fcf5; color: #0ca678; }
        .status-failed { background: #fff5f5; color: #fa5252; }
        .status-active { background: #e7f5ff; color: #228be6; }
        .status-inactive { background: #f1f3f5; color: #868e96; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2">Power Station Auto-Ingestion</h1>
                <p class="text-muted">Manage automated SMR feeds for high-volume terrestrial stations.</p>
            </div>
            <a href="add.php" class="btn btn-primary">+ Add New Station</a>
        </div>

        <div class="dashboard-card">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Station</th>
                        <th>Feed Type</th>
                        <th>Frequency</th>
                        <th>Last Ingested</th>
                        <th>Status</th>
                        <th>Last Run</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($profiles)): ?>
                        <tr><td colspan="7" class="text-center py-4">No power station profiles configured.</td></tr>
                    <?php endif; ?>
                    
                    <?php foreach ($profiles as $p): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($p['station_name']) ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($p['feed_url']) ?></small>
                        </td>
                        <td><span class="badge bg-secondary"><?= strtoupper($p['feed_type']) ?></span></td>
                        <td>Every <?= $p['ingestion_frequency_minutes'] ?>m</td>
                        <td><?= $p['last_ingested_at'] ? date('M j, H:i', strtotime($p['last_ingested_at'])) : 'Never' ?></td>
                        <td>
                            <span class="status-pill <?= $p['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                <?= $p['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($p['last_status']): ?>
                                <span class="status-pill <?= $p['last_status'] === 'success' ? 'status-success' : 'status-failed' ?>">
                                    <?= ucfirst($p['last_status']) ?> (<?= $p['last_count'] ?>)
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="index.php?toggle_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                    <?= $p['is_active'] ? 'Disable' : 'Enable' ?>
                                </a>
                                <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <a href="logs.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-info">Logs</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
