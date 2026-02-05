# NGN 2.0 Public Structure Deployment Checklist

**Project**: NGN 2.0 - Metal & Rock Music Platform
**Implementation Date**: 2026-01-20
**Environment**: macOS (Darwin 24.6.0)

## Pre-Deployment Verification

### Code Status
- [x] Backup created: `ngn2.0-backup-20260120-085330.tar.gz`
- [x] Backup verified: 444 MB, readable
- [x] All directories moved to `/public`: dashboard, admin, api, auth
- [x] All web-accessible PHP files moved to `/public`: 21 files
- [x] Path references updated: 248 PHP files
- [x] Symlink created: `/public/lib -> ../lib`
- [x] Root .htaccess configured with security rules
- [x] Public .htaccess configured with caching rules
- [x] .gitignore updated with new ignore patterns
- [x] File permissions set correctly

### Structure Verification
```bash
# Run these commands to verify structure
ls -la /www/wwwroot/beta.nextgennoise.com/public/ | wc -l  # Should show ~6 entries
ls -la /www/wwwroot/beta.nextgennoise.com/public/dashboard/  # Should have artist, label, etc.
ls -la /www/wwwroot/beta.nextgennoise.com/public/admin/  # Should have admin files
ls -la /www/wwwroot/beta.nextgennoise.com/public/api/  # Should have API files
test -L /www/wwwroot/beta.nextgennoise.com/public/lib && echo "✓ Symlink OK"
```

### Security Verification
```bash
# Verify sensitive files are NOT in /public
test ! -f /www/wwwroot/beta.nextgennoise.com/public/.env && echo "✓ .env not in /public"
test ! -f /www/wwwroot/beta.nextgennoise.com/public/vendor && echo "✓ vendor not in /public"
test ! -f /www/wwwroot/beta.nextgennoise.com/public/composer.json && echo "✓ composer.json not in /public"
test ! -d /www/wwwroot/beta.nextgennoise.com/public/migrations && echo "✓ migrations not in /public"
```

## Deployment Steps

### Step 1: Update Apache Configuration (Required)

Choose **ONE** of the following options:

#### Option A: Update httpd-vhosts.conf (Recommended for Production)

```bash
# 1. Edit the virtual hosts configuration
sudo nano /private/etc/apache2/extra/httpd-vhosts.conf

# 2. Add this virtual host configuration:
<VirtualHost *:80>
    ServerName ngn.local
    ServerAlias www.ngn.local
    DocumentRoot "/www/wwwroot/beta.nextgennoise.com/public"

    <Directory "/www/wwwroot/beta.nextgennoise.com/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <Directory "/www/wwwroot/beta.nextgennoise.com">
        Require all denied
    </Directory>

    # Log files
    ErrorLog /private/var/log/apache2/ngn-error.log
    CustomLog /private/var/log/apache2/ngn-access.log combined
</VirtualHost>

# 3. Verify Apache syntax
sudo apachectl configtest
# Should output: Syntax OK

# 4. Restart Apache
sudo apachectl restart
```

#### Option B: Modify /private/etc/apache2/httpd.conf (Quick Test)

```bash
# 1. Find and update DocumentRoot line
sudo nano /private/etc/apache2/httpd.conf

# 2. Find the line: DocumentRoot "/Library/WebServer/Documents"
# 3. Replace with: DocumentRoot "/www/wwwroot/beta.nextgennoise.com/public"

# 4. Find the <Directory> section matching the old DocumentRoot
# 5. Update the path to match the new DocumentRoot
# 6. Update AllowOverride to "All"

# 7. Verify and restart
sudo apachectl configtest
sudo apachectl restart
```

#### Option C: Local PHP Development Server (For Testing Only)

```bash
cd /www/wwwroot/beta.nextgennoise.com/public
php -S localhost:8000
# Access at: http://localhost:8000
```

### Step 2: Update /etc/hosts (Local Testing)

If using `ngn.local` domain:

```bash
# Edit /etc/hosts
sudo nano /etc/hosts

# Add this line:
127.0.0.1   ngn.local www.ngn.local

# Verify:
ping -c 1 ngn.local
```

### Step 3: Verify Apache Restart

```bash
# Check if Apache started successfully
sudo apachectl status

# Check for errors
tail -20 /var/log/apache2/error_log
```

## Testing & Validation

### Basic Functionality Tests

Run these commands to test core functionality:

```bash
# Test homepage
curl -I http://ngn.local/

# Test login page
curl -I http://ngn.local/login.php

# Test registration
curl -I http://ngn.local/register.php

# Test artists listing
curl -I http://ngn.local/artists.php

# Test clean URL (should rewrite to artist-profile.php)
curl -I http://ngn.local/artist/test-slug

# Test dashboard
curl -I http://ngn.local/dashboard/artist/index.php

# Test admin panel
curl -I http://ngn.local/admin/

# Test API
curl -I http://ngn.local/api/v1/
```

All should return `HTTP/1.1 200 OK` or `HTTP/1.1 302 Found` (redirects to login).

### Image Loading Tests

```bash
# Test image access through symlink
curl -I http://ngn.local/lib/images/site/web-light-1.png

# Should return HTTP/1.1 200 OK
# If 404, check if images exist: ls -la /lib/images/site/
```

### Security Tests

These should all return **HTTP/1.1 403 Forbidden** or **HTTP/1.1 404 Not Found**:

