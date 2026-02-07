# NGN Infrastructure Reorganization & Deployment Guide

**Prepared:** February 7, 2026
**Target Deployment Date:** To be scheduled
**Estimated Duration:** 12-15 hours (over 2-3 days)

---

## Executive Summary

This guide orchestrates the complete infrastructure reorganization for Next Gen Noise (NGN) 2.0:

1. **Promote Beta 2.0.1 to Production** - Move finalized 2.0.1 to main `nextgennoise.com`
2. **Deploy Beta 2.0.2 (Digital Safety Seal)** - New content ledger features to `beta.nextgennoise.com`
3. **Compartmentalize Subdomains** - Enable clean separation: API, Admin, Legal, Help, Dashboard
4. **Prepare Custom Domains** - Infrastructure for artist custom domains (e.g., `artistname.com` → NGN)
5. **Establish Rollback Capability** - Safe deployment with quick recovery procedures

---

## Pre-Deployment Checklist

### Local Environment (Run Immediately)

```bash
cd /Users/brock/Documents/Projects/ngn_fresh

# 1. Run pre-deployment validation
chmod +x scripts/pre-deployment-checklist.sh
./scripts/pre-deployment-checklist.sh

# Expected output: All checks passed ✅

# 2. Verify all new files exist
ls -la lib/UI/VersionBanner.php
ls -la lib/HTTP/SubdomainRouter.php
ls -la scripts/verify-custom-domain.php
ls -la scripts/check-custom-domain.php
ls -la public/health.php
ls -la public/api/.htaccess
ls -la public/admin/.htaccess
ls -la public/legal/.htaccess
ls -la public/help/.htaccess
ls -la public/dashboard/.htaccess

# 3. Check .env variables
grep "NGN_VERSION\|NGN_RELEASE_DATE" .env

# Expected:
# NGN_VERSION=2.0.2
# NGN_RELEASE_DATE=2026-02-06

# 4. Verify .htaccess subdomain rules
grep -c "api\.nextgennoise\.com" public/.htaccess
# Expected: 1

# 5. Test database health (if local DB available)
php -r "require 'lib/bootstrap.php'; echo 'Bootstrap OK\n';"
```

---

## Phase 1: Pre-Deployment Preparation (Local Only)

**Time:** 2-3 hours
**Location:** Local development machine
**Risk:** None (local changes only)

### 1.1 Verify All Code Changes

```bash
# Review changed files
git status

# Expected files:
# - lib/UI/VersionBanner.php (new)
# - lib/HTTP/SubdomainRouter.php (new)
# - lib/bootstrap.php (modified - version banner injection)
# - .env (modified - version variables)
# - .env-reference (modified - version variables)
# - public/.htaccess (modified - subdomain routing)
# - public/api/.htaccess (new)
# - public/admin/.htaccess (new)
# - public/legal/.htaccess (new)
# - public/help/.htaccess (new)
# - public/dashboard/.htaccess (new)
# - lib/URL/ProfileRouter.php (modified - custom domain helper)
# - scripts/pre-deployment-checklist.sh (new)
# - scripts/verify-custom-domain.php (new)
# - scripts/check-custom-domain.php (new)
# - public/health.php (new)

# Show only new/modified files
git diff --name-only

# Show file size changes
git diff --stat
```

### 1.2 Create Feature Branch (for review)

```bash
git checkout -b feature/infrastructure-reorganization-2.0.2

# Stage all changes
git add -A

# Create commit with description
git commit -m "feat: Implement infrastructure reorganization for NGN 2.0

- Add VersionBanner component for environment visibility
- Implement SubdomainRouter for compartmentalization
- Configure .htaccess subdomain routing (api, admin, legal, help, my)
- Add custom domain verification scripts
- Create pre-deployment checklist and health endpoint
- Update ProfileRouter with custom domain support
- Add deployment documentation

Version: 2.0.2
Release Date: 2026-02-06
"

# Push to remote for review
git push origin feature/infrastructure-reorganization-2.0.2

# Open PR for code review
# Notify team: 'PR #XXX ready for review - infrastructure changes'
```

