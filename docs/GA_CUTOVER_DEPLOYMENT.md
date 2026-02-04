# GA Cutover & Hyper-Care Deployment Playbook

**Version:** 1.0
**Date:** January 17, 2026
**Status:** Ready for Phase 1 (Staging Verification)
**Owner:** Release/DevOps Team

---

## Executive Summary

This document provides a complete deployment guide for transitioning from NGN Legacy 1.0 to NGN 2.0 in production. The rollout uses a **feature flag-based gradual deployment** with **automated monitoring** and **manual rollback capability**.

### Key Deployment Strategy
- **Phase 1:** Staging verification (48 hours)
- **Phase 2:** Canary rollout (1% → 10% → 25% → 50% → 75% → 100%)
- **Phase 3:** 72-hour hyper-care monitoring
- **Phase 4:** Cleanup and decommission

### Critical Success Metrics
- P95 API latency must remain **< 250ms**
- Error rate must stay **< 5%**
- Webhook delivery latency must be **< 5 seconds**
- Zero data loss during cutover

---

## Part 1: Pre-Cutover Checklist (48 Hours Before)

### 1.1 Code Quality Verification

- [ ] All critical tests pass (unit, integration, API)
- [ ] Code review complete for all deployment-related changes
- [ ] Performance profiling done (identify slow endpoints)
- [ ] Security audit complete (no SQL injection, XSS, auth bypasses)
- [ ] New feature flags documented in Config.php
- [ ] Environment variables reviewed in .env-reference

**Verification Command:**
```bash
# Verify Config.php contains current feature flags
grep -n "public function.*Feature" lib/Config.php

# Check .env-reference for deployment vars
grep -E "FEATURE_|MAINTENANCE_|ROLLOUT_" .env-reference
```

### 1.2 Database Readiness

- [ ] All migrations applied to staging (via Migrator.php)
- [ ] Database backup created (pre-cutover point-in-time)
- [ ] ngn_2025 schema verified (all tables populated with test data)
- [ ] Legacy nextgennoise schema verified (read-only during cutover)
- [ ] Migration rollback procedures documented (manual SQL scripts)
- [ ] Data validation queries written (spot-check 100+ records)

**Verification SQL:**
```sql
-- Check ngn_2025 table count
SELECT COUNT(*) as table_count
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA='ngn_2025';

-- Expected: 100+ tables

-- Verify critical tables
SHOW TABLES FROM ngn_2025 LIKE 'cdm_%';
SHOW TABLES FROM ngn_2025 LIKE 'ranking%';
SHOW TABLES FROM ngn_2025 LIKE 'api_%';

-- Check for data
SELECT COUNT(*) FROM ngn_2025.users;        -- Should be > 1000
SELECT COUNT(*) FROM ngn_2025.artists;      -- Should be > 100
SELECT COUNT(*) FROM ngn_2025.releases;     -- Should be > 50
SELECT COUNT(*) FROM ngn_2025.rankings;     -- Should be > 0
```

### 1.3 Infrastructure Verification

- [ ] Load balancer configured for feature flag header routing
- [ ] Fastly CDN cache layer tested and purge key verified
- [ ] Read replica database configured and tested
- [ ] Redis cache (if used) verified and flushed
- [ ] File storage (/storage) mounted and writable on all servers
- [ ] Log aggregation configured (storage/logs/ directory)

**Health Check Command:**
```bash
# Test API health endpoint
curl -H "Authorization: Bearer [ADMIN_TOKEN]" \
  https://nextgennoise.com/api/v1/admin/health

# Expected response: { "success": true, "services": [...] }
```

### 1.4 Monitoring Setup Verification

- [ ] Alert system active and tested (AlertService.php)
- [ ] P95 latency monitoring job scheduled (check_api_latency.php)
- [ ] Admin monitoring dashboard accessible
- [ ] Admin alerts dashboard accessible
- [ ] Slack/PagerDuty webhook endpoints tested
- [ ] Email alerts configured for P2 events
- [ ] Log files configured with proper rotation

