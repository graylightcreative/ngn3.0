# Monitoring & Alerting Setup Guide

**For**: DevOps, SRE, Operations
**Purpose**: Monitor system health during beta launch
**Critical for**: 24/7 awareness of issues

---

## 📊 WHAT TO MONITOR

### 1. API Performance
**Metric**: P95 API Response Time
- **Target**: < 250ms (from Chapter 12)
- **Warning**: > 200ms
- **Critical**: > 250ms

**Key Endpoints to Monitor**:
```
GET  /api/v1/governance/dashboard
GET  /api/v1/governance/sir
GET  /api/v1/governance/sir/{id}
POST /api/v1/governance/sir
POST /api/v1/governance/sir/{id}/verify
```

### 2. Error Rate
**Metric**: % of Requests Returning 5xx Errors
- **Target**: < 1%
- **Warning**: 1-5%
- **Critical**: > 5%

**Track by Endpoint**:
```
/api/v1/governance/* - Critical path
/webhooks/stripe.php - Payment processing
/dashboard/artist/* - User dashboards
```

### 3. Stripe Webhook Success Rate
**Metric**: % of Webhooks Successfully Processed
- **Target**: > 99%
- **Warning**: 95-99%
- **Critical**: < 95%

**Webhooks to Track**:
```
checkout.session.completed
invoice.payment_succeeded
customer.subscription.updated
customer.subscription.deleted
```

### 4. Database Performance
**Metric**: Average Query Time
- **Target**: < 100ms P50, < 300ms P95
- **Warning**: P95 > 250ms
- **Critical**: P95 > 500ms

**Queries to Monitor**:
```sql
SELECT status, COUNT(*) FROM directorate_sirs GROUP BY status;
SELECT * FROM directorate_sirs WHERE status = 'open';
SELECT * FROM sir_audit_log WHERE sir_id = ?;
```

### 5. System Resources
**Metrics**:
- **CPU Usage**: Target < 70%, Warning > 80%, Critical > 90%
- **Memory Usage**: Target < 70%, Warning > 80%, Critical > 90%
- **Disk Usage**: Target < 70%, Warning > 80%, Critical > 90%
- **Database Connections**: Target < 80%, Warning > 90%, Critical > 95%

---

## 🚨 ALERTING RULES

### HIGH PRIORITY ALERTS

#### Alert 1: API Latency Critical
```yaml
Name: "API P95 Latency > 250ms"
Condition: P95(api_response_time) > 250ms for 5 minutes
Severity: CRITICAL
Action: Page on-call engineer
Notification: Slack #ngn-alerts, SMS
```

#### Alert 2: Error Rate Spike
```yaml
Name: "API Error Rate > 5%"
Condition: count(status=5xx) / count(*) > 5% for 2 minutes
Severity: CRITICAL
Action: Page on-call engineer
Notification: Slack #ngn-alerts, SMS
```

#### Alert 3: Stripe Webhook Failures
```yaml
Name: "Stripe Webhooks Failing"
Condition: webhook_success_rate < 95% for 10 minutes
Severity: CRITICAL
Action: Page on-call engineer
Notification: Slack #ngn-alerts, Email, SMS
Context: Check STRIPE_WEBHOOK_SECRET in .env
```

#### Alert 4: Database Connection Pool Exhausted
```yaml
Name: "DB Connections at 95%+"
Condition: db_connections / max_connections > 0.95 for 2 minutes
Severity: CRITICAL
Action: Increase pool size, investigate long-running queries
Notification: Slack #ngn-alerts
```

#### Alert 5: Governance System Down
```yaml
Name: "Governance API 500 Errors"
Condition: count(path="/api/v1/governance/*" AND status=500) > 10 for 1 minute
Severity: CRITICAL
Action: Page on-call engineer
Notification: Slack #ngn-alerts, SMS
Context: Check service logs, database connectivity
```

### MEDIUM PRIORITY ALERTS

#### Alert 6: Database Latency Warning
```yaml
Name: "DB P95 Latency > 250ms"
Condition: P95(query_time) > 250ms for 10 minutes
Severity: WARNING
Action: Review slow query log, optimize queries
Notification: Slack #ngn-ops
```

#### Alert 7: High CPU Usage
```yaml
Name: "CPU Usage > 80%"
Condition: cpu_usage > 80% for 10 minutes
Severity: WARNING
Action: Investigate process, consider scaling
Notification: Slack #ngn-ops
```