### 1.3 Prepare Backup Strategy

```bash
# Verify backup script exists
ls -la bin/backup.sh

# Test backup generation (local)
mkdir -p /tmp/ngn-backups
./bin/backup.sh /tmp/ngn-backups

# Verify backup size
du -sh /tmp/ngn-backups

# Expected: Database dump (~50MB) + files (~500MB) = ~550MB
```

---

## Phase 2: Production Promotion (2.0.1 to Main)

**Time:** 1-2 hours
**Location:** Remote server (`nextgennoise.com`)
**Risk:** Medium (production impact if failed)
**Window:** 3-5 AM PST (low traffic)

### 2.1 Pre-Promotion Checks (30 minutes before start)

```bash
# SSH to server
ssh user@nextgennoise.com

# 1. Check current production status
curl -s https://nextgennoise.com/health.php | jq '.status'
# Expected: "ok"

# 2. Check Beta 2.0.1 status
curl -s https://beta.nextgennoise.com/health.php | jq '.status'
# Expected: "ok"

# 3. Record baseline metrics
uptime
free -h
df -h /www/wwwroot

# 4. Check recent errors
tail -100 /var/log/apache2/error.log | grep -c ERROR
# Note the count for post-deployment comparison
```

### 2.2 Create Backup

```bash
# Create timestamped backup directory
mkdir -p /www/wwwroot/backups/2026-02-07_pre-2.0.1-promotion

# Backup production files
cp -r /www/wwwroot/nextgennoise \
      /www/wwwroot/backups/2026-02-07_pre-2.0.1-promotion/nextgennoise_files

# Verify backup
du -sh /www/wwwroot/backups/2026-02-07_pre-2.0.1-promotion/nextgennoise_files/
# Expected: ~400-600MB

# Backup production database
mysqldump -u root -p ngn_2025 > \
  /www/wwwroot/backups/2026-02-07_pre-2.0.1-promotion/ngn_2025_db.sql

# Verify backup integrity
wc -l /www/wwwroot/backups/2026-02-07_pre-2.0.1-promotion/ngn_2025_db.sql
# Expected: 100k+ lines

# Backup nextgennoise database (if exists)
mysqldump -u root -p nextgennoise > \
  /www/wwwroot/backups/2026-02-07_pre-2.0.1-promotion/nextgennoise_db.sql 2>/dev/null

echo "Backup completed: $(date)"
```

### 2.3 Sync Beta 2.0.1 to Production

```bash
# Option A: rsync (Recommended - with dry run first)
cd /www/wwwroot

# Dry run (preview changes)
rsync -av --dry-run \
    --exclude='.env' \
    --exclude='storage/logs/*' \
    --exclude='storage/cache/*' \
    --exclude='.git' \
    beta.nextgennoise.com/ \
    nextgennoise/ | head -100

# Review output - should show files being copied

# Execute sync
rsync -av --delete \
    --exclude='.env' \
    --exclude='storage/logs/*' \
    --exclude='storage/cache/*' \
    --exclude='.git' \
    beta.nextgennoise.com/ \
    nextgennoise/

echo "Sync completed: $(date)"
```

### 2.4 Update Production .env

```bash
# Edit production .env
nano /www/wwwroot/nextgennoise/.env

# Verify/update these variables:
# NGN_VERSION=2.0.1
# NGN_RELEASE_DATE=2026-01-30
# APP_ENV=production
# MAINTENANCE_MODE=false

# Verify database credentials match production
# DB_HOST=localhost
# DB_NAME=ngn_2025
# DB_USER=nextgennoise
# DB_PASS=(use password)

# Save and exit (Ctrl+X, Y, Enter)
```

### 2.5 Set File Permissions

