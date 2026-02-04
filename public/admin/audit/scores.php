<?php

/**
 * NGN Score Audit Dashboard
 * Admin interface for verifying NGN scores, managing disputes, and reviewing audit results
 * Path: /admin/audit/scores.php
 */

require_once __DIR__ . '/../../../config/bootstrap.php';

use NGN\Config;
use NGN\Lib\Rankings\NGNScoreAuditService;
use NGN\Lib\Rankings\ScoreVerificationService;
use NGN\Lib\API\Response;
use NGN\Lib\API\Auth;
use NGN\Lib\Database\ConnectionFactory;
use PDO;

// Require admin role
$auth = Auth::verify();
if (!$auth || $auth['role'] !== 'admin') {
    header('Location: /login');
    exit;
}

$config = Config::getInstance();
$readConnection = ConnectionFactory::read();
$writeConnection = ConnectionFactory::write();
$auditService = new NGNScoreAuditService($config);
$verificationService = new ScoreVerificationService($config, $auditService);

// Get action from query string
$action = $_GET['action'] ?? 'overview';
$tab = $_GET['tab'] ?? 'dashboard';

// Start output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGN Score Audit Dashboard</title>
    <link rel="stylesheet" href="/css/admin.css">
    <style>
        .audit-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
        }

        .tab-navigation {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
        }

        .tab-nav-item {
            padding: 12px 20px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }

        .tab-nav-item.active {
            color: #1976d2;
            border-bottom-color: #1976d2;
        }

        .tab-nav-item:hover {
            color: #1976d2;
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .kpi-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .kpi-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .kpi-value {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            margin-bottom: 4px;
        }

        .kpi-subtext {
            font-size: 13px;
            color: #666;
        }

        .kpi-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 8px;
        }

        .badge-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-warning {
            background: #fff3e0;
            color: #e65100;
        }

        .badge-danger {
            background: #ffebee;
            color: #c62828;
        }

        .search-panel {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .search-form {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: flex-end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }

        .form-group input,
        .form-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #1976d2;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
        }

        button {
            padding: 8px 16px;
            background: #1976d2;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        button:hover {
            background: #1565c0;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }

        .results-table thead {
            background: #f5f5f5;
            border-bottom: 2px solid #e0e0e0;
        }

        .results-table th {
            padding: 12px 15px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .results-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }

        .results-table tbody tr:hover {
            background: #f9f9f9;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-passed {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-failed {
            background: #ffebee;
            color: #c62828;
        }

        .status-pending {
            background: #e3f2fd;
            color: #1565c0;
        }

        .action-links {
            display: flex;
            gap: 8px;
        }

        .action-links a {
            color: #1976d2;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
        }

        .action-links a:hover {
            text-decoration: underline;
        }

        .detail-panel {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .detail-section {
            margin-bottom: 25px;
        }

        .detail-section h4 {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 20px;
            padding: 10px 0;
            border-bottom: 1px solid #f9f9f9;
        }

        .detail-label {
            font-size: 12px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 13px;
            color: #333;
        }

        .percentage-bar {
            display: inline-block;
            width: 100px;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
            margin-right: 8px;
            vertical-align: middle;
        }

        .percentage-bar-fill {
            height: 100%;
            background: #4caf50;
            transition: width 0.3s;
        }

        .percentage-bar-fill.warning {
            background: #ff9800;
        }

        .percentage-bar-fill.danger {
            background: #f44336;
        }

        .dispute-card {
            background: #fff9c4;
            border-left: 4px solid #fbc02d;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .dispute-card.critical {
            background: #ffebee;
            border-left-color: #f44336;
        }

        .dispute-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .dispute-type {
            font-weight: 600;
            color: #333;
        }

        .dispute-status {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 6px;
            background: rgba(0,0,0,0.1);
            border-radius: 3px;
        }

        .approval-form {
            background: #f5f5f5;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        .approval-form h5 {
            margin: 0 0 15px 0;
            font-size: 14px;
            color: #333;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 12px;
            resize: vertical;
            min-height: 80px;
        }

        textarea:focus {
            outline: none;
            border-color: #1976d2;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .button-secondary {
            background: #757575;
        }

        .button-secondary:hover {
            background: #616161;
        }

        .button-danger {
            background: #f44336;
        }

        .button-danger:hover {
            background: #d32f2f;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .empty-state-text {
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="audit-container">
        <h1>NGN Score Audit Dashboard</h1>
        <p style="color: #666; margin-top: -10px; margin-bottom: 30px;">Verify scores, manage disputes, and review audit reports</p>

        <div class="tab-navigation">
            <button class="tab-nav-item <?= $tab === 'dashboard' ? 'active' : '' ?>" onclick="window.location.href='?tab=dashboard'">Dashboard</button>
            <button class="tab-nav-item <?= $tab === 'history' ? 'active' : '' ?>" onclick="window.location.href='?tab=history'">Score History</button>
            <button class="tab-nav-item <?= $tab === 'verification' ? 'active' : '' ?>" onclick="window.location.href='?tab=verification'">Verification Results</button>
            <button class="tab-nav-item <?= $tab === 'disputes' ? 'active' : '' ?>" onclick="window.location.href='?tab=disputes'">Disputes</button>
            <button class="tab-nav-item <?= $tab === 'corrections' ? 'active' : '' ?>" onclick="window.location.href='?tab=corrections'">Corrections</button>
        </div>

        <?php if ($tab === 'dashboard'): ?>
            <?php
            // Get dashboard metrics
            $stmt = $readConnection->prepare('
                SELECT
                    COUNT(*) as total_scores,
                    COUNT(CASE WHEN calculated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_scores
                FROM ngn_score_history
            ');
            $stmt->execute();
            $scoreMetrics = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $readConnection->prepare('
                SELECT
                    COUNT(*) as total_verifications,
                    SUM(CASE WHEN verification_status = "passed" THEN 1 ELSE 0 END) as passed,
                    SUM(CASE WHEN verification_status = "failed" THEN 1 ELSE 0 END) as failed,
                    AVG(percent_difference) as avg_difference
                FROM ngn_score_verification
            ');
            $stmt->execute();
            $verificationMetrics = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $readConnection->prepare('
                SELECT
                    COUNT(*) as total_disputes,
                    SUM(CASE WHEN status = "open" THEN 1 ELSE 0 END) as open_disputes,
                    SUM(CASE WHEN status = "resolved" THEN 1 ELSE 0 END) as resolved
                FROM ngn_score_disputes
            ');
            $stmt->execute();
            $disputeMetrics = $stmt->fetch(PDO::FETCH_ASSOC);

            $passRate = $verificationMetrics['total_verifications'] > 0
                ? ($verificationMetrics['passed'] / $verificationMetrics['total_verifications'] * 100)
                : 0;

            $passRateBadge = $passRate >= 95 ? 'success' : ($passRate >= 80 ? 'warning' : 'danger');
            ?>

            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-label">Total Scores in System</div>
                    <div class="kpi-value"><?= number_format($scoreMetrics['total_scores']) ?></div>
                    <div class="kpi-subtext"><?= $scoreMetrics['recent_scores'] ?> calculated in last 7 days</div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-label">Verification Pass Rate</div>
                    <div class="kpi-value"><?= number_format($passRate, 1) ?>%</div>
                    <div class="kpi-badge badge-<?= $passRateBadge ?>">
                        <?= $verificationMetrics['total_verifications'] ?> verifications
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-label">Score Discrepancies</div>
                    <div class="kpi-value"><?= $verificationMetrics['failed'] ?></div>
                    <div class="kpi-subtext">Avg difference: <?= number_format($verificationMetrics['avg_difference'], 2) ?>%</div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-label">Open Disputes</div>
                    <div class="kpi-value"><?= $disputeMetrics['open_disputes'] ?></div>
                    <div class="kpi-subtext"><?= $disputeMetrics['resolved'] ?> resolved</div>
                </div>
            </div>

            <!-- Recent Verification Failures -->
            <div class="detail-panel">
                <h3>Recent Verification Failures</h3>
                <?php
                $stmt = $readConnection->prepare('
                    SELECT
                        v.id, v.artist_id, a.name as artist_name, v.original_score,
                        v.recalculated_score, v.percent_difference, v.completed_at
                    FROM ngn_score_verification v
                    LEFT JOIN artists a ON v.artist_id = a.id
                    WHERE v.verification_status = "failed"
                    ORDER BY v.completed_at DESC
                    LIMIT 10
                ');
                $stmt->execute();
                $failures = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($failures)): ?>
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>Artist</th>
                                <th>Original Score</th>
                                <th>Recalculated Score</th>
                                <th>Difference %</th>
                                <th>Verified</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($failures as $failure): ?>
                                <tr>
                                    <td><?= htmlspecialchars($failure['artist_name'] ?? 'Unknown') ?></td>
                                    <td><?= number_format($failure['original_score'], 2) ?></td>
                                    <td><?= number_format($failure['recalculated_score'], 2) ?></td>
                                    <td><?= number_format($failure['percent_difference'], 2) ?>%</td>
                                    <td><?= date('M d, Y', strtotime($failure['completed_at'])) ?></td>
                                    <td>
                                        <div class="action-links">
                                            <a href="?tab=verification&verify_id=<?= $failure['id'] ?>">Review</a>
                                            <a href="?tab=disputes&create_for=<?= $failure['artist_id'] ?>">File Dispute</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">✓</div>
                        <div class="empty-state-text">No verification failures found. Excellent audit status!</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Open Disputes -->
            <div class="detail-panel">
                <h3>Open Artist Disputes</h3>
                <?php
                $stmt = $readConnection->prepare('
                    SELECT
                        d.id, d.artist_id, a.name as artist_name, d.dispute_type,
                        d.severity, d.description, d.created_at
                    FROM ngn_score_disputes d
                    LEFT JOIN artists a ON d.artist_id = a.id
                    WHERE d.status = "open"
                    ORDER BY FIELD(d.severity, "critical", "high", "medium", "low") DESC,
                             d.created_at DESC
                    LIMIT 5
                ');
                $stmt->execute();
                $disputes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($disputes)): ?>
                    <?php foreach ($disputes as $dispute): ?>
                        <div class="dispute-card <?= $dispute['severity'] === 'critical' ? 'critical' : '' ?>">
                            <div class="dispute-header">
                                <div>
                                    <div class="dispute-type"><?= htmlspecialchars($dispute['artist_name']) ?></div>
                                    <div style="font-size: 12px; color: #666; margin-top: 3px;">
                                        <?= htmlspecialchars($dispute['dispute_type']) ?> • <?= date('M d, Y', strtotime($dispute['created_at'])) ?>
                                    </div>
                                </div>
                                <div class="dispute-status"><?= strtoupper($dispute['severity']) ?></div>
                            </div>
                            <div style="font-size: 13px; margin: 10px 0; color: #333;">
                                <?= htmlspecialchars($dispute['description']) ?>
                            </div>
                            <div style="font-size: 12px;">
                                <a href="?tab=disputes&dispute_id=<?= $dispute['id'] ?>">Review & Investigate →</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📋</div>
                        <div class="empty-state-text">No open disputes. All artists satisfied with scores.</div>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'history'): ?>
            <!-- Score History Lookup -->
            <div class="search-panel">
                <form method="GET" class="search-form">
                    <input type="hidden" name="tab" value="history">
                    <div class="form-group">
                        <label>Artist ID or Name</label>
                        <input type="text" name="artist_search" value="<?= htmlspecialchars($_GET['artist_search'] ?? '') ?>" placeholder="Search...">
                    </div>
                    <div class="form-group">
                        <label>Period Type</label>
                        <select name="period_type">
                            <option value="">All Periods</option>
                            <option value="daily" <?= ($_GET['period_type'] ?? '') === 'daily' ? 'selected' : '' ?>>Daily</option>
                            <option value="weekly" <?= ($_GET['period_type'] ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                            <option value="monthly" <?= ($_GET['period_type'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date Range</label>
                        <input type="date" name="from_date" value="<?= htmlspecialchars($_GET['from_date'] ?? '') ?>">
                    </div>
                    <button type="submit">Search</button>
                </form>
            </div>

            <?php
            // Build query
            $query = 'SELECT h.*, a.name as artist_name FROM ngn_score_history h LEFT JOIN artists a ON h.artist_id = a.id WHERE 1=1';
            $params = [];

            if (!empty($_GET['artist_search'])) {
                $query .= ' AND (h.artist_id = ? OR a.name LIKE ?)';
                $params[] = $_GET['artist_search'];
                $params[] = '%' . $_GET['artist_search'] . '%';
            }

            if (!empty($_GET['period_type'])) {
                $query .= ' AND h.period_type = ?';
                $params[] = $_GET['period_type'];
            }

            if (!empty($_GET['from_date'])) {
                $query .= ' AND h.period_start >= ?';
                $params[] = $_GET['from_date'];
            }

            $query .= ' ORDER BY h.calculated_at DESC LIMIT 50';

            $stmt = $readConnection->prepare($query);
            $stmt->execute($params);
            $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <div class="detail-panel">
                <?php if (!empty($scores)): ?>
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>Artist</th>
                                <th>Period</th>
                                <th>Score</th>
                                <th>Spins</th>
                                <th>Plays</th>
                                <th>Engagements</th>
                                <th>Calculated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scores as $score): ?>
                                <tr>
                                    <td><?= htmlspecialchars($score['artist_name'] ?? 'Unknown') ?></td>
                                    <td><?= $score['period_type'] . ': ' . $score['period_start'] . ' to ' . $score['period_end'] ?></td>
                                    <td><strong><?= number_format($score['score_value'], 2) ?></strong></td>
                                    <td><?= $score['spins_count'] ?></td>
                                    <td><?= $score['plays_count'] ?></td>
                                    <td><?= $score['engagements_count'] ?></td>
                                    <td><?= date('M d, Y', strtotime($score['calculated_at'])) ?></td>
                                    <td>
                                        <div class="action-links">
                                            <a href="?tab=history&detail_id=<?= $score['id'] ?>">View</a>
                                            <a href="?tab=verification&verify_score=<?= $score['id'] ?>">Verify</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📊</div>
                        <div class="empty-state-text">No score history found. Try adjusting your search criteria.</div>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'verification'): ?>
            <!-- Verification Results -->
            <?php
            $stmt = $readConnection->prepare('
                SELECT v.*, a.name as artist_name
                FROM ngn_score_verification v
                LEFT JOIN artists a ON v.artist_id = a.id
                ORDER BY v.completed_at DESC
                LIMIT 50
            ');
            $stmt->execute();
            $verifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <div class="detail-panel">
                <?php if (!empty($verifications)): ?>
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>Artist</th>
                                <th>Original Score</th>
                                <th>Recalculated</th>
                                <th>Difference</th>
                                <th>Status</th>
                                <th>Type</th>
                                <th>Completed</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($verifications as $verification): ?>
                                <tr>
                                    <td><?= htmlspecialchars($verification['artist_name'] ?? 'Unknown') ?></td>
                                    <td><?= number_format($verification['original_score'], 2) ?></td>
                                    <td><?= number_format($verification['recalculated_score'], 2) ?></td>
                                    <td><?= number_format($verification['percent_difference'], 2) ?>%</td>
                                    <td><span class="status-badge status-<?= str_replace('_', '-', $verification['verification_status']) ?>"><?= strtoupper($verification['verification_status']) ?></span></td>
                                    <td><?= $verification['verification_type'] ?></td>
                                    <td><?= date('M d, Y', strtotime($verification['completed_at'])) ?></td>
                                    <td>
                                        <div class="action-links">
                                            <a href="?tab=verification&detail_id=<?= $verification['id'] ?>">Review</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">✓</div>
                        <div class="empty-state-text">No verification results yet. Run bulk verification from the dashboard.</div>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'disputes'): ?>
            <!-- Dispute Management -->
            <?php
            $stmt = $readConnection->prepare('
                SELECT d.*, a.name as artist_name
                FROM ngn_score_disputes d
                LEFT JOIN artists a ON d.artist_id = a.id
                ORDER BY FIELD(d.status, "open", "investigating", "resolved", "closed") ASC,
                         FIELD(d.severity, "critical", "high", "medium", "low") ASC
                LIMIT 50
            ');
            $stmt->execute();
            $disputes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <div class="detail-panel">
                <?php if (!empty($disputes)): ?>
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>Artist</th>
                                <th>Type</th>
                                <th>Severity</th>
                                <th>Status</th>
                                <th>Description</th>
                                <th>Filed</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($disputes as $dispute): ?>
                                <tr>
                                    <td><?= htmlspecialchars($dispute['artist_name'] ?? 'Unknown') ?></td>
                                    <td><?= str_replace('_', ' ', ucfirst($dispute['dispute_type'])) ?></td>
                                    <td><span class="status-badge status-<?= strtolower($dispute['severity']) ?>" style="background: <?= ['critical' => '#ffebee', 'high' => '#fff3e0', 'medium' => '#f3e5f5', 'low' => '#e8f5e9'][$dispute['severity']] ?? '#f5f5f5' ?>; color: <?= ['critical' => '#c62828', 'high' => '#e65100', 'medium' => '#6a1b9a', 'low' => '#2e7d32'][$dispute['severity']] ?? '#666' ?>"><?= strtoupper($dispute['severity']) ?></span></td>
                                    <td><span class="status-badge" style="background: #e3f2fd; color: #1565c0;"><?= strtoupper($dispute['status']) ?></span></td>
                                    <td><?= htmlspecialchars(substr($dispute['description'], 0, 50)) ?>...</td>
                                    <td><?= date('M d, Y', strtotime($dispute['created_at'])) ?></td>
                                    <td>
                                        <div class="action-links">
                                            <a href="?tab=disputes&detail_id=<?= $dispute['id'] ?>">Details</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🛡️</div>
                        <div class="empty-state-text">No disputes filed. Artists are satisfied with score verification.</div>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'corrections'): ?>
            <!-- Score Corrections -->
            <?php
            $stmt = $readConnection->prepare('
                SELECT c.*, a.name as artist_name, req.name as requested_by_name, app.name as approved_by_name
                FROM ngn_score_corrections c
                LEFT JOIN artists a ON c.artist_id = a.id
                LEFT JOIN users req ON c.requested_by = req.id
                LEFT JOIN users app ON c.approved_by = app.id
                ORDER BY c.approved_at DESC
                LIMIT 50
            ');
            $stmt->execute();
            $corrections = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <div class="detail-panel">
                <?php if (!empty($corrections)): ?>
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>Artist</th>
                                <th>Type</th>
                                <th>Original</th>
                                <th>Corrected</th>
                                <th>Adjustment</th>
                                <th>Approved By</th>
                                <th>Approved</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($corrections as $correction): ?>
                                <tr>
                                    <td><?= htmlspecialchars($correction['artist_name'] ?? 'Unknown') ?></td>
                                    <td><?= str_replace('_', ' ', ucfirst($correction['correction_type'])) ?></td>
                                    <td><?= number_format($correction['original_score'], 2) ?></td>
                                    <td><?= number_format($correction['corrected_score'], 2) ?></td>
                                    <td><?= ($correction['adjustment_amount'] > 0 ? '+' : '') . number_format($correction['adjustment_amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($correction['approved_by_name'] ?? 'Unknown') ?></td>
                                    <td><?= date('M d, Y', strtotime($correction['approved_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📝</div>
                        <div class="empty-state-text">No score corrections have been made.</div>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>
</body>
</html>
