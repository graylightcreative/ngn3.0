<?php
/**
 * NGN 2.0 Pricing Page
 * Public subscription tiers and pricing display
 */
$root = dirname(__DIR__);
require_once $root.'/lib/bootstrap.php';

use NGN\Lib\Env;
use NGN\Lib\Config;

Env::load($root);
$cfg = new Config();

// Fetch tiers from API
$tiers = [];
try {
    $ch = curl_init();
    $apiUrl = rtrim(Env::get('APP_URL', 'https://nextgennoise.com'), '/') . '/api/v1/subscription-tiers';
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response) {
        $data = json_decode($response, true);
        $tiers = $data['data'] ?? [];
    }
} catch (\Throwable $e) {}

// Fallback tiers if API fails
if (empty($tiers)) {
    $tiers = [
        ['slug' => 'free', 'name' => 'Free', 'price_monthly' => '0.00', 'price_annual' => '0.00', 'description' => 'Basic access to NGN platform', 'features' => ['profile' => true, 'basic_analytics' => true]],
        ['slug' => 'pro', 'name' => 'Pro', 'price_monthly' => '9.99', 'price_annual' => '99.90', 'description' => 'Enhanced features for serious artists', 'features' => ['profile' => true, 'basic_analytics' => true, 'ai_coach' => true, 'advanced_analytics' => true]],
        ['slug' => 'premium', 'name' => 'Premium', 'price_monthly' => '24.99', 'price_annual' => '249.90', 'description' => 'Full platform access with priority support', 'features' => ['profile' => true, 'basic_analytics' => true, 'ai_coach' => true, 'advanced_analytics' => true, 'priority_support' => true, 'api_access' => true]],
    ];
}