```bash
cd /www/wwwroot/nextgennoise

# Set ownership
chown -R www:www .

# Set directory permissions
find . -type d -exec chmod 755 {} \;

# Set file permissions
find . -type f -exec chmod 644 {} \;

# Storage directories must be writable
chmod -R 775 storage/
chmod -R 775 storage/logs storage/cache storage/uploads storage/certificates

# Verify
ls -la storage/logs | head -3
# Expected: drwxrwxr-x
```

### 2.6 Clear Caches

```bash
cd /www/wwwroot/nextgennoise

# OPcache (in-process cache)
php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache cleared\n'; }"

# Redis cache (if available)
redis-cli FLUSHALL 2>/dev/null && echo "Redis cleared" || echo "Redis not available"

# File cache
rm -rf storage/cache/*
rm -rf storage/logs/* 2>/dev/null

echo "Caches cleared: $(date)"
```

### 2.7 Restart Web Server

```bash
# Graceful restart (completes existing requests)
systemctl reload apache2

# Wait for restart
sleep 5

# Verify running
systemctl status apache2 | grep Active
# Expected: "Active: active (running)"

echo "Web server restarted: $(date)"
```

### 2.8 Smoke Test Production

```bash
# 1. Check HTTP response
echo "=== HTTP Response ==="
curl -s -I https://nextgennoise.com/ | head -5
# Expected: HTTP/1.1 200 OK

# 2. Check version banner
echo "=== Version Banner ==="
curl -s https://nextgennoise.com/ | grep -o "NGN 2.0.1" | head -1

# 3. Check page loads
echo "=== Page Load (first 500 chars) ==="
curl -s https://nextgennoise.com/ | head -c 500

# 4. Check health endpoint
echo "=== Health Check ==="
curl -s https://nextgennoise.com/health.php | jq '.'

# 5. Check API
echo "=== API Health ==="
curl -s https://nextgennoise.com/api/v1/health | jq '.status' 2>/dev/null || echo "API endpoint check"

# 6. Check logs
echo "=== Recent Errors ==="
tail -20 /www/wwwroot/nextgennoise/storage/logs/$(date +%Y-%m-%d).log 2>/dev/null | grep -i error || echo "No recent errors"
```

### 2.9 Post-Promotion Monitoring (First 4 hours)

```bash
# Monitor every 15 minutes
watch -n 900 'curl -s https://nextgennoise.com/health.php | jq ".status"'

# OR manual checks every 15 minutes:
for i in {1..16}; do
  echo "Check $i at $(date)"
  curl -s https://nextgennoise.com/health.php | jq '.'
  sleep 900  # 15 minutes
done
```

---

## Phase 3: Deploy Beta 2.0.2 (Digital Safety Seal)

**Time:** 1-2 hours
**Location:** Remote server (`beta.nextgennoise.com`)
**Risk:** Low (beta environment)
**Window:** Any time (no traffic impact)

### 3.1 Backup Current Beta

```bash
ssh user@nextgennoise.com

# Backup current beta before 2.0.2 deployment
cp -r /www/wwwroot/beta.nextgennoise.com \
      /www/wwwroot/backups/2026-02-07_beta-2.0.1

echo "Beta backup completed: $(date)"
```

### 3.2 Deploy 2.0.2 Code

```bash
# Option A: rsync from local machine
cd /Users/brock/Documents/Projects/ngn_fresh

rsync -av --delete \
    --exclude='.env' \
    --exclude='.git' \
    --exclude='storage/logs/*' \
    --exclude='storage/cache/*' \
    --exclude='node_modules/*' \
    -e "ssh -p 22" \
    ./ user@nextgennoise.com:/www/wwwroot/beta.nextgennoise.com/

# Option B: Git deployment on server
ssh user@nextgennoise.com
cd /www/wwwroot/beta.nextgennoise.com
git fetch origin
git pull origin main  # or git checkout feature/infrastructure-reorganization-2.0.2
```

### 3.3 Update Beta .env