**Verification Commands:**
```bash
# Check alert job is scheduled
crontab -l | grep check_api_latency

# Verify alert tables exist
mysql -u root -p ngn_2025 << EOF
SHOW TABLES LIKE 'alert%';
SHOW TABLES LIKE 'api_health%';
EOF

# Test Slack webhook
curl -X POST [SLACK_WEBHOOK_URL] \
  -d '{"text": "Test alert from GA cutover checklist"}'
```

### 1.5 Feature Flag Configuration Verification

- [ ] `FEATURE_PUBLIC_VIEW_MODE` set to `'next'` in staging
- [ ] `FEATURE_PUBLIC_ROLLOUT` set to `false` initially
- [ ] `ROLLOUT_PERCENTAGE` set to `0` initially
- [ ] Sticky session bucketing tested (same session = same version)
- [ ] FeatureFlagController endpoints tested

**Test Feature Flags:**
```bash
# Test in staging: visit as user, verify routing to 2.0
# Open dev tools → Application → Cookies → check session ID
# Refresh page multiple times, should stay on same version

# Test API endpoint (if implemented)
curl -H "Authorization: Bearer [ADMIN_TOKEN]" \
  https://nextgennoise.com/api/v1/admin/feature-flags
```

### 1.6 Maintenance Mode Verification

- [ ] Maintenance mode middleware implemented and tested
- [ ] Admin bypass logic verified (session + JWT)
- [ ] Maintenance mode message customizable
- [ ] Maintenance ETA displayed to users
- [ ] IP whitelist for deployment servers configured

**Implementation Check:**
```php
// Verify in lib/bootstrap.php or middleware
$cfg = new NGN\Lib\Config();
if ($cfg->maintenanceMode()) {
    if (!$isAdminBypass) {
        http_response_code(503);
        // Show maintenance page
    }
}
```

### 1.7 Team & Documentation Review

