<?php
/**
 * Label Dashboard - Settings
 * Manage label account preferences and settings
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('label');

$user = dashboard_get_user();
$entity = dashboard_get_entity('label');
$pageTitle = 'Settings';
$currentPage = 'settings';

$success = $error = null;
$csrf = dashboard_csrf_token();

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">Manage your label account preferences</p>
    </header>

    <div class="page-content">
        <!-- Account Settings -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Account</h2>
            </div>

            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-input" value="<?= htmlspecialchars($user['Email'] ?? '') ?>" disabled>
                <p style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">Contact support to change your email</p>
            </div>

            <div class="form-group">
                <label class="form-label">Label Slug</label>
                <input type="text" class="form-input" value="<?= htmlspecialchars($user['Slug'] ?? '') ?>" disabled>
                <p style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">Your public profile URL: nextgennoise.com/labels/<?= htmlspecialchars($user['Slug'] ?? '') ?></p>
            </div>
        </div>

        <!-- Change Password -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Change Password</h2>
            </div>

            <form method="POST" action="handlers/change-password.php">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-input" required>
                </div>

                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-input" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-input" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-key"></i> Update Password
                </button>
            </form>
        </div>

        <!-- Notifications -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Notifications</h2>
            </div>

            <div style="display: grid; gap: 16px;">
                <label style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--bg-primary); border-radius: 8px; cursor: pointer;">
                    <div>
                        <div style="font-weight: 500;">Weekly Ranking Updates</div>
                        <div style="font-size: 13px; color: var(--text-muted);">Get notified when your label chart position changes</div>
                    </div>
                    <input type="checkbox" checked style="width: 20px; height: 20px;">
                </label>

                <label style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--bg-primary); border-radius: 8px; cursor: pointer;">
                    <div>
                        <div style="font-weight: 500;">Release Notifications</div>
                        <div style="font-size: 13px; color: var(--text-muted);">Get notified when roster artists release new music</div>
                    </div>
                    <input type="checkbox" checked style="width: 20px; height: 20px;">
                </label>

                <label style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--bg-primary); border-radius: 8px; cursor: pointer;">
                    <div>
                        <div style="font-weight: 500;">Radio Spin Alerts</div>
                        <div style="font-size: 13px; color: var(--text-muted);">Get notified when your artists' music is played on radio</div>
                    </div>
                    <input type="checkbox" checked style="width: 20px; height: 20px;">
                </label>

                <label style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--bg-primary); border-radius: 8px; cursor: pointer;">
                    <div>
                        <div style="font-weight: 500;">Fan Engagement Updates</div>
                        <div style="font-size: 13px; color: var(--text-muted);">Get notified about fan activity and engagement</div>
                    </div>
                    <input type="checkbox" checked style="width: 20px; height: 20px;">
                </label>
            </div>
        </div>

        <!-- Label Information -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Label Information</h2>
            </div>

            <div class="form-group">
                <label class="form-label">Label Name</label>
                <input type="text" class="form-input" value="<?= htmlspecialchars($entity['name'] ?? $user['Title'] ?? '') ?>" disabled>
            </div>

            <div class="form-group">
                <label class="form-label">Account Created</label>
                <input type="text" class="form-input" value="<?= htmlspecialchars($user['CreatedAt'] ?? 'N/A') ?>" disabled>
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="card card-danger">
            <div class="card-header">
                <h2 class="card-title">Danger Zone</h2>
                <p style="font-size: 13px; color: var(--text-muted); margin: 0;">Irreversible actions</p>
            </div>

            <div style="display: flex; gap: 12px;">
                <button class="btn btn-secondary" onclick="alert('Deactivate account functionality coming soon')">
                    <i class="bi bi-exclamation-triangle"></i> Deactivate Account
                </button>

                <button class="btn btn-danger" onclick="alert('Delete account functionality coming soon')">
                    <i class="bi bi-trash"></i> Delete Account
                </button>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/lib/partials/footer.php'; ?>
