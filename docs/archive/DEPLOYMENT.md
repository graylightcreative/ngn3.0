# NGN 2.0 Deployment Guide

## Quick Start (One Command)

After cloning the repository and setting up your server, run:

```bash
cd /path/to/ngn2.0
php scripts/deploy.php
```

This single command handles everything:

- ✅ Validates environment variables
- ✅ Verifies database connection
- ✅ Creates storage directories
- ✅ Runs SQL schema migrations
- ✅ Runs PHP migrations
- ✅ Ensures admin user exists
- ✅ Sets file permissions
- ✅ Clears caches (OPcache, Redis, file cache)
- ✅ Performs health checks

## Full Deployment Steps

### 1. Clone & Install Dependencies

```bash
cd /www/wwwroot
git clone https://github.com/graylightcreative/ngn2.0.git beta.nextgennoise.com
cd beta.nextgennoise.com

composer install --no-dev --prefer-dist
composer dump-autoload -o
```

### 2. Configure Environment

Copy `.env-reference` to `.env` and update values for your environment:

```bash
cp .env-reference .env
# Edit .env with your database credentials, API keys, etc.
```

Optional: Set admin credentials for automatic creation:

```bash
# Add to .env
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=SecurePassword123!
```

### 3. Run Deployment

```bash
php scripts/deploy.php
```

## Manual Steps (if needed)

If you prefer to run each step individually:

```bash
# SQL migrations only
php scripts/run-migrations.php

# PHP migrations only
php scripts/run-php-migrations.php

# Or both SQL and PHP
php scripts/run-migrations.php && php scripts/run-php-migrations.php
```

## Environment Variables

Required variables (in `.env`):

```
# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=ngn_2025
DB_USER=ngn_2025
DB_PASS=your_password

# Application
APP_ENV=production
APP_DEBUG=false

# Optional: Admin creation
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=your_password
```

## Migration Tracking

All migrations are tracked in the database:

- **SQL migrations**: `migrations` table
- **PHP migrations**: `php_migrations` table

Each migration records:
- Filename
- Applied timestamp
- Never runs twice

To reset migrations (dangerous!):

```bash
mysql -u ngn_2025 -p ngn_2025 -e "TRUNCATE TABLE migrations; TRUNCATE TABLE php_migrations;"
```

## Seed Data

Seed data is automatically part of the migration process. Seed files are located in:

```
migrations/active/seeds/
```

They execute in numeric order (10_seed_*, 11_seed_*, etc.) and are tracked like migrations.

## Troubleshooting

### Database connection failed
- Check `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS` in `.env`
- Verify MySQL is running
- Ensure user has proper privileges

### Permission denied on storage directories
- Check file ownership: `ls -la storage/`
- Reset permissions: `chmod -R 755 storage/`

### OPcache not cleared
- Restart PHP-FPM: `systemctl restart php-fpm`
- Or manually: Clear in admin panel or restart web server

### Missing admin user
- Set `ADMIN_EMAIL` and `ADMIN_PASSWORD` in `.env`
- Run deployment again

## Post-Deployment

After deployment completes:

1. ✅ Verify application loads in browser
2. ✅ Check database tables were created
3. ✅ Verify admin user can log in
4. ✅ Check logs: `tail -f storage/logs/app.log`

## Reverting Migrations

If a migration causes issues:

1. Identify problematic migration in migration table
2. Delete the entry from `migrations` table
3. Fix the migration file
4. Re-run: `php scripts/run-migrations.php`

This is only recommended for development. For production, create new "fix" migrations instead.

## What Gets Deployed

The deployment script orchestrates:

### Schema (SQL)
- Core tables: users, roles, artists, labels, etc.
- Analytics tables
- Commerce/payment tables
- Content tables

### Seeds (SQL)
- Artists and labels
- Stations and venues
- Writers and content
- Rankings and initial data

### Configuration (PHP)
- Admin user (if configured)
- Feature flags initialization
- PHP-specific setup

## See Also

- [migrations/README.md](migrations/README.md) - Detailed migration structure
- [.env-reference](.env-reference) - All available environment variables
