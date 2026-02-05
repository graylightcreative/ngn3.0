# DAY 5: BETA LAUNCH RUNBOOK - Go Live Procedure

**Date**: 2026-01-25 (Friday - LAUNCH DAY)
**Duration**: 1-2 hours deployment + ongoing support
**Goal**: Deploy to staging, invite beta testers, go live

---

## 🎯 PRE-LAUNCH CHECKLIST (30 mins before deployment)

### Verify Everything Ready
- [ ] DAY 4 prep checklist completed: ✅
- [ ] Database backup created: ✅
- [ ] Rollback procedure tested: ✅
- [ ] Cron jobs configured: ✅
- [ ] Security headers set: ✅
- [ ] Monitoring configured: ✅
- [ ] All tests passing: ✅
- [ ] Team standing by: ✅

### Notify Team
- [ ] Message board: "Beta deployment starting in 30 mins"
- [ ] Alert stakeholders
- [ ] Ensure backup admin available

---

## 🚀 STEP 1: DEPLOY TO STAGING (15 mins)

### 1.1 Pull Latest Code
```bash
cd /path/to/ngn2.0
git status  # Verify clean working directory
git pull origin main  # Get latest
```
- [ ] No conflicts
- [ ] Working directory clean

### 1.2 Install/Update Dependencies
```bash
composer install
npm install  # if applicable
```
- [ ] All dependencies installed
- [ ] No errors

### 1.3 Run Database Migrations
```bash
# List pending migrations
mysql -u root -p ngn_2025 < migrations/sql/schema/45_directorate_sir_registry.sql

# Verify tables created
mysql -u root -p ngn_2025 -e "SHOW TABLES LIKE 'directorate%'; SHOW TABLES LIKE 'sir_%';"
```
- [ ] All 4 tables created
- [ ] No errors

### 1.4 Set Environment Variables
```bash
# Copy .env template
cp .env.example .env

# Update with actual values
nano .env  # or your editor

# Required for governance:
GOVERNANCE_CHAIRMAN_USER_ID=1
GOVERNANCE_BRANDON_USER_ID=2
GOVERNANCE_PEPPER_USER_ID=3
GOVERNANCE_ERIK_USER_ID=4
SIR_OVERDUE_THRESHOLD_DAYS=14
SIR_PUSH_NOTIFICATIONS_ENABLED=true

# Required for Stripe:
STRIPE_WEBHOOK_SECRET=whsec_xxx
STRIPE_API_KEY=sk_test_xxx

# JWT config (update if needed)
JWT_SECRET=your_jwt_secret_here
```
- [ ] .env file configured
- [ ] All required vars set
- [ ] No sensitive info committed

### 1.5 Clear Caches & Rebuild
```bash
php artisan cache:clear  # if using Laravel
composer dump-autoload
```
- [ ] Cache cleared
- [ ] Autoload rebuilt

### 1.6 Restart Web Server
```bash
# Nginx
systemctl restart nginx

# Or Apache
systemctl restart apache2

# Verify running
systemctl status nginx
```
- [ ] Web server running
- [ ] No errors in logs

---

## ✅ STEP 2: SMOKE TEST STAGING (10 mins)

### 2.1 Test Core Endpoints
```bash
# Test governance dashboard
curl -H "Authorization: Bearer TOKEN" \
  https://staging.ngn.local/api/v1/governance/dashboard

# Should return JSON with stats, not error

# Test SIR listing
curl -H "Authorization: Bearer TOKEN" \
  https://staging.ngn.local/api/v1/governance/sir

# Should return array or empty array, not error
```
- [ ] Governance endpoints responding
- [ ] No 500 errors
- [ ] Valid JSON responses

### 2.2 Test Payment Webhook
```bash
# Test Stripe webhook endpoint
curl -X POST \
  -H "Content-Type: application/json" \
  -H "Stripe-Signature: test" \
  -d '{"type":"test"}' \
  https://staging.ngn.local/webhooks/stripe.php

# Should return 400 (invalid signature) not 500
```
- [ ] Webhook endpoint accessible
- [ ] Handles requests properly

### 2.3 Test Dashboard Features
- [ ] Visit: https://staging.ngn.local/dashboard/artist/analytics.php
  - [ ] Loads without 500 error
  - [ ] No console JavaScript errors

- [ ] Visit: https://staging.ngn.local/dashboard/station/tier.php
  - [ ] Loads correctly
  - [ ] Stripe checkout ready

### 2.4 Check Logs for Errors
```bash
tail -20 /var/log/nginx/error.log
tail -20 /storage/logs/stripe_webhooks.log
tail -20 /storage/logs/app.log
```
- [ ] No critical errors
- [ ] Only expected warnings (if any)

---

## 📊 STEP 3: INVITE BETA TESTERS (10 mins)

