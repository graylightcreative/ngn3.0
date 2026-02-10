<?php
/**
 * Royalties & Payouts Management Dashboard
 * Manage artist payments, royalty balances, and payout history
 *
 * Bible References: Ch. 13 (90/10 Rule), Ch. 14 (Monthly Pool), Ch. 26 (Payouts), Ch. 30 (PPV)
 */

require_once dirname(__DIR__, 1) . '/_guard.php';
$root = dirname(__DIR__, 1);

use NGN\Lib\Env;
use NGN\Lib\Config;
use NGN\Lib\Royalty\RoyaltyLedgerService;

if (!class_exists('NGN\Lib\Env')) { if (function_exists('ngn_autoload_diagnostics')) { ngn_autoload_diagnostics($root, false); } exit; }
Env::load($root);
$cfg = new Config();

// Get filter parameters
$filterUser = isset($_GET['user']) ? (int)$_GET['user'] : null;
$filterType = isset($_GET['type']) ? $_GET['type'] : null; // 'direct', 'pool', 'ppv'
$filterStatus = isset($_GET['status']) ? $_GET['status'] : null; // 'pending', 'paid'

// Initialize data
$royaltySummary = [
    'total_pending' => 0,
    'total_paid' => 0,
    'pending_artists' => 0,
    'direct_revenue' => 0,
    'pool_revenue' => 0,
    'ppv_revenue' => 0
];

$pendingPayouts = [];
$recentTransactions = [];
$topEarners = [];

