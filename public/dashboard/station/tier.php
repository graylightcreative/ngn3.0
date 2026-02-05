<?php
/**
 * Station Dashboard - Subscription Tier Management
 * Display current tier, features, limits, and upgrade options
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Stations\StationTierService;

dashboard_require_auth();
dashboard_require_entity_type('station');

$user = dashboard_get_user();
$entity = dashboard_get_entity('station');
$pageTitle = 'Subscription';
$currentPage = 'tier';

$config = new Config();
$tierService = new StationTierService($config);

$currentTier = null;
$allTiers = [];
$error = null;

// Get current tier
try {
    if ($entity && isset($entity['id'])) {
        $currentTier = $tierService->getStationTier($entity['id']);
        if (!$currentTier) {
            $error = 'Unable to load tier information.';
        }
    } else {
        $error = 'Station profile not found. Please set up your profile first.';
    }
} catch (\Throwable $e) {
    $error = 'Failed to load tier: ' . $e->getMessage();
}

// Get all available tiers
try {
    $allTiers = $tierService->getAvailableTiers();
} catch (\Throwable $e) {
    $error = 'Failed to load available tiers.';
}

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Subscription</h1>
        <p class="page-subtitle">Manage your station's subscription tier and features</p>
    </header>

    <div class="page-content">
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Current Tier Display -->
        <?php if ($currentTier): ?>
        <div class="card" style="margin-bottom: 2rem; border: 2px solid var(--brand);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                <h2 class="text-xl" style="margin: 0;">Current Tier</h2>
                <span class="badge badge-primary"><?= htmlspecialchars($currentTier['name']) ?></span>
            </div>
            <p style="margin: 0; color: var(--text-muted);"><?= htmlspecialchars($currentTier['description'] ?? '') ?></p>
        </div>
        <?php endif; ?>

        <!-- Tier Comparison -->
        <?php if (!dashboard_is_test_account()): ?>
        <div style="margin-bottom: 2rem;">
            <h2 class="page-title" style="margin-top: 0;">Available Tiers</h2>
            <div id="tier-comparison-placeholder" style="/* Placeholder styles for tier comparison */ margin-bottom: 1.5rem; padding: 1rem; border: 1px dashed var(--accent); text-align: center; color: var(--text-muted);">
                <i class="bi bi-bar-chart-line" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                <span>Detailed Tier Comparison Table / Benefits Display Area</span>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
                <?php foreach ($allTiers as $tier): ?>
                <div class="card" style="<?= ($currentTier && $currentTier['id'] === $tier['id']) ? 'border: 2px solid var(--brand);' : '' ?>">
                    <h3 style="margin-top: 0;"><?= htmlspecialchars($tier['name']) ?></h3>
                    <p style="color: var(--text-muted); margin-bottom: 1rem;"><?= htmlspecialchars($tier['description'] ?? '') ?></p>

                    <!-- Pricing -->
                    <div style="padding: 1rem 0; border-bottom: 1px solid var(--border); margin-bottom: 1rem;">
                        <?php if ($tier['price_monthly_cents'] > 0): ?>
                            <p style="margin: 0; font-weight: bold;">$<?= ($tier['price_monthly_cents'] / 100) ?>/month</p>
                            <p style="margin: 0.25rem 0 0 0; font-size: 0.875rem; color: var(--text-muted);">or $<?= ($tier['price_annual_cents'] / 100) ?>/year</p>
                        <?php else: ?>
                            <p style="margin: 0; font-weight: bold; color: var(--success);">Free</p>
                        <?php endif; ?>
                    </div>

                    <!-- Features -->
                    <div style="margin-bottom: 1rem;">
                        <p style="font-size: 0.875rem; font-weight: bold; margin: 0 0 0.5rem 0; text-transform: uppercase; color: var(--text-muted);">Features</p>
                        <ul style="margin: 0; padding-left: 1.5rem; font-size: 0.875rem;">
                            <?php if (isset($tier['features']) && is_array($tier['features'])): ?>
                                <?php foreach ($tier['features'] as $feature => $enabled): ?>
                                    <?php if ($enabled): ?>
                                    <li style="color: var(--success);">
                                        <i class="bi bi-check-circle-fill"></i> <?= ucfirst(str_replace('_', ' ', $feature)) ?>
                                    </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <!-- Limits -->
                    <div style="margin-bottom: 1rem;">
                        <p style="font-size: 0.875rem; font-weight: bold; margin: 0 0 0.5rem 0; text-transform: uppercase; color: var(--text-muted);">Limits</p>
                        <ul style="margin: 0; padding-left: 1.5rem; font-size: 0.875rem; list-style: none;">
                            <?php if (isset($tier['limits']) && is_array($tier['limits'])): ?>
                                <?php foreach ($tier['limits'] as $limit => $value): ?>
                                <li style="margin-bottom: 0.25rem;">
                                    <strong><?= str_replace('_', ' ', $limit) ?>:</strong>
                                    <?= ($value === -1 ? 'Unlimited' : $value) ?>
                                </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <!-- Action -->
                    <?php if ($currentTier && $currentTier['id'] === $tier['id']): ?>
                    <button class="btn btn-secondary" disabled style="width: 100%; cursor: default;">Current Tier</button>
                    <?php elseif ($tier['price_monthly_cents'] > 0): ?>
                    <button class="btn btn-primary upgrade-btn" data-tier-id="<?= $tier['id'] ?>" style="width: 100%;">Upgrade</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Usage & Stats -->
        <?php if ($currentTier): ?>
        <div class="card">
            <h2 class="text-xl" style="margin-top: 0;">Current Usage</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <?php foreach (($currentTier['limits'] ?? []) as $limitName => $limitValue): ?>
                <div style="padding: 1rem; background: var(--bg-secondary); border-radius: 0.5rem;">
                    <p style="margin: 0 0 0.5rem 0; font-size: 0.875rem; color: var(--text-muted); text-transform: uppercase;">
                        <?= str_replace('_', ' ', $limitName) ?>
                    </p>
                    <p style="margin: 0; font-size: 1.5rem; font-weight: bold;">
                        <?= ($limitValue === -1 ? '∞' : $limitValue) ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check for simulated successful payment
    if (localStorage.getItem('stripePaymentSuccess')) {
        alert('Simulated Stripe Payment Successful! Your tier has been upgraded.');
        localStorage.removeItem('stripePaymentSuccess'); // Clear the flag
        // Simulate UI update for upgraded tier
        const currentTierCard = document.querySelector('.card[style*="border: 2px solid var(--brand);"]');
        if (currentTierCard) {
            const tierNameSpan = currentTierCard.querySelector('.badge');
            if (tierNameSpan) {
                tierNameSpan.textContent = 'UPGRADED MOCK TIER'; // Example mock upgrade
                tierNameSpan.style.backgroundColor = 'var(--success)';
            }
        }
    }


    // const stripe = Stripe('<?= $_ENV['STRIPE_SANDBOX_PUBLISHABLE_KEY'] ?>'); // Commented out for simulation
    const upgradeButtons = document.querySelectorAll('.upgrade-btn');

    upgradeButtons.forEach(button => {
        button.addEventListener('click', async function() {
            const tierId = this.dataset.tierId;
            button.disabled = true;
            button.textContent = 'Processing...';

            try {
                // Simulate fetch call to create-checkout-session
                await new Promise(resolve => setTimeout(resolve, 1000)); // Simulate network delay
                const mockResponse = { success: true, session_id: 'cs_test_mock_session_id' }; // Mock successful response

                if (mockResponse.success && mockResponse.session_id) {
                    // Simulate redirect to Stripe Checkout and subsequent success
                    localStorage.setItem('stripePaymentSuccess', 'true');
                    window.location.href = window.location.origin + window.location.pathname; // Simulate redirect back
                } else {
                    alert('Simulated initiation of upgrade failed. Please try again.');
                    button.disabled = false;
                    button.textContent = 'Upgrade';
                }
            } catch (error) {
                console.error('Simulated Upgrade error:', error);
                alert('An error occurred during simulated upgrade. Please try again.');
                button.disabled = false;
                button.textContent = 'Upgrade';
            }
        });
    });
});
</script>

</body>
</html>