### 3.1 Create Access List
Beta testers:
- [ ] Jon Brock Lamb (Chairman)
- [ ] Brandon Lamb (SaaS Director)
- [ ] Pepper Gomez (Ecosystem Director)
- [ ] Erik Baker (Data Integrity Director)
- [ ] 5-10 early adopter artists/labels (TBD)

### 3.2 Send Invitations

**Email Template:**
```
Subject: 🚀 NGN 2.0.1 BETA - You're Invited!

Hi [Name],

We're excited to invite you to beta test NGN 2.0.1!

What's New (Chapter 31 - Governance):
• Standardized Input Request (SIR) tracking for board decisions
• Mobile push notifications
• One-tap verification
• Audit trail for all governance actions

What We Need:
• Test the governance workflow
• Report any bugs or issues
• Send feedback to: beta@ngn.local

Access:
URL: https://beta.ngn.local
Login: Your existing credentials
Test Mode: Stripe sandbox (use card: 4242 4242 4242 4242)

Duration: 2-4 weeks
Support: Reply to this email or visit #ngn-beta Slack channel

Thanks for helping us launch!
- Team NGN
```

- [ ] Send invitations to 4 board members (ASAP)
- [ ] Send invitations to 5-10 early adopters
- [ ] Include Slack channel link
- [ ] Include test data (if applicable)

### 3.3 Set Up Support Channel
```bash
# Slack channel: #ngn-beta or similar
# Post welcome message
# Pin beta tester guide
# Pin bug reporting form
```
- [ ] Slack channel created
- [ ] Welcome message posted
- [ ] Guides pinned

---

## 📋 STEP 4: PRODUCTION DEPLOYMENT (30 mins)

### 4.1 Final Database Backup
```bash
mysqldump -u root -p ngn_2025 > /backup/ngn_2025_before_launch.sql
tar -czf /backup/ngn_2.0.1_pre_launch.tar.gz \
  /path/to/ngn2.0/lib /path/to/ngn2.0/public
```
- [ ] Backup created
- [ ] Backups verified readable

### 4.2 Update Production Environment
```bash
# On production server
cd /var/www/ngn2.0
git pull origin main
composer install --no-dev  # Production
npm install --production  # if needed
```
- [ ] Code pulled
- [ ] Dependencies installed
- [ ] No conflicts

### 4.3 Run Production Migrations
```bash
# Same migration as staging
mysql -u root -p ngn_2025 < migrations/sql/schema/45_directorate_sir_registry.sql
```
- [ ] Migrations applied
- [ ] All 4 tables exist

### 4.4 Update Production Configuration
```bash
# Update .env with production values
# DO NOT commit to git
nano /var/www/ngn2.0/.env

# Production values:
APP_ENV=production
DEBUG=false  # CRITICAL!
STRIPE_API_KEY=sk_live_xxx  # Not test key!
STRIPE_WEBHOOK_SECRET=whsec_live_xxx  # Not test secret!
```
- [ ] .env updated with production keys
- [ ] DEBUG=false (no error exposure)
- [ ] All secrets from secure vault

### 4.5 Optimize & Secure Production
```bash
# Composer optimizations
composer dump-autoload --optimize --no-dev

# Set proper file permissions
chown -R www-data:www-data /var/www/ngn2.0
chmod -R 755 /var/www/ngn2.0
chmod -R 777 /var/www/ngn2.0/storage/logs

# Enable HTTPS
# Verify SSL certificates valid
curl -I https://your-domain.com  # Should show 200, HTTPS
```
- [ ] Optimizations applied
- [ ] Permissions set correctly
- [ ] HTTPS enabled
- [ ] SSL certificates valid

### 4.6 Restart Production Services
```bash
# Restart web server
systemctl restart nginx
systemctl restart apache2  # if applicable

# Verify
systemctl status nginx
ps aux | grep nginx  # Should show processes

# Clear caches
php artisan cache:clear  # if applicable
```
- [ ] Services restarted
- [ ] No errors
- [ ] Services confirmed running

### 4.7 Smoke Test Production
```bash
# Test governance endpoint on production
curl -H "Authorization: Bearer PROD_TOKEN" \
  https://your-domain.com/api/v1/governance/dashboard

# Should return valid JSON
```
- [ ] Production endpoint responding
- [ ] No 500 errors
- [ ] Valid responses

---

## 📢 STEP 5: ANNOUNCE BETA LAUNCH (5 mins)

### 5.1 Internal Announcement
```
Subject: ✅ NGN 2.0.1 BETA LIVE!

Team,

🚀 Beta is live! All systems green.

What's deployed:
✅ Governance system (Chapter 31)
✅ Enhanced dashboards
✅ Stripe integration
✅ Mobile PWA support

Access:
URL: https://your-domain.com
Staging: https://staging.your-domain.com

Support:
Slack: #ngn-beta
Issues: Create GitHub issue

Status Dashboard: https://your-domain.com/admin/beta-status

Let's ship!
```