```bash
ssh user@nextgennoise.com

nano /www/wwwroot/beta.nextgennoise.com/.env

# Update:
# NGN_VERSION=2.0.2
# NGN_RELEASE_DATE=2026-02-06
# APP_ENV=beta
# MAINTENANCE_MODE=false
```

### 3.4 Run Database Migrations (2.0.2)

```bash
ssh user@nextgennoise.com

# Run content ledger migration
mysql -u root -p ngn_2025 < scripts/2026_02_06_content_ledger.sql

# Verify migration
mysql -u root -p -e "
USE ngn_2025;
SHOW TABLES LIKE 'content_ledger%';
DESCRIBE content_ledger;
DESCRIBE content_ledger_verification_log;
"

# Expected output: Two new tables with proper columns
```

### 3.5 Create Storage Directories

```bash
ssh user@nextgennoise.com
cd /www/wwwroot/beta.nextgennoise.com

# Create certificate storage
mkdir -p storage/certificates
chmod 775 storage/certificates
chown www:www storage/certificates

# Verify
ls -la storage/ | grep certificates
```

### 3.6 Set Permissions & Clear Caches

```bash
ssh user@nextgennoise.com
cd /www/wwwroot/beta.nextgennoise.com

# Ownership
chown -R www:www .

# Permissions
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;

# Storage writable
chmod -R 775 storage/

# Clear caches
php -r "opcache_reset();" 2>/dev/null
rm -rf storage/cache/*
redis-cli FLUSHALL 2>/dev/null || true
```

### 3.7 Smoke Test Beta 2.0.2

```bash
# 1. Check version banner
curl -s https://beta.nextgennoise.com/ | grep -o "NGN 2.0.2"

# 2. Check health endpoint
curl -s https://beta.nextgennoise.com/health.php | jq '.version'
# Expected: "2.0.2"

# 3. Check certificate endpoint
curl -s https://beta.nextgennoise.com/legal/certificate.php \
  | head -100
# Should show "The Fortress" page HTML

# 4. Check API
curl -s https://beta.nextgennoise.com/api/v1/health | jq '.'

# 5. Check for errors
tail -50 /www/wwwroot/beta.nextgennoise.com/storage/logs/$(date +%Y-%m-%d).log | grep -i error || echo "No errors"
```

---

## Phase 4: Subdomain Configuration

**Time:** 2-3 hours
**Location:** Remote server & domain registrar
**Risk:** Medium (DNS propagation)
**Note:** Can be done in parallel with Phase 3

### 4.1 Configure Subdomains in aaPanel

**Via SSH (if aaPanel API available):**

```bash
# Create subdomain sites
for subdomain in api admin legal help my; do
  # aaPanel CLI or API
  # aapanel-cli add-site "$subdomain.nextgennoise.com" \
  #   --path "/www/wwwroot/nextgennoise/public/$subdomain" \
  #   --php 8.2
  echo "Manual step: Create $subdomain.nextgennoise.com in aaPanel GUI"
done
```

**Manual Steps (via aaPanel Web UI):**

1. Login to `aaPanel` → **Website**
2. Click **Add Site**
3. For each subdomain (`api`, `admin`, `legal`, `help`, `my`):
   - **Domain:** `subdomain.nextgennoise.com`
   - **Path:** `/www/wwwroot/nextgennoise/public`
   - **PHP Version:** 8.2
   - **Database:** ngn_2025 (link existing)
   - **Click:** Add
4. For each site, add SSL certificate:
   - Select site
   - **SSL** → **Let's Encrypt**
   - Add domain: `subdomain.nextgennoise.com`
   - **Apply**

### 4.2 Add DNS Records

**Via Domain Registrar (Namecheap, Route53, etc.):**

```
Type    Name     Value                       TTL     Comment
================================================================
A       api      [Server IP]                 3600    API subdomain
A       admin    [Server IP]                 3600    Admin panel
A       legal    [Server IP]                 3600    Legal/Certificates
A       help     [Server IP]                 3600    Help/Support
A       my       [Server IP]                 3600    User dashboard
```

