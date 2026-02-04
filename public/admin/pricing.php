<?php
/**
 * Pricing & Commission Management
 *
 * Centralized control for merchandise pricing and platform commission rules.
 * Ensures 60%+ net margin KPI (Bible Ch. 17, I.6) is maintained across all products.
 *
 * Features:
 * - Pricing rule management (markup, margin, fixed)
 * - Commission rule management (percent, fixed, tiered)
 * - Margin analysis and reporting
 * - Pricing calculator/simulator
 * - Rule priority and conflict resolution
 *
 * Related: Bible Ch. 17 (Financial Integrity), I.6 (Margin KPI), I.9 (COGS Validation)
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config\Config;

// Check authentication (replace with your auth system)
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     header('Location: /login');
//     exit;
// }

$config = new Config();
$pdo = ConnectionFactory::read($config);

$error = null;
$success = null;

// Handle pricing rule creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_pricing_rule') {
    try {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $rule_type = $_POST['rule_type'] ?? 'global';
        $pricing_strategy = $_POST['pricing_strategy'] ?? 'margin';
        $margin_percent = isset($_POST['margin_percent']) ? (float)$_POST['margin_percent'] : null;
        $markup_percent = isset($_POST['markup_percent']) ? (float)$_POST['markup_percent'] : null;
        $min_price_cents = isset($_POST['min_price_cents']) ? (int)$_POST['min_price_cents'] : null;
        $round_to_cents = isset($_POST['round_to_cents']) ? (int)$_POST['round_to_cents'] : 100;
        $priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 100;
        $active = isset($_POST['active']) ? 1 : 0;

        if (!$name) {
            throw new \Exception("Rule name is required.");
        }

        if ($id) {
            // Update existing rule
            $stmt = $pdo->prepare("
                UPDATE ngn_2025.pricing_rules
                SET name = ?, description = ?, rule_type = ?, pricing_strategy = ?,
                    margin_percent = ?, markup_percent = ?, min_price_cents = ?,
                    round_to_cents = ?, priority = ?, active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $rule_type, $pricing_strategy, $margin_percent,
                           $markup_percent, $min_price_cents, $round_to_cents, $priority, $active, $id]);
            $success = "Pricing rule updated successfully!";
        } else {
            // Create new rule
            $stmt = $pdo->prepare("
                INSERT INTO ngn_2025.pricing_rules
                (name, description, rule_type, pricing_strategy, margin_percent, markup_percent,
                 min_price_cents, round_to_cents, priority, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description, $rule_type, $pricing_strategy, $margin_percent,
                           $markup_percent, $min_price_cents, $round_to_cents, $priority, $active]);
            $success = "Pricing rule created successfully!";
        }

        header("Location: pricing.php?success=" . urlencode($success));
        exit;

    } catch (\Throwable $e) {
        $error = $e->getMessage();
        error_log("Pricing rule save error: " . $e->getMessage());
    }
}

// Handle commission rule creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_commission_rule') {
    try {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $rule_type = $_POST['rule_type'] ?? 'global';
        $entity_type = $_POST['entity_type'] ?? null;
        $commission_type = $_POST['commission_type'] ?? 'percent';
        $commission_percent = isset($_POST['commission_percent']) ? (float)$_POST['commission_percent'] : null;
        $priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 100;
        $active = isset($_POST['active']) ? 1 : 0;

        if (!$name) {
            throw new \Exception("Rule name is required.");
        }

        if ($id) {
            // Update existing rule
            $stmt = $pdo->prepare("
                UPDATE ngn_2025.commission_rules
                SET name = ?, description = ?, rule_type = ?, entity_type = ?,
                    commission_type = ?, commission_percent = ?, priority = ?, active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $rule_type, $entity_type,
                           $commission_type, $commission_percent, $priority, $active, $id]);
            $success = "Commission rule updated successfully!";
        } else {
            // Create new rule
            $stmt = $pdo->prepare("
                INSERT INTO ngn_2025.commission_rules
                (name, description, rule_type, entity_type, commission_type, commission_percent, priority, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description, $rule_type, $entity_type,
                           $commission_type, $commission_percent, $priority, $active]);
            $success = "Commission rule created successfully!";
        }

        header("Location: pricing.php?tab=commissions&success=" . urlencode($success));
        exit;

    } catch (\Throwable $e) {
        $error = $e->getMessage();
        error_log("Commission rule save error: " . $e->getMessage());
    }
}

// Handle rule deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_rule') {
    try {
        $rule_id = (int)($_POST['rule_id'] ?? 0);
        $rule_table = $_POST['rule_table'] ?? '';

        if (!$rule_id || !in_array($rule_table, ['pricing_rules', 'commission_rules'])) {
            throw new \Exception("Invalid rule deletion request.");
        }

        $stmt = $pdo->prepare("DELETE FROM ngn_2025.{$rule_table} WHERE id = ?");
        $stmt->execute([$rule_id]);

        $success = "Rule deleted successfully!";
        $tab = $rule_table === 'pricing_rules' ? 'pricing' : 'commissions';
        header("Location: pricing.php?tab={$tab}&success=" . urlencode($success));
        exit;

    } catch (\Throwable $e) {
        $error = $e->getMessage();
        error_log("Rule deletion error: " . $e->getMessage());
    }
}

// Fetch pricing rules
$pricingRules = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM ngn_2025.pricing_rules
        ORDER BY priority ASC, created_at DESC
    ");
    $stmt->execute();
    $pricingRules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    error_log("Failed to fetch pricing rules: " . $e->getMessage());
}

// Fetch commission rules
$commissionRules = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM ngn_2025.commission_rules
        ORDER BY priority ASC, created_at DESC
    ");
    $stmt->execute();
    $commissionRules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    error_log("Failed to fetch commission rules: " . $e->getMessage());
}

// Margin statistics
$marginStats = [
    'total_products' => 0,
    'avg_margin_percent' => 0,
    'below_60_count' => 0,
    'negative_count' => 0
];

try {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_products,
            AVG(net_margin_percent) as avg_margin_percent,
            COUNT(CASE WHEN net_margin_percent < 60 THEN 1 END) as below_60_count,
            COUNT(CASE WHEN net_margin_percent < 0 THEN 1 END) as negative_count
        FROM ngn_2025.product_margin_analysis
        WHERE analyzed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $marginStats = $row;
    }
} catch (\Throwable $e) {
    error_log("Failed to fetch margin stats: " . $e->getMessage());
}

// Get current tab
$currentTab = $_GET['tab'] ?? 'pricing';

?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing & Commission Management - NextGenNoise Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="min-h-screen p-6">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-white mb-2">Pricing & Commission Management</h1>
                    <p class="text-gray-400">Universal merch pricing control and margin integrity</p>
                </div>
                <a href="index.php" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition">
                    ← Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="mb-6 p-4 bg-green-900/30 border border-green-500 rounded-lg">
                <p class="text-green-400">✓ <?= htmlspecialchars($_GET['success']) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-900/30 border border-red-500 rounded-lg">
                <p class="text-red-400">✗ Error: <?= htmlspecialchars($error) ?></p>
            </div>
        <?php endif; ?>

        <!-- KPI Alert -->
        <?php if ($marginStats['below_60_count'] > 0): ?>
            <div class="mb-6 p-4 bg-yellow-900/30 border border-yellow-500 rounded-lg">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <p class="text-yellow-400 font-medium">
                        Warning: <?= $marginStats['below_60_count'] ?> products below 60% net margin KPI target
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-gradient-to-br from-blue-900/40 to-blue-800/20 border border-blue-700/50 rounded-lg p-6">
                <div class="text-blue-400 text-sm font-medium mb-2">Total Products</div>
                <div class="text-3xl font-bold text-white"><?= number_format($marginStats['total_products']) ?></div>
            </div>

            <div class="bg-gradient-to-br from-green-900/40 to-green-800/20 border border-green-700/50 rounded-lg p-6">
                <div class="text-green-400 text-sm font-medium mb-2">Avg Net Margin</div>
                <div class="text-3xl font-bold text-white"><?= number_format($marginStats['avg_margin_percent'], 1) ?>%</div>
                <?php if ($marginStats['avg_margin_percent'] >= 60): ?>
                    <div class="mt-2 text-green-400 text-xs">✓ Above 60% target</div>
                <?php else: ?>
                    <div class="mt-2 text-red-400 text-xs">⚠ Below 60% target</div>
                <?php endif; ?>
            </div>

            <div class="bg-gradient-to-br from-yellow-900/40 to-yellow-800/20 border border-yellow-700/50 rounded-lg p-6">
                <div class="text-yellow-400 text-sm font-medium mb-2">Below 60% Margin</div>
                <div class="text-3xl font-bold text-white"><?= $marginStats['below_60_count'] ?></div>
            </div>

            <div class="bg-gradient-to-br from-red-900/40 to-red-800/20 border border-red-700/50 rounded-lg p-6">
                <div class="text-red-400 text-sm font-medium mb-2">Negative Margin</div>
                <div class="text-3xl font-bold text-white"><?= $marginStats['negative_count'] ?></div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mb-6 border-b border-gray-700">
            <div class="flex space-x-4">
                <button onclick="showTab('pricing')" id="tab-pricing" class="tab-button px-4 py-2 border-b-2 <?= $currentTab === 'pricing' ? 'border-blue-500 text-blue-400' : 'border-transparent text-gray-400' ?> font-medium hover:text-gray-300">
                    Pricing Rules (<?= count($pricingRules) ?>)
                </button>
                <button onclick="showTab('commissions')" id="tab-commissions" class="tab-button px-4 py-2 border-b-2 <?= $currentTab === 'commissions' ? 'border-blue-500 text-blue-400' : 'border-transparent text-gray-400' ?> font-medium hover:text-gray-300">
                    Commission Rules (<?= count($commissionRules) ?>)
                </button>
                <button onclick="showTab('calculator')" id="tab-calculator" class="tab-button px-4 py-2 border-b-2 <?= $currentTab === 'calculator' ? 'border-blue-500 text-blue-400' : 'border-transparent text-gray-400' ?> font-medium hover:text-gray-300">
                    Pricing Calculator
                </button>
                <button onclick="showTab('docs')" id="tab-docs" class="tab-button px-4 py-2 border-b-2 <?= $currentTab === 'docs' ? 'border-blue-500 text-blue-400' : 'border-transparent text-gray-400' ?> font-medium hover:text-gray-300">
                    Documentation
                </button>
            </div>
        </div>

        <!-- Pricing Rules Tab -->
        <div id="content-pricing" class="tab-content <?= $currentTab !== 'pricing' ? 'hidden' : '' ?>">
            <?php include __DIR__ . '/_pricing_rules_tab.php'; ?>
        </div>

        <!-- Commission Rules Tab -->
        <div id="content-commissions" class="tab-content <?= $currentTab !== 'commissions' ? 'hidden' : '' ?>">
            <?php include __DIR__ . '/_commission_rules_tab.php'; ?>
        </div>

        <!-- Calculator Tab -->
        <div id="content-calculator" class="tab-content <?= $currentTab !== 'calculator' ? 'hidden' : '' ?>">
            <?php include __DIR__ . '/_pricing_calculator_tab.php'; ?>
        </div>

        <!-- Documentation Tab -->
        <div id="content-docs" class="tab-content <?= $currentTab !== 'docs' ? 'hidden' : '' ?>">
            <?php include __DIR__ . '/_pricing_docs_tab.php'; ?>
        </div>

    </div>

    <script>
        function showTab(tabName) {
            // Hide all content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });

            // Reset all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-blue-500', 'text-blue-400');
                button.classList.add('border-transparent', 'text-gray-400');
            });

            // Show selected content
            document.getElementById('content-' + tabName).classList.remove('hidden');

            // Highlight selected tab
            const selectedTab = document.getElementById('tab-' + tabName);
            selectedTab.classList.remove('border-transparent', 'text-gray-400');
            selectedTab.classList.add('border-blue-500', 'text-blue-400');

            // Update URL
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
        }

        // Calculator functions
        function calculatePrice() {
            const cost = parseFloat(document.getElementById('calc_cost').value) || 0;
            const margin = parseFloat(document.getElementById('calc_margin').value) || 60;
            const commission = parseFloat(document.getElementById('calc_commission').value) || 30;

            // Price = Cost / (1 - Margin% - Commission%)
            const marginDecimal = margin / 100;
            const commissionDecimal = commission / 100;
            const divisor = 1 - marginDecimal - commissionDecimal;

            if (divisor <= 0) {
                document.getElementById('calc_result').innerHTML = '<div class="text-red-400">Error: Margin + Commission cannot be >= 100%</div>';
                return;
            }

            const price = cost / divisor;
            const grossProfit = price - cost;
            const platformFee = price * commissionDecimal;
            const netProfit = grossProfit - platformFee;
            const actualMargin = (netProfit / price) * 100;

            document.getElementById('calc_result').innerHTML = `
                <div class="space-y-3">
                    <div class="text-2xl font-bold text-green-400">Recommended Price: $${price.toFixed(2)}</div>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <div class="text-gray-400">Cost</div>
                            <div class="text-white font-medium">$${cost.toFixed(2)}</div>
                        </div>
                        <div>
                            <div class="text-gray-400">Sale Price</div>
                            <div class="text-white font-medium">$${price.toFixed(2)}</div>
                        </div>
                        <div>
                            <div class="text-gray-400">Gross Profit</div>
                            <div class="text-white font-medium">$${grossProfit.toFixed(2)}</div>
                        </div>
                        <div>
                            <div class="text-gray-400">Platform Fee (${commission}%)</div>
                            <div class="text-white font-medium">$${platformFee.toFixed(2)}</div>
                        </div>
                        <div>
                            <div class="text-gray-400">Net Profit</div>
                            <div class="text-white font-medium">$${netProfit.toFixed(2)}</div>
                        </div>
                        <div>
                            <div class="text-gray-400">Net Margin</div>
                            <div class="text-${actualMargin >= 60 ? 'green' : 'red'}-400 font-bold">${actualMargin.toFixed(1)}%</div>
                        </div>
                    </div>
                </div>
            `;
        }
    </script>
</body>
</html>