- [ ] Announce to team
- [ ] Announce to stakeholders
- [ ] Post in Slack/Teams

### 5.2 Update Status Page
- [ ] Set status to "Beta Launch"
- [ ] Add timestamp
- [ ] Link to release notes

### 5.3 Monitor Initial Traffic
```bash
# Watch real-time logs
tail -f /var/log/nginx/access.log

# Monitor error rates
tail -f /var/log/nginx/error.log

# Check system resources
watch -n 2 'top -b -n 1 | head -20'
```
- [ ] Traffic flowing in
- [ ] No unusual error spikes
- [ ] System resources normal

---

## 🔍 STEP 6: INITIAL MONITORING (First 30 mins)

### 6.1 Watch for Errors
Monitor for first 30 minutes:
- [ ] API response times (should be < 250ms P95)
- [ ] Error rate (should be < 1%)
- [ ] Database connections (should not be exhausted)
- [ ] Stripe webhook success rate (should be > 99%)

### 6.2 Check User Adoption
- [ ] First login events?
- [ ] First SIR created?
- [ ] First payment processed?
- [ ] First push notification sent?

### 6.3 Be Ready for Issues
- [ ] Have rollback procedure ready
- [ ] Have debugging tools open
- [ ] Have backup admin standing by
- [ ] Have communication channels open

### 6.4 Common Issues to Watch For
- [ ] Database connection pool exhausted → Increase pool size
- [ ] High latency spike → Check query logs
- [ ] Stripe webhooks failing → Check signature verification
- [ ] Push notifications not sent → Check FCM credentials
- [ ] Permission errors → Check .env and database config

---

## ✅ STEP 7: FIRST DAY SUCCESS CRITERIA

### By end of DAY 5:
- [ ] Beta testers have access
- [ ] No critical errors (P0) found
- [ ] System staying up reliably
- [ ] Performance within spec (P95 < 250ms)
- [ ] At least 1 SIR created successfully
- [ ] At least 1 payment processed successfully

### If all above met: ✅ **BETA LAUNCH SUCCESSFUL**

### If issues found:
1. Assess severity (P0/P1/P2/P3)
2. If P0: Consider rollback
3. If P1/P2: Create GitHub issue, start fix
4. If P3: Document for future sprint

---

## 📞 SUPPORT ESCALATION

**If critical issue (P0):**

1. Immediately notify team in Slack
2. Start diagnosis:
   - Check logs for errors
   - Check database connectivity
   - Check Stripe webhook status
   - Check external service status
3. Decide: Fix vs Rollback
   - If fixable in < 30 mins: Fix
   - If > 30 mins: Rollback to pre-launch
4. Implement solution
5. Verify fix works
6. Announce resolution

**Rollback Command (if needed):**
```bash
# Stop current version
systemctl stop nginx

# Restore from backup
mysql -u root -p ngn_2025 < /backup/ngn_2025_before_launch.sql
tar -xzf /backup/ngn_2.0.1_pre_launch.tar.gz -C /

# Restart
systemctl start nginx

# Verify
curl https://your-domain.com/api/v1/governance/dashboard
```

---

## 🎉 LAUNCH DAY SUMMARY

### What We're Launching:
✅ NGN 2.0.1 with governance system (Chapter 31)
✅ Board member workflow automation
✅ Enhanced dashboards and features
✅ Production-grade payments

### Success Metrics:
✅ Zero critical bugs on launch day
✅ System uptime: 99.9%+
✅ API performance: P95 < 250ms
✅ Beta tester adoption: 4/4 board members + early adopters

### Next Steps After Launch:
- Day 6-7: Monitor beta closely
- Week 2: Collect feedback
- Week 3-4: Bug fixes and iterations
- Week 5+: Plan full production release

---

## 📊 FINAL GO/NO-GO CHECKLIST

**Ready to launch?**

- [ ] All DAY 4 prep completed
- [ ] Staging tested successfully
- [ ] Production configured
- [ ] Backups created
- [ ] Rollback procedure tested
- [ ] Beta testers identified
- [ ] Support channels ready
- [ ] Monitoring configured
- [ ] Team briefed
- [ ] Emergency contacts available

**If ALL above: ✅ GO FOR LAUNCH**
**If ANY missing: 🔴 WAIT - address first**

---

**Runbook Status**: Ready to execute
**Estimated Duration**: 1-2 hours
**Team Required**: 1-2 engineers + 1 QA
**Support**: 24/7 for first week of beta

🚀 **GOOD LUCK! SEE YOU ON THE OTHER SIDE OF BETA LAUNCH!**

