<?php
/**
 * Artist/Label Rights Management Dashboard
 * Bible Ch. 14 - Rights Ledger & Ownership Infrastructure
 * Displays ownership splits and allows acceptance/dispute
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

// Fetch all rights ledgers for this user
$stmt = $pdo->prepare("
    SELECT DISTINCT l.*,
        t.title as track_title,
        r.title as release_title,
        COUNT(s.id) as split_count,
        SUM(CASE WHEN s.accepted_at IS NOT NULL THEN 1 ELSE 0 END) as accepted_count
    FROM cdm_rights_ledger l
    LEFT JOIN cdm_tracks t ON l.track_id = t.id
    LEFT JOIN cdm_releases r ON l.release_id = r.id
    INNER JOIN cdm_rights_splits s ON l.id = s.ledger_id
    WHERE s.user_id = ?
    GROUP BY l.id
    ORDER BY l.updated_at DESC
");
$stmt->execute([$userId]);
$ledgers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Rights Management - ' . htmlspecialchars($user['Title']);
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
        .badge-status {
            font-size: 0.85rem;
            padding: 0.5rem 0.75rem;
        }
        .badge-active { background-color: #10b981; }
        .badge-pending { background-color: #f59e0b; }
        .badge-draft { background-color: #6b7280; }
        .badge-disputed { background-color: #ef4444; }
        .split-item {
            padding: 1rem;
            border: 1px solid rgba(148, 163, 184, 0.12);
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .split-item.accepted {
            background: rgba(16, 185, 129, 0.1);
            border-color: #10b981;
        }
        .btn-action {
            font-size: 0.875rem;
            padding: 0.375rem 0.75rem;
        }
        .progress-container {
            background: rgba(148, 163, 184, 0.1);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="h3 mb-1">
                    <i class="bi bi-shield-check"></i> Rights Management
                </h1>
                <p class="text-muted">Manage ownership splits and verify artist rights</p>
            </div>
        </div>

        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">Total Ledgers</small>
                        <h3 class="mb-0"><?= count($ledgers) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">Active</small>
                        <h3 class="mb-0"><?= count(array_filter($ledgers, fn($l) => $l['status'] == 'active')) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">Pending Review</small>
                        <h3 class="mb-0"><?= count(array_filter($ledgers, fn($l) => $l['status'] == 'pending')) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">Disputed</small>
                        <h3 class="mb-0"><?= count(array_filter($ledgers, fn($l) => $l['status'] == 'disputed')) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rights Ledgers -->
        <div class="row">
            <div class="col-12">
                <?php if (empty($ledgers)): ?>
                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle"></i> You don't have any rights ledgers yet.
                </div>
                <?php else: ?>

                <?php foreach ($ledgers as $ledger): ?>
                <div class="card mb-4">
                    <!-- Header -->
                    <div class="card-header bg-transparent border-bottom">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5 class="mb-1">
                                    <?= htmlspecialchars($ledger['track_title'] ?? $ledger['title'] ?? 'Untitled Track') ?>
                                </h5>
                                <small class="text-muted">
                                    ISRC: <code><?= htmlspecialchars($ledger['isrc'] ?? 'N/A') ?></code>
                                </small>
                                <?php if ($ledger['release_title']): ?>
                                    <small class="text-muted d-block">
                                        Release: <?= htmlspecialchars($ledger['release_title']) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <span class="badge badge-status badge-<?= $ledger['status'] ?>">
                                    <?= ucfirst($ledger['status']) ?>
                                </span>
                                <?php if ($ledger['is_royalty_eligible']): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle"></i> Royalty Eligible
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="card-body">
                        <!-- Splits Progress -->
                        <?php
                        $acceptancePercent = 0;
                        if ($ledger['split_count'] > 0) {
                            $acceptancePercent = round(($ledger['accepted_count'] / $ledger['split_count']) * 100);
                        }
                        ?>
                        <div class="progress-container mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted">Split Acceptance Progress</small>
                                <small class="text-muted"><?= $ledger['accepted_count'] ?>/<?= $ledger['split_count'] ?></small>
                            </div>
                            <div class="progress" role="progressbar">
                                <div class="progress-bar" style="width: <?= $acceptancePercent ?>%"></div>
                            </div>
                        </div>

                        <!-- Splits -->
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT s.*, u.Title as user_name, u.Email as user_email
                            FROM cdm_rights_splits s
                            LEFT JOIN ngn_2025.users u ON s.user_id = u.Id
                            WHERE s.ledger_id = ?
                            ORDER BY s.percentage DESC
                        ");
                        $stmt->execute([$ledger['id']]);
                        $splits = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>

                        <div class="mb-3">
                            <h6 class="mb-2">Ownership Splits</h6>
                            <?php foreach ($splits as $split): ?>
                            <div class="split-item <?= $split['accepted_at'] ? 'accepted' : '' ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?= htmlspecialchars($split['user_name'] ?? 'Unknown User') ?></strong>
                                        <small class="text-muted d-block"><?= htmlspecialchars($split['user_email'] ?? '') ?></small>
                                        <small class="text-muted d-block">
                                            Role: <span class="badge bg-secondary"><?= ucfirst(str_replace('_', ' ', $split['role'])) ?></span>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <h6 class="mb-0"><?= number_format($split['percentage'], 2) ?>%</h6>
                                        <?php if ($split['accepted_at']): ?>
                                            <small class="text-success">
                                                <i class="bi bi-check-circle"></i> Accepted
                                            </small>
                                        <?php else: ?>
                                            <small class="text-warning">
                                                <i class="bi bi-clock"></i> Pending
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <?php if ($split['user_id'] == $userId && !$split['accepted_at']): ?>
                                <div class="mt-2">
                                    <button class="btn btn-sm btn-success btn-action" onclick="acceptSplit(<?= $split['id'] ?>)">
                                        <i class="bi bi-check"></i> Accept
                                    </button>
                                    <button class="btn btn-sm btn-danger btn-action" onclick="showDisputeForm(<?= $ledger['id'] ?>)">
                                        <i class="bi bi-exclamation-circle"></i> Dispute
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Ledger Info -->
                        <hr>
                        <small class="text-muted d-block">
                            Created: <?= date('M d, Y', strtotime($ledger['created_at'])) ?>
                            <?php if ($ledger['disputed_at']): ?>
                                | Disputed: <?= date('M d, Y', strtotime($ledger['disputed_at'])) ?>
                                <?php if ($ledger['disputed_reason']): ?>
                                    <br><strong>Dispute Reason:</strong> <?= htmlspecialchars(substr($ledger['disputed_reason'], 0, 100)) ?>...
                                <?php endif; ?>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Dispute Modal -->
    <div class="modal fade" id="disputeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">Dispute Ledger</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="disputeReason" class="form-label">Dispute Reason</label>
                        <textarea class="form-control bg-secondary border-secondary" id="disputeReason" rows="4" placeholder="Explain why you're disputing this ledger..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="submitDispute()">Submit Dispute</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentDisputeLedgerId = null;

        function acceptSplit(splitId) {
            if (!confirm('Accept this rights split?')) return;

            const token = localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');

            fetch('/api/v1/rights/split/accept', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({ split_id: splitId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Split accepted successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => alert('Error: ' + err));
        }

        function showDisputeForm(ledgerId) {
            currentDisputeLedgerId = ledgerId;
            document.getElementById('disputeReason').value = '';
            new bootstrap.Modal(document.getElementById('disputeModal')).show();
        }

        function submitDispute() {
            const reason = document.getElementById('disputeReason').value.trim();
            if (!reason) {
                alert('Please provide a dispute reason');
                return;
            }

            const token = localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');

            fetch('/api/v1/rights/ledger/dispute', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({
                    ledger_id: currentDisputeLedgerId,
                    reason: reason
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Dispute submitted. Admin will review shortly.');
                    bootstrap.Modal.getInstance(document.getElementById('disputeModal')).hide();
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
