<?php
/**
 * Artist Dashboard - Fan Subscription Tiers Management
 * (Bible Ch. 7 - C.4 Subscriptions: Gold/Silver tiers for exclusive content)
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('artist');

$user = dashboard_get_user();
$entity = dashboard_get_entity('artist');
$pageTitle = 'Fan Tiers';
$currentPage = 'fans'; // Or a new one 'tiers'

$action = $_GET['action'] ?? 'list';
$tierId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$success = $error = null;
$tiers = [];
$editTier = null;

// Fetch tiers for this artist
if ($entity) {
    try {
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("SELECT * FROM fan_subscription_tiers WHERE artist_id = ? ORDER BY price_monthly ASC");
        $stmt->execute([$entity['id']]);
        $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($tierId && $action === 'edit') {
            $stmt = $pdo->prepare("SELECT * FROM fan_subscription_tiers WHERE id = ? AND artist_id = ?");
            $stmt->execute([$tierId, $entity['id']]);
            $editTier = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $entity) {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $post_action = $_POST['action'] ?? $action;

        if ($post_action === 'delete') {
            $tierId_to_delete = (int)($_POST['tier_id'] ?? 0);
            if ($tierId_to_delete > 0) {
                try {
                    $pdo = dashboard_pdo();
                    $stmt = $pdo->prepare("DELETE FROM fan_subscription_tiers WHERE id = ? AND artist_id = ?");
                    $stmt->execute([$tierId_to_delete, $entity['id']]);
                    if ($stmt->rowCount() > 0) {
                        $success = 'Tier deleted successfully.';
                    } else {
                        $error = 'Tier not found or you do not have permission to delete it.';
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            } else {
                $error = 'Invalid tier ID for deletion.';
            }
        } else {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price_monthly = (float)($_POST['price_monthly'] ?? 0);
            $price_yearly = (float)($_POST['price_yearly'] ?? 0);

            if (empty($name) || $price_monthly <= 0) {
                $error = 'Tier name and monthly price are required.';
            } else {
                try {
                    $pdo = dashboard_pdo();
                    if ($action === 'edit' && $editTier) {
                        $stmt = $pdo->prepare("UPDATE fan_subscription_tiers SET name = ?, description = ?, price_monthly = ?, price_yearly = ? WHERE id = ? AND artist_id = ?");
                        $stmt->execute([$name, $description, $price_monthly, $price_yearly, $editTier['id'], $entity['id']]);
                        $success = 'Tier updated!';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO fan_subscription_tiers (artist_id, name, description, price_monthly, price_yearly) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$entity['id'], $name, $description, $price_monthly, $price_yearly]);
                        $success = 'Tier created!';
                    }
                    $action = 'list';
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }

        // Refresh list
        $stmt = $pdo->prepare("SELECT * FROM fan_subscription_tiers WHERE artist_id = ? ORDER BY price_monthly ASC");
        $stmt->execute([$entity['id']]);
        $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$csrf = dashboard_csrf_token();
include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Fan Subscription Tiers</h1>
        <p class="page-subtitle">Create and manage tiers for your fans to subscribe to</p>
    </header>
    
    <div class="page-content">
        <?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
        
        <?php if ($action === 'add' || ($action === 'edit' && $editTier)): ?>
        
        <div class="card">
            <div class="card-header"><h2 class="card-title"><?= $action === 'edit' ? 'Edit Tier' : 'Create New Tier' ?></h2></div>
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-group">
                    <label class="form-label">Tier Name *</label>
                    <input type="text" name="name" class="form-input" required value="<?= htmlspecialchars($editTier['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3"><?= htmlspecialchars($editTier['description'] ?? '') ?></textarea>
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Monthly Price ($) *</label>
                        <input type="number" name="price_monthly" class="form-input" step="0.01" min="0.01" required value="<?= htmlspecialchars($editTier['price_monthly'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Yearly Price ($)</label>
                        <input type="number" name="price_yearly" class="form-input" step="0.01" min="0" value="<?= htmlspecialchars($editTier['price_yearly'] ?? '') ?>">
                    </div>
                </div>
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <a href="tiers.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> <?= $action === 'edit' ? 'Update Tier' : 'Create Tier' ?></button>
                </div>
            </form>
        </div>
        
        <?php else: ?>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Your Tiers (<?= count($tiers) ?>)</h2>
                <a href="tiers.php?action=add" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Tier</a>
            </div>
            <?php if (empty($tiers)): ?>
            <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                <i class="bi bi-award" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                <p>No tiers created yet.</p>
                <a href="tiers.php?action=add" class="btn btn-primary" style="margin-top: 16px;"><i class="bi bi-plus-lg"></i> Create First Tier</a>
            </div>
            <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($tiers as $tier): ?>
                <div style="display: flex; align-items: center; gap: 16px; padding: 16px; background: var(--bg-primary); border-radius: 8px;">
                    <div style="flex: 1;">
                        <div style="font-weight: 600;"><?= htmlspecialchars($tier['name']) ?></div>
                        <div style="font-size: 13px; color: var(--text-muted);">$<?= htmlspecialchars($tier['price_monthly']) ?>/month</div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <a href="tiers.php?action=edit&id=<?= $tier['id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;"><i class="bi bi-pencil"></i> Edit</a>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this tier?');">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="tier_id" value="<?= $tier['id'] ?>">
                            <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
