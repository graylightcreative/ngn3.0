<?php
/**
 * Creator Earnings Dashboard
 * Bible Ch. 13 & 14 - Royalty reporting, statements, and payout management
 * Shows estimated earnings, transaction history, and pending payouts
 */

require_once __DIR__ . '/../../lib/bootstrap.php';

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;
use NGN\Lib\Auth\TokenService;

// Check authentication
$config = new Config();
$tokenService = new TokenService($config);

// Get token from header
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
    header('HTTP/1.1 401 Unauthorized');
    die('Authentication required');
}

try {
    $token = $matches[1];
    $claims = $tokenService->decode($token);
    $userId = $claims['user_id'] ?? null;

    if (!$userId) {
        header('HTTP/1.1 401 Unauthorized');
        die('Invalid token');
    }
} catch (\Throwable $e) {
    header('HTTP/1.1 401 Unauthorized');
    die('Invalid token');
}

$pdo = ConnectionFactory::read($config);

// Fetch user info
$stmt = $pdo->prepare("SELECT * FROM ngn_2025.users WHERE Id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('HTTP/1.1 404 Not Found');
    die('User not found');
}

// Get balance
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN source_type = 'spark_tip' THEN amount_net ELSE 0 END), 0) as spark_earnings,
        COALESCE(SUM(CASE WHEN source_type = 'eqs_distribution' THEN amount_net ELSE 0 END), 0) as eqs_earnings,
        COALESCE(SUM(CASE WHEN source_type = 'rights_payment' THEN amount_net ELSE 0 END), 0) as rights_earnings
    FROM cdm_royalty_transactions
    WHERE to_user_id = ? AND status = 'completed'
");
$stmt->execute([$userId]);
$earnings = $stmt->fetch(PDO::FETCH_ASSOC);

$total_earnings = $earnings['spark_earnings'] + $earnings['eqs_earnings'] + $earnings['rights_earnings'];

// Get pending balance
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount_net), 0) as pending_amount
    FROM cdm_royalty_transactions
    WHERE to_user_id = ? AND status = 'pending'