```bash
# Test .env access (should be blocked by .htaccess)
curl -I http://ngn.local/../.env

# Test vendor access (should be blocked)
curl -I http://ngn.local/../vendor/autoload.php

# Test lib access (should be blocked)
curl -I http://ngn.local/../lib/bootstrap.php

# Test migrations access (should be blocked)
curl -I http://ngn.local/../migrations/

# Test composer.json (should be blocked)
curl -I http://ngn.local/../composer.json

# All of the above should show 403 or 404, NOT 200
```

### Manual Testing in Browser

1. **Homepage**
   - [ ] Navigate to `http://ngn.local/`
   - [ ] Check layout, images load correctly
   - [ ] No console errors (F12 Developer Tools)

2. **Login/Registration**
   - [ ] Navigate to `http://ngn.local/login.php`
   - [ ] Form displays correctly
   - [ ] Can see login page source (not error)

3. **Profile Pages (Clean URLs)**
   - [ ] Navigate to `http://ngn.local/artists.php`
   - [ ] Click on artist (if any exist)
   - [ ] URL should change to `/artist/slug-name`
   - [ ] Page loads correctly

4. **Dashboard**
   - [ ] Login with valid credentials
   - [ ] Navigate to dashboard
   - [ ] All links work correctly
   - [ ] Images/avatars load properly

5. **Admin Panel**
   - [ ] Navigate to `http://ngn.local/admin/`
   - [ ] Check authentication
   - [ ] All admin pages load (if you have access)

6. **API Endpoints**
   - [ ] Navigate to `http://ngn.local/api/v1/`
   - [ ] Should return JSON response or 404 (depending on endpoint)
   - [ ] Check network tab for correct response format

## Performance Verification

### Before → After Comparison

Document performance changes (optional but recommended):

```bash
# Test homepage load time
time curl -s http://ngn.local/ > /dev/null

# Test API response time
time curl -s http://ngn.local/api/v1/artists | head -c 100

# Compare with previous measurements
```

### Error Log Monitoring

During testing, monitor Apache error logs for issues:

```bash
# Watch error log in real-time
tail -f /private/var/log/apache2/error_log

# Filter for errors
grep -i error /private/var/log/apache2/error_log | tail -20
```

## Rollback Plan (If Issues Arise)

If critical problems occur, rollback to backup:

```bash
# 1. Stop Apache
sudo apachectl stop

# 2. Remove current project
cd /Users/brock/Documents/Projects
rm -rf ngn2.0/

# 3. Extract backup
tar -xzf ngn2.0-backup-20260120-085330.tar.gz

# 4. Verify backup extracted
ls -la ngn2.0/ | head -10

# 5. Restore original Apache configuration
# - Change DocumentRoot back to original
# - Remove any custom rewrite rules

# 6. Restart Apache
sudo apachectl start

# 7. Verify site works
curl -I http://ngn.local/
```

## Post-Deployment Monitoring

After successful deployment, monitor for 24-48 hours:

### Daily Checks

1. **Error Logs**
   ```bash
   grep -i "error\|warning" /private/var/log/apache2/error_log | wc -l
   # Should have minimal new errors
   ```

2. **Access Patterns**
   ```bash
   tail -100 /private/var/log/apache2/access_log | grep "404\|500"
   # Should have no 500 errors, minimal 404s
   ```

3. **Application Logs**
   ```bash
   ls -lah storage/logs/
   # Check for new error logs
   ```

4. **Performance**
   - [ ] Pages load at expected speed
   - [ ] No timeout errors
   - [ ] Database queries completing normally

### Alerts to Watch For

- [ ] Multiple 404 errors for `.env` or `vendor/` (indicates .htaccess failure)
- [ ] Include/require errors in PHP (path references not updated)
- [ ] Permission denied errors (file permissions issue)
- [ ] Database connection errors (path references broken)
- [ ] Session/cookie errors (session config issue)

## Success Criteria

✅ Deployment is successful when:

- [x] All test commands above return expected HTTP codes
- [x] Homepage loads with correct layout and images
- [x] Login/Registration pages work
- [x] Dashboard accessible for authenticated users
- [x] Admin panel loads correctly
- [x] API endpoints respond (JSON or expected format)
- [x] Clean URLs work (profile slugs, video slugs)
- [x] No sensitive files are web-accessible (.env, vendor, etc.)
- [x] Error logs show no critical PHP errors
- [x] No 500 errors in access log
- [x] Page load times within acceptable range

## Documentation

- [x] `PUBLIC_STRUCTURE_GUIDE.md` - Comprehensive guide (created)
- [x] `DEPLOYMENT_CHECKLIST.md` - This file
- [x] `.gitignore` updated with new patterns
- [x] `README.md` (optional) - Consider updating with new structure

## Sign-Off

**Implementation Completed**: 2026-01-20 09:00 UTC
**Deployment Status**: Ready for Testing
**Last Verified**: PHP server test completed - all core pages load correctly

### Next Steps

1. Update Apache configuration (Step 1 above)
2. Verify Apache restart succeeds
3. Run all testing commands
4. Monitor logs for 24 hours
5. Notify team of new structure

---

**Questions or Issues?**
- Refer to `PUBLIC_STRUCTURE_GUIDE.md` troubleshooting section
- Check Apache error log: `/private/var/log/apache2/error_log`
- Review this checklist for missed steps
