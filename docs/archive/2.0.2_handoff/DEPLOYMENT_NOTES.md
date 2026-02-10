# NGN 2.0.2 Digital Safety Seal - Deployment Notes

## 🎯 Implementation Status: ✅ COMPLETE

All code for the Digital Safety Seal system has been implemented, tested locally, committed to git, and pushed to GitHub.

### Quick Stats
- **Total New Code**: 1,493 lines
- **Files Created**: 5 (services, API endpoint, database migration, documentation)
- **Files Modified**: 4 (upload flows)
- **Git Commits**: 2 (feature + documentation)
- **Repository**: https://github.com/graylightcreative/ngn2.0.git
- **Branch**: main (ready to deploy)

---

## 🚀 Deployment to beta.nextgennoise.com

### Server Details
- **Host**: 209.59.156.82
- **User**: root
- **SSH Connection**: `ssh root@209.59.156.82 -p 22`
- **Password**: Stored in .env (SSH_PASSWORD)
- **Document Root**: /www/wwwroot/beta.nextgennoise.com
- **Database**: server.starrship1.com:3306 / ngn_2025
- **DB User**: ngn_2025 / NextGenNoise!1

### Step 1: Deploy Code to Server

**Option A: Using Git (Recommended)**
```bash
ssh root@209.59.156.82

# Navigate to site directory
cd /www/wwwroot/beta.nextgennoise.com

# Pull latest changes from main branch
git pull origin main

# Verify new files exist
ls -la lib/Legal/
ls -la public/api/v1/legal/
```

**Option B: Direct SCP (If Git not available)**
```bash
# Copy new service files
scp lib/Legal/ContentLedgerService.php root@209.59.156.82:/www/wwwroot/beta.nextgennoise.com/lib/Legal/
scp lib/Legal/DigitalCertificateService.php root@209.59.156.82:/www/wwwroot/beta.nextgennoise.com/lib/Legal/

# Copy API endpoint
mkdir -p /www/wwwroot/beta.nextgennoise.com/public/api/v1/legal/
scp public/api/v1/legal/verify.php root@209.59.156.82:/www/wwwroot/beta.nextgennoise.com/public/api/v1/legal/
```

### Step 2: Apply Database Migration

```bash
# From your local machine or remote server:
mysql -h server.starrship1.com -u ngn_2025 -pNextGenNoise!1 ngn_2025 < scripts/2026_02_06_content_ledger.sql

# Verify tables were created:
mysql -h server.starrship1.com -u ngn_2025 -pNextGenNoise!1 ngn_2025 -e "SHOW TABLES LIKE 'content_ledger%';"

# Expected output:
# Tables_in_ngn_2025 (content_ledger%)
# content_ledger
# content_ledger_verification_log
```

### Step 3: Verify Directory Permissions

```bash
ssh root@209.59.156.82

# Ensure certificate storage directory is writable
mkdir -p /www/wwwroot/beta.nextgennoise.com/storage/certificates/
chmod 775 /www/wwwroot/beta.nextgennoise.com/storage/certificates/
chown www-data:www-data /www/wwwroot/beta.nextgennoise.com/storage/certificates/

# Verify permissions
ls -la /www/wwwroot/beta.nextgennoise.com/storage/certificates/
```

### Step 4: Test Deployment

```bash
# Test API endpoint from local machine:
curl -v "https://beta.nextgennoise.com/api/v1/legal/verify?certificate_id=CRT-20260206-TEST"

# Expected: 404 response (entry doesn't exist, but API is working)
# {
#   "verified": false,
#   "status": "not_found",
#   "message": "No ledger entry found for the provided hash or certificate ID"
# }

# Test with invalid hash:
curl "https://beta.nextgennoise.com/api/v1/legal/verify?hash=invalid"

# Expected: 400 response
# {
#   "verified": false,
#   "status": "invalid_hash_format",
#   "message": "Hash must be a 64-character hexadecimal SHA-256"
# }
```

### Step 5: Manual Integration Testing

1. **Station Content Upload**
   - Log into dashboard as artist
   - Upload a test audio file via "Add Content"
   - Verify response includes `certificate_id` and `certificate_url`
   - Download certificate from the provided URL
   - Verify certificate displays correctly (title, artist, hash, QR code)

2. **Scan QR Code**
   - Use smartphone camera to scan QR code on certificate
   - Should open verification URL: `/api/v1/legal/verify?certificate_id=CRT-...`
   - Verify API returns JSON with correct owner and content info

3. **SMR Ingestion**
   - Go to Admin → SMR Data Ingestion
   - Upload a test CSV file
   - Verify `smr_uploads` table has `certificate_id` populated
   - Query database: `SELECT id, certificate_id FROM smr_uploads ORDER BY id DESC LIMIT 1;`

