<?php
/**
 * Venue Dashboard - Social Connections
 * OAuth integration for Facebook, Instagram, TikTok, YouTube
 */
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('venue');

$user = dashboard_get_user();
$entity = dashboard_get_entity('venue');
$pageTitle = 'Connections';
$currentPage = 'connections';

$success = $error = null;
$connectedPlatforms = [];

// Allowed OAuth providers - whitelist for security
$ALLOWED_PROVIDERS = ['facebook', 'instagram', 'tiktok', 'youtube'];

// Get connected platforms
if ($entity) {
    try {
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("SELECT * FROM oauth_tokens WHERE entity_type = 'venue' AND entity_id = ?");
        $stmt->execute([$entity['id']]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $connectedPlatforms[$row['provider']] = $row;
        }
    } catch (PDOException $e) {
        error_log('Venue connections fetch error: ' . $e->getMessage());
        // Table may not exist - continue with empty connections
    }
}

// Handle disconnect
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disconnect'])) {
    $provider = strtolower(trim($_POST['disconnect']));

    // Validate provider against whitelist (security: prevent injection)
    if (!in_array($provider, $ALLOWED_PROVIDERS, true)) {
        $error = 'Invalid provider specified.';
    } elseif ($entity && dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        try {
            $pdo = dashboard_pdo();
            $stmt = $pdo->prepare("DELETE FROM oauth_tokens WHERE entity_type = 'venue' AND entity_id = ? AND provider = ?");
            $stmt->execute([$entity['id'], $provider]);
            unset($connectedPlatforms[$provider]);
            $success = ucfirst($provider) . ' disconnected.';
            // Clear any caches related to this provider
            dashboard_clear_cache("venue_oauth_{$entity['id']}_{$provider}");
        } catch (PDOException $e) {
            error_log('Venue disconnect error: ' . $e->getMessage());
            $error = 'Could not disconnect. Please try again.';
        }
    }
}

$csrf = dashboard_csrf_token();

