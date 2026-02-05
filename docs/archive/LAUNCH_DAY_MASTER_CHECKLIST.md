# 🚀 LAUNCH DAY MASTER CHECKLIST

**Date**: 2026-01-25 (Friday)
**Status**: All systems green, ready to go
**Time Estimate**: 1-2 hours deployment + ongoing monitoring

---

## ⏰ TIMELINE

```
08:00 - Team standby, coffee time ☕
08:30 - Final pre-launch checks
09:00 - Deploy to staging
09:15 - Smoke test staging
09:30 - Invite beta testers
10:00 - Deploy to production
10:30 - Monitor first 30 mins closely
11:00 - Announce launch
12:00 - First day debrief
```

---

## 🎯 PRE-LAUNCH (30 mins before - 08:30)

### ✅ System Status Checks

- [ ] **Database**: MySQL running and accessible
  ```bash
  systemctl status mysql
  mysql -u root -p -e "SELECT 1;"
  ```
  Expected: Connected

- [ ] **Web Server**: Nginx/Apache running
  ```bash
  systemctl status nginx
  ```
  Expected: Active

- [ ] **PHP-FPM**: PHP running
  ```bash
  systemctl status php-fpm
  ```
  Expected: Active

- [ ] **Disk Space**: Check available space
  ```bash
  df -h
  ```
  Expected: > 20% free

- [ ] **Network**: Connectivity verified
  ```bash
  ping 8.8.8.8
  ```
  Expected: Replies

### ✅ Application Checks

- [ ] **Recent Changes**: All code committed to git
  ```bash
  git status
  ```
  Expected: Working directory clean

- [ ] **Dependencies**: All installed
  ```bash
  composer install --no-dev
  npm install --production
  ```
  Expected: No errors

- [ ] **Configuration**: .env configured correctly
  ```bash
  grep GOVERNANCE_CHAIRMAN /var/www/ngn2.0/.env
  ```
  Expected: GOVERNANCE_CHAIRMAN_USER_ID=1

- [ ] **Tests**: All passing
  ```bash
  ./vendor/bin/phpunit tests/Governance/ --no-coverage
  ```
  Expected: 27/27 PASS

- [ ] **Validation**: Pre-launch check passes
  ```bash
  php scripts/pre_launch_validation.php
  ```
  Expected: ✅ VALIDATION PASSED

### ✅ Team Readiness

- [ ] **On-Call Team**: Confirmed available
  - [ ] DevOps: Standing by
  - [ ] Backend: Standing by
  - [ ] QA: Standing by
  - [ ] Support: Available for questions

- [ ] **Communication**: Channels active
  - [ ] Slack #ngn-beta monitored
  - [ ] #ngn-alerts channel ready
  - [ ] Email notifications working

- [ ] **Monitoring**: Tools active
  - [ ] Datadog/Grafana dashboard open
  - [ ] Log aggregation streaming
  - [ ] Alert system armed

- [ ] **Backups**: Recent backup verified
  - [ ] Database backup < 1 hour old
  - [ ] File backup < 1 hour old
  - [ ] Backup restoration tested

### ✅ Beta Testers: Access Ready

- [ ] **4 Board Members Have Access**:
  - [ ] Jon Brock Lamb (Chairman)
  - [ ] Brandon Lamb (Director)
  - [ ] Pepper Gomez (Director)
  - [ ] Erik Baker (Director)

- [ ] **Early Adopters Ready** (5-10 additional):
  - [ ] Access created
  - [ ] Credentials sent
  - [ ] Onboarding guide shared

---

## 🚀 DEPLOY TO STAGING (09:00 - 15 mins)

### ✅ Step 1: Pull Latest Code
```bash
cd /var/www/staging/ngn2.0
git pull origin main
```
- [ ] No conflicts
- [ ] Code updated

### ✅ Step 2: Install Dependencies
```bash
composer install
npm install
```
- [ ] All dependencies installed
- [ ] No errors

### ✅ Step 3: Run Migrations
```bash
mysql -u root -p ngn_2025 < migrations/sql/schema/45_directorate_sir_registry.sql
```
- [ ] All 4 tables created
- [ ] No errors

### ✅ Step 4: Cache & Optimize
```bash
composer dump-autoload --optimize
php artisan cache:clear
```
- [ ] Cache cleared
- [ ] Autoload optimized

### ✅ Step 5: Restart Services
```bash
systemctl restart nginx
systemctl restart php-fpm
```
- [ ] Services restarted successfully
- [ ] No errors in logs

**Status**: ✅ Staging deployment complete

---

## ✅ SMOKE TEST STAGING (09:15 - 10 mins)

### ✅ Test 1: Governance Endpoints
```bash
curl -H "Authorization: Bearer TEST_TOKEN" \
  https://staging.ngn.local/api/v1/governance/dashboard
```
- [ ] Returns 200 OK with JSON
- [ ] No 500 errors

### ✅ Test 2: Dashboard Features
- [ ] Visit: https://staging.ngn.local/dashboard/artist/analytics.php
  - [ ] Loads without 500 error
  - [ ] No console JavaScript errors

