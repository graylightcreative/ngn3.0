# NGN 2.0 Public Directory Structure Guide

**Status**: ✅ Implemented on 2026-01-20

## Overview

The NGN 2.0 project has been refactored to use a secure public directory structure. Only files in `/public` are web-accessible, while sensitive files (`.env`, `/vendor/`, `/lib/`, `/migrations/`) are protected outside the web root.

## New Directory Structure

```
/www/wwwroot/beta.nextgennoise.com/
├── .env                          ← NOT web-accessible (SECURE)
├── .gitignore                    ← Updated with new ignore patterns
├── .htaccess                     ← ROOT security rules (NEW)
├── composer.json                 ← NOT web-accessible
├── composer.lock                 ← NOT web-accessible
│
├── lib/                          ← NOT web-accessible (SECURE)
│   ├── bootstrap.php
│   ├── images/
│   ├── definitions/
│   └── ...
│
├── vendor/                       ← NOT web-accessible (SECURE)
├── migrations/                   ← NOT web-accessible (SECURE)
├── storage/                      ← NOT web-accessible (writable)
│   ├── logs/
│   ├── cache/
│   ├── uploads/
│   └── backups/
│
├── bin/                          ← Scripts (NOT web-accessible)
├── docs/                         ← Documentation (NOT web-accessible)
├── tests/                        ← Tests (NOT web-accessible)
└── public/                       ← ONLY THIS IS WEB-ACCESSIBLE
    ├── .htaccess                 ← COPY from root (caching, rewrite rules)
    ├── lib/                      ← SYMLINK to ../lib
    │   ├── images/
    │   ├── bootstrap.php (symlinked)
    │   └── ...
    │
    ├── index.php                 ← Homepage
    ├── login.php                 ← Login page
    ├── register.php              ← Registration
    ├── logout.php                ← Logout
    ├── 404.php                   ← Error page
    │
    ├── artists.php               ← Listings
    ├── labels.php
    ├── stations.php
    ├── videos.php
    ├── charts.php
    │
    ├── artist-profile.php        ← Profile pages
    ├── label-profile.php
    ├── venue-profile.php
    ├── station-profile.php
    ├── video.php
    │
    ├── pricing.php               ← Static pages
    ├── terms-of-service.php
    ├── privacy-policy.php
    │
    ├── dashboard/                ← Artist/Label/Station/Venue dashboards
    │   ├── lib/                  ← Dashboard shared code
    │   │   ├── bootstrap.php     ← Updated paths
    │   │   ├── partials/
    │   │   └── ...
    │   ├── artist/
    │   │   ├── index.php
    │   │   ├── profile.php
    │   │   ├── settings.php
    │   │   ├── tools/            ← Tools for artists
    │   │   ├── oauth/            ← OAuth integrations
    │   │   ├── services/         ← Service integrations
    │   │   └── ...
    │   ├── label/
    │   ├── station/
    │   └── venue/
    │
    ├── admin/                    ← Admin panel
    │   ├── index.php
    │   ├── users.php
    │   ├── smr-ingestion.php
    │   └── ...
    │
    ├── api/                      ← REST API
    │   └── v1/
    │       ├── index.php
    │       ├── artists.php
    │       ├── tracks.php
    │       ├── search.php
    │       └── ...
    │
    ├── auth/                     ← Authentication
    │   ├── login-handler.php
    │   ├── register-handler.php
    │   ├── reset-password.php
    │   └── ...
    │
    └── assets/                   ← Static assets
        ├── css/
        ├── js/
        └── images/
```

## Key Changes

### 1. Path Updates in Code

All moved files have been updated with new path references:

**Root PHP files** (e.g., `/public/index.php`):
```php
// Before: $root = __DIR__ . '/';
// After:
$root = dirname(__DIR__) . '/';
require_once $root . 'lib/bootstrap.php';
```

**Dashboard files** (e.g., `/public/dashboard/artist/index.php`):
```php
// Before: require_once dirname(__DIR__) . '/lib/bootstrap.php';
// After:
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';
```