try {
    if (isset($pdo) && $pdo instanceof \PDO) {
        // Get royalty summary statistics
        $stmt = $pdo->prepare("
            SELECT
                SUM(CASE WHEN status = 'pending' THEN amount_cents ELSE 0 END) as total_pending,
                SUM(CASE WHEN status = 'paid' THEN amount_cents ELSE 0 END) as total_paid,
                COUNT(DISTINCT CASE WHEN status = 'pending' THEN user_id END) as pending_artists,
                SUM(CASE WHEN revenue_type = 'direct' THEN amount_cents ELSE 0 END) as direct_revenue,
                SUM(CASE WHEN revenue_type = 'pool' THEN amount_cents ELSE 0 END) as pool_revenue,
                SUM(CASE WHEN revenue_type = 'ppv' THEN amount_cents ELSE 0 END) as ppv_revenue
            FROM ngn_2025.royalty_ledger
        ");
        $stmt->execute();
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($summary) {
            $royaltySummary = [
                'total_pending' => (float)($summary['total_pending'] ?? 0) / 100,
                'total_paid' => (float)($summary['total_paid'] ?? 0) / 100,
                'pending_artists' => (int)($summary['pending_artists'] ?? 0),
                'direct_revenue' => (float)($summary['direct_revenue'] ?? 0) / 100,
                'pool_revenue' => (float)($summary['pool_revenue'] ?? 0) / 100,
                'ppv_revenue' => (float)($summary['ppv_revenue'] ?? 0) / 100
            ];
        }

        // Get pending payouts grouped by artist
        $sql = "
            SELECT
                u.Id as user_id,
                u.Name as artist_name,
                u.Email,
                SUM(rl.amount_cents) as total_pending_cents,
                COUNT(rl.id) as transaction_count,
                MAX(rl.created_at) as latest_transaction,
                GROUP_CONCAT(DISTINCT rl.revenue_type) as revenue_types
            FROM ngn_2025.royalty_ledger rl
            INNER JOIN ngn_2025.users u ON u.Id = rl.user_id
            WHERE rl.status = 'pending'
        ";

        if ($filterUser) {
            $sql .= " AND u.Id = :user_id";
        }
        if ($filterType) {
            $sql .= " AND rl.revenue_type = :revenue_type";
        }

        $sql .= " GROUP BY u.Id ORDER BY total_pending_cents DESC LIMIT 50";

        $stmt = $pdo->prepare($sql);
        if ($filterUser) $stmt->bindValue(':user_id', $filterUser, PDO::PARAM_INT);
        if ($filterType) $stmt->bindValue(':revenue_type', $filterType);
        $stmt->execute();
        $pendingPayouts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get recent transactions
        $sql = "
            SELECT
                rl.*,
                u.Name as artist_name,
                u.Email as artist_email
            FROM ngn_2025.royalty_ledger rl
            INNER JOIN ngn_2025.users u ON u.Id = rl.user_id
            WHERE 1=1
        ";

        if ($filterUser) {
            $sql .= " AND rl.user_id = :user_id";
        }
        if ($filterType) {
            $sql .= " AND rl.revenue_type = :revenue_type";
        }
        if ($filterStatus) {
            $sql .= " AND rl.status = :status";
        }

        $sql .= " ORDER BY rl.created_at DESC LIMIT 100";

        $stmt = $pdo->prepare($sql);
        if ($filterUser) $stmt->bindValue(':user_id', $filterUser, PDO::PARAM_INT);
        if ($filterType) $stmt->bindValue(':revenue_type', $filterType);
        if ($filterStatus) $stmt->bindValue(':status', $filterStatus);
        $stmt->execute();
        $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get top earners (all time)
        $stmt = $pdo->prepare("
            SELECT
                u.Id as user_id,
                u.Name as artist_name,
                SUM(rl.amount_cents) as total_earned_cents,
                COUNT(rl.id) as transaction_count
            FROM ngn_2025.royalty_ledger rl
            INNER JOIN ngn_2025.users u ON u.Id = rl.user_id
            GROUP BY u.Id
            ORDER BY total_earned_cents DESC
            LIMIT 10
        ");
        $stmt->execute();
        $topEarners = $stmt->fetchAll(PDO::FETCH_ASSOC);

    }
} catch (\Throwable $e) {
    error_log("Royalties Dashboard Error: " . $e->getMessage());
}

$pageTitle = 'Royalties & Payouts';
$currentPage = 'royalties';
include __DIR__.'/_header.php';
include __DIR__.'/_topbar.php';
?>

<style>
.stat-card {
    @apply rounded-lg border border-gray-200 dark:border-white/10 p-5 bg-white/70 dark:bg-white/5 shadow-sm;
}

.payout-row {
    @apply flex items-center justify-between p-4 rounded-lg border border-gray-200 dark:border-white/10 bg-white/50 dark:bg-white/5 hover:bg-gray-50 dark:hover:bg-white/10 transition cursor-pointer;
}

.transaction-row {
    @apply flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-white/10 bg-white/50 dark:bg-white/5;
}

.tab-button {
    @apply px-4 py-2 font-semibold rounded-t-lg transition;
}

.tab-active {
    @apply bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 border-b-2 border-blue-600;
}

.tab-inactive {
    @apply text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700/50;
}
</style>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-3xl font-bold sk-text-primary">Royalties & Payouts</h1>
        <p class="text-sm text-gray-500 mt-1">Artist payment management & financial transparency</p>
    </div>

    <!-- Actions -->
    <div class="flex gap-2">
        <button onclick="alert('Payout processing feature coming soon!')" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 3 3 3 3 .895 3 2-1.343 3-3 3m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Process Payouts
        </button>
        <button onclick="location.reload()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Refresh
        </button>
    </div>
</div>

<!-- Summary Statistics -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
    <!-- Total Pending -->
    <div class="stat-card">
        <div class="text-sm font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-2">
            Pending Payouts
        </div>
        <div class="text-3xl font-bold text-orange-600 dark:text-orange-400">
            $<?php echo number_format($royaltySummary['total_pending'], 2); ?>
        </div>
        <div class="mt-2 text-xs text-gray-500">
            <?php echo $royaltySummary['pending_artists']; ?> artists waiting
        </div>
    </div>

    <!-- Total Paid -->
    <div class="stat-card">
        <div class="text-sm font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-2">
            Total Paid (All Time)
        </div>
        <div class="text-3xl font-bold text-green-600 dark:text-green-400">
            $<?php echo number_format($royaltySummary['total_paid'], 2); ?>
        </div>
        <div class="mt-2 text-xs text-gray-500">
            Historical payouts
        </div>
    </div>

    <!-- Revenue Breakdown -->
    <div class="stat-card">
        <div class="text-sm font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-2">
            Revenue Breakdown
        </div>
        <div class="space-y-1 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-300">Direct (90/10):</span>
                <span class="font-semibold">$<?php echo number_format($royaltySummary['direct_revenue'], 2); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-300">Pool (Monthly):</span>
                <span class="font-semibold">$<?php echo number_format($royaltySummary['pool_revenue'], 2); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-300">PPV (Per-View):</span>
                <span class="font-semibold">$<?php echo number_format($royaltySummary['ppv_revenue'], 2); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="stat-card mb-6">
    <form method="GET" class="flex flex-wrap items-end gap-4">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-semibold text-gray-600 dark:text-gray-300 mb-2">Artist/User ID</label>
            <input type="number" name="user" value="<?php echo $filterUser ?? ''; ?>" placeholder="Filter by user ID" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
        </div>

        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-semibold text-gray-600 dark:text-gray-300 mb-2">Revenue Type</label>
            <select name="type" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                <option value="">All Types</option>
                <option value="direct" <?php echo $filterType === 'direct' ? 'selected' : ''; ?>>Direct (90/10)</option>
                <option value="pool" <?php echo $filterType === 'pool' ? 'selected' : ''; ?>>Monthly Pool</option>
                <option value="ppv" <?php echo $filterType === 'ppv' ? 'selected' : ''; ?>>Pay-Per-View</option>
            </select>
        </div>

        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-semibold text-gray-600 dark:text-gray-300 mb-2">Status</label>
            <select name="status" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                <option value="">All Statuses</option>
                <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="paid" <?php echo $filterStatus === 'paid' ? 'selected' : ''; ?>>Paid</option>
            </select>
        </div>

        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            Apply Filters
        </button>
        <a href="?" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
            Clear
        </a>
    </form>
</div>

<!-- Tabs -->
<div class="mb-6">
    <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700">
        <button id="tab-pending" class="tab-button tab-active" onclick="switchTab('pending')">
            Pending Payouts (<?php echo count($pendingPayouts); ?>)
        </button>
        <button id="tab-transactions" class="tab-button tab-inactive" onclick="switchTab('transactions')">
            Transaction History (<?php echo count($recentTransactions); ?>)
        </button>
        <button id="tab-earners" class="tab-button tab-inactive" onclick="switchTab('earners')">
            Top Earners
        </button>
    </div>
</div>

<!-- Tab Content: Pending Payouts -->
<div id="content-pending" class="tab-content">
    <div class="stat-card">
        <h2 class="text-xl font-bold sk-text-primary mb-6">Pending Payouts by Artist</h2>

        <?php if (!empty($pendingPayouts)): ?>
            <div class="space-y-3">
                <?php foreach ($pendingPayouts as $payout): ?>
                <div class="payout-row" onclick="viewPayoutDetails(<?php echo $payout['user_id']; ?>)">
                    <div class="flex-1">
                        <div class="font-semibold text-gray-800 dark:text-gray-100">
                            <?php echo htmlspecialchars($payout['artist_name']); ?>
                        </div>
                        <div class="text-sm text-gray-500 mt-1">
                            <?php echo htmlspecialchars($payout['Email']); ?> • ID: <?php echo $payout['user_id']; ?>
                        </div>
                        <div class="text-xs text-gray-400 mt-1">
                            <?php echo $payout['transaction_count']; ?> transactions • Revenue: <?php echo htmlspecialchars($payout['revenue_types']); ?>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                            $<?php echo number_format($payout['total_pending_cents'] / 100, 2); ?>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            Latest: <?php echo date('M j, Y', strtotime($payout['latest_transaction'])); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-12 text-gray-500">
                <svg class="w-12 h-12 mx-auto mb-4 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <p class="font-semibold">No pending payouts</p>
                <p class="text-sm mt-1">All artists have been paid or there are no transactions yet</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tab Content: Transaction History -->
<div id="content-transactions" class="tab-content hidden">
    <div class="stat-card">
        <h2 class="text-xl font-bold sk-text-primary mb-6">Recent Transactions</h2>

        <?php if (!empty($recentTransactions)): ?>
            <div class="space-y-2">
                <?php foreach ($recentTransactions as $txn): ?>
                <div class="transaction-row">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-800 dark:text-gray-100">
                                <?php echo htmlspecialchars($txn['artist_name']); ?>
                            </span>
                            <span class="px-2 py-1 rounded text-xs font-bold uppercase <?php
                                echo $txn['revenue_type'] === 'direct' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' :
                                    ($txn['revenue_type'] === 'pool' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300' :
                                    'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300');
                            ?>">
                                <?php echo htmlspecialchars($txn['revenue_type']); ?>
                            </span>
                            <span class="px-2 py-1 rounded text-xs font-bold uppercase <?php
                                echo $txn['status'] === 'pending' ? 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300' :
                                    'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
                            ?>">
                                <?php echo htmlspecialchars($txn['status']); ?>
                            </span>
                        </div>
                        <div class="text-sm text-gray-500 mt-1">
                            <?php echo htmlspecialchars($txn['description'] ?? 'No description'); ?>
                        </div>
                        <div class="text-xs text-gray-400 mt-1">
                            <?php echo date('M j, Y g:ia', strtotime($txn['created_at'])); ?>
                            <?php if (!empty($txn['paid_at'])): ?>
                                • Paid: <?php echo date('M j, Y', strtotime($txn['paid_at'])); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-lg font-bold text-gray-800 dark:text-gray-100">
                            $<?php echo number_format($txn['amount_cents'] / 100, 2); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-12 text-gray-500">
                <p>No transactions found matching your filters</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tab Content: Top Earners -->
<div id="content-earners" class="tab-content hidden">
    <div class="stat-card">
        <h2 class="text-xl font-bold sk-text-primary mb-6">Top 10 Earners (All Time)</h2>

        <?php if (!empty($topEarners)): ?>
            <div class="space-y-3">
                <?php foreach ($topEarners as $index => $earner): ?>
                <div class="payout-row">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg">
                            <?php echo $index + 1; ?>
                        </div>
                        <div>
                            <div class="font-semibold text-gray-800 dark:text-gray-100">
                                <?php echo htmlspecialchars($earner['artist_name']); ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?php echo $earner['transaction_count']; ?> transactions
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                            $<?php echo number_format($earner['total_earned_cents'] / 100, 2); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-12 text-gray-500">
                <p>No earnings data available yet</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Financial Transparency Notice -->
<div class="stat-card mt-8 bg-blue-50 dark:bg-blue-900/20 border-blue-300 dark:border-blue-500/30">
    <div class="flex items-start gap-3">
        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
            <h3 class="font-bold text-blue-800 dark:text-blue-200 mb-2">Financial Transparency (Bible Ch. 13 & 14)</h3>
            <div class="text-sm text-blue-700 dark:text-blue-300 space-y-1">
                <p><strong>• 90/10 Rule (Direct Revenue):</strong> Artists receive 90%, platform retains 10%</p>
                <p><strong>• Monthly Pool:</strong> Distributed based on EQS (Engagement Quality Score)</p>
                <p><strong>• PPV Revenue:</strong> Artists earn per qualifying stream (30+ seconds)</p>
                <p><strong>• Pay Stub:</strong> Artists can view detailed earning breakdowns in their dashboard</p>
                <p><strong>• Stripe Connect:</strong> Payments processed via secure Stripe infrastructure</p>
            </div>
        </div>
    </div>
</div>

</section>
</main>

<?php include __DIR__.'/_footer.php'; ?>

<script>
function switchTab(tab) {
    const tabs = ['pending', 'transactions', 'earners'];
    tabs.forEach(t => {
        const button = document.getElementById(`tab-${t}`);
        const content = document.getElementById(`content-${t}`);

        if (t === tab) {
            button.classList.remove('tab-inactive');
            button.classList.add('tab-active');
            content.classList.remove('hidden');
        } else {
            button.classList.remove('tab-active');
            button.classList.add('tab-inactive');
            content.classList.add('hidden');
        }
    });
}

function viewPayoutDetails(userId) {
    window.location.href = '?user=' + userId + '&status=pending';
}
</script>

</body>
</html>