- [ ] Visit: https://staging.ngn.local/dashboard/station/tier.php
  - [ ] Loads correctly
  - [ ] Stripe button visible

### ✅ Test 3: Logs Check
```bash
tail -20 /var/log/nginx/error.log
tail -20 /storage/logs/app.log
```
- [ ] No critical errors
- [ ] Only expected warnings (if any)

**Status**: ✅ Staging smoke test complete

---

## 👥 INVITE BETA TESTERS (09:30 - 15 mins)

### ✅ Step 1: Send Invitations

**Email Template**:
```
Subject: 🚀 NGN 2.0.1 BETA - You're Invited!

Hi [Name],

You're invited to beta test NGN 2.0.1 with Chapter 31 (Governance).

Access:
URL: https://beta.ngn.local
Login: Your existing credentials

What to Test:
• Create a SIR (Chairman only)
• Claim & verify SIRs (Directors)
• Mobile push notifications
• Tier upgrade payment flow

Support:
Slack: #ngn-beta
Guide: [Link to beta tester onboarding]

Duration: 2-4 weeks

Thanks for helping us ship!
```

- [ ] Send to Jon Brock Lamb
- [ ] Send to Brandon Lamb
- [ ] Send to Pepper Gomez
- [ ] Send to Erik Baker
- [ ] Send to 5-10 early adopters (if applicable)

### ✅ Step 2: Post in Slack

```
#ngn-beta channel:

🚀 BETA LAUNCH!

Governance System (Chapter 31) is now live!

👉 Get started: [Link to beta tester guide]

📋 What to test:
✅ SIR creation & workflow
✅ Mobile notifications
✅ Payment integration
✅ Tier upgrades

🐛 Found a bug? Report in this channel!

🙏 Thanks for being early adopters!
```

- [ ] Post welcome message
- [ ] Pin beta tester guide
- [ ] Pin bug reporting template

**Status**: ✅ Beta testers invited

---

## 📦 DEPLOY TO PRODUCTION (10:00 - 30 mins)

### ✅ Step 1: Final Database Backup
```bash
mysqldump -u root -p ngn_2025 > /backup/ngn_2025_pre_launch_final.sql
tar -czf /backup/ngn_2.0.1_pre_launch_final.tar.gz \
  /var/www/ngn2.0/lib /var/www/ngn2.0/public
```
- [ ] Database backup created
- [ ] File backup created
- [ ] Both verified readable

### ✅ Step 2: Pull Production Code
```bash
cd /var/www/ngn2.0
git pull origin main
composer install --no-dev
npm install --production
```
- [ ] Code pulled
- [ ] Dependencies installed

### ✅ Step 3: Run Migrations
```bash
mysql -u root -p ngn_2025 < migrations/sql/schema/45_directorate_sir_registry.sql
```
- [ ] Migrations completed
- [ ] No errors

### ✅ Step 4: Set Production Environment
```bash
# Update .env with production values
nano /var/www/ngn2.0/.env

# Verify critical settings:
# APP_ENV=production
# DEBUG=false
# STRIPE_API_KEY=sk_live_xxx (NOT test)
```
- [ ] .env updated
- [ ] DEBUG=false
- [ ] Live Stripe keys (not test)

### ✅ Step 5: Optimize Production
```bash
composer dump-autoload --optimize --no-dev
chown -R www-data:www-data /var/www/ngn2.0
chmod -R 755 /var/www/ngn2.0
```
- [ ] Optimizations applied
- [ ] Permissions set

### ✅ Step 6: Restart Production
```bash
systemctl restart nginx
systemctl restart php-fpm
sleep 5
systemctl status nginx php-fpm
```
- [ ] Services restarted
- [ ] Status: Active

### ✅ Step 7: Verify Production
```bash
curl https://your-domain.com/api/v1/governance/dashboard
```
- [ ] Returns 200 OK
- [ ] No 500 errors

**Status**: ✅ Production deployment complete

---

## 📢 ANNOUNCE LAUNCH (10:30 - 5 mins)

### ✅ Step 1: Internal Announcement

**Slack #general or #announcements**:
```
🚀 NGN 2.0.1 BETA IS LIVE!

✅ Governance System (Chapter 31) deployed
✅ All systems operational
✅ Board members have access

What's new:
• Standardized Input Requests (SIRs) for board decisions
• Mobile push notifications
• One-tap verification
• Audit trails & governance dashboard

Join: #ngn-beta
Report issues: Create GitHub issue or post in #ngn-beta

🙏 Thanks to the amazing team!
```

- [ ] Posted to internal Slack
- [ ] Pinned key information

### ✅ Step 2: Status Page Update
- [ ] Update status.ngn.local (if you have one)
  - [ ] Set to "Beta Launch"
  - [ ] Add timestamp
  - [ ] Link to release notes