// Platform configurations for venues
$platforms = [
    'facebook' => [
        'name' => 'Facebook',
        'icon' => 'bi-facebook',
        'color' => '#1877f2',
        'description' => 'Connect your Facebook Page to promote shows and track event engagement.',
        'permissions' => ['Read page insights', 'View event engagement', 'Access page followers'],
    ],
    'instagram' => [
        'name' => 'Instagram',
        'icon' => 'bi-instagram',
        'color' => '#e4405f',
        'description' => 'Connect your Instagram Business account for venue photos and engagement metrics.',
        'permissions' => ['Read profile insights', 'View post engagement', 'Access follower demographics'],
    ],
    'tiktok' => [
        'name' => 'TikTok',
        'icon' => 'bi-tiktok',
        'color' => '#000000',
        'description' => 'Connect TikTok to share venue highlights and track video engagement.',
        'permissions' => ['Read video analytics', 'View engagement metrics', 'Access follower count'],
        'coming_soon' => true,
    ],
    'youtube' => [
        'name' => 'YouTube',
        'icon' => 'bi-youtube',
        'color' => '#ff0000',
        'description' => 'Connect your YouTube channel for live show recordings and venue content.',
        'permissions' => ['Read channel analytics', 'View video statistics', 'Access subscriber count'],
        'coming_soon' => true,
    ],
];

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Connections</h1>
        <p class="page-subtitle">Connect your social accounts to boost your venue's NGN Score</p>
    </header>
    
    <div class="page-content">
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (!$entity): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            Set up your venue profile first to connect social accounts.
            <a href="profile.php">Set up profile →</a>
        </div>
        <?php else: ?>
        
        <div class="card" style="background: linear-gradient(135deg, rgba(29, 185, 84, 0.05) 0%, rgba(0, 212, 255, 0.05) 100%);">
            <h3 style="font-size: 16px; margin-bottom: 12px;">Why Connect Your Accounts?</h3>
            <div class="grid grid-3">
                <div style="display: flex; gap: 12px; align-items: flex-start;">
                    <i class="bi bi-graph-up-arrow" style="font-size: 24px; color: var(--brand);"></i>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">Boost Your Score</div>
                        <div style="font-size: 13px; color: var(--text-secondary);">Social metrics directly impact your venue ranking</div>
                    </div>
                </div>
                <div style="display: flex; gap: 12px; align-items: flex-start;">
                    <i class="bi bi-calendar-event" style="font-size: 24px; color: var(--accent);"></i>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">Promote Shows</div>
                        <div style="font-size: 13px; color: var(--text-secondary);">Get your events in front of more fans</div>
                    </div>
                </div>
                <div style="display: flex; gap: 12px; align-items: flex-start;">
                    <i class="bi bi-people" style="font-size: 24px; color: #a855f7;"></i>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">Attract Artists</div>
                        <div style="font-size: 13px; color: var(--text-secondary);">Show artists your venue's reach</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div style="display: grid; gap: 16px;">
            <?php foreach ($platforms as $key => $platform): 
                $isConnected = !empty($connectedPlatforms[$key]);
                $isComingSoon = !empty($platform['coming_soon']);
                $connection = $connectedPlatforms[$key] ?? null;
            ?>
            <div class="card" style="<?= $isComingSoon ? 'opacity: 0.6;' : '' ?>">
                <div style="display: flex; align-items: flex-start; gap: 20px;">
                    <div style="width: 56px; height: 56px; border-radius: 12px; background: <?= $platform['color'] ?>20; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="<?= $platform['icon'] ?>" style="font-size: 28px; color: <?= $platform['color'] ?>;"></i>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                            <h3 style="font-size: 18px; font-weight: 600;"><?= $platform['name'] ?></h3>
                            <?php if ($isConnected): ?>
                            <span style="font-size: 11px; background: var(--success); color: #000; padding: 2px 8px; border-radius: 4px; font-weight: 600;">CONNECTED</span>
                            <?php elseif ($isComingSoon): ?>
                            <span style="font-size: 11px; background: var(--warning); color: #000; padding: 2px 8px; border-radius: 4px; font-weight: 600;">COMING SOON</span>
                            <?php endif; ?>
                        </div>
                        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;"><?= $platform['description'] ?></p>
                        <?php if ($isConnected && $connection): ?>
                        <div style="font-size: 12px; color: var(--text-muted);">Connected <?= date('M j, Y', strtotime($connection['created_at'])) ?></div>
                        <?php else: ?>
                        <div style="font-size: 12px; color: var(--text-muted);"><strong>Permissions:</strong> <?= implode(' · ', $platform['permissions']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div style="flex-shrink: 0;">
                        <?php if ($isConnected): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="disconnect" value="<?= $key ?>">
                            <button type="submit" class="btn btn-secondary" style="color: var(--danger);" onclick="return confirm('Disconnect <?= $platform['name'] ?>?')">
                                <i class="bi bi-x-circle"></i> Disconnect
                            </button>
                        </form>
                        <?php elseif (!$isComingSoon): ?>
                        <a href="oauth/<?= $key ?>.php" class="btn btn-primary" style="background: <?= $platform['color'] ?>;"><i class="bi bi-link-45deg"></i> Connect</a>
                        <?php else: ?>
                        <button class="btn btn-secondary" disabled><i class="bi bi-clock"></i> Coming Soon</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="card" style="margin-top: 24px;">
            <div style="display: flex; gap: 16px; align-items: flex-start;">
                <i class="bi bi-shield-check" style="font-size: 24px; color: var(--brand);"></i>
                <div>
                    <h4 style="font-size: 14px; margin-bottom: 8px;">Your Data is Secure</h4>
                    <p style="font-size: 13px; color: var(--text-secondary); margin: 0;">We only request read-only access to your public analytics. We never post on your behalf or share your data.</p>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
</div>

</body>
</html>

