<?php
/**
 * Stripe Sandbox Setup Script
 *
 * This script helps create Stripe products and prices in the sandbox environment
 * and updates the local database with the Stripe price IDs.
 *
 * Usage: Run from browser at /admin/stripe-setup.php
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use Stripe\Stripe;
use Stripe\Product;
use Stripe\Price;

// Verify admin access
session_start();
$isAdmin = false;
$currentUser = $_SESSION['User'] ?? null;
if ($currentUser) {
    $config = new Config(); // Temporarily instantiate Config here to get admin role IDs
    $adminRoleIds = array_map('strval', $config->legacyAdminRoleIds());
    $currentUserRoleId = (string)($currentUser['RoleId'] ?? 0);
    $isAdmin = in_array($currentUserRoleId, $adminRoleIds, true);
}

if (!$isAdmin) {
    http_response_code(401);
    die('Unauthorized. Admin access required.');
}

$config = new Config();
$stripeKey = $_ENV['STRIPE_SECRET_KEY'];

if (!$stripeKey) {
    die('STRIPE_SECRET_KEY not configured in .env');
}

Stripe::setApiKey($stripeKey);

// Check if operating in test mode
$is_test_mode = strpos($stripeKey, 'sk_test_') === 0;

if (!$is_test_mode) {
    die('ERROR: This script can only run in Stripe test mode (sandbox). Current key is live.');
}

$pdo = ConnectionFactory::write($config);

// Get all subscription tiers
$stmt = $pdo->query("SELECT id, slug, name, description, price_monthly_cents, price_annual_cents, stripe_product_id, stripe_price_id_monthly, stripe_price_id_annual FROM `ngn_2025`.`subscription_tiers` ORDER BY sort_order");
$tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$action = $_GET['action'] ?? null;
$tier_id = $_GET['tier_id'] ?? null;
$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create_prices') {
    $tier_id = $_POST['tier_id'] ?? null;

    if (!$tier_id) {
        $error = 'No tier specified';
    } else {
        try {
            // Find the tier
            $tier = null;
            foreach ($tiers as $t) {
                if ($t['id'] == $tier_id) {
                    $tier = $t;
                    break;
                }
            }

            if (!$tier) {
                throw new Exception('Tier not found');
            }

            $product_name = "NGN Subscription: {$tier['name']}";

            // Check if product already exists
            $stmt = $pdo->prepare("SELECT stripe_product_id FROM `ngn_2025`.`subscription_tiers` WHERE id = ? LIMIT 1");
            $stmt->execute([$tier_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            $product_id = $existing['stripe_product_id'] ?? null;

            // Create or reuse product
            if (!$product_id) {
                $product = Product::create([
                    'name' => $product_name,
                    'description' => $tier['description'] ?? "NGN {$tier['name']} subscription",
                    'type' => 'service',
                    'metadata' => [
                        'tier_slug' => $tier['slug'],
                        'tier_id' => $tier['id']
                    ]
                ]);
                $product_id = $product->id;

                // Store product ID in database for future reference
                $stmt = $pdo->prepare("UPDATE `ngn_2025`.`subscription_tiers` SET stripe_product_id = ? WHERE id = ?");
                $stmt->execute([$product_id, $tier_id]);
            }

            $prices_created = [];

            // Create monthly price
            if ($tier['price_monthly_cents'] > 0 && !$tier['stripe_price_id_monthly']) {
                $monthly_price = Price::create([
                    'unit_amount' => (int)$tier['price_monthly_cents'],
                    'currency' => 'usd',
                    'recurring' => [
                        'interval' => 'month',
                        'interval_count' => 1
                    ],
                    'product' => $product_id,
                    'metadata' => [
                        'billing_period' => 'monthly'
                    ]
                ]);

                $prices_created['monthly'] = $monthly_price->id;

                // Update database
                $stmt = $pdo->prepare("UPDATE `ngn_2025`.`subscription_tiers` SET stripe_price_id_monthly = ? WHERE id = ?");
                $stmt->execute([$monthly_price->id, $tier_id]);
            } elseif ($tier['price_monthly_cents'] > 0 && $tier['stripe_price_id_monthly']) {
                $prices_created['monthly'] = $tier['stripe_price_id_monthly'] . ' (existing)';
            }

            // Create annual price
            if ($tier['price_annual_cents'] > 0 && !$tier['stripe_price_id_annual']) {
                $annual_price = Price::create([
                    'unit_amount' => (int)$tier['price_annual_cents'],
                    'currency' => 'usd',
                    'recurring' => [
                        'interval' => 'year',
                        'interval_count' => 1
                    ],
                    'product' => $product_id,
                    'metadata' => [
                        'billing_period' => 'annual'
                    ]
                ]);

                $prices_created['annual'] = $annual_price->id;

                // Update database
                $stmt = $pdo->prepare("UPDATE `ngn_2025`.`subscription_tiers` SET stripe_price_id_annual = ? WHERE id = ?");
                $stmt->execute([$annual_price->id, $tier_id]);
            } elseif ($tier['price_annual_cents'] > 0 && $tier['stripe_price_id_annual']) {
                $prices_created['annual'] = $tier['stripe_price_id_annual'] . ' (existing)';
            }

            $result = [
                'success' => true,
                'product_id' => $product_id,
                'prices' => $prices_created,
                'message' => 'Stripe product and prices created/updated successfully'
            ];

            // Refresh tiers list
            $stmt = $pdo->query("SELECT id, slug, name, description, price_monthly_cents, price_annual_cents, stripe_product_id, stripe_price_id_monthly, stripe_price_id_annual FROM `ngn_2025`.`subscription_tiers` ORDER BY sort_order");
            $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            $error = 'Stripe API Error: ' . $e->getError()->message;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stripe Sandbox Setup - NGN Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f5f5f5; padding: 20px; }
        .container { max-width: 1000px; margin-top: 20px; }
        .tier-card { background: white; border-radius: 8px; padding: 15px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .tier-card.complete { border-left: 4px solid #28a745; }
        .tier-card.pending { border-left: 4px solid #ffc107; }
        .price-badge { display: inline-block; font-size: 12px; padding: 4px 8px; margin: 2px; border-radius: 4px; background: #f0f0f0; }
        .price-id { font-family: monospace; font-size: 11px; word-break: break-all; }
        .alert { margin-bottom: 20px; }
        .btn-small { padding: 4px 8px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1>Stripe Sandbox Setup</h1>
                <p class="text-muted">Manage Stripe products and prices for subscription tiers</p>

                <div class="alert alert-info">
                    <strong>ℹ️ Test Mode:</strong> Connected to Stripe Sandbox environment
                    <br><small>Keys must start with <code>sk_test_</code> and <code>pk_test_</code></small>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($result && $result['success']): ?>
                    <div class="alert alert-success">
                        <strong>✓ Success!</strong> <?= htmlspecialchars($result['message']) ?>
                        <ul style="margin-top: 10px; margin-bottom: 0;">
                            <li><strong>Product ID:</strong> <code><?= htmlspecialchars($result['product_id']) ?></code></li>
                            <?php foreach ($result['prices'] as $period => $price_id): ?>
                                <li><strong><?= ucfirst($period) ?> Price ID:</strong> <code><?= htmlspecialchars($price_id) ?></code></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-12">
                        <h3 style="margin-top: 30px; margin-bottom: 20px;">Subscription Tiers</h3>

                        <?php foreach ($tiers as $tier): ?>
                            <?php
                                $is_complete = !empty($tier['stripe_price_id_monthly']) || !empty($tier['stripe_price_id_annual']);
                                $monthly_ready = ($tier['price_monthly_cents'] > 0 && !empty($tier['stripe_price_id_monthly']));
                                $annual_ready = ($tier['price_annual_cents'] > 0 && !empty($tier['stripe_price_id_annual']));
                            ?>
                            <div class="tier-card <?= $is_complete ? 'complete' : 'pending' ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5><?= htmlspecialchars($tier['name']) ?></h5>
                                        <p class="text-muted" style="font-size: 13px; margin-bottom: 10px;">
                                            Slug: <code><?= htmlspecialchars($tier['slug']) ?></code>
                                        </p>

                                        <div style="margin-top: 10px;">
                                            <?php if ($tier['price_monthly_cents'] > 0): ?>
                                                <div style="margin-bottom: 8px;">
                                                    <span class="price-badge">
                                                        Monthly: $<?= number_format($tier['price_monthly_cents'] / 100, 2) ?>
                                                    </span>
                                                    <?php if ($tier['stripe_price_id_monthly']): ?>
                                                        <span style="color: #28a745; font-size: 12px;">✓</span>
                                                        <br>
                                                        <span class="price-id text-success"><?= htmlspecialchars($tier['stripe_price_id_monthly']) ?></span>
                                                    <?php else: ?>
                                                        <span style="color: #ffc107; font-size: 12px;">⏳</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($tier['price_annual_cents'] > 0): ?>
                                                <div style="margin-bottom: 8px;">
                                                    <span class="price-badge">
                                                        Annual: $<?= number_format($tier['price_annual_cents'] / 100, 2) ?>
                                                    </span>
                                                    <?php if ($tier['stripe_price_id_annual']): ?>
                                                        <span style="color: #28a745; font-size: 12px;">✓</span>
                                                        <br>
                                                        <span class="price-id text-success"><?= htmlspecialchars($tier['stripe_price_id_annual']) ?></span>
                                                    <?php else: ?>
                                                        <span style="color: #ffc107; font-size: 12px;">⏳</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($tier['price_monthly_cents'] == 0 && $tier['price_annual_cents'] == 0): ?>
                                                <span class="badge bg-secondary">Free Tier</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="col-md-6" style="text-align: right; display: flex; flex-direction: column; justify-content: center;">
                                        <?php if (!$is_complete && ($tier['price_monthly_cents'] > 0 || $tier['price_annual_cents'] > 0)): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="create_prices">
                                                <input type="hidden" name="tier_id" value="<?= $tier['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    Create Stripe Prices
                                                </button>
                                            </form>
                                        <?php elseif ($is_complete && ($tier['price_monthly_cents'] > 0 || $tier['price_annual_cents'] > 0)): ?>
                                            <span style="color: #28a745; font-weight: bold;">✓ Ready for Checkout</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="margin-top: 40px; padding: 20px; background: #f9f9f9; border-radius: 8px;">
                    <h4>Next Steps</h4>
                    <ol>
                        <li>Click "Create Stripe Prices" for each paid tier above</li>
                        <li>Verify prices created in <a href="https://dashboard.stripe.com/test/products" target="_blank">Stripe Dashboard</a></li>
                        <li>Test checkout flow with test card: <code>4242 4242 4242 4242</code> (exp: any future date, CVC: any 3 digits)</li>
                        <li>Set up webhook testing with Stripe CLI or ngrok</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
