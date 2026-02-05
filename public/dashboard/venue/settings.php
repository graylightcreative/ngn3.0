<?php
/**
 * Venue Dashboard - Settings
 * Account preferences and security (per Bible Ch. 7.3 QR Code System & V.2)
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('venue');

$user = dashboard_get_user();
$entity = dashboard_get_entity('venue');
$pageTitle = 'Settings';
$currentPage = 'settings';

$baseurl = getenv('BASEURL') ?: '/';

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">Manage your account and preferences</p>
    </header>

    <div class="page-content">
        <!-- Account Information -->
        <div class="card mb-8">
            <div class="card-header">
                <h2 class="card-title">Account Information</h2>
            </div>

            <div class="card-content space-y-4">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-input" value="<?php echo htmlspecialchars($user['Email'] ?? ''); ?>" disabled>
                    <p class="text-sm text-muted mt-2">Contact support to change your email address</p>
                </div>

                <div class="form-group">
                    <label class="form-label">Username / Slug</label>
                    <input type="text" class="form-input" value="<?php echo htmlspecialchars($user['Slug'] ?? ''); ?>" disabled>
                    <p class="text-sm text-muted mt-2">Your public venue URL: nextgennoise.com/venues/<?php echo htmlspecialchars($user['Slug'] ?? ''); ?></p>
                </div>

                <div class="form-group">
                    <label class="form-label">Member Since</label>
                    <input type="text" class="form-input" value="<?php echo isset($user['Created']) ? date('F j, Y', strtotime($user['Created'])) : 'Unknown'; ?>" disabled>
                </div>
            </div>
        </div>

        <!-- Security -->
        <div class="card mb-8">
            <div class="card-header">
                <h2 class="card-title">Security</h2>
            </div>

            <div class="card-content space-y-4">
                <div>
                    <h3 class="font-medium mb-2">Password</h3>
                    <p class="text-sm text-muted mb-4">Change your password to keep your account secure.</p>
                    <a href="<?php echo $baseurl; ?>account/change-password" class="btn btn-secondary btn-sm">Change Password</a>
                </div>

                <div class="divider my-6"></div>

                <div>
                    <h3 class="font-medium mb-2">Sessions</h3>
                    <p class="text-sm text-muted mb-4">Sign out from all other devices and sessions.</p>
                    <a href="<?php echo $baseurl; ?>account/sessions" class="btn btn-secondary btn-sm">Manage Sessions</a>
                </div>
            </div>
        </div>

        <!-- QR Code Settings (Bible Ch. 7.3 & V.2) -->
        <div class="card mb-8">
            <div class="card-header">
                <h2 class="card-title">QR Code Settings</h2>
            </div>

            <div class="card-content space-y-4">
                <p class="text-sm text-muted mb-4">Generate and manage QR codes for your venue and events. Perfect for printing at your door or in promotional materials.</p>

                <div>
                    <h3 class="font-medium mb-2">Venue QR Code</h3>
                    <p class="text-sm text-muted mb-4">Direct link to your venue profile and current shows</p>
                    <a href="<?php echo $baseurl; ?>dashboard/venue/qr-codes.php" class="btn btn-secondary btn-sm">
                        <i class="bi bi-qr-code"></i> Manage QR Codes
                    </a>
                </div>
            </div>
        </div>

        <!-- Email Preferences -->
        <div class="card mb-8">
            <div class="card-header">
                <h2 class="card-title">Email Preferences</h2>
            </div>

            <div class="card-content space-y-4">
                <div class="checkbox-group">
                    <label class="checkbox-item">
                        <input type="checkbox" checked disabled>
                        <span class="checkbox-label">
                            <strong>Show Updates</strong>
                            <p class="text-sm text-muted">Get notified when shows are viewed or tickets sell</p>
                        </span>
                    </label>
                </div>

                <div class="checkbox-group">
                    <label class="checkbox-item">
                        <input type="checkbox" checked disabled>
                        <span class="checkbox-label">
                            <strong>Artist Inquiries</strong>
                            <p class="text-sm text-muted">Receive messages from artists interested in performing</p>
                        </span>
                    </label>
                </div>

                <div class="checkbox-group">
                    <label class="checkbox-item">
                        <input type="checkbox" checked disabled>
                        <span class="checkbox-label">
                            <strong>Community News</strong>
                            <p class="text-sm text-muted">Updates about NGN features and opportunities</p>
                        </span>
                    </label>
                </div>

                <div class="checkbox-group">
                    <label class="checkbox-item">
                        <input type="checkbox" disabled>
                        <span class="checkbox-label">
                            <strong>Marketing Emails</strong>
                            <p class="text-sm text-muted">Promotional offers and special announcements</p>
                        </span>
                    </label>
                </div>

                <p class="text-sm text-muted mt-4">Email preferences management coming soon</p>
            </div>
        </div>

        <!-- Support & Resources -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Support & Resources</h2>
            </div>

            <div class="card-content space-y-3">
                <a href="https://help.nextgennoise.com" target="_blank" class="flex items-center justify-between p-3 hover:bg-gray-800 rounded">
                    <span>Help Center</span>
                    <i class="bi bi-arrow-up-right"></i>
                </a>
                <a href="https://help.nextgennoise.com/venues" target="_blank" class="flex items-center justify-between p-3 hover:bg-gray-800 rounded">
                    <span>Venue Guide</span>
                    <i class="bi bi-arrow-up-right"></i>
                </a>
                <a href="https://help.nextgennoise.com/events" target="_blank" class="flex items-center justify-between p-3 hover:bg-gray-800 rounded">
                    <span>Event Management Guide</span>
                    <i class="bi bi-arrow-up-right"></i>
                </a>
                <a href="mailto:support@nextgennoise.com" class="flex items-center justify-between p-3 hover:bg-gray-800 rounded">
                    <span>Contact Support</span>
                    <i class="bi bi-arrow-up-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>