#### Alert 8: Memory Usage Warning
```yaml
Name: "Memory Usage > 80%"
Condition: memory_usage > 80% for 10 minutes
Severity: WARNING
Action: Clear caches, investigate memory leaks
Notification: Slack #ngn-ops
```

### LOW PRIORITY ALERTS

#### Alert 9: Disk Space Running Low
```yaml
Name: "Disk Usage > 80%"
Condition: disk_usage > 80%
Severity: INFO
Action: Cleanup logs, archive old data
Notification: Slack #ngn-ops (daily digest)
```

#### Alert 10: Certificate Expiring Soon
```yaml
Name: "SSL Certificate Expires in 7 Days"
Condition: ssl_cert_expiry < 7 days
Severity: INFO
Action: Renew certificate
Notification: Slack #ngn-ops
```

---

## 📈 DASHBOARDS TO CREATE

### Dashboard 1: Real-Time System Health
```
Panels:
├── API Response Time (P50, P95, P99)
├── Error Rate (by endpoint)
├── Active Users (concurrent)
├── Database Connections
├── CPU/Memory/Disk Usage
└── Stripe Webhook Success Rate
```

### Dashboard 2: Governance System
```
Panels:
├── SIRs by Status (OPEN, IN_REVIEW, RANT_PHASE, VERIFIED, CLOSED)
├── Overdue SIRs Count
├── Avg Days to Verify (by director)
├── Recent SIR Activity Timeline
├── Governance API Latency
└── Permission Errors Count
```

### Dashboard 3: Payment Processing
```
Panels:
├── Transactions (count, revenue)
├── Stripe Webhook Status
├── Failed Transactions
├── Refunds Processed
├── Subscription Status Distribution
└── Payment Processing Latency
```

---

## 🛠️ MONITORING SETUP OPTIONS

### Option A: Datadog (Recommended for Easy Setup)
```bash
# 1. Install agent
DD_AGENT_MAJOR_VERSION=7 DD_API_KEY=<your_key> DD_SITE="datadoghq.com" bash -c "$(curl -L https://s3.amazonaws.com/dd-agent/scripts/install_mac_os.sh)"

# 2. Configure dashboards
# Dashboard → Create → JSON Editor
# Reference: See dashboards above

# 3. Set up alerts
# Monitors → Create Monitor → Select type
# Configure thresholds from "ALERTING RULES" section above
```

### Option B: New Relic
```bash
# 1. Install
curl -s https://download.newrelic.com/install/newrelic-cli/scripts/install.sh | bash

# 2. Configure APM
# Configure auto-instrumentation for PHP

# 3. Create alerts
# Alerts & AI → Create alert policy
```

### Option C: DIY with ELK Stack (Elasticsearch, Logstash, Kibana)
```bash
# 1. Collect logs
# Nginx → Logstash → Elasticsearch

# 2. Create dashboards in Kibana
# Index Patterns → Create visualizations

# 3. Set up alerts with Elastalert
```

### Option D: Self-Hosted Prometheus + Grafana
```bash
# 1. Install Prometheus
docker run -d -p 9090:9090 prom/prometheus

# 2. Install Grafana
docker run -d -p 3000:3000 grafana/grafana

# 3. Configure scrapers
# Prometheus config → Add job for APIs

# 4. Import dashboards
# Grafana → Dashboards → Import
```

---

## 📝 LOG AGGREGATION

### Logs to Monitor

**Application Logs**:
```
/storage/logs/app.log
/storage/logs/stripe_webhooks.log
/storage/logs/governance.log  (if created)
```

**Web Server Logs**:
```
/var/log/nginx/error.log
/var/log/nginx/access.log
/var/log/apache2/error.log (if Apache)
/var/log/apache2/access.log (if Apache)
```

**System Logs**:
```
/var/log/syslog
/var/log/auth.log
/var/log/mysql/slow.log (if enabled)
```

### Log Aggregation Setup

**Approach 1: Filebeat → ELK**
```yaml
filebeat.inputs:
- type: log
  enabled: true
  paths:
    - /storage/logs/*.log
    - /var/log/nginx/*.log

output.elasticsearch:
  hosts: ["elasticsearch:9200"]
```

**Approach 2: CloudWatch (AWS)**
```bash
# Install CloudWatch agent
wget https://s3.amazonaws.com/amazoncloudwatch-agent/amazon_linux/amd64/latest/amazon-cloudwatch-agent.rpm
rpm -U ./amazon-cloudwatch-agent.rpm

# Configure to send logs to CloudWatch
# Reference: AWS CloudWatch docs
```

