<?php
/**
 * Cron Job Setup Guide
 *
 * Admin panel page that documents all required cron jobs
 * for aaPanel configuration.
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Auth\TokenService;
use NGN\Lib\Http\Request;

// Authentication check
$config = new Config();
$tokenSvc = new TokenService($config);
$request = new Request();

// Simple auth check (you should replace with your admin auth system)
function isAdminAuthed(): bool {
    // TODO: Implement proper admin authentication
    return true; // For now, allow access (secure this in production!)
}

if (!isAdminAuthed()) {
    http_response_code(403);
    echo "Access denied. Admin authentication required.";
    exit;
}

// Get project root path
$projectRoot = realpath(__DIR__ . '/..');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cron Job Setup Guide | NGN 2.0 Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #0f0f0f;
            color: #e0e0e0;
            padding: 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%);
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 1px solid #333;
        }

        h1 {
            font-size: 32px;
            margin-bottom: 10px;
            color: #fff;
        }

        .subtitle {
            color: #888;
            font-size: 14px;
        }

        .cron-job {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            transition: border-color 0.3s;
        }

        .cron-job:hover {
            border-color: #0099ff;
        }

        .cron-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .cron-title {
            font-size: 20px;
            color: #0099ff;
            margin-bottom: 5px;
        }

        .priority-badge {
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .priority-critical {
            background: #ff4444;
            color: #fff;
        }

        .priority-high {
            background: #ff9900;
            color: #fff;
        }

        .priority-normal {
            background: #0099ff;
            color: #fff;
        }

        .cron-description {
            color: #aaa;
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .cron-schedule {
            background: #0f0f0f;
            border: 1px solid #2a2a2a;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 15px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }

        .schedule-label {
            color: #666;
            font-size: 11px;
            text-transform: uppercase;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }

        .schedule-value {
            color: #0099ff;
            font-size: 14px;
        }

        .cron-command {
            background: #0f0f0f;
            border: 1px solid #2a2a2a;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
        }

        .command-code {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #66ff66;
            word-break: break-all;
            padding-right: 60px;
        }

        .copy-btn {
            position: absolute;
            top: 12px;
            right: 12px;
            background: #0099ff;
            color: #fff;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background 0.2s;
        }

        .copy-btn:hover {
            background: #0077cc;
        }

        .cron-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #2a2a2a;
        }

        .detail-item {
            font-size: 13px;
        }

        .detail-label {
            color: #666;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .detail-value {
            color: #ccc;
        }

        .alert-box {
            background: #2a1a1a;
            border: 1px solid #ff4444;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .alert-title {
            color: #ff4444;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .alert-content {
            color: #ccc;
            font-size: 14px;
            line-height: 1.6;
        }

        .setup-instructions {
            background: #1a2a1a;
            border: 1px solid #33aa33;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .instructions-title {
            color: #66ff66;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .instructions-step {
            margin-bottom: 15px;
            padding-left: 25px;
            position: relative;
        }

        .step-number {
            position: absolute;
            left: 0;
            top: 0;
            color: #66ff66;
            font-weight: 600;
        }

        code {
            background: #0f0f0f;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: #0099ff;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>⏰ Cron Job Setup Guide</h1>
            <div class="subtitle">NGN 2.0 | Scheduled Tasks Configuration for aaPanel</div>
        </header>

        <div class="alert-box">
            <div class="alert-title">⚠️ Important: Production Requirement</div>
            <div class="alert-content">
                All cron jobs listed below MUST be configured in aaPanel before going to production.
                These jobs are critical for system health monitoring, data synchronization, and
                performance alerting per <strong>Bible Chapter 12: System Integrity & Monitoring</strong>.
            </div>
        </div>

        <div class="setup-instructions">
            <div class="instructions-title">📋 aaPanel Setup Instructions</div>
            <div class="instructions-step">
                <span class="step-number">1.</span>
                Log into your aaPanel admin panel at <code>https://your-server:7800</code>
            </div>
            <div class="instructions-step">
                <span class="step-number">2.</span>
                Navigate to <strong>Cron</strong> in the left sidebar
            </div>
            <div class="instructions-step">
                <span class="step-number">3.</span>
                Click <strong>Add Scheduled Task</strong>
            </div>
            <div class="instructions-step">
                <span class="step-number">4.</span>
                For each cron job below:
                <ul style="margin-top: 8px; margin-left: 20px;">
                    <li>Set <strong>Task Type</strong> to "Shell Script"</li>
                    <li>Set <strong>Task Name</strong> to the job name (e.g., "NGN P95 Latency Monitor")</li>
                    <li>Set <strong>Execution Cycle</strong> using the schedule provided</li>
                    <li>Paste the command into the <strong>Script Content</strong> field</li>
                    <li>Click <strong>Save</strong></li>
                </ul>
            </div>
            <div class="instructions-step">
                <span class="step-number">5.</span>
                Verify all cron jobs are running by checking logs in <code>/storage/logs/</code>
            </div>
        </div>

        <!-- Cron Job 1: P95 Latency Monitor -->
        <div class="cron-job">
            <div class="cron-header">
                <div>
                    <div class="cron-title">P95 API Latency Monitor</div>
                    <div class="cron-description">
                        Monitors API response times and fires P1 alerts when P95 latency exceeds 250ms threshold.
                        Critical for maintaining "native-app feel" performance requirements.
                    </div>
                </div>
                <span class="priority-badge priority-critical">Critical</span>
            </div>

            <div class="cron-schedule">
                <div class="schedule-label">Schedule</div>
                <div class="schedule-value">* * * * * (Every minute)</div>
            </div>

            <div class="cron-command">
                <div class="schedule-label" style="margin-bottom: 8px;">Command</div>
                <div class="command-code">/usr/bin/php <?php echo $projectRoot; ?>/jobs/check_api_latency.php</div>
                <button class="copy-btn" onclick="copyToClipboard(this)">Copy</button>
            </div>

            <div class="cron-details">
                <div class="detail-item">
                    <div class="detail-label">Log File</div>
                    <div class="detail-value">/storage/logs/latency_monitor.log</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Alert File</div>
                    <div class="detail-value">/storage/logs/alerts.log</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Bible Reference</div>
                    <div class="detail-value">Chapter 12 - Alert Tier P1</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Retention</div>
                    <div class="detail-value">Metrics: 7 days, Alerts: Indefinite</div>
                </div>
            </div>
        </div>

        <!-- Cron Job 2: Operational Health Verification -->
        <div class="cron-job">
            <div class="cron-header">
                <div>
                    <div class="cron-title">Operational Health Verification</div>
                    <div class="cron-description">
                        Verifies chart completeness, policy adherence (checksums, QA Gatekeeper),
                        backup verification, and data integrity. Ensures system stability post-launch.
                    </div>
                </div>
                <span class="priority-badge priority-critical">Critical</span>
            </div>

            <div class="cron-schedule">
                <div class="schedule-label">Schedule</div>
                <div class="schedule-value">0 */6 * * * (Every 6 hours)</div>
            </div>

            <div class="cron-command">
                <div class="schedule-label" style="margin-bottom: 8px;">Command</div>
                <div class="command-code">/usr/bin/php <?php echo $projectRoot; ?>/jobs/verify_op_health.php</div>
                <button class="copy-btn" onclick="copyToClipboard(this)">Copy</button>
            </div>

            <div class="cron-details">
                <div class="detail-item">
                    <div class="detail-label">Log File</div>
                    <div class="detail-value">/storage/logs/op_verify_health.log</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Checks</div>
                    <div class="detail-value">Coverage: 98%, Linkage: 95%, Backups: 7-day window</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Bible Reference</div>
                    <div class="detail-value">Chapter 12 - Data Integrity</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Alert Level</div>
                    <div class="detail-value">P0 if critical thresholds breached</div>
                </div>
            </div>
        </div>

        <!-- Cron Job 3: SMR Spins Synchronization -->
        <div class="cron-job">
            <div class="cron-header">
                <div>
                    <div class="cron-title">SMR Spins Data Synchronization</div>
                    <div class="cron-description">
                        Synchronizes radio spin data from Station Music Reports (SMR) for chart calculations.
                        Includes API key governance for secure data ingestion. Essential for "Moneyball" edge.
                    </div>
                </div>
                <span class="priority-badge priority-high">High</span>
            </div>

            <div class="cron-schedule">
                <div class="schedule-label">Schedule</div>
                <div class="schedule-value">0 */4 * * * (Every 4 hours)</div>
            </div>

            <div class="cron-command">
                <div class="schedule-label" style="margin-bottom: 8px;">Command</div>
                <div class="command-code">/usr/bin/php <?php echo $projectRoot; ?>/jobs/spins_sync.php</div>
                <button class="copy-btn" onclick="copyToClipboard(this)">Copy</button>
            </div>

            <div class="cron-details">
                <div class="detail-item">
                    <div class="detail-label">Log File</div>
                    <div class="detail-value">/storage/logs/spins_sync.log</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Data Source</div>
                    <div class="detail-value">SMR API (Station Music Reports)</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Bible Reference</div>
                    <div class="detail-value">Chapter 12 - SMR Ingestion</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Alert Level</div>
                    <div class="detail-value">P1 if ingestion fails</div>
                </div>
            </div>
        </div>

        <!-- Cron Job 4: Fastly Cache Purge Verification (Future) -->
        <div class="cron-job" style="opacity: 0.6;">
            <div class="cron-header">
                <div>
                    <div class="cron-title">Fastly Cache Purge Verification (Pending Implementation)</div>
                    <div class="cron-description">
                        Nightly verification that Fastly cache purges are successful. Samples "Hot" URLs
                        and compares X-Cache-Hits header with database updated_at timestamps.
                    </div>
                </div>
                <span class="priority-badge priority-high">High</span>
            </div>

            <div class="cron-schedule">
                <div class="schedule-label">Schedule</div>
                <div class="schedule-value">0 2 * * * (Daily at 2:00 AM)</div>
            </div>

            <div class="cron-command">
                <div class="schedule-label" style="margin-bottom: 8px;">Command</div>
                <div class="command-code">/usr/bin/php <?php echo $projectRoot; ?>/jobs/verify_fastly_purges.php</div>
                <button class="copy-btn" onclick="copyToClipboard(this)">Copy</button>
            </div>

            <div class="cron-details">
                <div class="detail-item">
                    <div class="detail-label">Status</div>
                    <div class="detail-value" style="color: #ff9900;">⚠️ Not Yet Implemented</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Log File</div>
                    <div class="detail-value">/storage/logs/fastly_purge_verify.log</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Bible Reference</div>
                    <div class="detail-value">Chapter 12 - Fastly Purge Monitoring</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Alert Level</div>
                    <div class="detail-value">P1 if stale data detected</div>
                </div>
            </div>
        </div>

        <!-- Cron Job 5: Daily Database Backup (Future) -->
        <div class="cron-job" style="opacity: 0.6;">
            <div class="cron-header">
                <div>
                    <div class="cron-title">Daily Database Backup (Pending Implementation)</div>
                    <div class="cron-description">
                        Daily automated backup of all databases with 7-day rolling retention.
                        Includes backup verification and integrity checks.
                    </div>
                </div>
                <span class="priority-badge priority-critical">Critical</span>
            </div>

            <div class="cron-schedule">
                <div class="schedule-label">Schedule</div>
                <div class="schedule-value">0 3 * * * (Daily at 3:00 AM)</div>
            </div>

            <div class="cron-command">
                <div class="schedule-label" style="margin-bottom: 8px;">Command</div>
                <div class="command-code">/usr/bin/php <?php echo $projectRoot; ?>/jobs/backup_databases.php</div>
                <button class="copy-btn" onclick="copyToClipboard(this)">Copy</button>
            </div>

            <div class="cron-details">
                <div class="detail-item">
                    <div class="detail-label">Status</div>
                    <div class="detail-value" style="color: #ff9900;">⚠️ Not Yet Implemented</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Log File</div>
                    <div class="detail-value">/storage/logs/backup_databases.log</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Retention</div>
                    <div class="detail-value">7 days rolling (168 hours)</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Alert Level</div>
                    <div class="detail-value">P0 if backup fails</div>
                </div>
            </div>
        </div>

        <!-- Summary Section -->
        <div style="background: #1a1a1a; border: 1px solid #333; border-radius: 8px; padding: 25px; margin-top: 30px;">
            <h2 style="color: #fff; font-size: 20px; margin-bottom: 15px;">📊 Cron Job Summary</h2>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <div>
                    <div style="color: #666; font-size: 12px; margin-bottom: 5px;">TOTAL JOBS</div>
                    <div style="color: #0099ff; font-size: 28px; font-weight: 600;">5</div>
                </div>
                <div>
                    <div style="color: #666; font-size: 12px; margin-bottom: 5px;">IMPLEMENTED</div>
                    <div style="color: #66ff66; font-size: 28px; font-weight: 600;">3</div>
                </div>
                <div>
                    <div style="color: #666; font-size: 12px; margin-bottom: 5px;">PENDING</div>
                    <div style="color: #ff9900; font-size: 28px; font-weight: 600;">2</div>
                </div>
            </div>
        </div>

        <div style="margin-top: 30px; padding: 20px; background: #1a1a1a; border-radius: 8px; border: 1px solid #333;">
            <h3 style="color: #fff; margin-bottom: 10px;">📖 Related Documentation</h3>
            <ul style="list-style: none; padding: 0;">
                <li style="margin-bottom: 8px;">
                    <a href="../docs/bible/12 - System Integrity and Monitoring.md" style="color: #0099ff; text-decoration: none;">
                        📄 Bible Chapter 12: System Integrity & Monitoring
                    </a>
                </li>
                <li style="margin-bottom: 8px;">
                    <a href="../storage/logs/" style="color: #0099ff; text-decoration: none;">
                        📁 View Log Files
                    </a>
                </li>
                <li>
                    <a href="./api-health.php" style="color: #0099ff; text-decoration: none;">
                        🔍 API Health Dashboard
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <script>
        function copyToClipboard(button) {
            const commandDiv = button.previousElementSibling;
            const command = commandDiv.textContent;

            navigator.clipboard.writeText(command).then(() => {
                const originalText = button.textContent;
                button.textContent = 'Copied!';
                button.style.background = '#66ff66';
                setTimeout(() => {
                    button.textContent = originalText;
                    button.style.background = '#0099ff';
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy:', err);
                alert('Failed to copy command');
            });
        }
    </script>
</body>
</html>
