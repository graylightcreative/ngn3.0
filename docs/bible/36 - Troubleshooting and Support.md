# Beta Launch Troubleshooting Guide

**For**: DevOps, Support, Emergency Response
**Purpose**: Diagnose and fix issues during beta launch
**Severity Levels**: P0 (Critical), P1 (High), P2 (Medium), P3 (Low)

---

## 🚨 CRITICAL ISSUES (P0 - Fix Immediately)

### Issue 1: API Endpoints Returning 500 Errors

**Symptoms**:
- `/api/v1/governance/sir` returns 500
- Governance dashboard shows "Server Error"
- Multiple endpoints affected

**Diagnosis Steps**:
```bash
# 1. Check error logs
tail -50 /var/log/nginx/error.log
tail -50 /storage/logs/app.log

# 2. Check database connection
mysql -u root -p -e "SELECT 1;"

# 3. Check file permissions
ls -la /var/www/ngn2.0/lib/Governance/

# 4. Verify PHP syntax
php -l /var/www/ngn2.0/lib/Governance/*.php

# 5. Test API directly
curl -X GET http://localhost/api/v1/governance/dashboard
```

**Common Causes & Fixes**:

| Cause | Fix |
|-------|-----|
| Database connection failed | Check DB credentials in .env, verify MySQL running |
| File permissions wrong | `chown -R www-data:www-data /var/www/ngn2.0` |
| PHP syntax error | Run `php -l` on all files, check logs |
| Missing .env file | Copy .env.example to .env, update values |
| Autoloader issue | Run `composer dump-autoload` |