$billingCycle = $_GET['billing'] ?? 'monthly';
?>
<!doctype html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pricing — NextGenNoise</title>
    <meta name="description" content="Choose the right plan for your music career. From free to premium, NGN has options for every artist.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: { brand: '#1DB954' }
        }
      }
    }
    </script>
    <style>
        body { background: linear-gradient(135deg, #0b1020 0%, #1a1a2e 50%, #0b1020 100%); min-height: 100vh; }
        .tier-card { transition: transform 0.2s, box-shadow 0.2s; }
        .tier-card:hover { transform: translateY(-4px); }
        .tier-popular { border-color: #1DB954 !important; }
    </style>
</head>
<body class="text-white">
    <!-- Header -->
    <header class="py-4 px-6 border-b border-white/10">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <a href="/" class="flex items-center gap-2">
                <img src="/lib/images/site/web-light-1.png" alt="NextGenNoise" class="h-8">
            </a>
            <nav class="flex items-center gap-4">
                <a href="/frontend/" class="text-sm text-gray-400 hover:text-white">Home</a>
                <a href="/login" class="px-4 py-2 rounded bg-brand text-white text-sm font-medium hover:bg-brand/90">Sign In</a>
            </nav>
        </div>
    </header>

    <main class="py-16 px-6">
        <div class="max-w-6xl mx-auto">
            <!-- Hero -->
            <div class="text-center mb-12">
                <h1 class="text-4xl md:text-5xl font-bold mb-4">Simple, Transparent Pricing</h1>
                <p class="text-xl text-gray-400 max-w-2xl mx-auto">Choose the plan that fits your music career. Upgrade or downgrade anytime.</p>
            </div>

            <!-- Billing Toggle -->
            <div class="flex justify-center mb-12">
                <div class="inline-flex rounded-lg bg-white/5 p-1">
                    <a href="?billing=monthly" class="px-6 py-2 rounded-md text-sm font-medium transition <?= $billingCycle === 'monthly' ? 'bg-brand text-white' : 'text-gray-400 hover:text-white' ?>">Monthly</a>
                    <a href="?billing=annual" class="px-6 py-2 rounded-md text-sm font-medium transition <?= $billingCycle === 'annual' ? 'bg-brand text-white' : 'text-gray-400 hover:text-white' ?>">
                        Annual <span class="text-xs text-brand ml-1">Save 17%</span>
                    </a>
                </div>
            </div>

            <!-- Pricing Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-16">
                <?php foreach ($tiers as $i => $tier): 
                    $isPopular = ($tier['slug'] ?? '') === 'pro';
                    $price = $billingCycle === 'annual' ? ($tier['price_annual'] ?? '0.00') : ($tier['price_monthly'] ?? '0.00');
                    $priceNum = (float)$price;
                    $features = $tier['features'] ?? [];
                ?>
                <div class="tier-card rounded-2xl border <?= $isPopular ? 'tier-popular border-2' : 'border-white/10' ?> bg-white/5 backdrop-blur p-6 relative">
                    <?php if ($isPopular): ?>
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full bg-brand text-xs font-bold">MOST POPULAR</div>
                    <?php endif; ?>
                    
                    <h3 class="text-xl font-bold mb-2"><?= htmlspecialchars($tier['name'] ?? 'Plan') ?></h3>
                    <p class="text-sm text-gray-400 mb-4"><?= htmlspecialchars($tier['description'] ?? '') ?></p>
                    
                    <div class="mb-6">
                        <span class="text-4xl font-bold">$<?= number_format($priceNum, 2) ?></span>
                        <span class="text-gray-400">/<?= $billingCycle === 'annual' ? 'year' : 'month' ?></span>
                    </div>
                    
                    <ul class="space-y-3 mb-6">
                        <?php 
                        $featureLabels = [
                            'profile' => 'Artist Profile',
                            'basic_analytics' => 'Basic Analytics',
                            'ai_coach' => 'AI Career Coach',
                            'advanced_analytics' => 'Advanced Analytics',
                            'priority_support' => 'Priority Support',
                            'api_access' => 'API Access',
                            'custom_branding' => 'Custom Branding',
                        ];
                        foreach ($featureLabels as $key => $label):
                            $hasFeature = !empty($features[$key]);
                        ?>
                        <li class="flex items-center gap-2 text-sm <?= $hasFeature ? 'text-white' : 'text-gray-500' ?>">
                            <?php if ($hasFeature): ?>
                            <svg class="w-5 h-5 text-brand flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <?php else: ?>
                            <svg class="w-5 h-5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            <?php endif; ?>
                            <?= htmlspecialchars($label) ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <?php if ($priceNum > 0): ?>
                    <button onclick="startCheckout('<?= htmlspecialchars($tier['slug'] ?? '') ?>', '<?= $billingCycle ?>')" 
                            class="w-full py-3 rounded-lg font-medium transition <?= $isPopular ? 'bg-brand text-white hover:bg-brand/90' : 'bg-white/10 text-white hover:bg-white/20' ?>">
                        Get Started
                    </button>
                    <?php else: ?>
                    <a href="/register" class="block w-full py-3 rounded-lg font-medium text-center bg-white/10 text-white hover:bg-white/20 transition">
                        Sign Up Free
                    </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- FAQ Section -->
            <div class="max-w-3xl mx-auto">
                <h2 class="text-2xl font-bold text-center mb-8">Frequently Asked Questions</h2>
                <div class="space-y-4">
                    <details class="group rounded-lg bg-white/5 border border-white/10">
                        <summary class="flex items-center justify-between p-4 cursor-pointer">
                            <span class="font-medium">Can I change my plan later?</span>
                            <svg class="w-5 h-5 text-gray-400 group-open:rotate-180 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </summary>
                        <div class="px-4 pb-4 text-gray-400">
                            Yes! You can upgrade or downgrade your plan at any time. Changes take effect immediately, and we'll prorate any differences.
                        </div>
                    </details>
                    <details class="group rounded-lg bg-white/5 border border-white/10">
                        <summary class="flex items-center justify-between p-4 cursor-pointer">
                            <span class="font-medium">What payment methods do you accept?</span>
                            <svg class="w-5 h-5 text-gray-400 group-open:rotate-180 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </summary>
                        <div class="px-4 pb-4 text-gray-400">
                            We accept all major credit cards (Visa, Mastercard, American Express) through our secure Stripe payment processing.
                        </div>
                    </details>
                    <details class="group rounded-lg bg-white/5 border border-white/10">
                        <summary class="flex items-center justify-between p-4 cursor-pointer">
                            <span class="font-medium">Is there a free trial?</span>
                            <svg class="w-5 h-5 text-gray-400 group-open:rotate-180 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </summary>
                        <div class="px-4 pb-4 text-gray-400">
                            Our Free tier gives you access to core features forever. For paid plans, we offer a 14-day money-back guarantee.
                        </div>
                    </details>
                    <details class="group rounded-lg bg-white/5 border border-white/10">
                        <summary class="flex items-center justify-between p-4 cursor-pointer">
                            <span class="font-medium">What's included in the AI Career Coach?</span>
                            <svg class="w-5 h-5 text-gray-400 group-open:rotate-180 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </summary>
                        <div class="px-4 pb-4 text-gray-400">
                            The AI Coach analyzes your streaming data, social metrics, and industry trends to provide personalized recommendations for growing your music career.
                        </div>
                    </details>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="py-8 px-6 border-t border-white/10">
        <div class="max-w-6xl mx-auto text-center text-sm text-gray-500">
            <p>&copy; <?= date('Y') ?> NextGenNoise. All rights reserved.</p>
            <div class="mt-2 space-x-4">
                <a href="/privacy-policy" class="hover:text-white">Privacy Policy</a>
                <a href="/terms-of-service" class="hover:text-white">Terms of Service</a>
            </div>
        </div>
    </footer>

    <script src="https://js.stripe.com/v3/"></script>
    <script>
    async function startCheckout(tierSlug, billingCycle) {
        try {
            const response = await fetch('/api/v1/checkout/subscription', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tier: tierSlug, billing_cycle: billingCycle })
            });
            const data = await response.json();
            if (data.data?.url) {
                window.location.href = data.data.url;
            } else if (data.data?.session_id) {
                const stripe = Stripe('<?= htmlspecialchars(Env::get('STRIPE_PUBLISHABLE_KEY', '')) ?>');
                await stripe.redirectToCheckout({ sessionId: data.data.session_id });
            } else {
                alert(data.errors?.[0]?.message || 'Unable to start checkout. Please try again.');
            }
        } catch (e) {
            alert('Error starting checkout: ' + e.message);
        }
    }
    </script>
<?php require dirname(__DIR__) . "/lib/partials/global-footer.php"; ?>

</body>
</html>