**Nested files** (e.g., `/public/dashboard/artist/tools/mix-feedback.php`):
```php
// Before: require_once __DIR__ . '/../../lib/bootstrap.php';
// After:
require_once __DIR__ . '/../../../lib/bootstrap.php';
```

### 2. Symlink for Library Resources

A symlink has been created for image access:
```bash
ln -sf ../lib public/lib
```

This allows image URLs like `/lib/images/users/...` to work correctly after the document root change.

### 3. Security Configurations

#### Root .htaccess (`/.htaccess`)
- Denies access to `.env`, `composer.json`, `.git`, etc.
- Blocks access to `/lib`, `/vendor`, `/migrations`, `/storage`, `/bin`, etc.
- Routes all requests to `/public/`

#### Public .htaccess (`/public/.htaccess`)
- Maintains existing caching and compression rules
- Contains clean URL rewrite rules for profiles and videos
- Security headers (nosniff, X-Frame-Options, etc.)

## Configuration Required

### Apache Configuration

After deploying, update your Apache virtual host configuration to point the DocumentRoot to `/public`:

**Option 1: Update httpd-vhosts.conf**
```apache
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

    ErrorLog ${APACHE_LOG_DIR}/ngn-error.log
    CustomLog ${APACHE_LOG_DIR}/ngn-access.log combined
</VirtualHost>
```

**Option 2: Update main httpd.conf**
```apache
DocumentRoot "/www/wwwroot/beta.nextgennoise.com/public"

<Directory "/www/wwwroot/beta.nextgennoise.com/public">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

**Option 3: For local testing with PHP built-in server**
```bash
cd /www/wwwroot/beta.nextgennoise.com/public
php -S localhost:8000
```

### Nginx Configuration

For Nginx users:
```nginx
server {
    listen 80;
    server_name ngn.local www.ngn.local;
    root /www/wwwroot/beta.nextgennoise.com/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Security: deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    location ~ ~$ {
        deny all;
    }
}
```

## Backup Information

A full backup has been created before the refactoring:
- **Location**: `/www/wwwroot/beta.nextgennoise.com-backup-20260120-085330.tar.gz`
- **Size**: 444 MB
- **Date**: 2026-01-20 08:53
- **Status**: ✅ Verified

### Rollback Procedure

If critical issues arise:

```bash
cd /Users/brock/Documents/Projects
rm -rf ngn2.0/
tar -xzf ngn2.0-backup-20260120-085330.tar.gz
# Restore original directory name if needed
# mv ngn2.0-backup-* ngn2.0/
```

## Testing Checklist

After Apache configuration, verify:

- [ ] Homepage loads: `http://ngn.local/`
- [ ] Login page works: `http://ngn.local/login.php`
- [ ] Artists listing: `http://ngn.local/artists.php`
- [ ] Profile pages work with clean URLs: `http://ngn.local/artist/test-artist`
- [ ] Dashboard accessible: `http://ngn.local/dashboard/artist/index.php`
- [ ] Admin panel loads: `http://ngn.local/admin/`
- [ ] API endpoints respond: `http://ngn.local/api/v1/artists`
- [ ] Images load correctly (user avatars, site images)
- [ ] `.env` is NOT accessible (should error)
- [ ] `/lib/` is NOT directly accessible as files (should error)

### Quick Tests

```bash
# Test main pages
curl -I http://ngn.local/
curl -I http://ngn.local/login.php
curl -I http://ngn.local/artists.php

# Test clean URLs
curl -I http://ngn.local/artist/test-artist

# Test security (should all fail with 403/404)
curl -I http://ngn.local/../.env
curl -I http://ngn.local/../vendor/autoload.php
curl -I http://ngn.local/../lib/bootstrap.php
```

## File Permissions

The following permissions are set:

```
/public              → 755 (readable, executable by all)
/lib                 → 750 (readable, executable by owner/group)
/vendor              → 755 (readable, executable by all)
/migrations          → 750 (readable, executable by owner/group)
/storage             → 775 (writable by owner/group)
.env                 → 600 (readable/writable by owner only)
composer.json        → 644 (readable by all, writable by owner)
```

