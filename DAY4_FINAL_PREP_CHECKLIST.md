# DAY 4: FINAL PREP CHECKLIST - Execute in Order

**Date**: 2026-01-24 (Saturday)
**Duration**: 2-3 hours
**Goal**: Prepare for DAY 5 beta launch

---

## 📋 SECTION 1: DOCUMENTATION FINALIZATION (30 mins)

### 1.1 Update API Documentation
- [ ] Add governance endpoints to API docs
  ```
  POST   /api/v1/governance/sir
  GET    /api/v1/governance/sir
  GET    /api/v1/governance/sir/{id}
  PATCH  /api/v1/governance/sir/{id}/status
  POST   /api/v1/governance/sir/{id}/verify
  POST   /api/v1/governance/sir/{id}/feedback
  GET    /api/v1/governance/sir/{id}/feedback
  GET    /api/v1/governance/dashboard
  ```
- [ ] Document authentication: Bearer JWT token
- [ ] Document request/response formats with examples

### 1.2 Create Beta Tester Guide
- [ ] Who: Board members (Chairman, Directors), early adopters
- [ ] Access: Link to staging environment
- [ ] Features to test:
  - Governance SIR workflow (Chairman creates, Director verifies)
  - Tier upgrade payment flow (Stripe sandbox)
  - Dashboard features (posts, shows, analytics)
  - Mobile PWA (one-tap governance verification)
- [ ] Bug reporting: Where to send issues
- [ ] Expected beta duration: 2-4 weeks

### 1.3 Create Admin Guide
- [ ] Governance dashboard navigation
- [ ] How to create/manage SIRs
- [ ] How to view audit logs
- [ ] How to check payment subscriptions
- [ ] Monitoring access points

### 1.4 Update README
- [ ] Add "Chapter 31: Governance System" section
- [ ] Link to new API docs
- [ ] List new dependencies (if any)
- [ ] Installation: How to run migrations

---

## ⚙️ SECTION 2: CRON JOB CONFIGURATION (30 mins)

### 2.1 Test Governance Cron Jobs

**Test 1: SIR Reminders (9 AM UTC daily)**
```bash
# Manually trigger to test
php /path/to/jobs/governance/send_sir_reminders.php

# Expected output:
# Sent reminder for SIR-2026-xxx to Brandon Lamb
# Processed N overdue SIRs
```

- [ ] Check `/storage/logs/` for output
- [ ] Verify `sir_notifications` table updated
- [ ] Confirm no errors

**Test 2: Quarterly Reports (6 AM on quarter start)**
```bash
# Manually trigger to test
php /path/to/jobs/governance/generate_governance_report.php

# Expected output:
# === Quarterly Governance Audit ===
# Total SIRs: X
# Completion Rate: Y%
# Average Days to Verify: Z
```

- [ ] Check report generation
- [ ] Verify calculations accurate
- [ ] Confirm no errors

### 2.2 Configure Cron Schedule

Add to crontab:
```bash
# SIR Reminders - Daily at 9 AM UTC
0 9 * * * php /path/to/jobs/governance/send_sir_reminders.php >> /var/log/cron_governance.log 2>&1

# Quarterly Reports - First day of each quarter at 6 AM UTC
0 6 1 1,4,7,10 * php /path/to/jobs/governance/generate_governance_report.php >> /var/log/cron_governance.log 2>&1
```

- [ ] Edit crontab: `crontab -e`
- [ ] Paste above lines
- [ ] Save and verify: `crontab -l`

### 2.3 Verify Cron Execution

- [ ] Check if jobs run on schedule (for next occurrence)
- [ ] Monitor `/var/log/cron_governance.log`
- [ ] Verify database updates happening

---

## 🔐 SECTION 3: SECURITY & MONITORING SETUP (30 mins)

### 3.1 Configure Security Headers (Web Server)

**For Nginx:**
```nginx
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'";
add_header X-Frame-Options "DENY";
add_header X-Content-Type-Options "nosniff";
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";
add_header X-XSS-Protection "1; mode=block";
```

**For Apache:**
```apache
Header set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'"
Header set X-Frame-Options "DENY"
Header set X-Content-Type-Options "nosniff"
Header set Strict-Transport-Security "max-age=31536000; includeSubDomains"
Header set X-XSS-Protection "1; mode=block"
```

- [ ] Add headers to web server config
- [ ] Restart web server
- [ ] Test with curl: `curl -I https://your-domain.com | grep X-Frame`

### 3.2 Set Up Monitoring Dashboard

