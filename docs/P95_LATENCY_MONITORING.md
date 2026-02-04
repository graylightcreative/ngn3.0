# P95 API Latency Monitoring System

## Overview

The P95 Latency Monitoring System ensures NGN 2.0 maintains a "native-app feel" by continuously tracking API response times and alerting when the 95th percentile (P95) latency exceeds 250ms.

**Status:** ✅ Complete (January 2026)

**Bible Reference:** Chapter 12.8 - P95 API Latency Monitoring

---

## Architecture

### Components

1. **ApiTimingMiddleware** - Wraps all API requests to measure execution time
2. **MetricsService** - Stores metrics and calculates percentiles
3. **AlertService** - Fires alerts when thresholds are exceeded
4. **Cron Job** - Runs every minute to check P95 latency

### Data Flow

```
API Request
    ↓
ApiTimingMiddleware (start timer)
    ↓
Route Handler Execution
    ↓
ApiTimingMiddleware (end timer, record metrics)
    ↓
api_request_metrics table
    ↓
check_api_latency.php (cron, every minute)
    ↓
MetricsService::calculatePercentile(95, 5 minutes)
    ↓
If P95 > 250ms → AlertService::createAlert('p95_latency', 'p1')
    ↓
Notification (Slack, Email, Log)
```

---

## Database Schema

### api_request_metrics

Stores timing data for all API requests.

```sql
CREATE TABLE api_request_metrics (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  endpoint VARCHAR(255) NOT NULL,       -- e.g., /api/v1/posts/:id
  method VARCHAR(10) NOT NULL,          -- GET, POST, PUT, DELETE
  status_code INT NOT NULL,             -- 200, 404, 500, etc.
  duration_ms DECIMAL(10,2) NOT NULL,   -- Request duration in milliseconds
  user_id BIGINT UNSIGNED NULL,         -- Authenticated user (if applicable)
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_created (created_at),
  INDEX idx_endpoint_created (endpoint, created_at),
  INDEX idx_duration (duration_ms)
);
```

**Retention:** 7 days (automatically cleaned by cron job)

### alert_history

Tracks all system alerts including P95 violations.

```sql
CREATE TABLE alert_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  alert_type VARCHAR(64) NOT NULL,      -- e.g., 'p95_latency'
  severity ENUM('p0', 'p1', 'p2') NOT NULL,
  message TEXT,                         -- Human-readable alert message
  details JSON,                         -- Metrics, thresholds, context
  notified_at TIMESTAMP NULL,           -- When notification was sent
  resolved_at TIMESTAMP NULL,           -- When condition was resolved
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_alert_type (alert_type, created_at),
  INDEX idx_severity (severity, created_at),
  INDEX idx_resolved (resolved_at)
);
```

**Retention:** Indefinite (for post-mortem analysis)

---

## Implementation Details

### 1. Request Timing Middleware

**File:** `/lib/Middleware/ApiTimingMiddleware.php`

Wraps all API requests to measure execution time:

```php
// In api/v1/index.php
$response = $timingMiddleware->wrap($request, $userId, function () use ($router, $request) {
    $handler = $router->dispatch($request);
    return $handler($request);
});
```