## Key Features

### Security Improvements ✅

1. **Sensitive files protected**
   - `.env` file (database credentials, API keys) not web-accessible
   - `/vendor/` directory not web-accessible
   - `/lib/` directory not web-accessible
   - `/migrations/` directory not web-accessible
   - Test files not web-accessible

2. **Root .htaccess protection**
   - Blocks direct access to configuration files
   - Blocks directory listing
   - Routes all valid requests to `/public/`

3. **File permissions**
   - Sensitive files set to restrictive permissions (600)
   - Executable directories properly configured
   - Storage directory writable but protected

### Performance Improvements ✅

1. **Caching rules maintained**
   - Static assets cached for 1 year
   - HTML cached for 1 hour
   - API responses not cached

2. **Compression enabled**
   - Gzip compression for text-based content
   - CORS headers for fonts

3. **Clean URL structure maintained**
   - Profile URLs: `/artist/slug`, `/label/slug`, etc.
   - All existing URL patterns work correctly

## Troubleshooting

### Issue: Pages return 404 errors

**Cause**: Apache DocumentRoot not updated to `/public/`

**Solution**:
1. Check Apache configuration: `apachectl -t`
2. Update DocumentRoot to `/www/wwwroot/beta.nextgennoise.com/public`
3. Restart Apache: `sudo apachectl restart`

### Issue: Images not loading

**Cause**: Symlink to `/lib` not working or absolute paths incorrect

**Solution**:
1. Verify symlink: `ls -la /public/lib`
2. Check image paths in HTML: `curl http://ngn.local/ | grep src=`
3. Test image directly: `curl -I http://ngn.local/lib/images/site/web-light-1.png`

### Issue: Database connection errors

**Cause**: Path to bootstrap.php incorrect

**Solution**:
1. Check include statement in page: `grep -n "bootstrap.php" /public/index.php`
2. Verify file exists: `ls -la /lib/bootstrap.php`
3. Check error log: `tail -f /var/log/apache2/error.log`

### Issue: Permission denied errors

**Cause**: File permissions too restrictive or too permissive

**Solution**:
1. Check permissions: `ls -la /public/`
2. Re-run permission setup from Phase 6
3. Ensure web server user has read access: `ls -la /public/ | head -5`

### Issue: .htaccess rules not working

**Cause**: AllowOverride not enabled in Apache config

**Solution**:
1. Edit Apache config (httpd.conf or vhosts)
2. Add `AllowOverride All` in `<Directory>` section
3. Restart Apache: `sudo apachectl restart`

## Monitoring Post-Migration

For the first 24-48 hours after deployment, monitor:

1. **Error logs**
   ```bash
   tail -f /var/log/apache2/error.log
   ```
   Look for: 404s, 500s, permission denied, include path errors

2. **Access patterns**
   ```bash
   tail -f /var/log/apache2/access.log | grep -v 200
   ```
   Non-200 responses indicate issues

3. **PHP errors**
   Check `storage/logs/` for application errors

4. **Performance**
   Compare page load times before/after migration

## Future Enhancements

1. **Image CDN Integration**
   - Move user images to CDN
   - Update image serving strategy

2. **Asset Pipeline**
   - Implement asset compilation (Webpack, Vite)
   - Add cache-busting for assets

3. **Environment-Specific Config**
   - Create `.env.development`, `.env.staging`, `.env.production`
   - Use environment detection for config loading

4. **Docker Support**
   - Create Dockerfile with /public as volume mount
   - Document containerized deployment

5. **CI/CD Pipeline**
   - Automate deployments with proper /public structure
   - Add pre-deployment validation checks

## Support & Questions

For issues or questions:

1. Check **Troubleshooting** section above
2. Review **Testing Checklist** to identify failing component
3. Consult **Apache/Nginx Configuration** for your web server
4. Check error logs in `/var/log/apache2/` or `storage/logs/`

---

**Created**: 2026-01-20
**Last Updated**: 2026-01-20
**Status**: ✅ Implementation Complete