");
$stmt->execute([$userId]);
$pending = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent transactions
$stmt = $pdo->prepare("
    SELECT *,
        CASE source_type
            WHEN 'spark_tip' THEN 'Fan Spark Tip'
            WHEN 'eqs_distribution' THEN 'EQS Distribution'
            WHEN 'rights_payment' THEN 'Rights Payment'
            ELSE source_type
        END as source_label
    FROM cdm_royalty_transactions
    WHERE to_user_id = ?
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->execute([$userId]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payout requests
$stmt = $pdo->prepare("
    SELECT *
    FROM cdm_royalty_transactions
    WHERE from_user_id = ? AND source_type = 'payout'
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute([$userId]);
$payouts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get balance record for user
$stmt = $pdo->prepare("
    SELECT * FROM cdm_royalty_balances
    WHERE user_id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$balance_record = $stmt->fetch(PDO::FETCH_ASSOC);

$pageTitle = 'Creator Earnings - ' . htmlspecialchars($user['Title']);
$currency = '$';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --bs-primary: #1DB954;
            --bs-dark-bg: #0b1020;
            --bs-secondary-bg: #141b2e;
        }
        body {
            background: var(--bs-dark-bg);
            color: #f8fafc;
        }
        .card {
            background: var(--bs-secondary-bg);
            border: 1px solid rgba(148, 163, 184, 0.12);
        }
        .earnings-card {
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        .earnings-card.primary {
            background: linear-gradient(135deg, #1DB954 0%, #1ed760 100%);
            color: white;
        }
        .earnings-card.secondary {
            background: rgba(29, 185, 84, 0.1);
            border: 1px solid rgba(29, 185, 84, 0.3);
        }
        .transaction-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
        }
        .transaction-item:last-child {
            border-bottom: none;
        }
        .badge-pending {
            background-color: #f59e0b;
        }
        .badge-completed {
            background-color: #10b981;
        }
        .badge-failed {
            background-color: #ef4444;
        }
        .chart-placeholder {
            height: 300px;
            background: rgba(148, 163, 184, 0.05);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
        }
        .icon-spark { color: #fbbf24; }
        .icon-eqs { color: #06b6d4; }
        .icon-rights { color: #8b5cf6; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="h3 mb-1">
                    <i class="bi bi-wallet2"></i> Creator Earnings
                </h1>
                <p class="text-muted">View your earnings, royalties, and statements</p>
            </div>
        </div>

        <!-- Earnings Overview -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="earnings-card primary">
                    <small class="d-block opacity-75">Total Earnings (Completed)</small>
                    <h2 class="mb-0 mt-2"><?= $currency ?><?= number_format($total_earnings, 2) ?></h2>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="earnings-card secondary">
                    <small class="d-block text-muted">Pending Payout</small>
                    <h2 class="mb-0 mt-2 text-warning"><?= $currency ?><?= number_format($pending['pending_amount'], 2) ?></h2>
                    <small class="text-muted d-block mt-2">Will be available after 7-day hold</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="earnings-card secondary">
                    <small class="d-block text-muted">Available Balance</small>
                    <h2 class="mb-0 mt-2"><?= $currency ?><?= number_format(
                        ($balance_record['balance_available'] ?? 0), 2
                    ) ?></h2>
                    <small class="text-muted d-block mt-2">Ready to request payout</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#payoutModal">
                            <i class="bi bi-arrow-up-right"></i> Request Payout
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Earnings Breakdown -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-transparent border-bottom">
                        <h5 class="mb-0">Earnings by Source</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><i class="bi bi-star-fill icon-spark"></i> Fan Spark Tips</span>
                                <strong><?= $currency ?><?= number_format($earnings['spark_earnings'], 2) ?></strong>
                            </div>
                            <div class="progress" role="progressbar">
                                <div class="progress-bar bg-warning" style="width: <?= $total_earnings > 0 ? ($earnings['spark_earnings'] / $total_earnings * 100) : 0 ?>%"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><i class="bi bi-music-note-beamed icon-eqs"></i> EQS Distribution</span>
                                <strong><?= $currency ?><?= number_format($earnings['eqs_earnings'], 2) ?></strong>
                            </div>
                            <div class="progress" role="progressbar">
                                <div class="progress-bar bg-info" style="width: <?= $total_earnings > 0 ? ($earnings['eqs_earnings'] / $total_earnings * 100) : 0 ?>%"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><i class="bi bi-shield-check icon-rights"></i> Rights Payments</span>
                                <strong><?= $currency ?><?= number_format($earnings['rights_earnings'], 2) ?></strong>
                            </div>
                            <div class="progress" role="progressbar">
                                <div class="progress-bar bg-purple" style="width: <?= $total_earnings > 0 ? ($earnings['rights_earnings'] / $total_earnings * 100) : 0 ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-transparent border-bottom">
                        <h5 class="mb-0">Monthly Trends</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-placeholder">
                            <small class="text-muted">Monthly earnings chart coming soon</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-transparent border-bottom">
                        <h5 class="mb-0">Recent Transactions</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($transactions)): ?>
                        <p class="text-muted mb-0">No transactions yet</p>
                        <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                        <div class="transaction-item">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong><?= htmlspecialchars($tx['source_label']) ?></strong>
                                    <small class="text-muted d-block"><?= date('M d, Y', strtotime($tx['created_at'])) ?></small>
                                </div>
                                <div class="text-end">
                                    <strong class="text-success"><?= $currency ?><?= number_format($tx['amount_net'], 2) ?></strong>
                                    <br>
                                    <span class="badge badge-<?= $tx['status'] ?>"><?= ucfirst($tx['status']) ?></span>
                                </div>
                            </div>
                            <small class="text-muted">
                                Gross: <?= $currency ?><?= number_format($tx['amount_gross'], 2) ?> |
                                Fee: <?= $currency ?><?= number_format($tx['platform_fee'], 2) ?>
                                <?php if ($tx['entity_type']): ?>
                                    | <?= htmlspecialchars(ucfirst($tx['entity_type'])) ?>
                                <?php endif; ?>
                            </small>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Payout History -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-transparent border-bottom">
                        <h5 class="mb-0">Payout History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($payouts)): ?>
                        <p class="text-muted mb-0">No payouts yet</p>
                        <?php else: ?>
                        <?php foreach ($payouts as $payout): ?>
                        <div class="transaction-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong class="text-success">Payout Sent</strong>
                                    <small class="text-muted d-block"><?= date('M d, Y', strtotime($payout['created_at'])) ?></small>
                                </div>
                                <div class="text-end">
                                    <strong><?= $currency ?><?= number_format($payout['amount_net'], 2) ?></strong>
                                    <br>
                                    <span class="badge badge-<?= $payout['status'] ?>"><?= ucfirst($payout['status']) ?></span>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-1">
                                Method: <?= htmlspecialchars(ucfirst($payout['payment_method'])) ?>
                            </small>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Platform Fees Info -->
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle"></i>
                    <strong>Platform Fees:</strong> NGN charges a 10% fee on all earnings to support platform operations,
                    security, and artist growth initiatives. Your net earnings reflect this fee deduction.
                </div>
            </div>
        </div>
    </div>

    <!-- Payout Request Modal -->
    <div class="modal fade" id="payoutModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">Request Payout</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning mb-3">
                        <i class="bi bi-exclamation-triangle"></i>
                        Available balance: <strong><?= $currency ?><?= number_format(
                            ($balance_record['balance_available'] ?? 0), 2
                        ) ?></strong>
                    </div>
                    <div class="mb-3">
                        <label for="payoutAmount" class="form-label">Amount to Payout</label>
                        <input type="number" class="form-control bg-secondary border-secondary" id="payoutAmount"
                               placeholder="0.00" step="0.01" min="0" max="<?= $balance_record['balance_available'] ?? 0 ?>">
                    </div>
                    <div class="mb-3">
                        <label for="payoutMethod" class="form-label">Payout Method</label>
                        <select class="form-select bg-secondary border-secondary" id="payoutMethod">
                            <option value="">Select method...</option>
                            <option value="stripe">Stripe Connect</option>
                            <option value="bank_transfer">Bank Transfer (ACH)</option>
                            <option value="paypal">PayPal</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="submitPayout()">Request Payout</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function submitPayout() {
            const amount = parseFloat(document.getElementById('payoutAmount').value);
            const method = document.getElementById('payoutMethod').value;

            if (!amount || amount <= 0) {
                alert('Please enter a valid amount');
                return;
            }
            if (!method) {
                alert('Please select a payout method');
                return;
            }

            const token = localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');

            fetch('/api/v1/royalty/request-payout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({
                    amount: amount,
                    payout_method: method
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Payout request submitted! You will receive funds within 5-7 business days.');
                    bootstrap.Modal.getInstance(document.getElementById('payoutModal')).hide();
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => alert('Error: ' + err));
        }
    </script>
</body>
</html>