**Get Server IP:**

```bash
ssh user@nextgennoise.com
curl -s https://checkip.amazonaws.com
# Example: 12.34.56.789
```

### 4.3 Verify DNS Propagation

```bash
# Check DNS resolution (do this every 5 minutes until all resolve)
for subdomain in api admin legal help my; do
  echo "=== $subdomain.nextgennoise.com ==="
  dig $subdomain.nextgennoise.com +short
  nslookup $subdomain.nextgennoise.com | grep "Address:"
done

# Expected: All should return the server IP
# Note: May take 10-30 minutes to propagate
```

### 4.4 Verify SSL Certificates

```bash
# Check each subdomain has valid HTTPS
for subdomain in api admin legal help my; do
  echo "=== $subdomain.nextgennoise.com SSL ==="
  openssl s_client -connect $subdomain.nextgennoise.com:443 \
    -servername $subdomain.nextgennoise.com < /dev/null 2>/dev/null \
    | grep -A 2 "subject="
done

# Expected: Valid certificate issued for each subdomain
```

### 4.5 Test Subdomain Routing

```bash
# API Subdomain
echo "=== API ==="
curl -s https://api.nextgennoise.com/v1/health | jq '.status' 2>/dev/null || echo "Testing..."

# Admin Subdomain
echo "=== Admin ==="
curl -s https://admin.nextgennoise.com/ -I | grep HTTP

# Legal Subdomain
echo "=== Legal ==="
curl -s https://legal.nextgennoise.com/certificate.php | grep -o "Fortress" | head -1

# Help Subdomain
echo "=== Help ==="
curl -s https://help.nextgennoise.com/ -I | grep HTTP

# Dashboard Subdomain
echo "=== Dashboard ==="
curl -s https://my.nextgennoise.com/ -I | grep HTTP
```

---

## Phase 5: Custom Domain Preparation

**Time:** 1 hour
**Location:** Local/Remote (database only)
**Risk:** Low (preparation only)

### 5.1 Verify `url_routes` Table

```bash
mysql -u root -p -e "
USE ngn_2025;
SHOW TABLES LIKE 'url_routes';
DESCRIBE url_routes;
"

# Expected columns:
# id, entity_type, entity_id, url_slug, canonical_url,
# custom_domain, custom_domain_verified, custom_domain_verified_at,
# custom_domain_expires_at, created_at, updated_at
```

### 5.2 Create url_routes Table (if missing)

```bash
mysql -u root -p << 'EOF'
USE ngn_2025;

CREATE TABLE IF NOT EXISTS url_routes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('artist', 'label', 'station', 'venue', 'user') NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    url_slug VARCHAR(255) NOT NULL,
    canonical_url VARCHAR(512) NOT NULL,
    custom_domain VARCHAR(255) NULL,
    custom_domain_verified BOOLEAN DEFAULT FALSE,
    custom_domain_verified_at TIMESTAMP NULL,
    custom_domain_expires_at TIMESTAMP NULL,
    custom_domain_verification_token VARCHAR(255) NULL,
    custom_domain_requested_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    UNIQUE KEY uk_entity (entity_type, entity_id),
    UNIQUE KEY uk_slug (url_slug),
    UNIQUE KEY uk_domain (custom_domain),
    KEY idx_verified (custom_domain_verified),
    KEY idx_expires (custom_domain_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

EOF
```

### 5.3 Document Custom Domain Verification Process

**For artists setting up custom domain:**

1. **Request custom domain** in artist dashboard
2. **Verify ownership** via DNS TXT record:
   ```bash
   # Artist runs (or support runs for them):
   php scripts/verify-custom-domain.php example.com
   ```
3. **Add DNS TXT record** to their domain registrar:
   ```
   Name:  _ngn-verify
   Type:  TXT
   Value: ngn-verify-<random-token>
   TTL:   3600
   ```
