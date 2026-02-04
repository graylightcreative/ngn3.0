12. System Integrity & Monitoring

12.1 The Observability Goal

With a decoupled architecture involving Fastly (CDN), Liquid Web (Origin), and autonomous AI agents (Niko + Writers), traditional error logs are insufficient. We must monitor the relationships and latencies between these systems to ensure a "native-app" feel.

12.2 Real-Time Health Checks (The Heartbeat)

The /api/v1/health endpoint serves as the platform's heartbeat. It provides a real-time status report on:

Database Connectivity: Latency between the PHP Service Layer and the MySQL 8 Cluster.

Cache Health: Redis hit/miss ratios and memory pressure.

Storage Availability: Verification that /storage mounts are writable for SMR uploads and logs.

Queue Depth: Monitoring the backlog of "Niko" assignments and SMR ingestion tasks.

12.3 The "Niko" Audit Trail

Since AI writers (Alex, Sam, etc.) produce opinionated content, we must track their decision-making process to prevent "hallucination drift" and ensure brand safety.

Prompt Logging: Every assignment Niko triggers is logged with the raw JSON context sent to the LLM.

Persona Verification: Logs must confirm the Persona constraints were respected (e.g., "Did Alex mention Nickelback?").

Safety Gate Logs: Records of any content blocked by the Defamation Filter before it reached the Human-in-the-Loop dashboard.

12.4 Infrastructure Monitoring (Split-Brain Safeguards)

A. Fastly Purge Monitoring

If a chart is updated but the Fastly Purge fails, users see stale data.

The Relay: Every PURGE request to Fastly is logged at the Origin.

The Reconciliation: A nightly cron job samples "Hot" URLs. If the X-Cache-Hits header returns data older than the updated_at timestamp in the DB, it triggers a forced re-purge.

B. Origin Protection

Shielding Verification: We monitor for any traffic hitting Liquid Web that did not originate from a Fastly IP. These are logged as "Security Anomalies" and the source IPs are auto-banned via OS-level firewall rules.

12.5 Financial & Commerce Reconciliation

Ticketing and Merch are the highest-risk facets.

Webhook Integrity: We monitor the health of Stripe Webhooks. If an invoice.paid event is received but the cdm_tickets record isn't generated within 2 seconds, an Urgent Financial Discrepancy alert is raised.

Bouncer Sync Logs: We track the sync-state of all active "Bouncer Mode" devices at venues to identify "Split-Brain" ticket scans during offline periods.

12.6 Data Integrity (The SMR Check)

To maintain the "Moneyball" edge, data ingestion must be flawless.

Linkage Rate Monitoring: A dashboard for Admin (Erik) showing the percentage of "Unlinked" artist names in the last 24 hours.

Volume Parity: If the total spin count for a week drops by >5% compared to the previous week, the QA Gatekeeper blocks the chart publication and alerts the Admin.

12.7 Alerting Tiers

Tier

Event Type

Notification Channel

P0 (Critical)

Chart Calculation Failure, Payment Gateway Down, Origin Unreachable.

SMS / PagerDuty / Phone Call

P1 (High)

SMR Ingestion Error, AI Writer Hallucination detected, Webhook Latency > 5s.

Slack / Discord Channel

P2 (Normal)

Successful Weekly Backup, New Artist Claim, Daily Health Report.

Email / Weekly Digest

12.8 P95 API Latency Monitoring

To ensure the "native-app feel" remains stellar after launch, we implement continuous P95 latency monitoring with a 250ms threshold.

**Architecture:**

Request Timing Middleware: Every API request is wrapped in ApiTimingMiddleware which measures execution time and records metrics to the database.

Metrics Storage: The api_request_metrics table stores: endpoint path, HTTP method, status code, duration in milliseconds, user ID (if authenticated), and timestamp.

Percentile Calculation: MetricsService calculates P50, P95, and P99 percentiles over a 5-minute rolling window for all endpoints (globally) or per-endpoint.

Alert Monitoring: A cron job (check_api_latency.php) runs every minute to calculate P95 latency. If P95 > 250ms for 5 consecutive minutes with minimum 10 requests, a P1 alert is fired.

Notification Channels: P1 alerts are sent via Slack webhook (if configured), email, and logged to /storage/logs/alerts.log.

Debouncing: Alerts are debounced with a 15-minute window to prevent alert spam during temporary degradation.

**Database Schema:**

```sql
CREATE TABLE api_request_metrics (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  endpoint VARCHAR(255) NOT NULL,
  method VARCHAR(10) NOT NULL,
  status_code INT NOT NULL,
  duration_ms DECIMAL(10,2) NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at),
  INDEX idx_endpoint_created (endpoint, created_at)
);

CREATE TABLE alert_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  alert_type VARCHAR(64) NOT NULL,
  severity ENUM('p0', 'p1', 'p2') NOT NULL,
  message TEXT,
  details JSON,
  notified_at TIMESTAMP NULL,
  resolved_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Alert Example:**

When P95 latency exceeds 250ms, the system fires a P1 alert with detailed breakdown:

```
Alert Type: p95_latency
Severity: P1 (High)
Message: API P95 latency exceeded threshold: 312.45ms (threshold: 250ms, window: 5 minutes)
Details: {
  "p95_ms": 312.45,
  "threshold_ms": 250,
  "p50_ms": 145.67,
  "p99_ms": 456.23,
  "avg_ms": 178.92,
  "request_count": 1247,
  "slowest_endpoints": [
    {
      "endpoint": "/api/v1/posts",
      "p95_ms": 487.12,
      "request_count": 342
    }
  ]
}
```

**Retention Policy:**

Metrics data is retained for 7 days (automatically cleaned by the cron job).

Alert history is retained indefinitely for post-mortem analysis.

**Configuration:**

Set alert notification channels in config:

- alerts.high_priority_email - Email address for P1 alerts
- alerts.slack_webhook_url - Slack webhook URL for real-time notifications

**Files:**

- /lib/Services/MetricsService.php - Metrics collection and percentile calculations
- /lib/Services/AlertService.php - Alert creation and notification dispatching
- /lib/Middleware/ApiTimingMiddleware.php - Request timing wrapper
- /jobs/check_api_latency.php - Cron job (runs every minute)
- /admin/cron-setup.php - Admin panel for cron configuration

**Cron Schedule:**

```
* * * * * /usr/bin/php /path/to/jobs/check_api_latency.php
```