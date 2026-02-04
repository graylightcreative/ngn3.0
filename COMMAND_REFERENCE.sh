#!/bin/bash
# NGN 2.0 /Public Directory - Command Reference
# All essential commands for deployment, testing, and troubleshooting

# ============================================================================
# QUICK DEPLOYMENT COMMANDS
# ============================================================================

# Test with PHP development server (no Apache config needed)
cd /www/wwwroot/beta.nextgennoise.com/public && php -S localhost:8000

# ============================================================================
# APACHE CONFIGURATION & RESTART
# ============================================================================

# Verify Apache syntax
sudo apachectl configtest

# Restart Apache
sudo apachectl restart

# Stop Apache
sudo apachectl stop

# Start Apache
sudo apachectl start

# Edit Apache vhosts configuration
sudo nano /private/etc/apache2/extra/httpd-vhosts.conf

# Edit main Apache configuration
sudo nano /private/etc/apache2/httpd.conf

# Edit /etc/hosts for local domain
sudo nano /etc/hosts

# ============================================================================
# QUICK TESTS
# ============================================================================

# Test core pages (all should return 200)
curl -I http://ngn.local/
curl -I http://ngn.local/login.php
curl -I http://ngn.local/artists.php
curl -I http://ngn.local/dashboard/artist/index.php

# Test clean URLs
curl -I http://ngn.local/artist/test-slug

# Test API
curl -I http://ngn.local/api/v1/

# Test images
curl -I http://ngn.local/lib/images/site/web-light-1.png

# ============================================================================
# SECURITY VERIFICATION TESTS
# ============================================================================

# These should ALL return 403 or 404 (NOT 200)
curl -I http://ngn.local/../.env
curl -I http://ngn.local/../vendor/autoload.php
curl -I http://ngn.local/../lib/bootstrap.php
curl -I http://ngn.local/../migrations/
curl -I http://ngn.local/../composer.json

# ============================================================================
# VERIFICATION COMMANDS
# ============================================================================

# Verify directory structure
ls -la /www/wwwroot/beta.nextgennoise.com/public/
ls -la /www/wwwroot/beta.nextgennoise.com/public/dashboard/
ls -la /www/wwwroot/beta.nextgennoise.com/public/admin/

# Verify sensitive files are outside /public
test -f /www/wwwroot/beta.nextgennoise.com/.env && echo "✓ .env exists outside /public"
test -d /www/wwwroot/beta.nextgennoise.com/lib && echo "✓ lib/ exists outside /public"
test -d /www/wwwroot/beta.nextgennoise.com/vendor && echo "✓ vendor/ exists outside /public"

# Verify symlink
ls -la /www/wwwroot/beta.nextgennoise.com/public/lib

# Verify permissions
stat -f "%A %N" /www/wwwroot/beta.nextgennoise.com/public
stat -f "%A %N" /www/wwwroot/beta.nextgennoise.com/.env
stat -f "%A %N" /www/wwwroot/beta.nextgennoise.com/lib

# ============================================================================
# LOG MONITORING
# ============================================================================

# Watch Apache error log in real-time
tail -f /private/var/log/apache2/error_log

# Watch Apache access log
tail -f /private/var/log/apache2/access_log

# Search for errors in Apache error log
grep -i "error" /private/var/log/apache2/error_log | tail -20

# Search for 404 errors in access log
grep "404" /private/var/log/apache2/access_log | tail -20

# Search for 500 errors in access log
grep "500" /private/var/log/apache2/access_log | tail -20

# ============================================================================
# FILE VERIFICATION COMMANDS
# ============================================================================

# Verify path updates in key files
grep -n "dirname(__DIR__)" /www/wwwroot/beta.nextgennoise.com/public/index.php
grep -n "dirname(__DIR__, 2)" /www/wwwroot/beta.nextgennoise.com/public/dashboard/artist/index.php

# Find all PHP files in /public
find /www/wwwroot/beta.nextgennoise.com/public -name "*.php" | wc -l

# Check for any remaining .bak files
find /www/wwwroot/beta.nextgennoise.com -name "*.bak" 2>/dev/null

# ============================================================================
# BACKUP & RECOVERY
# ============================================================================