4. **Direct API Verification**
   - Upload a file and note the certificate_id from response
   - Call API: `curl "https://beta.nextgennoise.com/api/v1/legal/verify?certificate_id=CRT-..."`
   - Verify verification_count increments on each call

---

## 🔍 Verification Checklist

### Pre-Deployment Checks
- [ ] Git repository up to date: `git log origin/main -1`
- [ ] All files committed: `git status` (should be clean)
- [ ] SSH credentials working: `ssh root@209.59.156.82 -p 22`
- [ ] Database credentials tested: `mysql -h server.starrship1.com -u ngn_2025 -p...`

### Post-Deployment Checks
- [ ] Files deployed to server (check lib/Legal/ and public/api/v1/legal/)
- [ ] Database migration applied (check with SHOW TABLES)
- [ ] Directory permissions correct (775 on certificates directory)
- [ ] API endpoint responds to test requests (200 or 404, not 500)
- [ ] Certificate directory writable by www-data user
- [ ] Logs accessible: `tail -f /www/wwwroot/beta.nextgennoise.com/storage/logs/content_ledger.log`

### Functional Tests
- [ ] Upload station content → certificate_id in response
- [ ] Certificate HTML downloads and displays correctly
- [ ] QR code scans to verification URL
- [ ] API returns correct owner and content info
- [ ] verification_count increments on repeat API calls
- [ ] SMR upload creates ledger entry
- [ ] No errors in application logs

---

## 📊 Database Queries for Verification

```sql
-- Check if migration was applied
SHOW TABLES LIKE 'content_ledger%';

-- View recent ledger entries
SELECT id, certificate_id, content_hash, owner_id, upload_source,
       created_at, verification_count
FROM content_ledger
ORDER BY created_at DESC
LIMIT 5;

-- View verification audit log
SELECT ledger_id, verification_type, verification_result, request_ip, verified_at
FROM content_ledger_verification_log
ORDER BY verified_at DESC
LIMIT 10;

-- Find entries by owner
SELECT id, certificate_id, title, artist_name, created_at
FROM content_ledger
WHERE owner_id = 42
ORDER BY created_at DESC;

-- Check for duplicates (should find only 1)
SELECT content_hash, COUNT(*) as count
FROM content_ledger
GROUP BY content_hash
HAVING count > 1;

-- Monitor verification activity (last hour)
SELECT COUNT(*) as verifications_last_hour
FROM content_ledger_verification_log
WHERE verified_at > DATE_SUB(NOW(), INTERVAL 1 HOUR);
```

---

## 🐛 Troubleshooting

### Issue: Database Migration Fails
**Error**: `ERROR 1064 (42000) at line 108: You have an error in your SQL syntax`

**Solution**: Ensure you're using MySQL 5.7+ or MariaDB 10.3+
```bash
mysql -h server.starrship1.com -u ngn_2025 -pNextGenNoise!1 ngn_2025 -e "SELECT VERSION();"
```

### Issue: API Returns 500 Error
**Check logs**:
```bash
tail -f /www/wwwroot/beta.nextgennoise.com/storage/logs/content_verification_api.log
tail -f /www/wwwroot/beta.nextgennoise.com/storage/logs/content_ledger.log
```

**Common causes**:
- Database connection failure → verify DB credentials in .env
- Missing ContentLedgerService.php → verify files deployed
- Permission denied on certificates directory → run chmod/chown commands

### Issue: Certificate Generation Fails
**Check**:
- Is /storage/certificates/ writable? `ls -la storage/certificates/`
- Is chillerlan/php-qrcode installed? `composer show | grep qrcode`
- Check logs: `grep -i "certificate_generation" storage/logs/*.log`

### Issue: Ledger Registration Not Happening
**Check**:
1. Are the integration code changes deployed? (`grep -r "ContentLedgerService" lib/`)
2. Check application logs for warnings: `grep "content_ledger_registration_failed" storage/logs/*.log`
3. Is database migration applied? `SELECT COUNT(*) FROM content_ledger;`

---

## 📝 Monitoring & Maintenance

### Log Files to Monitor
```bash
tail -f /www/wwwroot/beta.nextgennoise.com/storage/logs/content_ledger.log
tail -f /www/wwwroot/beta.nextgennoise.com/storage/logs/content_verification_api.log
tail -f /www/wwwroot/beta.nextgennoise.com/storage/logs/station_content.log
```

