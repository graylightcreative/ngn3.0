<?php
/**
 * Station Dashboard - Social Connections
 * OAuth integration for Facebook, Instagram, TikTok, YouTube
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('station');

$user = dashboard_get_user();
$entity = dashboard_get_entity('station');
$pageTitle = 'Connections';
$currentPage = 'connections';

$success = $error = null;
$connectedPlatforms = [];

// Get connected platforms
if ($entity) {
    try {
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("SELECT * FROM oauth_tokens WHERE entity_type = 'station' AND entity_id = ?");
        $stmt->execute([$entity['id']]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $connectedPlatforms[$row['provider']] = $row;
        }
    } catch (PDOException $e) {
        // Table may not exist
    }
}

// Allowed OAuth providers - whitelist for security
$ALLOWED_PROVIDERS = ['facebook', 'instagram', 'tiktok', 'youtube'];

// Handle disconnect
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disconnect'])) {
    $provider = strtolower(trim($_POST['disconnect']));

    // Validate provider against whitelist (security: prevent injection/unexpected providers)
    if (!in_array($provider, $ALLOWED_PROVIDERS, true)) {
        $error = 'Invalid provider specified.';
    } elseif ($entity && dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        try {
            $pdo = dashboard_pdo();
            $stmt = $pdo->prepare("DELETE FROM oauth_tokens WHERE entity_type = 'station' AND entity_id = ? AND provider = ?");
            $stmt->execute([$entity['id'], $provider]);
            unset($connectedPlatforms[$provider]);
            $success = ucfirst($provider) . ' disconnected.';
            // Clear any caches related to this provider
            dashboard_clear_cache("station_oauth_{$entity['id']}_{$provider}");
        } catch (PDOException $e) {
            error_log('Station disconnect error: ' . $e->getMessage());
            $error = 'Could not disconnect. Please try again.';
        }
    }
}

$csrf = dashboard_csrf_token();

// Platform configurations for stations
$platforms = [
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
    ],
    'youtube' => [
        'name' => 'YouTube',
        'icon' => 'bi-youtube',
        'color' => '#ff0000',
        'description' => 'Connect your YouTube channel for subscriber and view analytics.',
        'permissions' => ['Read channel analytics', 'View video statistics', 'Access subscriber count'],
    ],
];

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Connections</h1>
        <p class="page-subtitle">Connect your social accounts to boost your station's NGN Score</p>
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
            Set up your station profile first to connect social accounts.
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
                        <div style="font-size: 13px; color: var(--text-secondary);">Social metrics directly impact your station ranking</div>
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
                    <i class="bi bi-broadcast" style="font-size: 24px; color: #a855f7;"></i>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">Reach More Listeners</div>
                        <div style="font-size: 13px; color: var(--text-secondary);">Get discovered by artists and fans</div>
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
                    <div style="width: 56px; height: 56px; border-radius: 12px; background: <?= $platform['color'] ?>20; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="<?= $platform['icon'] ?>" style="font-size: 28px; color: <?= $platform['color'] ?>;"></i>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                            <h3 style="font-size: 18px; font-weight: 600;"><?= $platform['name'] ?></h3>
                            <?php // All status indicators are now consistently rendered here, to be updated by JS ?>
                            <?php if ($key === 'facebook'): ?>
                            <div id="facebook-connection-status" class="connection-indicator" style="border-radius: 4px; padding: 2px 8px; font-size: 11px; font-weight: 600;"></div>
                            <?php elseif ($key === 'tiktok'): ?>
                            <div id="tiktok-connection-status" class="connection-indicator" style="border-radius: 4px; padding: 2px 8px; font-size: 11px; font-weight: 600;"></div>
                            <?php elseif ($key === 'youtube'): ?>
                            <div id="youtube-connection-status" class="connection-indicator" style="border-radius: 4px; padding: 2px 8px; font-size: 11px; font-weight: 600;"></div>
                            <?php else: // For Instagram and other future platforms, use PHP for initial status ?>
                            <span class="connection-indicator" style="background: <?= $isConnected ? 'var(--success)' : '#ef4444' ?>; color: <?= $isConnected ? '#000' : '#fff' ?>; padding: 2px 8px; border-radius: 4px; font-weight: 600;"><?= $isConnected ? 'CONNECTED' : 'Disconnected' ?></span>
                            <?php endif; ?>
                        </div>
                        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;"><?= $platform['description'] ?></p>
                        <?php if ($isConnected && $connection): ?>
                        <div style="font-size: 12px; color: var(--text-muted);">
                            Connected <?= date('M j, Y', strtotime($connection['created_at'])) ?>
                        </div>
                        <?php else: ?>
                        <div style="font-size: 12px; color: var(--text-muted);">
                            <strong>Permissions:</strong> <?= implode(' · ', $platform['permissions']) ?>
                        </div>
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
                        <?php elseif (!$isComingSoon && $key === 'facebook'): // Facebook button is now dynamic via JS ?>
                            <div id="connectFacebookBtn" class="btn btn-primary" style="background: <?= $platform['color'] ?>;"></div>
                        <?php elseif (!$isComingSoon && $key === 'youtube'): ?>
                            <div id="connectYoutubeBtn" class="btn btn-primary" style="background: <?= $platform['color'] ?>;"></div>
                        <?php elseif (!$isComingSoon && $key === 'tiktok'): ?>
                            <div id="connectTiktokBtn" class="btn btn-primary" style="background: <?= $platform['color'] ?>;"></div>
                        <?php else: // Coming soon ?>
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
                    <p style="font-size: 13px; color: var(--text-secondary); margin: 0;">
                        We only request read-only access to your public analytics. We never post on your behalf or share your data with third parties.
                    </p>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
</div>

<script>

document.addEventListener('DOMContentLoaded', function() {

    // --- Facebook Logic ---

    const connectFacebookBtn = document.getElementById('connectFacebookBtn');

    const facebookConnectionStatusDiv = document.getElementById('facebook-connection-status');

    let facebookConnected = localStorage.getItem('facebookConnected') === 'true';



    function updateFacebookUI() {

        if (facebookConnectionStatusDiv) {

            if (facebookConnected) {

                facebookConnectionStatusDiv.style.backgroundColor = 'var(--success)';

                facebookConnectionStatusDiv.textContent = 'CONNECTED';

                facebookConnectionStatusDiv.style.color = '#000';

                if (connectFacebookBtn) {

                    connectFacebookBtn.innerHTML = '<i class="bi bi-x-circle"></i> Disconnect';

                    connectFacebookBtn.style.backgroundColor = 'var(--danger)';

                    connectFacebookBtn.removeEventListener('click', connectFacebook);

                    connectFacebookBtn.addEventListener('click', disconnectFacebook);

                    connectFacebookBtn.style.cursor = 'pointer';

                }

            } else {

                facebookConnectionStatusDiv.style.backgroundColor = '#ef4444';

                facebookConnectionStatusDiv.textContent = 'Disconnected';

                facebookConnectionStatusDiv.style.color = '#fff';

                if (connectFacebookBtn) {

                    connectFacebookBtn.innerHTML = '<i class="bi bi-link-45deg"></i> Connect (WIP)';

                    connectFacebookBtn.style.backgroundColor = '<?= $platforms['facebook']['color'] ?>';

                    connectFacebookBtn.removeEventListener('click', disconnectFacebook);

                    connectFacebookBtn.addEventListener('click', connectFacebook);

                    connectFacebookBtn.style.cursor = 'pointer';

                }

            }

        }

    }



    async function connectFacebook() {

        if (connectFacebookBtn) {

            connectFacebookBtn.disabled = true;

            connectFacebookBtn.textContent = 'Connecting...';

        }



        const oauthWindow = window.open('about:blank', '_blank', 'width=600,height=400');

        if (oauthWindow) {

            oauthWindow.document.write('<p>Simulating Facebook OAuth...</p><p>Please wait...</p>');

            await new Promise(resolve => setTimeout(resolve, 2000));

            oauthWindow.close();

        }



        localStorage.setItem('facebookConnected', 'true');

        facebookConnected = true;

        updateFacebookUI();

        if (connectFacebookBtn) {

            connectFacebookBtn.disabled = false;

        }

        alert('Successfully connected to Facebook (simulated)!');

    }



    function disconnectFacebook() {

        localStorage.removeItem('facebookConnected');

        facebookConnected = false;

        updateFacebookUI();

        alert('Disconnected from Facebook (simulated).');

    }



    // --- YouTube Logic ---

    const connectYoutubeBtn = document.getElementById('connectYoutubeBtn');

    const youtubeConnectionStatusDiv = document.getElementById('youtube-connection-status');

    let youtubeConnected = localStorage.getItem('youtubeConnected') === 'true';



    function updateYoutubeUI() {

        if (youtubeConnectionStatusDiv) {

            if (youtubeConnected) {

                youtubeConnectionStatusDiv.style.backgroundColor = 'var(--success)';

                youtubeConnectionStatusDiv.textContent = 'CONNECTED';

                youtubeConnectionStatusDiv.style.color = '#000';

                if (connectYoutubeBtn) {

                    connectYoutubeBtn.innerHTML = '<i class="bi bi-x-circle"></i> Disconnect';

                    connectYoutubeBtn.style.backgroundColor = 'var(--danger)';

                    connectYoutubeBtn.removeEventListener('click', connectYoutube);

                    connectYoutubeBtn.addEventListener('click', disconnectYoutube);

                    connectYoutubeBtn.style.cursor = 'pointer';

                }

            } else {

                youtubeConnectionStatusDiv.style.backgroundColor = '#ef4444';

                youtubeConnectionStatusDiv.textContent = 'Disconnected';

                youtubeConnectionStatusDiv.style.color = '#fff';

                if (connectYoutubeBtn) {

                    connectYoutubeBtn.innerHTML = '<i class="bi bi-link-45deg"></i> Connect (WIP)';

                    connectYoutubeBtn.style.backgroundColor = '<?= $platforms['youtube']['color'] ?>';

                    connectYoutubeBtn.removeEventListener('click', disconnectYoutube);

                    connectYoutubeBtn.addEventListener('click', connectYoutube);

                    connectYoutubeBtn.style.cursor = 'pointer';

                }

            }

        }

    }



    async function connectYoutube() {

        if (connectYoutubeBtn) {

            connectYoutubeBtn.disabled = true;

            connectYoutubeBtn.textContent = 'Connecting...';

        }



        const oauthWindow = window.open('about:blank', '_blank', 'width=600,height=400');

        if (oauthWindow) {

            oauthWindow.document.write('<p>Simulating YouTube OAuth...</p><p>Please wait...</p>');

            await new Promise(resolve => setTimeout(resolve, 2000));

            oauthWindow.close();

        }



        localStorage.setItem('youtubeConnected', 'true');

        youtubeConnected = true;

        updateYoutubeUI();

        if (connectYoutubeBtn) {

            connectYoutubeBtn.disabled = false;

        }

        alert('Successfully connected to YouTube (simulated)!');

    }



    function disconnectYoutube() {

        localStorage.removeItem('youtubeConnected');

        youtubeConnected = false;

        updateYoutubeUI();

        alert('Disconnected from YouTube (simulated).');

    }



    // --- TikTok Logic ---

    const connectTiktokBtn = document.getElementById('connectTiktokBtn');

    const tiktokConnectionStatusDiv = document.getElementById('tiktok-connection-status');

    let tiktokConnected = localStorage.getItem('tiktokConnected') === 'true';



    function updateTiktokUI() {

        if (tiktokConnectionStatusDiv) {

            if (tiktokConnected) {

                tiktokConnectionStatusDiv.style.backgroundColor = 'var(--success)';

                tiktokConnectionStatusDiv.textContent = 'CONNECTED';

                tiktokConnectionStatusDiv.style.color = '#000';

                if (connectTiktokBtn) {

                    connectTiktokBtn.innerHTML = '<i class="bi bi-x-circle"></i> Disconnect';

                    connectTiktokBtn.style.backgroundColor = 'var(--danger)';

                    connectTiktokBtn.removeEventListener('click', connectTiktok);

                    connectTiktokBtn.addEventListener('click', disconnectTiktok);

                    connectTiktokBtn.style.cursor = 'pointer';

                }

            } else {

                tiktokConnectionStatusDiv.style.backgroundColor = '#ef4444';

                tiktokConnectionStatusDiv.textContent = 'Disconnected';

                tiktokConnectionStatusDiv.style.color = '#fff';

                if (connectTiktokBtn) {

                    connectTiktokBtn.innerHTML = '<i class="bi bi-link-45deg"></i> Connect (WIP)';

                    connectTiktokBtn.style.backgroundColor = '<?= $platforms['tiktok']['color'] ?>';

                    connectTiktokBtn.removeEventListener('click', disconnectTiktok);

                    connectTiktokBtn.addEventListener('click', connectTiktok);

                    connectTiktokBtn.style.cursor = 'pointer';

                }

            }

        }

    }



    async function connectTiktok() {

        if (connectTiktokBtn) {

            connectTiktokBtn.disabled = true;

            connectTiktokBtn.textContent = 'Connecting...';

        }



        const oauthWindow = window.open('about:blank', '_blank', 'width=600,height=400');

        if (oauthWindow) {

            oauthWindow.document.write('<p>Simulating TikTok OAuth...</p><p>Please wait...</p>');

            await new Promise(resolve => setTimeout(resolve, 2000));

            oauthWindow.close();

        }



        localStorage.setItem('tiktokConnected', 'true');

        tiktokConnected = true;

        updateTiktokUI();

        if (connectTiktokBtn) {

            connectTiktokBtn.disabled = false;

        }

        alert('Successfully connected to TikTok (simulated)!');

    }



    function disconnectTiktok() {

        localStorage.removeItem('tiktokConnected');

        tiktokConnected = false;

        updateTiktokUI();

        alert('Disconnected from TikTok (simulated).');

    }





    // --- Initial Setup ---

    updateFacebookUI(); 

    if (connectFacebookBtn && !facebookConnected) {

        connectFacebookBtn.addEventListener('click', connectFacebook);

    }

    

    updateYoutubeUI();

    if (connectYoutubeBtn && !youtubeConnected) {

        connectYoutubeBtn.addEventListener('click', connectYoutube);

    }



    updateTiktokUI();

    if (connectTiktokBtn && !tiktokConnected) {

        connectTiktokBtn.addEventListener('click', connectTiktok);

    }

});

</script></body>
</html>