# Verify backup exists
ls -lh /www/wwwroot/beta.nextgennoise.com-backup-*.tar.gz

# Test backup extraction (dry run)
tar -tzf /www/wwwroot/beta.nextgennoise.com-backup-20260120-085330.tar.gz | head -20

# Restore from backup (EMERGENCY ONLY)
cd /Users/brock/Documents/Projects
rm -rf ngn2.0/
tar -xzf ngn2.0-backup-20260120-085330.tar.gz

# ============================================================================
# DOCUMENTATION FILES
# ============================================================================

# View implementation summary
cat /www/wwwroot/beta.nextgennoise.com/IMPLEMENTATION_SUMMARY.md

# View deployment checklist
cat /www/wwwroot/beta.nextgennoise.com/DEPLOYMENT_CHECKLIST.md

# View comprehensive guide
cat /www/wwwroot/beta.nextgennoise.com/PUBLIC_STRUCTURE_GUIDE.md

# View quick reference
cat /www/wwwroot/beta.nextgennoise.com/QUICK_REFERENCE.md

# ============================================================================
# FILE PERMISSION FIXES
# ============================================================================

# Fix /public permissions (if needed)
chmod -R 755 /www/wwwroot/beta.nextgennoise.com/public/

# Fix /lib permissions (if needed)
chmod -R 750 /www/wwwroot/beta.nextgennoise.com/lib/

# Fix .env permissions (if needed)
chmod 600 /www/wwwroot/beta.nextgennoise.com/.env

# Fix /storage permissions (if needed)
chmod -R 775 /www/wwwroot/beta.nextgennoise.com/storage/

# ============================================================================
# TROUBLESHOOTING COMMANDS
# ============================================================================

# Find broken symlinks
find -L /www/wwwroot/beta.nextgennoise.com/public -type l

# Check if symlink target exists
test -e /www/wwwroot/beta.nextgennoise.com/lib && echo "✓ lib exists" || echo "✗ lib not found"

# Verify Apache is loading .htaccess
grep -r "AllowOverride" /private/etc/apache2/httpd.conf

# Check if mod_rewrite is enabled
apachectl -M | grep rewrite

# Check if mod_headers is enabled
apachectl -M | grep headers

# ============================================================================
# QUICK DEVELOPMENT SERVER START/STOP
# ============================================================================

# Start PHP dev server in background
(cd /www/wwwroot/beta.nextgennoise.com/public && php -S localhost:8000 > /tmp/php.log 2>&1 &)

# Stop PHP dev server
pkill -f "php -S localhost:8000"

# Check if server is running
curl -s -I http://localhost:8000/ | head -1

# ============================================================================
# GIT OPERATIONS (if version control is set up)
# ============================================================================

# Check git status
cd /www/wwwroot/beta.nextgennoise.com && git status

# Add all changes
cd /www/wwwroot/beta.nextgennoise.com && git add -A

# Create commit with refactoring message
cd /www/wwwroot/beta.nextgennoise.com && git commit -m "Refactor: Move to secure /public directory structure"

# Check git log for verification
cd /www/wwwroot/beta.nextgennoise.com && git log --oneline | head -5

# ============================================================================
# NOTES
# ============================================================================

# For Apache on macOS:
#   - Main config: /private/etc/apache2/httpd.conf
#   - Vhosts config: /private/etc/apache2/extra/httpd-vhosts.conf
#   - Error log: /private/var/log/apache2/error_log
#   - Access log: /private/var/log/apache2/access_log

# For Apache on Linux:
#   - Main config: /etc/apache2/apache2.conf or /etc/httpd/conf/httpd.conf
#   - Vhosts config: /etc/apache2/sites-available/ or /etc/httpd/conf.d/
#   - Error log: /var/log/apache2/error.log
#   - Access log: /var/log/apache2/access.log

# DocumentRoot should be set to:
#   /www/wwwroot/beta.nextgennoise.com/public

# If you need to test with a specific port:
#   cd /www/wwwroot/beta.nextgennoise.com/public
#   php -S localhost:8080  # Use 8080 instead of 8000

# ============================================================================
# COPY THESE SECTIONS TO YOUR TERMINAL AS NEEDED
# ============================================================================