### ✅ Step 3: Email Stakeholders
```
To: [Stakeholders]
Subject: NGN 2.0.1 BETA LIVE - Chapter 31 Governance

The beta is now live!

Access: https://your-domain.com
Status Dashboard: [Link]
Support: #ngn-beta

ETA for production release: 2-4 weeks

Thanks for your support!
```

- [ ] Email sent to key stakeholders

**Status**: ✅ Launch announced

---

## 📊 MONITOR FIRST 30 MINS (10:30-11:00)

### ✅ Continuous Monitoring

**Every 2 minutes for first 30 minutes**:
- [ ] Check API response times (should be < 250ms)
- [ ] Check error rate (should be < 1%)
- [ ] Check active users (should be increasing)
- [ ] Review real-time logs for errors
- [ ] Check Slack for user issues

### ✅ Watch for Critical Issues

If you see ANY of these:
- 🚨 > 10 HTTP 500 errors/minute
- 🚨 API P95 latency > 500ms
- 🚨 Stripe webhooks failing
- 🚨 Database connection exhausted

**IMMEDIATE ACTION**: Page on-call engineer

### ✅ First User Success Metrics

- [ ] **First Login**: Board member accesses dashboard
- [ ] **First SIR**: Chairman creates SIR successfully
- [ ] **First Notification**: Director receives push notification
- [ ] **First Payment**: Someone initiates tier upgrade
- [ ] **First Verification**: Director verifies SIR

**Expected Timeline**: First 15 mins of launch

---

## ✅ FIRST HOUR CHECKLIST

### 11:00 - After First 30 Mins of Monitoring

- [ ] **No Critical Issues**: Zero P0 alerts
- [ ] **Performance Stable**: P95 < 250ms maintained
- [ ] **Users Active**: Multiple users testing
- [ ] **Logs Clean**: No error spikes
- [ ] **Alerts Working**: At least one test alert received

### If All Above ✅ → LAUNCH SUCCESSFUL

---

## 🎉 SUCCESS CRITERIA

**BETA LAUNCH IS SUCCESSFUL IF**:

- ✅ Zero critical issues (P0) in first hour
- ✅ API performance < 250ms P95 average
- ✅ Error rate < 1% average
- ✅ All 4 board members have access
- ✅ At least one SIR created successfully
- ✅ Push notifications arriving
- ✅ No database connection issues
- ✅ Monitoring dashboards visible
- ✅ Support team responsive in Slack

**IF ANY OF ABOVE FAIL** → Escalate to on-call lead

---

## 🚨 EMERGENCY RESPONSE

### If Critical Issue Found

**Decision Tree**:
1. **Can fix in < 15 mins?** → Fix it, test, monitor
2. **Can fix in < 60 mins?** → Start fix, monitor, document
3. **Can't fix quickly?** → Consider rollback

**To Rollback**:
```bash
# Stop services
systemctl stop nginx

# Restore from backup
mysql -u root -p ngn_2025 < /backup/ngn_2025_pre_launch_final.sql
tar -xzf /backup/ngn_2.0.1_pre_launch_final.tar.gz -C /

# Restart
systemctl start nginx

# Verify
curl https://your-domain.com/api/v1/governance/dashboard
```

**Announce**:
- Post in Slack: "Rolled back due to [issue]. Investigating..."
- Notify beta testers: "Temporary unavailability. We're on it!"

---

## 📋 FINAL SIGN-OFF

### Before Declaring Launch Successful

**Checklist**:
- [ ] All checks above completed
- [ ] Team lead approved
- [ ] Monitoring active
- [ ] Support team ready
- [ ] Documentation shared
- [ ] Beta testers confirmed active
- [ ] First issues logged (if any)
- [ ] Celebration scheduled 🎉

---

## 📞 WHO TO CONTACT

| Issue | Contact | Slack |
|-------|---------|-------|
| API Down | DevOps Lead | @devops |
| Payment Issue | Backend Lead | @backend |
| User Access Issue | Support Lead | @support |
| Emergency | On-Call Engineer | @oncall |

---

## 🎊 POST-LAUNCH (After 11:00)

- [ ] Send thank you message to team
- [ ] Log initial metrics
- [ ] Schedule first debrief (end of day)
- [ ] Create incident report (if any issues)
- [ ] Plan for Day 2 monitoring
- [ ] Celebrate! 🎉

---

## 📊 LAUNCH REPORT TEMPLATE

**After successful launch, complete**:

```
LAUNCH REPORT - NGN 2.0.1 BETA
Date: 2026-01-25
Time: 10:00 UTC

STATUS: ✅ SUCCESSFUL

Metrics:
- Users Active: X
- API P95: Xms
- Error Rate: X%
- Critical Issues: 0
- Beta Testers: 4+X

Issues Found: [List any P1/P2 issues]

Next Steps: [Tomorrow's tasks]

Team: [Thank the team!]
```

---

**LAUNCH DAY MASTER CHECKLIST**: READY TO EXECUTE
**Expected Outcome**: Successful beta launch with zero critical issues
**Confidence Level**: 🟢 VERY HIGH
**GO/NO-GO**: 🚀 GO FOR LAUNCH!

