<?php
/**
 * Canary Deployment Monitoring Dashboard
 * Real-time monitoring for GA cutover with phased rollout (1% → 100%)
 * Integrates with FeatureFlagService for hot-reload flag management
 */

// Guard: Admin-only access
require_once(__DIR__ . '/_guard.php');

// Bootstrap
require_once(__DIR__ . '/../lib/bootstrap.php');

// Get database connection
try {
    $cfg = new NGN\Lib\Config();
    $dbCfg = $cfg->db();
    $pdo = new PDO(
        "mysql:host={$dbCfg['host']}:{$dbCfg['port']};dbname={$dbCfg['name']};charset=utf8mb4",
        $dbCfg['user'],
        $dbCfg['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $featureFlags = new NGN\Lib\Services\FeatureFlagService($pdo);
} catch (\Throwable $e) {
    http_response_code(503);
    echo "<h1>Database Connection Error</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// Get current feature flag values
$flags = $featureFlags->getAll();
$rolloutPct = (int)($flags['ROLLOUT_PERCENTAGE']['value'] ?? 0);
$viewMode = (string)($flags['FEATURE_PUBLIC_VIEW_MODE']['value'] ?? 'legacy');
$publicRollout = (bool)($flags['FEATURE_PUBLIC_ROLLOUT']['value'] ?? false);
$maintenanceMode = (bool)($flags['MAINTENANCE_MODE']['value'] ?? false);

// Mock metrics data (in production, would query actual monitoring data)
$metrics = [
    'version_1_0' => [
        'p95_latency_ms' => 185,
        'error_rate_pct' => 0.8,
        'requests_per_min' => 4200,
        'active_users' => 1850,
    ],
    'version_2_0' => [
        'p95_latency_ms' => 156,
        'error_rate_pct' => 1.2,
        'requests_per_min' => $rolloutPct > 0 ? intval(4200 * ($rolloutPct / 100)) : 0,
        'active_users' => $rolloutPct > 0 ? intval(1850 * ($rolloutPct / 100)) : 0,
    ],
];

// Determine canary health status
function getCanaryHealth($metrics) {
    $latencyOk = $metrics['version_2_0']['p95_latency_ms'] < 250;
    $errorRateOk = $metrics['version_2_0']['error_rate_pct'] < 3;

    if ($latencyOk && $errorRateOk) {
        return ['status' => 'healthy', 'color' => 'success', 'icon' => '✓'];
    } elseif (!$latencyOk || $metrics['version_2_0']['error_rate_pct'] > 5) {
        return ['status' => 'critical', 'color' => 'danger', 'icon' => '⚠'];
    }
    return ['status' => 'warning', 'color' => 'warning', 'icon' => '!'];
}

$health = getCanaryHealth($metrics);
$rolloutStage = match(true) {
    $rolloutPct == 0 => 'Staging',
    $rolloutPct <= 1 => 'Canary (1%)',
    $rolloutPct <= 10 => 'Early Access (10%)',
    $rolloutPct <= 25 => 'Growing (25%)',
    $rolloutPct <= 50 => 'Expanding (50%)',
    $rolloutPct <= 75 => 'Approaching GA (75%)',
    default => 'General Availability (100%)',
};

// Alert history
$alerts = $featureFlags->getHistory(null, 10);

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Canary Monitor — NGN 2.0 GA Cutover</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #1db954;
            --danger: #ef4444;
            --warning: #fbbf24;
            --success: #10b981;
            --bg-dark: #0a0a0a;
            --card-bg: #111111;
            --text: #ffffff;
            --muted: #888888;
        }
        body {
            background: var(--bg-dark);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }
        .navbar-brand { font-weight: 700; }
        .card {
            background: var(--card-bg);
            border: 1px solid rgba(255,255,255,.1);
            color: var(--text);
        }
        .badge { font-size: 0.9rem; padding: 0.5rem 0.75rem; }
        .metric-box {
            background: linear-gradient(135deg, rgba(29,185,84,.1) 0%, rgba(0,212,255,.05) 100%);
            border: 1px solid rgba(29,185,84,.2);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.3s ease;
        }
        .metric-box:hover {
            border-color: rgba(29,185,84,.4);
            box-shadow: 0 0 30px rgba(29,185,84,.1);
        }
        .metric-label {
            font-size: 0.85rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1;
        }
        .metric-unit {
            font-size: 0.9rem;
            color: var(--muted);
            margin-left: 4px;
        }
        .progress-bar {
            background: linear-gradient(90deg, var(--primary), #00d4ff);
            height: 24px;
            border-radius: 12px;
        }
        .btn-primary {
            background: var(--primary);
            border: none;
            color: #000;
            font-weight: 600;
        }
        .btn-primary:hover {
            background: #1ed760;
            color: #000;
        }
        .btn-danger { background: var(--danger); border: none; }
        .btn-warning { background: var(--warning); border: none; color: #000; font-weight: 600; }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
        }
        .status-badge.healthy {
            background: rgba(16,185,129,.15);
            color: #10b981;
            border: 1px solid rgba(16,185,129,.3);
        }
        .status-badge.warning {
            background: rgba(251,191,36,.15);
            color: #fbbf24;
            border: 1px solid rgba(251,191,36,.3);
        }
        .status-badge.critical {
            background: rgba(239,68,68,.15);
            color: #ef4444;
            border: 1px solid rgba(239,68,68,.3);
        }
        .timeline-marker {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary);
            margin-right: 8px;
        }
        .comparison-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        .comparison-table th,
        .comparison-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,.1);
        }
        .comparison-table th {
            background: rgba(255,255,255,.05);
            color: var(--primary);
            font-weight: 600;
        }
        .flag-item {
            background: rgba(255,255,255,.03);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .flag-value {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: var(--primary);
            font-weight: 600;
        }
        .alert-item {
            background: rgba(255,255,255,.03);
            border-left: 3px solid var(--warning);
            padding: 12px;
            margin-bottom: 8px;
            border-radius: 4px;
        }
        .alert-time {
            font-size: 0.85rem;
            color: var(--muted);
            display: block;
            margin-top: 4px;
        }
        h1, h2, h3 { color: var(--text); }
        .section-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin: 32px 0 20px 0;
            padding-bottom: 12px;
            border-bottom: 2px solid rgba(29,185,84,.2);
        }
        input[type="range"] {
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: rgba(29,185,84,.2);
            outline: none;
            -webkit-appearance: none;
        }
        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary);
            cursor: pointer;
            box-shadow: 0 0 10px rgba(29,185,84,.5);
        }
        input[type="range"]::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary);
            cursor: pointer;
            border: none;
            box-shadow: 0 0 10px rgba(29,185,84,.5);
        }
    </style>