**If Not Fixed**:
- 🚨 **Severity: P0** - Consider rollback
- Command: See [DAY5_LAUNCH_RUNBOOK.md](DAY5_LAUNCH_RUNBOOK.md#rollback-command-if-needed)

---

### Issue 2: Stripe Webhook Not Processing Payments

**Symptoms**:
- Payment completes but `user_subscriptions` not updated
- Customer charged but tier doesn't upgrade
- Stripe dashboard shows successful charge

**Diagnosis Steps**:
```bash
# 1. Check webhook logs
tail -100 /storage/logs/stripe_webhooks.log

# 2. Verify webhook endpoint accessible
curl -X POST https://your-domain.com/webhooks/stripe.php -d '{}'

# 3. Check signature verification
# Look for: "Invalid signature" in logs

# 4. Verify webhook configured in Stripe
# Stripe Dashboard → Developers → Webhooks → Check endpoint URL

# 5. Check database for subscription records
mysql -u root -p -e "SELECT * FROM user_subscriptions LIMIT 5;"
```

**Common Causes & Fixes**:

| Cause | Fix |
|-------|-----|
| Wrong webhook secret | Update `STRIPE_WEBHOOK_SECRET` in .env with live value |
| Endpoint not accessible | Verify HTTPS working, check firewall |
| Database connection in webhook | Check `ngn_2025` database exists and accessible |
| Wrong Stripe key (test vs live) | In staging use sk_test_, production use sk_live_ |
| Webhook not configured in Stripe | Add endpoint in Stripe Dashboard |

**Test Webhook Manually**:
```bash
# Send test event from Stripe CLI (if available)
stripe trigger checkout.session.completed
```

**If Not Fixed**:
- Check that TEST mode events go to test tables
- Verify .env has correct STRIPE_WEBHOOK_SECRET
- Consider: Restart nginx + php-fpm

---

### Issue 3: Database Tables Don't Exist

**Symptoms**:
- Error: "Table 'directorate_sirs' doesn't exist"
- Governance dashboard shows error
- Can't create SIRs

**Diagnosis Steps**:
```bash
# 1. Connect to database
mysql -u root -p

# 2. Show tables
USE ngn_2025;
SHOW TABLES LIKE 'directorate%';
SHOW TABLES LIKE 'sir_%';

# 3. If missing, check migration
cat /var/www/ngn2.0/migrations/sql/schema/45_directorate_sir_registry.sql

# 4. Run migration if needed
mysql -u root -p ngn_2025 < /var/www/ngn2.0/migrations/sql/schema/45_directorate_sir_registry.sql

# 5. Verify creation
SHOW TABLES LIKE 'sir_%';
DESCRIBE directorate_sirs;
```

**If Not Fixed**:
- Check migration file exists and is readable
- Check MySQL user has CREATE TABLE permissions
- Look for errors in migration file (syntax)

---

## 🔴 HIGH PRIORITY ISSUES (P1 - Fix in 4 hours)

### Issue 4: Push Notifications Not Arriving

**Symptoms**:
- Director creates but doesn't receive push notification
- Mobile PWA doesn't get alerts
- Service worker registered but notifications silent

**Diagnosis Steps**:
```bash
# 1. Check notification service
mysql -u root -p -e "SELECT * FROM sir_notifications ORDER BY sent_at DESC LIMIT 5;"

# 2. Check logs
tail -50 /storage/logs/app.log

# 3. Verify FCM configuration
# Check: Does .env have FCM_SERVER_KEY or similar?
grep -i fcm /var/www/ngn2.0/.env

# 4. Test service worker
# On browser console: navigator.serviceWorker.getRegistrations()

# 5. Check push subscription
# Browser DevTools → Application → Service Workers
```

**Common Causes & Fixes**:

| Cause | Fix |
|-------|-----|
| Service worker not registered | Clear browser cache, reinstall PWA |
| FCM credentials missing | Add Firebase credentials to .env |
| Notifications permission denied | Ask user to re-enable in browser settings |
| Device offline when sent | Notifications queue locally, arrive when online |

**Manual Test**:
```bash
php /var/www/ngn2.0/lib/Governance/SirNotificationService.php test
```

---

### Issue 5: High Database Latency (P95 > 250ms)

**Symptoms**:
- Dashboard takes 5+ seconds to load
- API responses slow (especially analytics)
- Database CPU high

**Diagnosis Steps**:
```bash
# 1. Check slow query log
mysql -u root -p -e "SET GLOBAL slow_query_log=1; SET GLOBAL long_query_time=0.5;"
tail -f /var/log/mysql/slow.log

# 2. Check active queries
mysql -u root -p -e "SHOW PROCESSLIST;"

# 3. Check table sizes
mysql -u root -p -e "SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb FROM information_schema.TABLES WHERE table_schema = 'ngn_2025' ORDER BY size_mb DESC;"

# 4. Check indexes
SHOW INDEX FROM directorate_sirs;

# 5. Analyze query
EXPLAIN SELECT * FROM directorate_sirs WHERE status = 'open' ORDER BY created_at DESC;
```

**Common Causes & Fixes**:

| Cause | Fix |
|-------|-----|
| Missing indexes | Run recommended indexes from DAY 3 report |
| Too many joins | Optimize query, consider caching |
| No query caching | Implement Redis caching for analytics |
| Insufficient memory | Increase MySQL buffer pool size |
| Lock contention | Check for long-running transactions |

**Add Recommended Indexes** (from DAY 3 analysis):
```sql
ALTER TABLE directorate_sirs ADD INDEX idx_status_updated (status, updated_at);
ALTER TABLE sir_audit_log ADD INDEX idx_sir_created (sir_id, created_at);
ALTER TABLE sir_feedback ADD INDEX idx_sir_author (sir_id, author_user_id);
```

---

## 🟡 MEDIUM PRIORITY ISSUES (P2 - Fix Today)

### Issue 6: Permission Denied Errors

**Symptoms**:
- "You don't have permission to create SIR" (non-chairman)
- "Only assigned director can verify" (other director)
- 403 Forbidden responses

**Diagnosis Steps**:
```bash
# 1. Check user roles in database
mysql -u root -p -e "SELECT id, name, email FROM users WHERE id IN (1,2,3,4);"

# 2. Check governance config
grep -i "GOVERNANCE\|DIRECTOR" /var/www/ngn2.0/.env

# 3. Verify role checks in code
grep -n "isChairman\|isDirector" /var/www/ngn2.0/lib/Governance/DirectorateRoles.php

# 4. Test role assignment
php -r "require 'vendor/autoload.php'; \$roles = new NGN\\Lib\\Governance\\DirectorateRoles(1, ['brandon' => ['user_id' => 2]]); var_dump(\$roles->isChairman(1));"
```

**Common Causes & Fixes**:

| Cause | Fix |
|-------|-----|
| Wrong user IDs in .env | Update GOVERNANCE_CHAIRMAN_USER_ID, etc. to correct IDs |
| Director not in array | Add missing director to configuration |
| JWT token wrong user_id | Ensure token has correct user_id claim |
| Role check logic reversed | Review logic in DirectorateRoles.php |

---

### Issue 7: SIR Status Transitions Not Working

**Symptoms**:
- Can't change from OPEN to IN_REVIEW
- Get error: "Invalid status transition"
- Status stuck on current value

**Diagnosis Steps**:
```bash
# 1. Check status value in database
mysql -u root -p -e "SELECT id, status FROM directorate_sirs LIMIT 5;"

# 2. Check transition rules
grep -n "validTransitions\|validateStatusTransition" /var/www/ngn2.0/lib/Governance/SirRegistryService.php

# 3. Check permissions
# Is current user allowed to change status?
# Is current status actually in database or cache?

# 4. Check for stale cache
redis-cli FLUSHDB  # If using Redis
```

**Common Causes & Fixes**:

| Cause | Fix |
|-------|-----|
| Invalid transition attempted | Verify transition follows rules: OPEN→IN_REVIEW→RANT_PHASE→VERIFIED→CLOSED |
| Permission denied | Ensure user has role to make transition |
| Stale cache | Clear browser cache, refresh page |
| Status already changed | Reload SIR details |

---

## 🟢 LOW PRIORITY ISSUES (P3 - Fix Later)

### Issue 8: UI/UX Issues

**Common Issues & Fixes**:

| Issue | Fix |
|-------|-----|
| Button text misaligned | Clear browser cache, hard refresh (Cmd+Shift+R) |
| Icons not loading | Check CDN URL in .env, verify HTTPS |
| Mobile responsive broken | Check viewport meta tag in header |
| Colors inconsistent | Check CSS cache, verify theme config |

---

### Issue 9: Performance Optimizations

**If P95 latency is acceptable but could be better**:

```bash
# 1. Enable query caching
mysql -u root -p -e "SET GLOBAL query_cache_size=268435456;"

# 2. Add Redis for session storage
# Update .env: SESSION_DRIVER=redis

# 3. Enable gzip compression
# In nginx.conf: gzip on; gzip_types text/html application/json;

# 4. Implement page caching
# Cache dashboard for 60 seconds
# Cache API responses for 30 seconds
```

---

## 🛠️ QUICK REFERENCE: COMMANDS BY SEVERITY

### P0 - Critical (Fix Now)
```bash
# Restart services
systemctl restart nginx
systemctl restart php-fpm
systemctl restart mysql

# Check status
systemctl status nginx
systemctl status php-fpm
systemctl status mysql

# View logs
tail -50 /var/log/nginx/error.log
tail -50 /storage/logs/app.log
tail -50 /storage/logs/stripe_webhooks.log
```

### P1 - High (Fix in 4 hours)
```bash
# Database checks
mysql -u root -p ngn_2025 -e "CHECK TABLE directorate_sirs;"

# Query performance
EXPLAIN SELECT * FROM directorate_sirs WHERE status = 'open';

# Clear caches
redis-cli FLUSHDB
php artisan cache:clear
```

### P2 - Medium (Fix today)
```bash
# Configuration check
php scripts/pre_launch_validation.php

# Log review
tail -100 /storage/logs/app.log

# Permission check
ls -la /var/www/ngn2.0/
```

---

## 📞 ESCALATION PATH

### If Issue Unresolved

1. **15 minutes**: Check all diagnostics above
2. **30 minutes**: Search known issues
3. **60 minutes**: Post in #ngn-beta with details
4. **Emergency**: Page on-call engineer

### Information to Gather

When reporting unresolved issues:
- [ ] Error message (exact)
- [ ] URL that fails
- [ ] User affected (username)
- [ ] When it started (approximate time)
- [ ] Steps to reproduce
- [ ] Relevant log excerpts
- [ ] Screenshot (if applicable)
- [ ] Browser/device details

---

## ✅ ISSUE RESOLUTION CHECKLIST

After fixing any issue:

- [ ] Root cause identified
- [ ] Fix implemented
- [ ] Service restarted (if needed)
- [ ] Fix verified with test
- [ ] Issue reproduced and confirmed resolved
- [ ] User notified
- [ ] Issue documented for post-mortem
- [ ] Monitor for regression

---

**Troubleshooting Guide Status**: Ready to use
**Last Updated**: 2026-01-23
**Support**: Post in #ngn-beta or call on-call engineer