---

## 🔔 NOTIFICATION CHANNELS

### Primary: Slack

**Channel Setup**:
```bash
# Create channels
#ngn-alerts (critical alerts)
#ngn-ops (operational alerts)
#ngn-monitoring (all metrics/dashboards)

# Webhook URL for alerts
https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

**Message Format**:
```
🚨 CRITICAL: API P95 Latency > 250ms
Duration: 5+ minutes
P95: 385ms (Target: < 250ms)
Endpoints Affected: /api/v1/governance/dashboard, /api/v1/governance/sir
Action: Page on-call engineer
Time: 2026-01-25 14:32:00 UTC
Dashboard: [Link to dashboard]
```

### Secondary: Email

**For**: Low-priority, daily digests
**Example**:
```
To: ops-team@ngn.local
Subject: Daily Monitoring Report - 2026-01-25

Highlights:
✅ API P95: 65ms average (excellent)
✅ Error Rate: 0.2% (excellent)
⚠️ DB Latency: 180ms P95 (slightly elevated)
✅ Stripe Webhooks: 99.8% success rate

Details: [Dashboard link]
```

### Emergency: SMS/Phone

**For**: P0 critical issues only
**Trigger**: Any of these events
- API completely down (10+ 5xx errors/min)
- Stripe payment processing failing
- Database connection pool exhausted
- System at 95%+ CPU/Memory

---

## 📊 METRICS COLLECTION

### Using Prometheus Exporter

```bash
# 1. Add PHP exporter
composer require promphp/prometheus_client_php

# 2. Instrument code
<?php
require 'vendor/autoload.php';
use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;

$registry = new CollectorRegistry();

// Track API requests
$counter = $registry->getOrRegisterCounter(
    'api_requests_total',
    'Total API requests',
    ['method', 'endpoint', 'status']
);
$counter->incBy(1, ['GET', '/api/v1/governance/sir', '200']);

// Track SIRs created
$gauge = $registry->getOrRegisterGauge(
    'sirs_total',
    'Total SIRs',
    ['status']
);

// Expose metrics
$renderer = new Prometheus\RendererText();
echo $renderer->render($registry->collect());
?>

# 3. Configure Prometheus
global:
  scrape_interval: 15s

scrape_configs:
  - job_name: 'ngn-api'
    static_configs:
      - targets: ['localhost:9090']
```

---

## ✅ DAILY MONITORING CHECKLIST

**Every Morning**:
- [ ] Check P95 API latency (target < 250ms)
- [ ] Check error rate (target < 1%)
- [ ] Check Stripe webhook success (target > 99%)
- [ ] Check system resources (CPU, Memory, Disk)
- [ ] Review overnight logs for errors
- [ ] Check database connection count

**Every Hour (First Week of Beta)**:
- [ ] API response time trend
- [ ] Error rate trend
- [ ] Active user count
- [ ] Recent alerts

**Weekly**:
- [ ] Review slow query log
- [ ] Check backup completion
- [ ] Review security logs
- [ ] Plan capacity for next week
- [ ] Quarterly review of alerting thresholds

---

## 🚀 FIRST WEEK MONITORING PLAN

**DAY 1 (Launch)**:
- Hour 1: Manual monitoring of all dashboards
- Hour 1-4: Someone standing by to respond to alerts
- Hour 4-24: Automated alerts active, on-call response

**DAYS 2-3**:
- Continuous monitoring during business hours
- On-call engineer overnight
- Daily status check every morning

**DAYS 4-7**:
- Establish baseline performance metrics
- Fine-tune alerting thresholds
- Create weekly monitoring report

---

## 📈 SUCCESS METRICS

**Launch Week Goals**:
- ✅ API P95 < 250ms average
- ✅ Error rate < 1% average
- ✅ 99%+ Stripe webhook success
- ✅ 99.9%+ system uptime
- ✅ Zero critical alerts unresponded

**If metrics not met**:
1. Investigate root cause
2. Implement fix or optimization
3. Monitor for improvement
4. Adjust alerting if needed

---

**Monitoring Setup Status**: Ready to implement
**Recommended Tool**: Datadog or Prometheus + Grafana
**Setup Time**: 2-4 hours
**Ongoing Maintenance**: 30 mins/day during beta