- [ ] Team members briefed on deployment procedure
- [ ] Runbook printed/accessible (this document)
- [ ] Escalation contacts documented (on-call engineer, CTO, etc.)
- [ ] Rollback decision criteria defined (see Section 4)
- [ ] Communication plan established (status updates to stakeholders)
- [ ] 72-hour hyper-care schedule confirmed (who's monitoring when)

### 1.8 Stakeholder Sign-Off

- [ ] Engineering lead: ___________________ Date: ______
- [ ] DevOps/Release manager: ___________________ Date: ______
- [ ] Product/CTO: ___________________ Date: ______

---

## Part 2: Staging Verification (48 Hours)

### 2.1 Feature Parity Testing

Test the following in staging with `FEATURE_PUBLIC_VIEW_MODE=next`:

**Core Features:**
- [ ] Artist profile page loads correctly
- [ ] Label profile page loads correctly
- [ ] Charts/rankings page loads correctly
- [ ] Artist dashboard (authenticated) works
- [ ] Label dashboard (authenticated) works
- [ ] Fan engagement (comments, likes, sparks) functions
- [ ] Royalty/earnings dashboard displays correctly
- [ ] Rights management flows work (accept/dispute)

**User Flows:**
- [ ] User registration → artist/label setup
- [ ] Login → JWT token generation
- [ ] OAuth connections (Facebook, Instagram, Spotify)
- [ ] Spark tipping → royalty transaction recorded
- [ ] Rights split acceptance → verified in DB

**API Endpoints:**
- [ ] `/api/v1/artists` returns correct data
- [ ] `/api/v1/rankings` returns NGN scores
- [ ] `/api/v1/sparks/balance` returns user balance
- [ ] `/api/v1/rights/user` returns ledgers
- [ ] `/api/v1/royalty/*` endpoints functional

### 2.2 Performance Baseline

Run load test for 4+ hours with staging traffic:

**Target Metrics:**
- [ ] P95 latency < 200ms (target is 250ms, add 20% margin)
- [ ] P99 latency < 500ms
- [ ] Error rate < 2%
- [ ] 90th percentile response time < 400ms

**Commands:**
```bash
# Monitor metrics in real-time
watch -n 5 "curl -s -H 'Authorization: Bearer [TOKEN]' \
  https://nextgennoise.com/admin/api/metrics | jq '.api_latency'"

# Or check database directly
mysql ngn_2025 -e "
SELECT
  endpoint,
  ROUND(AVG(duration_ms), 2) as avg_ms,
  PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY duration_ms) as p95_ms,
  COUNT(*) as request_count
FROM api_request_metrics
WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY endpoint
ORDER BY p95_ms DESC
LIMIT 20;"
```

### 2.3 Alert System Dry Run

- [ ] Generate P0 alert (test SMS/PagerDuty)
- [ ] Generate P1 alert (test Slack)
- [ ] Generate P2 alert (test Email)
- [ ] Verify alert resolution workflow
- [ ] Verify alert deduplication (no spam)

**Generate Test Alert:**
```bash
# In admin panel, manually create alert or trigger via code
php -r "
require 'lib/bootstrap.php';
\$alertService = new NGN\Lib\Services\AlertService(\$pdo);
\$alertService->createAlert('test', 'P1', 'Test P1 alert for GA cutover');
"
```

### 2.4 Rollback Dry Run

- [ ] Set `FEATURE_PUBLIC_VIEW_MODE=legacy` (simulate rollback)
- [ ] Verify all traffic routes to 1.0
- [ ] Verify 1.0 still fully functional
- [ ] Clear any caches post-rollback
- [ ] Set back to `FEATURE_PUBLIC_VIEW_MODE=next`

---

## Part 3: Production Cutover Procedure

### 3.1 Pre-Cutover (T-0:30)

**30 minutes before cutover:**

1. Ensure on-call engineer is online and alert
2. Open monitoring dashboards in real-time
3. Set `MAINTENANCE_MODE=true` (takes site offline)
4. Display maintenance message with ETA (cutover start time + 15 mins)
5. Verify no ongoing transactions (wait 5 minutes for in-flight requests)

```bash
# Enable maintenance mode
# Update .env: MAINTENANCE_MODE=true
# Restart PHP-FPM (if needed) or reload config
systemctl reload php-fpm

# Verify maintenance mode is active
curl https://nextgennoise.com/ | grep -i "maintenance"
```

### 3.2 Phase 1: Canary Rollout (10 minutes)

**Objectives:** Route 1% of traffic to 2.0, verify stability

**Steps:**
1. Set `FEATURE_PUBLIC_ROLLOUT=true`
2. Set `ROLLOUT_PERCENTAGE=1`
3. Disable maintenance mode
4. Monitor metrics for 5 minutes

```bash
# Update .env:
# FEATURE_PUBLIC_ROLLOUT=true
# ROLLOUT_PERCENTAGE=1
# MAINTENANCE_MODE=false

# Reload configuration
systemctl reload php-fpm

# Verify feature flag is active
curl -H "Authorization: Bearer [ADMIN_TOKEN]" \
  https://nextgennoise.com/api/v1/admin/feature-flags | jq '.rollout_percentage'
```

**Canary Success Criteria (5 min monitor):**
- [ ] P95 latency < 300ms
- [ ] Error rate < 5%
- [ ] No P0 alerts fired
- [ ] No spike in webhook latency
- [ ] No data loss (spot-check DB)

**If Canary Fails:**
→ Execute Rollback Procedure (Section 4)

### 3.3 Phase 2: Gradual Rollout (40 minutes)

**Objectives:** Slowly increase 2.0 traffic, monitor each step

**Rollout Schedule:**
```
T+10m  → ROLLOUT_PERCENTAGE=10   (10% traffic)
T+20m  → ROLLOUT_PERCENTAGE=25   (25% traffic)
T+30m  → ROLLOUT_PERCENTAGE=50   (50% traffic, majority on 2.0)
T+40m  → ROLLOUT_PERCENTAGE=100  (100% traffic, all on 2.0)
```

**At Each Step:**
1. Wait 10 minutes
2. Check P95 latency (must be < 250ms)
3. Check error rate (must be < 5%)
4. Check for P1 alerts (resolve immediately if found)
5. Spot-check database for data consistency
6. If all green, proceed to next step

**Monitoring Commands (run every 2 minutes):**
```bash
# Real-time P95 latency
mysql ngn_2025 -e "SELECT ROUND(AVG(duration_ms), 2) as avg_ms, \
  COUNT(*) FROM api_request_metrics \
  WHERE created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE);"

# Error rate (HTTP 5xx)
mysql ngn_2025 -e "SELECT status_code, COUNT(*) FROM api_request_metrics \
  WHERE created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE) \
  GROUP BY status_code ORDER BY status_code;"

# Active alerts
mysql ngn_2025 -e "SELECT severity, type, COUNT(*) FROM alert_history \
  WHERE resolved_at IS NULL \
  AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE) \
  GROUP BY severity, type;"
```

**If Latency > 250ms:**
- Identify slowest endpoint: `SELECT endpoint, AVG(duration_ms) FROM api_request_metrics WHERE created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE) GROUP BY endpoint ORDER BY AVG(duration_ms) DESC;`
- Investigate (DB query? external API? missing index?)
- Pause rollout, troubleshoot
- Either fix and resume, or rollback if unfixable

**If Error Rate > 5%:**
- Check logs: `/storage/logs/api_errors.log`
- Identify error pattern (auth? payment processor? database?)
- Either fix quickly or rollback

**If P1 Alert Fires:**
- Acknowledge alert immediately
- Investigate root cause
- If critical (payment system down), rollback
- If minor (one endpoint slow), investigate without rollback

### 3.4 Phase 3: Verification (100% Rollout)

Once at 100%, monitor for 30 additional minutes:

**Success Criteria:**
- [ ] P95 latency stable < 250ms
- [ ] Error rate stable < 5%
- [ ] No new P0/P1 alerts in last 30 minutes
- [ ] User feedback positive (check support channel)
- [ ] Database transaction count normal
- [ ] Cache hit rate normal
- [ ] External API calls (Stripe, Spotify, etc.) successful

**If All Green:**
- [ ] Set `FEATURE_PUBLIC_ROLLOUT=false` (lock in 100% on 2.0)
- [ ] Notify stakeholders: "GA Cutover Complete"
- [ ] Update status page / social media
- [ ] Proceed to 72-hour hyper-care (Part 4)

---

## Part 4: 72-Hour Hyper-Care Protocol

### 4.1 Monitoring Schedule

**Hours 0-24 (First Day - Maximum Vigilance):**
- Continuous monitoring (no longer than 15-minute gaps)
- On-call engineer present (rotation acceptable, but overlap during first 8 hours)
- Check metrics every 5 minutes (admin dashboard auto-refreshes)
- Respond to alerts within 5 minutes
- User feedback channel monitored 24/7

**Hours 24-48 (Second Day - Close Watch):**
- Monitoring every 30 minutes during business hours
- Overnight monitoring (email-based alerts acceptable)
- Check metrics every 15 minutes during peak hours (8am-8pm)
- Respond to alerts within 15 minutes

**Hours 48-72 (Third Day - Standard Watch):**
- Monitoring every 2 hours during business hours
- Monitoring email alerts overnight
- Check metrics once per shift
- Respond to alerts within 30 minutes

### 4.2 Hyper-Care Checklist

**Daily (Each 24h Period):**
- [ ] Review P1/P0 alert log (any new alerts?)
- [ ] Verify P95 latency remains < 250ms
- [ ] Check error rate (should be < 5%, ideally < 2%)
- [ ] Sample 10 random user actions (profile view, transaction, etc.)
- [ ] Verify rights/royalty transactions processed correctly
- [ ] Check webhook delivery for Stripe events
- [ ] Review user support tickets for "2.0 specific" issues
- [ ] Database transaction log verification (no hanging transactions)
- [ ] Cache hit rate verification (should be > 80%)

**Database Verification Queries:**
```sql
-- Daily health check at T+6h, T+24h, T+48h, T+72h
SELECT
  DATE_FORMAT(NOW(), '%Y-%m-%d %H:00:00') as check_time,
  COUNT(*) as request_count,
  ROUND(AVG(duration_ms), 2) as avg_latency_ms,
  ROUND(PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY duration_ms), 2) as p95_ms,
  SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) as errors_5xx,
  ROUND(100.0 * SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) / COUNT(*), 2) as error_pct
FROM api_request_metrics
WHERE created_at BETWEEN DATE_SUB(NOW(), INTERVAL 1 HOUR) AND NOW();

-- Verify royalty transactions
SELECT DATE(created_at) as date, COUNT(*) as tx_count, SUM(amount_net) as total_net
FROM cdm_royalty_transactions
WHERE created_at > DATE_SUB(NOW(), INTERVAL 72 HOUR)
GROUP BY DATE(created_at);

-- Check for any disputed rights
SELECT COUNT(*) as disputed_ledgers FROM cdm_rights_ledger
WHERE status = 'disputed'
AND disputed_at > DATE_SUB(NOW(), INTERVAL 72 HOUR);
```

### 4.3 Escalation Decision Matrix

| Issue | Decision Point | Action |
|-------|---|---|
| **P95 > 300ms** | Sustained > 10 min | Investigate endpoint, consider rollback if unfixable |
| **Error Rate > 10%** | Immediate | Investigate, consider rollback |
| **Payment System Down** | Immediate | Rollback |
| **Data Corruption** | Immediate | Rollback + restore from backup |
| **Severe User Complaints** | > 50 reports | Assess severity, rollback if critical feature broken |
| **Webhook Latency > 15s** | Sustained > 5 min | Investigate payment processor, notify Stripe |
| **Cache Hit Rate < 50%** | Sustained > 30 min | Investigate cache invalidation logic |

### 4.4 Issue Resolution Workflow

**If Issue Detected:**

1. **Assess Severity**
   - P0 (Critical): Payment system, data loss, widespread outage
   - P1 (High): Single feature broken, performance degradation, partial outage
   - P2 (Normal): Minor bugs, cosmetic issues, low user impact

2. **Determine Root Cause**
   - Check recent code changes (git log)
   - Review monitoring dashboards (latency, errors, alerts)
   - Check database transaction log
   - Review integration status (Stripe, Spotify, etc.)

3. **Decide: Fix or Rollback**
   - **Fix (P2 issues):** Quick patch, test in staging, deploy
   - **Rollback (P0 issues):** Immediate rollback, investigate post-mortem

4. **Execute Decision**
   - If fixing: Deploy fix, monitor for 30 minutes
   - If rolling back: Execute Rollback Procedure (Section 5)

5. **Post-Incident**
   - Document in incident log
   - RCA (Root Cause Analysis) within 24 hours
   - Implement preventative measures

---

## Part 5: Rollback Procedures

### 5.1 Emergency Rollback (Immediate)

**Trigger:** P0 issue (payment system down, data loss, critical feature broken)

**Time to Execute:** 5 minutes

**Steps:**

1. **Immediate Action** (30 seconds)
   ```bash
   # Set feature flag to legacy
   # Edit .env or update database:
   # FEATURE_PUBLIC_VIEW_MODE=legacy

   # Reload PHP-FPM
   systemctl reload php-fpm
   ```

2. **Verify Rollback** (2 minutes)
   ```bash
   # Test that site is now on 1.0
   curl https://nextgennoise.com/ | grep -i "legacy\|version"

   # Verify no errors on home page
   curl https://nextgennoise.com/api/v1/health
   ```

3. **Enable Maintenance Mode** (1 minute)
   ```bash
   # Keep MAINTENANCE_MODE=true for 30 minutes
   # Set ETA for when 1.0 is fully verified
   # MAINTENANCE_MESSAGE="We're reverting to prepare a fix. Back online shortly."
   ```

4. **Notify Stakeholders** (1 minute)
   - Post to incident channel
   - Alert on-call team
   - Update status page: "We've temporarily rolled back to investigate an issue"

5. **Disable Maintenance Mode** (after 10-min verification)
   ```bash
   # MAINTENANCE_MODE=false
   systemctl reload php-fpm
   ```

### 5.2 Graceful Rollback (If Time Permits)

**Trigger:** P1 issue that can't be fixed quickly but not critical

**Time to Execute:** 15 minutes

**Procedure:** Same as Emergency Rollback, but with 5-minute pause to verify each step

### 5.3 Post-Rollback Investigation

**Timeline:**
- T+0 to T+30min: Immediate rollback + verification
- T+30min to T+4h: Root cause analysis
- T+4h to T+24h: Fix development and testing
- T+24h+: Retry cutover with fix

**RCA Template:**
```
## Rollback RCA: [Date/Time]

### Issue Description
[What happened?]

### Impact
- Duration: [minutes]
- Users affected: [estimate]
- Revenue impact: [if applicable]

### Root Cause
[What went wrong? Code? Config? External service?]

### Timeline
- T+0: Issue detected
- T+X: Root cause identified
- T+Y: Rollback executed
- T+Z: 1.0 verified stable

### Fix
[What will be fixed before retry?]

### Preventative Measures
[How to prevent recurrence?]

### Retry Date
[When will we attempt cutover again?]
```

---

## Part 6: Post-Cutover Cleanup (Day 4+)

### 6.1 Decommission Legacy 1.0 (After 7 Days of Stable 2.0)

- [ ] Ensure all user data migrated to 2.0
- [ ] Final backup of Legacy 1.0 database (archive to S3)
- [ ] Disable legacy API endpoints (deprecation notice for 30 days first)
- [ ] Redirect old URLs to 2.0 equivalents (301 permanent redirects)
- [ ] Update DNS records (remove legacy load balancer if separate)
- [ ] Decommission legacy load balancer infrastructure
- [ ] Archive legacy code to separate branch (`legacy/1.0-final`)

### 6.2 Optimize 2.0 (After Cutover Stable)

- [ ] Analyze bottleneck endpoints (which had highest latency?)
- [ ] Implement database indexes for slow queries
- [ ] Cache optimization (adjust TTLs based on usage patterns)
- [ ] CDN optimization (adjust Fastly cache rules)
- [ ] Remove feature flag code (no longer needed)
- [ ] Simplify Config.php (remove legacy conditionals)

### 6.3 Communicate Success

- [ ] Blog post: "NGN 2.0 Launch Complete"
- [ ] Email to artists: "Welcome to NGN 2.0"
- [ ] In-app notification with new features
- [ ] Update documentation (internal + public)
- [ ] Thank the team (release notes acknowledge contributors)

---

## Appendix A: Emergency Contacts

| Role | Name | Phone | Email |
|------|------|-------|-------|
| On-Call Engineer | [NAME] | [PHONE] | [EMAIL] |
| DevOps Lead | [NAME] | [PHONE] | [EMAIL] |
| CTO / Decision Maker | [NAME] | [PHONE] | [EMAIL] |
| Database Admin | [NAME] | [PHONE] | [EMAIL] |

---

## Appendix B: Tools & Dashboards

**Admin Dashboards:**
- Monitoring (P95 latency): https://nextgennoise.com/admin/monitoring.php
- Alerts: https://nextgennoise.com/admin/alerts.php
- Feature Flags: https://nextgennoise.com/admin/settings.php
- API Health: https://nextgennoise.com/admin/api-health.php

**Monitoring Tools:**
- CloudWatch: [Dashboard URL]
- New Relic: [Dashboard URL]
- Datadog: [Dashboard URL]
- Slack Integration: [Webhook URL]

**Database Access:**
```bash
# Primary database
mysql -h [DB_HOST] -u [USER] -p [DATABASE]

# Read replica
mysql -h [READ_REPLICA_HOST] -u [USER] -p [DATABASE]
```

---

## Appendix C: Rollback Decision Flowchart

```
┌─────────────────────────────┐
│  Issue Detected During GA   │
└──────────┬──────────────────┘
           │
      ┌────▼────┐
      │ P0 or   │
      │ P1 +    │
      │ Unfixed?│
      └────┬────┘
      ┌────▼──────────────────────┐
      │ YES: IMMEDIATE ROLLBACK   │
      │ - Set VIEW_MODE=legacy    │
      │ - Reload PHP              │
      │ - Verify working          │
      │ - Notify stakeholders     │
      └──────────────────────────┘

      ┌─────────────────────────┐
      │ NO: INVESTIGATE          │
      │ - Root cause analysis    │
      │ - Can fix in < 1 hour?  │
      └────┬────────────────────┘
           │
      ┌────▼─────┐
      │ Can fix? │
      └────┬────┬┘
      YES  │    │  NO
      ┌────▼┐ ┌─▼────────────────────────┐
      │Fix  │ │ ROLLBACK                │
      │Test │ │ - Set VIEW_MODE=legacy  │
      │Deploy   │ - Verify working      │
      │Monitor  │ - Schedule retry      │
      └─────┘ └───────────────────────┘
```

---

**Last Updated:** 2026-01-17
**Next Review:** After successful GA cutover
**Document Owner:** Release Manager

