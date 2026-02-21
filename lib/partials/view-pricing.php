<?php
/**
 * NGN Pricing View - Layman ROI Overhaul
 * Aesthetic: Industrial / Tactical / Glassmorphic
 */
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

$config = new Config();
$pdo = ConnectionFactory::read($config);

// Fetch active subscription tiers
$tiers = [];
try {
    $stmt = $pdo->query("SELECT * FROM subscription_tiers WHERE is_active = 1 ORDER BY sort_order ASC");
    $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

$billingCycle = $_GET['billing'] ?? 'monthly';
?>

<div class="max-w-7xl mx-auto py-12">
    <!-- Header -->
    <div class="text-center mb-20">
        <h4 class="text-brand font-black uppercase tracking-[0.5em] text-[10px] mb-4">Strategic_Access</h4>
        <h1 class="text-5xl md:text-7xl font-black tracking-tighter leading-none uppercase italic mb-6">Choose Your <br><span class="text-brand">Impact.</span></h1>
        <p class="text-zinc-500 max-w-2xl mx-auto text-sm md:text-base leading-relaxed font-bold">
            Select the partnership level that fits your growth targets. 
            All plans include coreSound ownership and data integrity verification.
        </p>
    </div>

    <!-- Billing Toggle -->
    <div class="flex justify-center mb-16">
        <div class="inline-flex rounded-full bg-white/5 border border-white/10 p-1.5 backdrop-blur-xl">
            <a href="?view=pricing&billing=monthly" class="px-8 py-2.5 rounded-full text-xs font-black uppercase tracking-widest transition-all <?= $billingCycle === 'monthly' ? 'bg-brand text-black shadow-lg shadow-brand/20' : 'text-zinc-500 hover:text-white' ?>">Monthly</a>
            <a href="?view=pricing&billing=annual" class="px-8 py-2.5 rounded-full text-xs font-black uppercase tracking-widest transition-all <?= $billingCycle === 'annual' ? 'bg-brand text-black shadow-lg shadow-brand/20' : 'text-zinc-500 hover:text-white' ?>">
                Annual <span class="ml-1 opacity-60 text-[10px]">(-17%)</span>
            </a>
        </div>
    </div>

    <!-- Pricing Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-24">
        <?php foreach ($tiers as $tier): 
            if (strpos($tier['slug'], 'station_') === 0) continue; // Filter out station tiers for main view
            
            $priceCents = ($billingCycle === 'annual') ? $tier['price_annual_cents'] : $tier['price_monthly_cents'];
            $priceDisplay = number_format($priceCents / 100, 2);
            $features = json_decode($tier['features'], true) ?: [];
            $isFeatured = $tier['slug'] === 'pro';
        ?>
        <div class="glass-panel p-8 rounded-[2.5rem] flex flex-col transition-all hover:translate-y-[-8px] group <?= $isFeatured ? 'border-brand/40 bg-brand/5' : '' ?>">
            <?php if ($isFeatured): ?>
                <div class="bg-brand text-black text-[9px] font-black uppercase tracking-widest px-4 py-1 rounded-full absolute -top-3 left-1/2 -translate-x-1/2">Recommended</div>
            <?php endif; ?>

            <div class="mb-8">
                <h3 class="text-xl font-black uppercase italic text-white group-hover:text-brand transition-colors"><?= htmlspecialchars($tier['name']) ?></h3>
                <p class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mt-1"><?= htmlspecialchars($tier['description']) ?></p>
            </div>

            <div class="mb-10 flex items-baseline gap-1">
                <span class="text-4xl font-black text-white">$<?= $priceDisplay ?></span>
                <span class="text-[10px] font-black text-zinc-600 uppercase tracking-widest">/ <?= ($billingCycle === 'annual' ? 'year' : 'mo') ?></span>
            </div>

            <ul class="space-y-4 mb-12 flex-1">
                <?php 
                $labels = [
                    'profile' => 'Global Partner Profile',
                    'basic_analytics' => 'Standard Market Data',
                    'advanced_analytics' => 'Deep ROI Intelligence',
                    'ai_coach' => 'AI Growth Analyst',
                    'api_access' => 'Direct Data Uplink',
                    'custom_branding' => 'Identity Security',
                    'priority_support' => 'Priority Operator Access'
                ];
                foreach ($labels as $key => $label): 
                    $has = !empty($features[$key]);
                ?>
                <li class="flex items-center gap-3 text-[11px] font-bold tracking-tight <?= $has ? 'text-zinc-300' : 'text-zinc-700 line-through' ?>">
                    <i class="bi <?= $has ? 'bi-check2 text-brand' : 'bi-x text-zinc-800' ?> text-base"></i>
                    <?= $label ?>
                </li>
                <?php endforeach; ?>
            </ul>

            <button onclick="startCheckout('<?= $tier['slug'] ?>', '<?= $billingCycle ?>')" class="w-full py-4 rounded-2xl font-black uppercase tracking-[0.2em] text-[10px] transition-all <?= $isFeatured ? 'bg-brand text-black shadow-xl shadow-brand/20 hover:scale-[1.02]' : 'bg-white/5 text-white border border-white/10 hover:bg-white/10' ?>">
                <?= $priceCents > 0 ? 'Secure Access' : 'Begin Free' ?>
            </button>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- FAQ Section -->
    <div class="max-w-3xl mx-auto">
        <h2 class="text-3xl font-black uppercase italic tracking-tighter text-center mb-12">Frequent_Queries</h2>
        <div class="space-y-4">
            <div class="glass-panel p-6 rounded-2xl border-white/5">
                <h4 class="text-xs font-black uppercase tracking-widest text-brand mb-2">Can I change plans?</h4>
                <p class="text-zinc-500 text-sm leading-relaxed">Yes. You can adjust your impact level at any time. Changes are calculated instantly and applied to your next billing cycle.</p>
            </div>
            <div class="glass-panel p-6 rounded-2xl border-white/5">
                <h4 class="text-xs font-black uppercase tracking-widest text-brand mb-2">What methods do you accept?</h4>
                <p class="text-zinc-500 text-sm leading-relaxed">We process all secure capital transfers via the Financial Engine (Stripe), including Visa, Mastercard, and American Express.</p>
            </div>
        </div>
    </div>
</div>

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
            const stripe = Stripe('<?= htmlspecialchars(\NGN\Lib\Env::get('STRIPE_PUBLISHABLE_KEY', '')) ?>');
            await stripe.redirectToCheckout({ sessionId: data.data.session_id });
        } else {
            showSysNotify(data.errors?.[0]?.message || 'Unable to start checkout.', 'error');
        }
    } catch (e) {
        showSysNotify('Communication error with Financial Engine.', 'error');
    }
}
</script>