**Features:**
- Measures request duration using `microtime(true)`
- Normalizes endpoints (e.g., `/api/v1/posts/123` → `/api/v1/posts/:id`)
- Non-blocking metrics recording (failures don't break requests)
- Captures user ID for authenticated requests

### 2. Metrics Service

**File:** `/lib/Services/MetricsService.php`

**Key Methods:**

```php
// Record a request metric
$metricsService->recordRequest(
    endpoint: '/api/v1/posts/:id',
    method: 'GET',
    statusCode: 200,
    durationMs: 145.67,
    userId: 123
);

// Calculate P95 latency (last 5 minutes, all endpoints)
$p95 = $metricsService->calculatePercentile(95, 5);

// Get comprehensive latency statistics
$stats = $metricsService->getLatencyStats(5);
// Returns: ['p50_ms', 'p95_ms', 'p99_ms', 'avg_ms', 'min_ms', 'max_ms', 'request_count']

// Get per-endpoint breakdown
$breakdown = $metricsService->getEndpointBreakdown(5, 20);
// Returns array of endpoints sorted by P95 latency (slowest first)
```

**Percentile Calculation:**
- Retrieves all durations from the time window
- Sorts in ascending order
- Calculates index: `ceil((percentile / 100) * count) - 1`
- Returns duration at that index

### 3. Alert Service

**File:** `/lib/Services/AlertService.php`

**Key Methods:**

```php
// Create and send an alert
$alertId = $alertService->createAlert(
    alertType: 'p95_latency',
    severity: 'p1',
    message: 'API P95 latency exceeded threshold: 312.45ms',
    details: [
        'p95_ms' => 312.45,
        'threshold_ms' => 250,
        'p50_ms' => 145.67,
        'request_count' => 1247
    ],
    notify: true
);

// Check if alert was recently fired (debouncing)
$wasRecentlyFired = $alertService->wasRecentlyFired('p95_latency', 15);

// Resolve an alert
$alertService->resolveAlert($alertId);
```

**Notification Channels:**

- **P0 (Critical):** SMS/PagerDuty/Phone + Email
- **P1 (High):** Slack/Discord + Email
- **P2 (Normal):** Email/Weekly digest

**Alert Debouncing:**
- Default: 15-minute window
- Prevents alert spam during sustained degradation
- Checks `alert_history` for recent alerts of same type

### 4. Cron Job

**File:** `/jobs/check_api_latency.php`

**Schedule:** `* * * * *` (every minute)

**Logic:**

1. Calculate P95 latency for last 5 minutes
2. Check if request count >= 10 (minimum threshold)
3. If P95 > 250ms:
   - Check debounce (was alert fired in last 15 minutes?)
   - If not debounced, fire P1 alert
   - Log slowest endpoints by P95
4. Clean up metrics older than 7 days
5. Log results to `/storage/logs/latency_monitor.log`

**Constants:**

```php
const P95_THRESHOLD_MS = 250.0;
const MONITORING_WINDOW_MINUTES = 5;
const ALERT_DEBOUNCE_MINUTES = 15;
const MIN_REQUESTS_FOR_ALERT = 10;
```

---

## Configuration

### Alert Notification Channels

Add to `/lib/config/config.php`:

```php
'alerts' => [
    'high_priority_email' => 'alerts@nextgennoise.com',
    'critical_email' => 'urgent@nextgennoise.com',
    'slack_webhook_url' => 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL'
],
```

### Slack Webhook Setup

1. Go to https://api.slack.com/apps
2. Create a new app or use existing
3. Enable Incoming Webhooks
4. Create webhook for desired channel
5. Add webhook URL to config

---

## Cron Setup (aaPanel)

### Step 1: Access aaPanel

Log into aaPanel at `https://your-server:7800`

### Step 2: Navigate to Cron

Click **Cron** in the left sidebar → **Add Scheduled Task**

### Step 3: Configure Task

- **Task Type:** Shell Script
- **Task Name:** NGN P95 Latency Monitor
- **Execution Cycle:** Custom → `* * * * *` (every minute)
- **Script Content:**

```bash
/usr/bin/php /path/to/ngn2.0/jobs/check_api_latency.php
```

### Step 4: Verify

Check log file to confirm execution:

```bash
tail -f /path/to/ngn2.0/storage/logs/latency_monitor.log
```

---

## Alert Example

### P95 Threshold Exceeded

**Log Entry:**

```
[2026-01-15 14:23:45] [p1] [p95_latency] API P95 latency exceeded threshold: 312.45ms (threshold: 250ms, window: 5 minutes)
Details: {
  "p95_ms": 312.45,
  "threshold_ms": 250,
  "p50_ms": 145.67,
  "p99_ms": 456.23,
  "avg_ms": 178.92,
  "request_count": 1247,
  "window_minutes": 5,
  "timestamp": "2026-01-15 14:23:45"
}
```

**Slack Notification:**

```
⚠️  P1 Alert: p95_latency

API P95 latency exceeded threshold: 312.45ms (threshold: 250ms, window: 5 minutes)

p95_ms: 312.45
threshold_ms: 250
request_count: 1247
```

**Slowest Endpoints:**

```
=== Slowest Endpoints (by P95) ===
  🔴 /api/v1/posts: P95=487.12ms, Requests=342
  🔴 /api/v1/engagement/comments: P95=356.78ms, Requests=189
  🟢 /api/v1/artists: P95=123.45ms, Requests=567
```

---

## Monitoring & Debugging

### View Real-Time Metrics

**SQL Queries:**

```sql
-- Current P95 (last 5 minutes)
SELECT
    COUNT(*) as request_count,
    AVG(duration_ms) as avg_ms,
    MIN(duration_ms) as min_ms,
    MAX(duration_ms) as max_ms
FROM api_request_metrics
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE);

-- Slowest endpoints (last hour)
SELECT
    endpoint,
    COUNT(*) as request_count,
    AVG(duration_ms) as avg_ms,
    MAX(duration_ms) as max_ms
FROM api_request_metrics
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY endpoint
ORDER BY avg_ms DESC
LIMIT 10;

-- Recent alerts
SELECT * FROM alert_history
WHERE alert_type = 'p95_latency'
ORDER BY created_at DESC
LIMIT 10;
```

### View Logs

```bash
# Latency monitor output
tail -f /path/to/storage/logs/latency_monitor.log

# All alerts
tail -f /path/to/storage/logs/alerts.log

# API errors
tail -f /path/to/storage/logs/api.log
```

### Test Alert System

Manually trigger alert check:

```bash
php /path/to/jobs/check_api_latency.php
```

Simulate slow endpoint (for testing):

```php
// In any API endpoint handler
sleep(1); // Force 1000ms delay
```

---

## Performance Optimization Tips

If P95 latency consistently exceeds threshold:

### 1. Identify Slow Endpoints

```php
$breakdown = $metricsService->getEndpointBreakdown(60, 20);
// Check which endpoints have highest P95
```

### 2. Common Causes

- **Database queries:** Missing indexes, N+1 queries, slow JOINs
- **External API calls:** Stripe, Printful, Meta API timeouts
- **Large payloads:** Inefficient serialization, missing pagination
- **Cache misses:** Redis unavailable, cache warming needed
- **CPU-intensive operations:** Chart calculations, image processing

### 3. Solutions

- Add database indexes for frequently queried columns
- Implement query result caching (Redis)
- Use pagination for large datasets
- Move heavy operations to background jobs (queue)
- Optimize service initialization (lazy loading)
- Enable Fastly caching for static endpoints

### 4. Temporary Workarounds

If production alert fires during incident:

```php
// Increase threshold temporarily (jobs/check_api_latency.php)
const P95_THRESHOLD_MS = 500.0; // Increase from 250ms
```

Then investigate and fix root cause, revert threshold.

---

## Testing

### Unit Tests

Test percentile calculation:

```php
// Create test metrics
$metricsService->recordRequest('/test', 'GET', 200, 100.0, null);
$metricsService->recordRequest('/test', 'GET', 200, 200.0, null);
$metricsService->recordRequest('/test', 'GET', 200, 300.0, null);

// Calculate P95 (should be ~300ms)
$p95 = $metricsService->calculatePercentile(95, 5);
assert($p95 >= 280 && $p95 <= 300);
```

### Integration Tests

Test full alert flow:

```bash
# 1. Generate slow requests
curl http://localhost/api/v1/slow-endpoint

# 2. Run cron job
php jobs/check_api_latency.php

# 3. Check alert was created
SELECT * FROM alert_history WHERE alert_type = 'p95_latency' ORDER BY created_at DESC LIMIT 1;

# 4. Verify notification sent
tail storage/logs/alerts.log
```

---

## Troubleshooting

### Alerts Not Firing

**Check 1: Cron running?**

```bash
# Check cron logs
tail -f /var/log/cron

# Manually run job
php jobs/check_api_latency.php
```

**Check 2: Enough requests?**

```sql
SELECT COUNT(*) FROM api_request_metrics
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE);
-- Must be >= 10 requests
```

**Check 3: Debouncing?**

```sql
SELECT * FROM alert_history
WHERE alert_type = 'p95_latency'
AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE);
-- If found, alert is debounced
```

### Metrics Not Recording

**Check 1: Middleware initialized?**

```php
// In api/v1/index.php
var_dump($timingMiddleware); // Should not be null
```

**Check 2: Database schema?**

```sql
SHOW TABLES LIKE 'api_request_metrics';
DESCRIBE api_request_metrics;
```

**Check 3: Database permissions?**

```sql
SHOW GRANTS FOR 'your_db_user'@'localhost';
-- Should have INSERT, SELECT on api_request_metrics
```

### High P95 Latency

**Check 1: Slow queries?**

```sql
-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 0.5; -- 500ms

-- Check slow queries
SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10;
```

**Check 2: External API latency?**

```sql
-- Check API health logs
SELECT * FROM api_health_log
WHERE response_time_ms > 1000
ORDER BY checked_at DESC
LIMIT 20;
```

**Check 3: Server load?**

```bash
# Check CPU usage
top

# Check memory
free -h

# Check disk I/O
iostat -x 1
```

---

## Files Reference

| File | Purpose | Lines |
|------|---------|-------|
| `/lib/Services/MetricsService.php` | Metrics collection, percentile calculation | 229 |
| `/lib/Services/AlertService.php` | Alert creation, notification dispatch | 245 |
| `/lib/Middleware/ApiTimingMiddleware.php` | Request timing wrapper | 130 |
| `/jobs/check_api_latency.php` | Cron job for P95 monitoring | 150 |
| `/migrations/sql/schema/14_api_metrics.sql` | Database schema | 40 |
| `/admin/cron-setup.php` | Admin panel for cron configuration | 550 |
| `/docs/bible/12 - System Integrity and Monitoring.md` | Bible chapter (section 12.8) | Updated |

---

## Related Documentation

- [Bible Chapter 12: System Integrity & Monitoring](./bible/12%20-%20System%20Integrity%20and%20Monitoring.md)
- [API Health Infrastructure](./API_HEALTH_INFRASTRUCTURE.md)
- [Cron Setup Guide](../admin/cron-setup.php)

---

## Status & Roadmap

### ✅ Complete (January 2026)

- [x] Database schema created
- [x] MetricsService implemented
- [x] AlertService implemented
- [x] ApiTimingMiddleware integrated
- [x] Cron job created
- [x] Admin panel cron setup page
- [x] Bible Chapter 12 updated
- [x] Documentation complete

### 🔄 Future Enhancements

- [ ] Admin dashboard with real-time P95 graphs
- [ ] PagerDuty integration for P0 alerts
- [ ] Auto-resolution detection (P95 drops below threshold)
- [ ] Anomaly detection (ML-based threshold adjustment)
- [ ] Cost analysis (which endpoints consume most resources)
- [ ] Distributed tracing integration (OpenTelemetry)

---

**Implementation Date:** January 15, 2026
**Status:** Production Ready ✅
**Next Review:** Before production launch