4. **Verify in NGN system:**
   ```bash
   php scripts/check-custom-domain.php example.com
   ```
5. **Domain is active** → `example.com` → Artist's NGN profile

---

## Phase 6: Monitoring & Verification

**Time:** Ongoing (especially first 4 hours)
**Location:** All environments

### 6.1 Set Up Uptime Monitoring

**Via Uptime Robot (Free tier):**

1. Create account: https://uptimerobot.com/
2. Add monitors:
   - `https://nextgennoise.com/health.php` (every 5 min)
   - `https://beta.nextgennoise.com/health.php` (every 5 min)
   - `https://api.nextgennoise.com/health` (every 5 min)
   - `https://nextgennoise.com/` (main page, every 5 min)

3. Configure alerts:
   - Email: dev@nextgennoise.com
   - Slack: #alerts (if integrated)

### 6.2 Monitor Logs in Real-Time

```bash
ssh user@nextgennoise.com

# Watch production logs
tail -f /www/wwwroot/nextgennoise/storage/logs/$(date +%Y-%m-%d).log

# Watch error logs
tail -f /var/log/apache2/error.log

# Watch MySQL slow queries (in separate terminal)
tail -f /var/log/mysql/slow.log
```

### 6.3 Performance Baseline Comparison

```bash
# Record baseline before deployment (from Phase 2.1)
# Compare after deployment:

echo "=== Post-Deployment Performance ==="

# Response time (should be < 500ms)
curl -o /dev/null -s -w "Response Time: %{time_total}s\n" https://nextgennoise.com/

# Database connections
mysql -u root -p -e "SHOW STATUS LIKE 'Threads_connected';"

# Server load
uptime

# Memory usage
free -h

# Disk usage
df -h /www/wwwroot
```

### 6.4 Verify Feature Flags

```bash
# Check feature flags in database
mysql -u root -p -e "
USE ngn_2025;
SELECT flag_key, enabled FROM feature_flags WHERE enabled = TRUE;
"

# Verify maintenance mode is OFF
mysql -u root -p -e "
USE ngn_2025;
SELECT * FROM feature_flags WHERE flag_key = 'maintenance_mode';
"
# Expected: enabled = FALSE
```

---

## Post-Deployment Checklist

### Immediate (First Hour)

- [ ] Health checks all return `status: ok`
- [ ] No critical errors in logs
- [ ] All subdomains resolving to correct servers
- [ ] Production version banner shows "2.0.1 - PRODUCTION"
- [ ] Beta version banner shows "2.0.2 - BETA"
- [ ] Users reporting no issues (monitor social media, support)

### 4 Hours

- [ ] Monitor logs for memory leaks, connection issues
- [ ] Run functional test suite
- [ ] Check database query performance
- [ ] Verify backup integrity

### 24 Hours

- [ ] Performance metrics stable
- [ ] No increase in error rates
- [ ] User engagement metrics normal
- [ ] Archive deployment logs

---

## Rollback Criteria

**Automatic Rollback If:**

- Production error rate > 5% in first hour
- Database connection failures
- Login functionality broken
- Upload functionality broken
- SSL certificate errors

**See:** `/docs/DEPLOYMENT_ROLLBACK_PROCEDURES.md` for detailed procedures

---

## File Manifest

### New Files Created

| File | Purpose | Location |
|------|---------|----------|
| VersionBanner.php | Version/environment visibility | `lib/UI/` |
| SubdomainRouter.php | Subdomain routing logic | `lib/HTTP/` |
| pre-deployment-checklist.sh | Validation script | `scripts/` |
| verify-custom-domain.php | Domain verification initiation | `scripts/` |
| check-custom-domain.php | DNS verification checker | `scripts/` |
| health.php | Health check endpoint | `public/` |
| api/.htaccess | API subdomain config | `public/api/` |
| admin/.htaccess | Admin subdomain config | `public/admin/` |
| legal/.htaccess | Legal subdomain config | `public/legal/` |
| help/.htaccess | Help subdomain config | `public/help/` |
| dashboard/.htaccess | Dashboard subdomain config | `public/dashboard/` |