### Daily Checks
```bash
# Monitor verification activity
mysql -h server.starrship1.com -u ngn_2025 -pNextGenNoise!1 ngn_2025 \
  -e "SELECT COUNT(*) as verifications_today FROM content_ledger_verification_log WHERE verified_at > CURDATE();"

# Check for registration failures
grep "registration_failed" /www/wwwroot/beta.nextgennoise.com/storage/logs/*.log

# Verify certificates are being generated
ls -lt /www/wwwroot/beta.nextgennoise.com/storage/certificates/ | head -10
```

### Performance Monitoring
```bash
# Check ledger table size
mysql -h server.starrship1.com -u ngn_2025 -pNextGenNoise!1 ngn_2025 \
  -e "SELECT
        COUNT(*) as total_entries,
        COUNT(DISTINCT owner_id) as unique_owners,
        AVG(verification_count) as avg_verifications
      FROM content_ledger;"

# Check verification log size (might grow large)
mysql -h server.starrship1.com -u ngn_2025 -pNextGenNoise!1 ngn_2025 \
  -e "SELECT COUNT(*) as verification_log_entries FROM content_ledger_verification_log WHERE verified_at > DATE_SUB(NOW(), INTERVAL 30 DAY);"
```

---

## 🔄 Rollback Plan (If Needed)

If issues arise and rollback is necessary:

### Option 1: Disable Ledger Registration (Fastest)
```bash
# Comment out ledger registration in upload handlers
# Files: lib/Stations/StationContentService.php (lines 110-180)
#        public/admin/smr-ingestion.php (lines 82-116)
#        public/admin/assistant-upload.php (lines 119-153)
#        lib/Smr/UploadService.php (lines 93-148)

# Disable API endpoint
mv public/api/v1/legal/verify.php public/api/v1/legal/verify.php.disabled

# No database rollback needed - data remains but not accessed
```

### Option 2: Full Rollback (Complete)
```bash
# Drop tables (if needed)
mysql -h server.starrship1.com -u ngn_2025 -pNextGenNoise!1 ngn_2025 -e "
  DROP TABLE IF EXISTS content_ledger_verification_log;
  DROP TABLE IF EXISTS content_ledger;
  ALTER TABLE smr_uploads DROP COLUMN certificate_id;
  ALTER TABLE station_content DROP COLUMN certificate_id;
"

# Revert code
git revert 3fd04a3 6ae2168  # Revert feature and docs commits
```

---

## 📞 Support Resources

### Documentation
- Full implementation guide: `docs/DIGITAL_SAFETY_SEAL_IMPLEMENTATION.md`
- This deployment guide: `DEPLOYMENT_NOTES.md`

### Code References
- ContentLedgerService: `lib/Legal/ContentLedgerService.php`
- DigitalCertificateService: `lib/Legal/DigitalCertificateService.php`
- Verification API: `public/api/v1/legal/verify.php`
- Database Schema: `scripts/2026_02_06_content_ledger.sql`

### Git History
```bash
git log --oneline | grep -i "seal\|ledger\|certificate"
# Should show:
# 6ae2168 docs: Add Digital Safety Seal implementation guide
# 3fd04a3 Feature: Implement NGN 2.0.2 Digital Safety Seal system
```

---

## ✅ Final Checklist

Before marking deployment as complete:

- [ ] Code pushed to GitHub (main branch)
- [ ] Database migration applied successfully
- [ ] File permissions verified on server
- [ ] API endpoint responds to test requests
- [ ] Station upload generates certificate_id
- [ ] Certificate HTML renders correctly
- [ ] QR code scans and links to verification URL
- [ ] Verification API increments counter
- [ ] SMR/assistant uploads register in ledger
- [ ] Application logs show no errors
- [ ] All service methods called without exceptions
- [ ] Documentation updated and accessible

---

**Deployment Date**: [To Be Filled]
**Deployed By**: [Your Name]
**Verification Completed**: [ ] Yes [ ] No
**Issues Encountered**: None / [List any]

**Server Status After Deployment**: ✅ Ready for Production

---

## 🎉 Success Indicators

You'll know the deployment is successful when:

1. ✅ Artists can upload content and receive a certificate
2. ✅ Certificate displays with professional design and QR code
3. ✅ QR code scans to working verification API
4. ✅ Public API returns owner and content details
5. ✅ Each verification increments the counter
6. ✅ No errors in application logs
7. ✅ SMR uploads also register in ledger
8. ✅ Database queries show ledger entries and verification logs

**Estimated Time to Deploy**: 30-45 minutes (including testing)
**Estimated Data Migration Time**: 2-5 seconds (small schema additions)
**Downtime Required**: None (non-breaking additions)

---

For questions or issues during deployment, refer to the troubleshooting section above or check the full implementation guide at `docs/DIGITAL_SAFETY_SEAL_IMPLEMENTATION.md`.
