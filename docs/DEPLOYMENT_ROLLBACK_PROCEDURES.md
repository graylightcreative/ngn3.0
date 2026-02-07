# NGN 2.0 Deployment Rollback & Emergency Procedures

This guide provides step-by-step procedures for rolling back deployments, enabling maintenance mode, and recovering from deployment failures.

## Table of Contents

1. [Quick Rollback](#quick-rollback)
2. [Emergency Maintenance Mode](#emergency-maintenance-mode)
3. [Database Rollback](#database-rollback)
4. [Rollback Triggers](#rollback-triggers)
5. [Post-Rollback Verification](#post-rollback-verification)
6. [Health Check Procedures](#health-check-procedures)

---

## Quick Rollback

### Scenario: Production Deployment Failed (NGN 2.0.1 → Previous Version)

**Time to Execute:** 5-10 minutes

#### Step 1: Stop Web Server

```bash
ssh user@nextgennoise.com
systemctl stop apache2
echo "Web server stopped at $(date)"
```

#### Step 2: Restore Files from Backup

```bash
# Verify backup exists
ls -lh /www/wwwroot/backups/2026-02-07_pre-2.0.1-promotion/nextgennoise_files/

# Remove current (broken) production
rm -rf /www/wwwroot/nextgennoise

# Restore from backup
cp -r /www/wwwroot/backups/2026-02-07_pre-2.0.1-promotion/nextgennoise_files \
      /www/wwwroot/nextgennoise

# Verify restoration
ls -la /www/wwwroot/nextgennoise/public/
```

#### Step 3: Restore Database (if needed)

```bash
# Check backup exists
ls -lh /www/wwwroot/backups/2026-02-07_pre-2.0.1-promotion/ngn_2025_db.sql

# Restore database
mysql -u root -p ngn_2025 < /www/wwwroot/backups/2026-02-07_pre-2.0.1-promotion/ngn_2025_db.sql

# Verify restore
mysql -u root -p -e "USE ngn_2025; SELECT COUNT(*) as artist_count FROM artists;"
```

#### Step 4: Fix File Permissions

```bash
cd /www/wwwroot/nextgennoise

# Ownership
chown -R www:www .

# Directory permissions
find . -type d -exec chmod 755 {} \;

# File permissions
find . -type f -exec chmod 644 {} \;

# Storage writable
chmod -R 775 storage/logs storage/cache storage/uploads storage/certificates
```

#### Step 5: Clear Caches

```bash
# OPcache
php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache cleared\n'; }"

# Redis
redis-cli FLUSHALL 2>/dev/null || echo "Redis not available"

# File cache
rm -rf storage/cache/*
```

#### Step 6: Restart Web Server

```bash
systemctl start apache2
systemctl status apache2

echo "Web server restarted at $(date)"
```

#### Step 7: Verify Rollback

```bash
# Check HTTP response
curl -s -w "\nHTTP Status: %{http_code}\n" https://nextgennoise.com/ | head -20

# Check logs
tail -50 /www/wwwroot/nextgennoise/storage/logs/$(date +%Y-%m-%d).log
```

---

### Scenario: Beta Deployment Failed (2.0.2 → 2.0.1)

**Time to Execute:** 3-5 minutes

```bash
ssh user@nextgennoise.com

# Remove broken beta
rm -rf /www/wwwroot/beta.nextgennoise.com

# Restore backup
cp -r /www/wwwroot/backups/2026-02-07_beta-2.0.1 \
      /www/wwwroot/beta.nextgennoise.com

# Fix permissions
cd /www/wwwroot/beta.nextgennoise.com
chown -R www:www .
chmod -R 755 . --max-depth=1

# Clear caches
rm -rf storage/cache/*

# Verify
curl -s https://beta.nextgennoise.com/ | head -20
```

---

## Emergency Maintenance Mode

### Enable Maintenance Mode (Immediate)

**Usage:** When you need to lock down the site without rolling back

#### Via Environment Variables

```bash
# SSH to server
ssh user@nextgennoise.com

# Edit .env
nano /www/wwwroot/nextgennoise/.env

# Add or change:
MAINTENANCE_MODE=true

# Save and exit (Ctrl+X, Y, Enter)

# Clear PHP opcache
php -r "opcache_reset();" 2>/dev/null

# Verify
curl -s https://nextgennoise.com/ | grep -i "maintenance"
```

#### Via Database (Hot-Reload, Instant)

```bash
# SSH to server
ssh user@nextgennoise.com

# Connect to MySQL
mysql -u root -p

# Run:
USE ngn_2025;
INSERT INTO feature_flags (flag_key, flag_value, enabled)
VALUES ('maintenance_mode', 'true', TRUE)
ON DUPLICATE KEY UPDATE flag_value = 'true', enabled = TRUE;

# Verify:
SELECT * FROM feature_flags WHERE flag_key = 'maintenance_mode';
```

### Disable Maintenance Mode

```bash
# Via .env
nano /www/wwwroot/nextgennoise/.env
# Change: MAINTENANCE_MODE=false

# OR via database
mysql -u root -p
USE ngn_2025;
UPDATE feature_flags SET enabled = FALSE WHERE flag_key = 'maintenance_mode';
```

---

## Database Rollback

### Verify Backups Exist

```bash
ssh user@nextgennoise.com

# List all backups
ls -lh /www/wwwroot/backups/

# Check pre-deployment backups
ls -lh /www/wwwroot/backups/2026-02-07_pre-2.0.1-promotion/

# Verify backup integrity
mysql -u root -p -e "mysql -u root -p < /www/wwwroot/backups/2026-02-07_pre-2.0.1-promotion/ngn_2025_db.sql --dry-run" 2>&1 | head
```

### Restore Specific Database

```bash
# Connect to server
ssh user@nextgennoise.com

# Create restore timestamp
RESTORE_TIME=$(date +%Y%m%d_%H%M%S)

# Backup current (for analysis)
mysqldump -u root -p ngn_2025 > /www/wwwroot/backups/failed_state_$RESTORE_TIME.sql

# Restore from known-good backup
mysql -u root -p ngn_2025 < /www/wwwroot/backups/2026-02-07_pre-2.0.1-promotion/ngn_2025_db.sql

# Verify restore
mysql -u root -p -e "USE ngn_2025; SELECT COUNT(*) FROM artists; SELECT COUNT(*) FROM users;"
```

### Verify Database Schema

```bash
mysql -u root -p

# Check table exists
USE ngn_2025;
SHOW TABLES LIKE 'content_ledger%';

# Check for migration issues
DESCRIBE content_ledger;
DESCRIBE content_ledger_verification_log;

# Check data integrity
SELECT COUNT(*) as total_routes FROM url_routes;
SELECT COUNT(*) as total_certificates FROM content_ledger;
```

---

## Rollback Triggers

### Trigger: Production Error Rate > 5%

**Symptom:** Error logs showing more than 5% request failures within first hour

**Action:**
```bash
# Check error rate
ssh user@nextgennoise.com
tail -1000 /www/wwwroot/nextgennoise/storage/logs/$(date +%Y-%m-%d).log | \
  grep -c "ERROR\|Exception\|Fatal"

# If high, enable maintenance mode immediately
echo "MAINTENANCE_MODE=true" >> /www/wwwroot/nextgennoise/.env
```

### Trigger: Database Connection Failures

**Symptom:** Connection pool exhaustion, "too many connections" errors

**Action:**
```bash
# Check MySQL status
mysql -u root -p -e "SHOW STATUS LIKE 'Threads_connected';"

# Check for stuck connections
mysql -u root -p -e "SHOW PROCESSLIST;" | grep -i sleep

# Kill idle connections
mysql -u root -p -e "KILL CONNECTION <id>;" # Repeat for stuck connections

# If persistent, rollback to previous version
```

### Trigger: Performance Degradation > 30%

**Symptom:** Response times triple (from ~200ms to ~600ms+)

**Action:**
```bash
# Measure current response time
curl -o /dev/null -s -w "Total: %{time_total}s\n" https://nextgennoise.com/

# If > 600ms:
# 1. Enable maintenance mode
# 2. Check database slow queries
tail -100 /var/log/mysql/slow.log

# 3. Clear caches
redis-cli FLUSHALL
rm -rf /www/wwwroot/nextgennoise/storage/cache/*

# 4. If still slow, rollback
```

### Trigger: SSL Certificate Errors

**Symptom:** HTTPS not working, certificate validation failures

**Action:**
```bash
# Check certificate status
openssl s_client -connect nextgennoise.com:443 -servername nextgennoise.com

# Check expiration
openssl x509 -in /path/to/cert.pem -noout -dates

# If expired, obtain new certificate
certbot renew --force-renewal

# If renewal fails, revert to previous certificate backup
```

### Trigger: Critical Features Broken (Login, Checkout, etc.)

**Symptom:** Users cannot log in, upload content, or complete transactions

**Action:**
1. **Immediately enable maintenance mode** (buy time)
2. **Check logs** for specific errors
3. **If error is in new code:** Rollback deployment
4. **If error is data-related:** Restore database from backup

```bash
ssh user@nextgennoise.com

# Enable maintenance ASAP
MAINT_FLAG='{"flag_key":"maintenance_mode","flag_value":"true","enabled":true}'
curl -X POST https://localhost/api/v1/admin/feature-flags \
  -H "Authorization: Bearer $ADMIN_JWT" \
  -d "$MAINT_FLAG"

# Check logs
grep -i "login\|auth\|checkout" /www/wwwroot/nextgennoise/storage/logs/*.log | tail -50
```

---

## Post-Rollback Verification

### Health Check (Required)

```bash
ssh user@nextgennoise.com

# Test production health endpoint
curl -s https://nextgennoise.com/health.php | jq '.'

# Expected output:
# {
#   "status": "ok",
#   "timestamp": "2026-02-07T10:30:00+00:00",
#   "version": "2.0.1",
#   "environment": "production",
#   "checks": {
#     "database": { "status": "ok" },
#     "storage": { "status": "ok" },
#     "tables": { "status": "ok" }
#   }
# }
```

### Functional Tests (Checklist)

```bash
# 1. Frontend accessibility
curl -s -I https://nextgennoise.com/ | grep "HTTP\|Content-Type"
# Expected: HTTP/1.1 200 OK

# 2. API responsiveness
curl -s https://nextgennoise.com/api/v1/artists | jq '.artists | length' 2>/dev/null
# Expected: Should return artist count

# 3. Database connectivity
curl -s https://nextgennoise.com/health.php | jq '.checks.database.status'
# Expected: "ok"

# 4. Storage writable
curl -s https://nextgennoise.com/health.php | jq '.checks.storage.status'
# Expected: "ok"

# 5. Login functionality
curl -c /tmp/cookies.txt -d "email=test@test.com&password=test" \
  https://nextgennoise.com/api/v1/auth/login
# Expected: JWT token returned

# 6. Content upload (admin only)
curl -X POST -F "file=@test.mp3" \
  -H "Authorization: Bearer $ADMIN_JWT" \
  https://nextgennoise.com/api/v1/upload
# Expected: 200 or 201 response
```

### Performance Baseline Check

```bash
# Response time should be < 500ms
for i in {1..10}; do
  curl -o /dev/null -s -w "Response time: %{time_total}s\n" https://nextgennoise.com/
  sleep 1
done

# Database query count
mysql -u root -p -e "SHOW GLOBAL STATUS LIKE 'Questions';"

# Server load
uptime
```

### Log Review

```bash
# Check for errors in last hour
ssh user@nextgennoise.com
tail -500 /www/wwwroot/nextgennoise/storage/logs/$(date +%Y-%m-%d).log | \
  grep -i "error\|warning\|exception\|fatal"

# Check Apache errors
tail -100 /var/log/apache2/error.log

# Check MySQL errors
tail -100 /var/log/mysql/error.log
```

---

## Health Check Procedures

### Automated Health Check (5-minute interval)

**Create monitoring script:**

```bash
#!/bin/bash
# health-check.sh - Run via cron every 5 minutes

PROD_URL="https://nextgennoise.com/health.php"
BETA_URL="https://beta.nextgennoise.com/health.php"
LOG="/var/log/ngn-health-check.log"

check_health() {
  local url=$1
  local env=$2

  response=$(curl -s -w "\n%{http_code}" "$url")
  http_code=$(echo "$response" | tail -1)
  body=$(echo "$response" | head -1)

  if [ "$http_code" != "200" ]; then
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] ERROR - $env returned HTTP $http_code" >> "$LOG"
    # Send alert
    echo "NGN $env health check failed: HTTP $http_code" | \
      mail -s "NGN Alert: $env Down" alert@nextgennoise.com
  else
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] OK - $env healthy" >> "$LOG"
  fi
}

check_health "$PROD_URL" "Production"
check_health "$BETA_URL" "Beta"
```

**Install cron job:**

```bash
# Add to crontab
crontab -e

# Add line:
*/5 * * * * /home/deploy/health-check.sh
```

### Manual Health Check (On-Demand)

```bash
# Production
echo "=== Production Health ==="
curl -s https://nextgennoise.com/health.php | jq '.'

# Beta
echo "=== Beta Health ==="
curl -s https://beta.nextgennoise.com/health.php | jq '.'

# API
echo "=== API Health ==="
curl -s https://api.nextgennoise.com/health | jq '.'
```

---

## Disaster Recovery Contact Tree

**In case of critical outage:**

1. **On-call Engineer:** Page from PagerDuty
2. **Engineering Lead:** Activate war room
3. **DevOps Lead:** Begin rollback procedures
4. **Communications:** Notify status page and users

**Emergency Contact:**
- DevOps Lead: [phone]
- Engineering Manager: [phone]
- On-call: See PagerDuty

---

## Documentation & Logs

### Deployment Log Format

```
[2026-02-07 10:30:00] Deployment started: 2.0.1 → Production
[2026-02-07 10:31:00] Backup created: nextgennoise_files
[2026-02-07 10:32:00] Code sync completed
[2026-02-07 10:33:00] Database migrations skipped (2.0.1 only)
[2026-02-07 10:34:00] Permissions set
[2026-02-07 10:35:00] Caches cleared
[2026-02-07 10:36:00] Apache restarted
[2026-02-07 10:37:00] Health check: OK
[2026-02-07 10:38:00] Deployment completed: SUCCESS
```

### Keep Records

```bash
# Store deployment log
cp deployment.log /var/log/ngn-deployments/2026-02-07_2.0.1_to_prod.log

# Archive backup
tar -czf /backups/archive/2026-02-07_pre-2.0.1.tar.gz \
  /www/wwwroot/backups/2026-02-07_pre-2.0.1-promotion/

# Cleanup old backups (keep 30 days)
find /www/wwwroot/backups -type d -mtime +30 -exec rm -rf {} \; 2>/dev/null
```

---

## Testing Rollback Procedure

**Practice rollback in staging before deploying to production:**

```bash
# 1. Deploy 2.0.1 to staging
./bin/deploy.sh deploy-staging

# 2. Verify staging works
curl -s https://staging.nextgennoise.com/health.php | jq '.'

# 3. Simulate failure (break a file)
rm staging/.env

# 4. Execute rollback
./scripts/rollback-staging.sh

# 5. Verify recovery
curl -s https://staging.nextgennoise.com/ | head

# 6. Document findings
echo "Rollback procedure tested and verified" >> docs/testing-log.txt
```

---

**Last Updated:** 2026-02-07
**Version:** 1.0
**Author:** NGN DevOps Team