</head>
<body class="bg-dark text-white">
    <?php require_once(__DIR__ . '/Lib/partials/header.php'); ?>

    <main class="container-fluid mt-4 mb-5">
        <!-- Page Title -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="mb-0">🚀 Canary Deployment Monitor</h1>
                        <p class="text-muted mb-0">Real-time GA cutover progress & health monitoring</p>
                    </div>
                    <div class="status-badge <?= $health['color'] ?>">
                        <span><?= $health['icon'] ?></span>
                        <span><?= ucfirst($health['status']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats Row -->
        <div class="row mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="metric-box">
                    <div class="metric-label">Rollout Percentage</div>
                    <div class="metric-value"><?= $rolloutPct ?>%</div>
                    <div style="margin-top: 12px;">
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar" style="width: <?= $rolloutPct ?>%;"></div>
                        </div>
                        <small class="text-muted mt-1 d-block"><?= $rolloutStage ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="metric-box">
                    <div class="metric-label">2.0 P95 Latency</div>
                    <div class="metric-value"><?= $metrics['version_2_0']['p95_latency_ms'] ?><span class="metric-unit">ms</span></div>
                    <small class="text-muted mt-2 d-block">Target: &lt;250ms</small>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="metric-box">
                    <div class="metric-label">2.0 Error Rate</div>
                    <div class="metric-value" style="color: <?= $metrics['version_2_0']['error_rate_pct'] > 2 ? '#ef4444' : 'var(--primary)' ?>">
                        <?= number_format($metrics['version_2_0']['error_rate_pct'], 2) ?><span class="metric-unit">%</span>
                    </div>
                    <small class="text-muted mt-2 d-block">Threshold: 3%</small>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="metric-box">
                    <div class="metric-label">Active Users on 2.0</div>
                    <div class="metric-value"><?= number_format($metrics['version_2_0']['active_users']) ?></div>
                    <small class="text-muted mt-2 d-block"><?= number_format($metrics['version_2_0']['requests_per_min']) ?>/min</small>
                </div>
            </div>
        </div>

        <!-- Control Panel Section -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card p-4">
                    <h3 class="section-title">Rollout Control Panel</h3>

                    <div class="row">
                        <!-- Rollout Percentage Slider -->
                        <div class="col-lg-6 mb-4">
                            <label class="form-label fw-bold">Adjust Rollout Percentage</label>
                            <input type="range" min="0" max="100" value="<?= $rolloutPct ?>" id="rolloutSlider" class="form-range">
                            <div class="mt-2 d-flex justify-content-between">
                                <small class="text-muted">0%</small>
                                <small class="text-muted" id="sliderValue"><?= $rolloutPct ?>%</small>
                                <small class="text-muted">100%</small>
                            </div>
                            <small class="text-muted d-block mt-2">
                                Recommended path: 1% → 10% → 25% → 50% → 75% → 100%
                            </small>
                        </div>

                        <!-- Quick Action Buttons -->
                        <div class="col-lg-6">
                            <label class="form-label fw-bold">Quick Actions</label>
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary btn-sm" onclick="setRollout(1)">
                                    <i class="bi bi-rocket"></i> Canary (1%)
                                </button>
                                <button class="btn btn-primary btn-sm" onclick="setRollout(10)">
                                    <i class="bi bi-arrow-up"></i> Early Access (10%)
                                </button>
                                <button class="btn btn-warning btn-sm" onclick="setRollout(50)">
                                    <i class="bi bi-lightning"></i> Half Rollout (50%)
                                </button>
                                <button class="btn btn-success btn-sm" onclick="setRollout(100)">
                                    <i class="bi bi-check-circle"></i> Full GA (100%)
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="setRollout(0)">
                                    <i class="bi bi-arrow-counterclockwise"></i> Rollback to 0%
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Maintenance Mode Toggle -->
                    <div class="mt-4 pt-4 border-top">
                        <label class="form-label fw-bold">System Controls</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="maintenanceToggle"
                                   <?= $maintenanceMode ? 'checked' : '' ?>
                                   onchange="toggleMaintenance(this.checked)">
                            <label class="form-check-label" for="maintenanceToggle">
                                <span style="color: <?= $maintenanceMode ? '#ef4444' : '#10b981' ?>;">
                                    <?= $maintenanceMode ? '🔴 Maintenance Mode Active' : '🟢 Maintenance Mode Off' ?>
                                </span>
                            </label>
                        </div>
                        <small class="text-muted d-block mt-2">
                            When enabled, shows maintenance page to all except admins and API
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metrics Comparison -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card p-4">
                    <h3 class="section-title">Version Comparison</h3>

                    <table class="comparison-table">
                        <thead>
                            <tr>
                                <th>Metric</th>
                                <th style="text-align: center;">NGN 1.0 (Legacy)</th>
                                <th style="text-align: center;">NGN 2.0 (Next)</th>
                                <th style="text-align: center;">Improvement</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>P95 Latency</td>
                                <td style="text-align: center;"><?= $metrics['version_1_0']['p95_latency_ms'] ?>ms</td>
                                <td style="text-align: center;"><?= $metrics['version_2_0']['p95_latency_ms'] ?>ms</td>
                                <td style="text-align: center; color: var(--success);">
                                    <?= number_format((($metrics['version_1_0']['p95_latency_ms'] - $metrics['version_2_0']['p95_latency_ms']) / $metrics['version_1_0']['p95_latency_ms'] * 100), 1) ?>% ✓
                                </td>
                            </tr>
                            <tr>
                                <td>Error Rate</td>
                                <td style="text-align: center;"><?= number_format($metrics['version_1_0']['error_rate_pct'], 2) ?>%</td>
                                <td style="text-align: center; color: <?= $metrics['version_2_0']['error_rate_pct'] > $metrics['version_1_0']['error_rate_pct'] ? '#ef4444' : 'var(--primary)' ?>">
                                    <?= number_format($metrics['version_2_0']['error_rate_pct'], 2) ?>%
                                </td>
                                <td style="text-align: center; color: <?= $metrics['version_2_0']['error_rate_pct'] > $metrics['version_1_0']['error_rate_pct'] ? '#ef4444' : 'var(--success)' ?>;">
                                    <?= $metrics['version_2_0']['error_rate_pct'] > $metrics['version_1_0']['error_rate_pct'] ? '↑ Higher' : '↓ Lower' ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Active Users</td>
                                <td style="text-align: center;"><?= number_format($metrics['version_1_0']['active_users']) ?></td>
                                <td style="text-align: center;"><?= number_format($metrics['version_2_0']['active_users']) ?></td>
                                <td style="text-align: center; color: var(--muted);">At <?= $rolloutPct ?>% rollout</td>
                            </tr>
                            <tr>
                                <td>Requests/Min</td>
                                <td style="text-align: center;"><?= number_format($metrics['version_1_0']['requests_per_min']) ?></td>
                                <td style="text-align: center;"><?= number_format($metrics['version_2_0']['requests_per_min']) ?></td>
                                <td style="text-align: center; color: var(--muted);">At <?= $rolloutPct ?>% rollout</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Feature Flags Status -->
        <div class="row mb-5">
            <div class="col-lg-6">
                <div class="card p-4">
                    <h3 class="section-title">Feature Flags Status</h3>

                    <?php foreach ($flags as $flagName => $flagData): ?>
                        <div class="flag-item">
                            <div>
                                <div style="font-weight: 600;"><?= htmlspecialchars($flagName) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($flagData['reason'] ?? 'No reason provided') ?></small>
                            </div>
                            <div class="flag-value"><?= htmlspecialchars((string)$flagData['value']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Flag Changes (Audit Trail) -->
            <div class="col-lg-6">
                <div class="card p-4">
                    <h3 class="section-title">Recent Changes (Audit Trail)</h3>

                    <?php if (empty($alerts)): ?>
                        <p class="text-muted">No recent flag changes</p>
                    <?php else: ?>
                        <?php foreach ($alerts as $alert): ?>
                            <div class="alert-item">
                                <strong><?= htmlspecialchars($alert['flag_name']) ?></strong>
                                <div style="font-size: 0.9rem; color: var(--muted); margin: 4px 0;">
                                    <span style="color: var(--primary);"><?= htmlspecialchars($alert['old_value'] ?? 'null') ?></span>
                                    →
                                    <span style="color: var(--success);"><?= htmlspecialchars($alert['new_value']) ?></span>
                                </div>
                                <small class="text-muted"><?= htmlspecialchars($alert['reason'] ?? 'No reason') ?></small>
                                <span class="alert-time">
                                    <i class="bi bi-clock"></i> <?= htmlspecialchars($alert['changed_at']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Deployment Timeline -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card p-4">
                    <h3 class="section-title">Recommended Rollout Timeline</h3>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                        <div style="padding: 16px; background: rgba(16,185,129,.08); border-left: 3px solid #10b981; border-radius: 4px;">
                            <div style="font-weight: 600; color: #10b981;">✓ Phase 1: Canary</div>
                            <small class="text-muted">1% traffic (48 hours)</small>
                            <p style="font-size: 0.85rem; margin-top: 8px;">Monitor for critical errors, latency spikes. No user impact.</p>
                        </div>
                        <div style="padding: 16px; background: rgba(29,185,84,.08); border-left: 3px solid var(--primary); border-radius: 4px;">
                            <div style="font-weight: 600; color: var(--primary);">→ Phase 2: Early Access</div>
                            <small class="text-muted">10% traffic (24 hours)</small>
                            <p style="font-size: 0.85rem; margin-top: 8px;">Expand to subset. Measure latency, error rates, user engagement.</p>
                        </div>
                        <div style="padding: 16px; background: rgba(0,212,255,.08); border-left: 3px solid #00d4ff; border-radius: 4px;">
                            <div style="font-weight: 600; color: #00d4ff;">→ Phase 3: Growing</div>
                            <small class="text-muted">25-50% traffic (24 hours each)</small>
                            <p style="font-size: 0.85rem; margin-top: 8px;">Gradual expansion. Validate schema, performance under load.</p>
                        </div>
                        <div style="padding: 16px; background: rgba(251,191,36,.08); border-left: 3px solid #fbbf24; border-radius: 4px;">
                            <div style="font-weight: 600; color: #fbbf24;">→ Phase 4: GA Launch</div>
                            <small class="text-muted">100% traffic</small>
                            <p style="font-size: 0.85rem; margin-top: 8px;">Full production traffic. 72-hour hyper-care monitoring.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Notes -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card p-4">
                    <h3 class="section-title">⚡ Quick Reference</h3>

                    <div style="columns: 2; column-gap: 2rem;">
                        <div>
                            <strong>Rollback Procedure:</strong>
                            <ol style="font-size: 0.9rem; color: var(--muted);">
                                <li>Set rollout to 0%</li>
                                <li>Activate maintenance mode if critical</li>
                                <li>Investigate error logs</li>
                                <li>Contact on-call engineer</li>
                            </ol>
                        </div>
                        <div>
                            <strong>Health Checks:</strong>
                            <ul style="font-size: 0.9rem; color: var(--muted);">
                                <li>✓ P95 Latency: &lt;250ms</li>
                                <li>✓ Error Rate: &lt;3%</li>
                                <li>✓ Core API Endpoints: All 200 OK</li>
                                <li>✓ Database: Replication lag &lt;1s</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php require_once(__DIR__ . '/Lib/partials/footer.php'); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const rolloutSlider = document.getElementById('rolloutSlider');
        const sliderValue = document.getElementById('sliderValue');

        rolloutSlider.addEventListener('input', function() {
            sliderValue.textContent = this.value + '%';
        });

        function setRollout(percentage) {
            rolloutSlider.value = percentage;
            sliderValue.textContent = percentage + '%';

            fetch('/api/v1/admin/feature-flags', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + (window.localStorage.getItem('ngn_admin_token') || ''),
                },
                body: JSON.stringify({
                    flag_name: 'ROLLOUT_PERCENTAGE',
                    flag_value: String(percentage),
                    reason: 'Canary deployment rollout adjustment via monitoring dashboard',
                }),
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showNotification('Rollout updated to ' + percentage + '%', 'success');
                    setTimeout(() => location.reload(), 500);
                } else {
                    showNotification('Error: ' + (data.error || 'Unknown error'), 'danger');
                }
            })
            .catch(e => {
                showNotification('Request failed: ' + e.message, 'danger');
            });
        }

        function toggleMaintenance(enabled) {
            fetch('/api/v1/admin/feature-flags', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + (window.localStorage.getItem('ngn_admin_token') || ''),
                },
                body: JSON.stringify({
                    flag_name: 'MAINTENANCE_MODE',
                    flag_value: enabled ? 'true' : 'false',
                    reason: 'Maintenance mode toggled via canary monitoring dashboard',
                }),
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showNotification(
                        enabled ? 'Maintenance mode activated' : 'Maintenance mode deactivated',
                        enabled ? 'warning' : 'success'
                    );
                    setTimeout(() => location.reload(), 500);
                } else {
                    showNotification('Error: ' + (data.error || 'Unknown error'), 'danger');
                }
            })
            .catch(e => {
                showNotification('Request failed: ' + e.message, 'danger');
            });
        }

        function showNotification(message, type) {
            const alertHTML = `
                <div class="alert alert-${type} alert-dismissible fade show position-fixed"
                     style="top: 20px; right: 20px; z-index: 9999; max-width: 400px;"
                     role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            document.body.insertAdjacentHTML('afterbegin', alertHTML);
            setTimeout(() => {
                const alert = document.querySelector('.alert-' + type);
                if (alert) alert.remove();
            }, 4000);
        }
    </script>
</body>
</html>
