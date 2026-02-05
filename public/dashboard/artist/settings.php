<?php
/**
 * Artist Dashboard - Settings
 * (Bible Ch. 7 - Account preferences, security, and notification settings)
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('artist');

$user = dashboard_get_user();
$entity = dashboard_get_entity('artist');
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
        <p class="page-subtitle">Manage your account preferences</p>
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
                <label class="form-label">Username / Slug</label>
                <input type="text" class="form-input" value="<?= htmlspecialchars($user['Slug'] ?? '') ?>" disabled>
                <p style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">Your public profile URL: nextgennoise.com/artists/<?= htmlspecialchars($user['Slug'] ?? '') ?></p>
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
                        <div style="font-size: 13px; color: var(--text-muted);">Get notified when your chart position changes</div>
                    </div>
                    <input type="checkbox" checked style="width: 20px; height: 20px;">
                </label>
                
                <label style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--bg-primary); border-radius: 8px; cursor: pointer;">
                    <div>
                        <div style="font-weight: 500;">Radio Spin Alerts</div>
                        <div style="font-size: 13px; color: var(--text-muted);">Get notified when your music is played on radio</div>
                    </div>
                    <input type="checkbox" checked style="width: 20px; height: 20px;">
                </label>
                
                <label style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--bg-primary); border-radius: 8px; cursor: pointer;">
                    <div>
                        <div style="font-weight: 500;">AI Coach Tips</div>
                        <div style="font-size: 13px; color: var(--text-muted);">Receive personalized growth recommendations</div>
                    </div>
                    <input type="checkbox" checked style="width: 20px; height: 20px;">
                </label>
                
                <label style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--bg-primary); border-radius: 8px; cursor: pointer;">
                    <div>
                        <div style="font-weight: 500;">Marketing Emails</div>
                        <div style="font-size: 13px; color: var(--text-muted);">News, features, and promotional content</div>
                    </div>
                    <input type="checkbox" style="width: 20px; height: 20px;">
                </label>
            </div>
        </div>
        
        <!-- Privacy -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Privacy</h2>
            </div>
            
            <div style="display: grid; gap: 16px;">
                <label style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--bg-primary); border-radius: 8px; cursor: pointer;">
                    <div>
                        <div style="font-weight: 500;">Public Profile</div>
                        <div style="font-size: 13px; color: var(--text-muted);">Allow anyone to view your artist profile</div>
                    </div>
                    <input type="checkbox" checked style="width: 20px; height: 20px;">
                </label>
                
                <label style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--bg-primary); border-radius: 8px; cursor: pointer;">
                    <div>
                        <div style="font-weight: 500;">Show on Charts</div>
                        <div style="font-size: 13px; color: var(--text-muted);">Include your profile in public rankings</div>
                    </div>
                    <input type="checkbox" checked style="width: 20px; height: 20px;">
                </label>
                
                <label style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--bg-primary); border-radius: 8px; cursor: pointer;">
                    <div>
                        <div style="font-weight: 500;">Share Analytics with Label</div>
                        <div style="font-size: 13px; color: var(--text-muted);">Allow your label to view your analytics</div>
                    </div>
                    <input type="checkbox" style="width: 20px; height: 20px;">
                </label>
            </div>
        </div>
        
        <!-- Danger Zone -->
        <div class="card" style="border-color: var(--danger);">
            <div class="card-header">
                <h2 class="card-title" style="color: var(--danger);"><i class="bi bi-exclamation-triangle"></i> Danger Zone</h2>
            </div>
            
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px; background: rgba(239, 68, 68, 0.1); border-radius: 8px;">
                <div>
                    <div style="font-weight: 500;">Delete Account</div>
                    <div style="font-size: 13px; color: var(--text-muted);">Permanently delete your account and all data</div>
                </div>
                <button class="btn btn-danger" onclick="alert('Please contact support to delete your account.')">
                    Delete Account
                </button>
            </div>
        </div>
    </div>
</div>

</body>
</html>