### Modified Files

| File | Change | Reason |
|------|--------|--------|
| lib/bootstrap.php | Added version banner injection | Environmental visibility |
| .env | Added NGN_VERSION, NGN_RELEASE_DATE | Version tracking |
| .env-reference | Added NGN_VERSION, NGN_RELEASE_DATE | Reference template |
| public/.htaccess | Added subdomain routing rules | Compartmentalization |
| lib/URL/ProfileRouter.php | Added custom domain helper | Custom domain support |

---

## Deployment Timeline

| Phase | Duration | Status |
|-------|----------|--------|
| Phase 1: Prep (local) | 2-3 hours | ✅ Completed |
| Phase 2: 2.0.1 → Prod | 1-2 hours | 🔄 In progress |
| Phase 3: 2.0.2 → Beta | 1-2 hours | ⏳ Ready |
| Phase 4: Subdomains | 2-3 hours | ⏳ Ready |
| Phase 5: Custom Domains | 1 hour | ⏳ Ready |
| Phase 6: Monitoring | 4+ hours | ⏳ Ongoing |
| **TOTAL** | **12-15 hours** | — |

---

## Success Criteria

### Production (nextgennoise.com) ✅

- [ ] HTTP 200 response on main page
- [ ] Version banner: "NGN 2.0.1 - PRODUCTION"
- [ ] Health endpoint returns `status: ok`
- [ ] API endpoints responding
- [ ] Login/authentication working
- [ ] User profiles loading
- [ ] Artist uploads functioning
- [ ] No spike in error logs

### Beta (beta.nextgennoise.com) ✅

- [ ] HTTP 200 response on main page
- [ ] Version banner: "NGN 2.0.2 - BETA"
- [ ] Health endpoint returns `status: ok`
- [ ] Digital Safety Seal certificate generation working
- [ ] `/legal/certificate.php?id=CRT-...` returns valid page
- [ ] QR codes generate and scan correctly
- [ ] Content ledger registration logging events

### Subdomains ✅

- [ ] `api.nextgennoise.com` - JSON responses, CORS headers
- [ ] `admin.nextgennoise.com` - Admin panel accessible
- [ ] `legal.nextgennoise.com` - Certificate pages loading
- [ ] `help.nextgennoise.com` - Support docs accessible
- [ ] `my.nextgennoise.com` - User dashboard (with auth)
- [ ] All have valid HTTPS certificates
- [ ] All show appropriate version banners

### Custom Domains ✅

- [ ] `url_routes` table exists and populated
- [ ] Verification scripts functional
- [ ] ProfileRouter custom domain resolution working
- [ ] Documentation prepared for artist onboarding

---

## Support & Escalation

### During Deployment

**Channel:** Slack #deployment (monitored continuously)

**Escalation Path:**
1. **Engineer:** Investigate issue, attempt fix
2. **Lead:** Consult on rollback decision
3. **Manager:** Approve rollback if needed
4. **DevOps:** Execute rollback procedure

### Post-Deployment

**Report bugs to:** dev@nextgennoise.com
**Status page:** status.nextgennoise.com
**Emergency contact:** [See war room contact tree]

---

## References

- **Rollback Procedures:** `/docs/DEPLOYMENT_ROLLBACK_PROCEDURES.md`
- **Version Banner Code:** `/lib/UI/VersionBanner.php`
- **Health Check:** `/public/health.php`
- **Digital Safety Seal Docs:** `/docs/DIGITAL_SAFETY_SEAL_IMPLEMENTATION.md`
- **Custom Domain Setup:** `/docs/CUSTOM_DOMAIN_GUIDE.md` (to be created)

---

**Prepared by:** Claude Code Agent
**Date:** February 7, 2026
**Status:** ✅ Ready for Execution

For questions or updates, contact: dev@nextgennoise.com