Create monitoring for:
- [ ] API P95 latency (target: < 250ms)
- [ ] Error rate (target: < 1%)
- [ ] Database query performance
- [ ] Stripe webhook success rate
- [ ] System CPU/memory usage
- [ ] Governance workflow events

**Tools to consider**:
- New Relic, DataDog, or similar APM
- Or use existing logging: Check `/storage/logs/`

### 3.3 Configure Alerting

Set up alerts for:
- [ ] P95 latency > 250ms
- [ ] Error rate > 5%
- [ ] Stripe webhook failures
- [ ] Database connection pool exhaustion
- [ ] Failed governance SIR operations

---

## 💾 SECTION 4: BACKUP & ROLLBACK PLANNING (30 mins)

### 4.1 Create Pre-Beta Backup

```bash
# Database backup
mysqldump -u root -p ngn_2025 > /backup/ngn_2025_pre_beta_$(date +%Y%m%d).sql

# File backup
tar -czf /backup/ngn_2.0.1_pre_beta_$(date +%Y%m%d).tar.gz \
  /path/to/ngn2.0/lib \
  /path/to/ngn2.0/public \
  /path/to/ngn2.0/migrations

# Verify backups
ls -lh /backup/
```

- [ ] Verify database backup is readable
- [ ] Verify file backup is readable
- [ ] Store backups in safe location

### 4.2 Create Rollback Procedure

**If critical issue found during beta:**

```bash
# 1. Stop new SIR operations
echo "MAINTENANCE" > /public/api/v1/governance/status.txt

# 2. Restore database
mysql -u root -p ngn_2025 < /backup/ngn_2025_pre_beta_YYYYMMDD.sql

# 3. Restore files
tar -xzf /backup/ngn_2.0.1_pre_beta_YYYYMMDD.tar.gz

# 4. Restart web server
systemctl restart nginx  # or apache2

# 5. Verify rollback
curl https://your-domain.com/api/v1/governance/dashboard
```

- [ ] Document rollback procedure
- [ ] Test rollback in staging
- [ ] Keep it accessible for emergency

### 4.3 Create Runbook

Document step-by-step what to do if:
- [ ] Database crashes
- [ ] API endpoint returns 500 errors
- [ ] Stripe webhook fails
- [ ] High latency spike
- [ ] Security incident detected

**Each should include**: Detection → Diagnosis → Resolution → Verification

---

## ✅ SECTION 5: PRE-LAUNCH VALIDATION (30 mins)

### 5.1 Final Syntax Check
```bash
# Check all PHP files
find /path/to/ngn2.0 -name "*.php" -exec php -l {} \; | grep -i error

# Should return: nothing (no errors)
```
- [ ] Run and verify: 0 errors

### 5.2 Database Integrity Check
```bash
# Connect to database
mysql -u root -p ngn_2025

# Run checks
CHECK TABLE directorate_sirs;
CHECK TABLE sir_feedback;
CHECK TABLE sir_audit_log;
CHECK TABLE user_subscriptions;

# All should show: OK
```
- [ ] All tables: OK

### 5.3 Run All Tests (Final)
```bash
./vendor/bin/phpunit tests/Governance/
```
- [ ] Expected: 27/27 tests PASS
- [ ] If any fail: Debug and fix before launch

### 5.4 Verify Migrations
```bash
# Check migration status
mysql -u root -p ngn_2025 -e "SHOW TABLES;" | grep directorate_sirs

# Should show tables exist
```
- [ ] directorate_sirs: exists
- [ ] sir_feedback: exists
- [ ] sir_audit_log: exists
- [ ] sir_notifications: exists

### 5.5 Test Key Workflows
- [ ] Create SIR (as Chairman) → Works
- [ ] List SIRs → Shows created SIR
- [ ] Update status → Status changes
- [ ] Add feedback → Feedback appears
- [ ] View dashboard → Stats correct

---

## 📊 SECTION 6: FINAL CHECKLIST

### Before Handing Off to DAY 5

- [ ] All documentation updated
- [ ] Cron jobs configured and tested
- [ ] Security headers configured
- [ ] Monitoring setup
- [ ] Backups created
- [ ] Rollback procedure documented
- [ ] All tests passing
- [ ] Database integrity verified
- [ ] Key workflows tested
- [ ] Team notified of launch time

### Ready for DAY 5?
```
YES: ✅ All items above completed
NO: 🔴 Fix remaining items before launch
```

---

## 🚀 FINAL STATUS

If all above completed: **READY TO DEPLOY ON DAY 5**

**Next Steps**: Review DAY5_LAUNCH_RUNBOOK.md for launch procedure

---

**Checklist Status**: Ready to execute
**Estimated Time**: 2-3 hours
**Dependencies**: Database access, cron access, web server config access

