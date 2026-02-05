<?php
/**
 * Artist Dashboard - Social Connections
 * OAuth integration for Facebook, Instagram, TikTok, Spotify, YouTube
 * (Bible Ch. 7 - Connected platforms for profile verification and engagement)
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('artist');

$user = dashboard_get_user();
$entity = dashboard_get_entity('artist');
$pageTitle = 'Connections';
$currentPage = 'connections';

$success = $error = null;
$connectedPlatforms = [];

// Get connected platforms
if ($entity) {
    try {
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("SELECT * FROM oauth_tokens WHERE entity_type = 'artist' AND entity_id = ?");
        $stmt->execute([$entity['id']]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $connectedPlatforms[$row['provider']] = $row;
        }
    } catch (PDOException $e) {
        // Table may not exist
    }
}

// Allowed OAuth providers - whitelist for security
$ALLOWED_PROVIDERS = ['facebook', 'instagram', 'tiktok', 'spotify', 'youtube'];

// Handle disconnect
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disconnect'])) {
    $provider = strtolower(trim($_POST['disconnect']));

    // Validate provider against whitelist (security: prevent injection/unexpected providers)
    if (!in_array($provider, $ALLOWED_PROVIDERS, true)) {
        $error = 'Invalid provider specified.';
    } elseif ($entity && dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        try {
            $pdo = dashboard_pdo();
            $stmt = $pdo->prepare("DELETE FROM oauth_tokens WHERE entity_type = 'artist' AND entity_id = ? AND provider = ?");
            $stmt->execute([$entity['id'], $provider]);
            unset($connectedPlatforms[$provider]);
            $success = ucfirst($provider) . ' disconnected.';
            // Clear any caches related to this provider
            dashboard_clear_cache("artist_oauth_{$entity['id']}_{$provider}");
        } catch (PDOException $e) {
            error_log('Artist disconnect error: ' . $e->getMessage());
            $error = 'Could not disconnect. Please try again.';
        }
    }
}

$csrf = dashboard_csrf_token();

// Helper function to safely parse dates
function safeStrtotime($dateStr) {
    if (empty($dateStr)) {
        return false;
    }
    $timestamp = strtotime($dateStr);
    return ($timestamp !== false) ? $timestamp : null;
}

// Platform configurations
$platforms = [
    'spotify' => [
        'name' => 'Spotify for Artists',
        'icon' => 'bi-spotify',
        'color' => '#1DB954',
        'description' => 'Connect to pull streaming stats, monthly listeners, and popularity scores.',
        'permissions' => ['Read artist profile', 'View streaming statistics', 'Access follower count'],
    ],
    'facebook' => [
        'name' => 'Facebook',
        'icon' => 'bi-facebook',
        'color' => '#1877f2',
        'description' => 'Connect your Facebook Page to track reach, engagement, and post performance.',
        'permissions' => ['Read page insights', 'View post engagement', 'Access page followers'],
    ],
    'instagram' => [
        'name' => 'Instagram',
        'icon' => 'bi-instagram',
        'color' => '#e4405f',
        'description' => 'Connect your Instagram Business account for insights and engagement metrics.',
        'permissions' => ['Read profile insights', 'View post engagement', 'Access follower demographics'],
    ],
    'tiktok' => [
        'name' => 'TikTok',
        'icon' => 'bi-tiktok',
        'color' => '#000000',
        'description' => 'Connect TikTok to track video views, engagement, and follower growth.',
        'permissions' => ['Read video analytics', 'View engagement metrics', 'Access follower count'],
        'coming_soon' => true,
    ],
    'youtube' => [
        'name' => 'YouTube',
        'icon' => 'bi-youtube',
        'color' => '#ff0000',
        'description' => 'Connect your YouTube channel for subscriber and view analytics.',
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
        <p class="page-subtitle">Connect your social accounts to boost your NGN Score</p>
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
            Set up your profile first to connect social accounts.
            <a href="profile.php">Set up profile →</a>
        </div>
        <?php else: ?>
        
        <!-- Why Connect -->
        <div class="card" style="background: linear-gradient(135deg, rgba(29, 185, 84, 0.05) 0%, rgba(0, 212, 255, 0.05) 100%);">
            <h3 style="font-size: 16px; margin-bottom: 12px;">Why Connect Your Accounts?</h3>
            <div class="grid grid-3">
                <div style="display: flex; gap: 12px; align-items: flex-start;">
                    <i class="bi bi-graph-up-arrow" style="font-size: 24px; color: var(--brand);"></i>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">Boost Your Score</div>
                        <div style="font-size: 13px; color: var(--text-secondary);">Social metrics directly impact your NGN ranking</div>
                    </div>
                </div>
                <div style="display: flex; gap: 12px; align-items: flex-start;">
                    <i class="bi bi-bar-chart" style="font-size: 24px; color: var(--accent);"></i>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">Unified Analytics</div>
                        <div style="font-size: 13px; color: var(--text-secondary);">See all your stats in one dashboard</div>
                    </div>
                </div>
                <div style="display: flex; gap: 12px; align-items: flex-start;">
                    <i class="bi bi-robot" style="font-size: 24px; color: #a855f7;"></i>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">AI Insights</div>
                        <div style="font-size: 13px; color: var(--text-secondary);">Get personalized growth recommendations</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Platform Cards -->
        <div style="display: grid; gap: 16px;">
            <?php foreach ($platforms as $key => $platform): 
                $isConnected = !empty($connectedPlatforms[$key]);
                $isComingSoon = !empty($platform['coming_soon']);
                $connection = $connectedPlatforms[$key] ?? null;
            ?>
            <div class="card" style="<?= $isComingSoon ? 'opacity: 0.6;' : '' ?>">
                <div style="display: flex; align-items: flex-start; gap: 20px;">
                    <!-- Icon -->
                    <div style="width: 56px; height: 56px; border-radius: 12px; background: <?= $platform['color'] ?>20; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="<?= $platform['icon'] ?>" style="font-size: 28px; color: <?= $platform['color'] ?>;"></i>
                    </div>
                    
                    <!-- Info -->
                    <div style="flex: 1; min-width: 0;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                            <h3 style="font-size: 18px; font-weight: 600;"><?= $platform['name'] ?></h3>
                            <?php if ($isConnected): ?>
                            <span style="font-size: 11px; background: var(--success); color: #000; padding: 2px 8px; border-radius: 4px; font-weight: 600;">CONNECTED</span>
                            <?php elseif ($isComingSoon): ?>
                            <span style="font-size: 11px; background: var(--warning); color: #000; padding: 2px 8px; border-radius: 4px; font-weight: 600;">COMING SOON</span>
                            <?php endif; ?>
                        </div>
                        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">
                            <?= $platform['description'] ?>
                        </p>
                        
                        <?php if ($isConnected && $connection): ?>
                        <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">
                            <?php $createdTs = safeStrtotime($connection['created_at']); ?>
                            Connected <?= $createdTs ? date('M j, Y', $createdTs) : 'at unknown time' ?>
                            <?php if ($connection['expires_at']): ?>
                            <?php $expiresTs = safeStrtotime($connection['expires_at']); ?>
                            · Expires <?= $expiresTs ? date('M j, Y', $expiresTs) : 'at unknown time' ?>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div style="font-size: 12px; color: var(--text-muted);">
                            <strong>Permissions:</strong> <?= implode(' · ', $platform['permissions']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Action -->
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
                        <a href="oauth/<?= $key ?>.php" class="btn btn-primary" style="background: <?= $platform['color'] ?>;">
                            <i class="bi bi-link-45deg"></i> Connect
                        </a>
                        <?php else: ?>
                        <button class="btn btn-secondary" disabled>
                            <i class="bi bi-clock"></i> Coming Soon
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Data Privacy Note -->
        <div class="card" style="margin-top: 24px;">
            <div style="display: flex; gap: 16px; align-items: flex-start;">
                <i class="bi bi-shield-check" style="font-size: 24px; color: var(--brand);"></i>
                <div>
                    <h4 style="font-size: 14px; margin-bottom: 8px;">Your Data is Secure</h4>
                    <p style="font-size: 13px; color: var(--text-secondary); margin: 0;">
                        We only request read-only access to your public analytics. We never post on your behalf, 
                        access private messages, or share your data with third parties. You can disconnect at any time.
                    </p>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
</div>

</body>
</html>

