# 🚀 Beta 2.0.1 Launch Guide

**Status**: ✅ READY FOR LAUNCH
**Last Updated**: 2026-01-27
**All Systems**: GO

---

## 🎯 Quick Start - Beta 2.0.1 Launch Checklist

### Step 1: Understand What's in Beta
- **33 Bible Chapters** implemented (all foundation + features)
- **47 Active Migrations** organized and tested
- **MySQL 8.0 Compatible** (all syntax fixed)
- **Governance System** (Directorate SIR) fully operational
- **Artist Royalty System** (Stripe Connect) ready

**Full Details**: See [BETA_2.0.1_ROADMAP.md](./BETA_2.0.1_ROADMAP.md)

---

## 📦 Before Launch - Database Setup

### Option 1: Fresh Database (Recommended for Testing)

```bash
# 1. Create new database
mysql -u root -p
> CREATE DATABASE ngn_2025_beta;
> EXIT;

# 2. Apply all migrations in CORRECT ORDER
cd /path/to/ngn2.0

# Core Migrations
mysql ngn_2025_beta < migrations/active/core/001_create_2025_databases.sql
mysql ngn_2025_beta < migrations/active/core/002_ngn_2025_core.sql
mysql ngn_2025_beta < migrations/active/core/003_releases_tracks.sql
mysql ngn_2025_beta < migrations/active/core/004_ngn_2025_features.sql
mysql ngn_2025_beta < migrations/active/core/005_create_rankings.sql
mysql ngn_2025_beta < migrations/active/core/006_rankings_2025.sql
mysql ngn_2025_beta < migrations/active/core/007_smr_2025.sql
mysql ngn_2025_beta < migrations/active/core/008_smr_2025_additions.sql
mysql ngn_2025_beta < migrations/active/core/009_spins_2025.sql

# Commerce Migrations (Adjusted order due to dependencies)
mysql ngn_2025_beta < migrations/active/commerce/010_commerce_schema.sql
mysql ngn_2025_beta < migrations/active/commerce/011_donations_schema.sql
mysql ngn_2025_beta < migrations/active/commerce/012_investments_schema.sql
mysql ngn_2025_beta < migrations/active/commerce/013_pricing_commission_schema.sql
mysql ngn_2025_beta < migrations/active/commerce/014_sparks_ledger_schema.sql
mysql ngn_2025_beta < migrations/active/commerce/015_subscription_tiers.sql

# Infrastructure Migrations
mysql ngn_2025_beta < migrations/active/infrastructure/016_admin_users_schema.sql
mysql ngn_2025_beta < migrations/active/infrastructure/017_ngn_2025_admin_migration_runs.sql
mysql ngn_2025_beta < migrations/active/infrastructure/018_oauth_analytics.sql
mysql ngn_2025_beta < migrations/active/infrastructure/019_pending_claims.sql
mysql ngn_2025_beta < migrations/active/infrastructure/020_add_stripe_connect_to_users.sql
mysql ngn_2025_beta < migrations/active/infrastructure/021_cogs_columns.sql
mysql ngn_2025_beta < migrations/active/infrastructure/022_enhance_cogs_schema.sql
mysql ngn_2025_beta < migrations/active/infrastructure/023_station_content.sql
mysql ngn_2025_beta < migrations/active/infrastructure/024_url_routes_and_slugs.sql
mysql ngn_2025_beta < migrations/active/infrastructure/025_fairness_receipts.sql
mysql ngn_2025_beta < migrations/active/infrastructure/026_writer_engine.sql
mysql ngn_2025_beta < migrations/active/infrastructure/027_writer_testing_tracker.sql
mysql ngn_2025_beta < migrations/active/infrastructure/028_smr_bounty_system.sql
mysql ngn_2025_beta < migrations/active/infrastructure/029_create_api_config.sql
mysql ngn_2025_beta < migrations/active/infrastructure/030_create_royalty_ledger.sql

# Engagement Migrations
mysql ngn_2025_beta < migrations/active/engagement/031_earned_reach_schema.sql
mysql ngn_2025_beta < migrations/active/engagement/032_station_spins_enhancements.sql
mysql ngn_2025_beta < migrations/active/engagement/033_discovery_engine.sql
mysql ngn_2025_beta < migrations/active/engagement/034_social_feed_algorithm.sql
mysql ngn_2025_beta < migrations/active/engagement/035_retention_loops.sql
mysql ngn_2025_beta < migrations/active/engagement/036_create_engagements.sql

# Governance Migrations
mysql ngn_2025_beta < migrations/active/governance/037_sir_governance_schema.sql
mysql ngn_2025_beta < migrations/active/governance/038_directorate_sir_registry.sql

# Analytics Migrations
mysql ngn_2025_beta < migrations/active/analytics/039_analytics_schema.sql
mysql ngn_2025_beta < migrations/active/analytics/040_api_metrics_schema.sql
mysql ngn_2025_beta < migrations/active/analytics/041_leaderboards_monthly.sql
mysql ngn_2025_beta < migrations/active/analytics/042_post_engagement_analytics.sql
mysql ngn_2025_beta < migrations/active/analytics/043_ngn_score_audit.sql
mysql ngn_2025_beta < migrations/active/analytics/044_audit_log.sql
mysql ngn_2025_beta < migrations/active/analytics/045_dashboard_indexes.sql


# 3. Load initial data
mysql ngn_2025_beta < migrations/sql/etl/10_seed_artists.sql
mysql ngn_2025_beta < migrations/sql/etl/11_seed_labels.sql
# ... continue with other ETL files

# 4. Verify schema
mysql ngn_2025_beta -e "SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema='ngn_2025_beta';"
```

**Migration Details**: See [docs/migrations/README.md](./docs/migrations/README.md)

### Option 2: Upgrade Existing Database

```bash
# Apply only NEW migrations since last deployment
# System tracks applied migrations in ngn_2025.migrations table

mysql ngn_2025 < migrations/active/infrastructure/2026_01_15_add_stripe_connect_to_users.sql
mysql ngn_2025 < migrations/active/infrastructure/2026_01_16_002608_create_live_streams_tables.php
# (Continue with other new migrations)
```

---

## ⚙️ Configuration

### 1. Database Connections
**File**: `.env` or `lib/config.php`

```php
// Ensure these databases exist and are accessible
NGN_DB_HOST = your-host
NGN_DB_USER = your-user
NGN_DB_PASS = your-password

// Primary database
NGN_DB_NAME = ngn_2025

// Sharded databases
RANKINGS_DB_NAME = ngn_rankings_2025
SPINS_DB_NAME = ngn_spins_2025
SMR_DB_NAME = ngn_smr_2025
```

### 2. Stripe Connect Setup
**For Artist Royalty Payouts** (Chapter 8)

```php
STRIPE_PUBLIC_KEY = pk_test_...
STRIPE_SECRET_KEY = sk_test_...
STRIPE_CONNECT_ID = ca_... (Stripe Connect account ID)
```

**Verification**:
```bash
# Test Stripe integration
curl -X GET https://api.stripe.com/v1/account \
  -H "Authorization: Bearer sk_test_..."
```

### 3. API Keys & OAuth
**For Multi-Platform Strategy** (Chapter 16)

```php
// Spotify
SPOTIFY_CLIENT_ID = your-id
SPOTIFY_CLIENT_SECRET = your-secret

// Apple Music
APPLE_MUSIC_KEY = your-key

// YouTube
YOUTUBE_API_KEY = your-key
```

---

## 🧪 Testing Before Launch

### 1. Schema Validation
```bash
# Verify all tables created
mysql ngn_2025 -e "SHOW TABLES;" | wc -l

# Expected: 50+ tables
# Check specific features:
mysql ngn_2025 -e "DESCRIBE cdm_users;"
mysql ngn_2025 -e "DESCRIBE directorate_sirs;"  # Governance (Ch 31)
mysql ngn_2025 -e "DESCRIBE user_sparks_ledger;" # Royalties (Ch 13)
```

### 2. API Smoke Test
```bash
# Start the application
php -S localhost:8000 -t public/

# Test core endpoints
curl -X GET http://localhost:8000/api/v1/status

# Test artist endpoint
curl -X GET http://localhost:8000/api/v1/artists/1

# Test governance
curl -X GET http://localhost:8000/api/v1/governance/sirs

# Test commerce
curl -X GET http://localhost:8000/api/v1/commerce/products
```

### 3. Governance System Test
```bash
# Test SIR (Shareholder Issue Request) workflow
curl -X POST http://localhost:8000/api/v1/governance/sirs \
  -H "Content-Type: application/json" \
  -d '{"title":"Test SIR","description":"Testing","category":"operational"}'

# Check audit trail
mysql ngn_2025 -e "SELECT * FROM sir_audit_log LIMIT 5;"
```

### 4. Artist Payout Flow
```bash
# Verify Stripe Connect fields
mysql ngn_2025 -e "DESCRIBE users;" | grep stripe

# Should show:
# - stripe_connect_id
# - stripe_account_status
# - payout_method
```

---

## 📊 Launch Monitoring

### Key Metrics to Monitor
1. **API Response Time**: Target P95 < 500ms (Chapter 12)
2. **Ranking Engine**: Ensure daily compute jobs succeed (Chapter 3)
3. **Governance**: SIR requests processed within 24h (Chapter 31)
4. **Payments**: Stripe webhook delivery success > 99% (Chapter 8)
5. **User Growth**: Track beta tester engagement (Chapter 23)

### Logs to Check
```bash
# Migration execution
tail -f logs/migrations.log

# API errors
tail -f logs/api.log

# Governance events
tail -f logs/governance.log

# Payment webhooks
tail -f logs/stripe_webhooks.log
```

---

## 🔍 Migration System Reference

### Directory Structure
```
migrations/
├── active/                 # ✅ 47 Beta v2.0.1 migrations
│   ├── core/              # Core tables (users, rankings, posts)
│   ├── commerce/          # E-commerce, royalties
│   ├── engagement/        # Social, discovery, retention
│   ├── governance/        # SIR system (Chapter 31)
│   ├── analytics/         # Metrics, audit logs
│   └── infrastructure/    # Admin, auth, URLs, payments
│
├── future/                # 🔮 Post-beta features (DO NOT APPLY)
│   ├── ppv/              # Pay-per-view
│   └── touring/          # Tours & booking
│
├── legacy_inactive/       # 📦 Deprecated (for reference only)
└── sql/etl/              # 🔄 Data seeding scripts
```

**Migration Details**: [docs/migrations/README.md](./docs/migrations/README.md)

### What Was Fixed
- ✅ MySQL 8.0 syntax errors (reserved keywords)
- ✅ Foreign key references (fully qualified names)
- ✅ Missing indexes for performance
- ✅ Database routing (correct shard placement)
- ✅ All 47 active migrations tested

**Fix Details**: [docs/migrations/MIGRATION_FIXES_APPLIED.md](./docs/migrations/MIGRATION_FIXES_APPLIED.md)

---

## 🆘 Troubleshooting

### Migration Fails with "Unknown Database"
```bash
# Ensure all 4 databases exist:
mysql -e "SHOW DATABASES;" | grep ngn_

# Should show:
# ngn_2025
# ngn_rankings_2025
# ngn_spins_2025
# ngn_smr_2025
```

### "Access Denied" Errors
```bash
# Verify database user permissions
mysql -u your_user -p your_database -e "SELECT USER();"

# Grant privileges if needed
GRANT ALL PRIVILEGES ON ngn_2025.* TO 'your_user'@'localhost';
GRANT ALL PRIVILEGES ON ngn_rankings_2025.* TO 'your_user'@'localhost';
# ... repeat for all 4 databases
```

### Missing Tables After Migration
```bash
# Check migration ledger
mysql ngn_2025 -e "SELECT * FROM migrations ORDER BY applied_at DESC;"

# If migration not listed, it wasn't applied
# Re-apply it manually
mysql ngn_2025 < migrations/active/core/your_migration.sql
```

### API Returns 500 Errors
```bash
# Check PHP error logs
tail -50 /var/log/php_errors.log

# Test database connection
php -r "require 'lib/DB/ConnectionFactory.php';
        echo 'DB Connected: OK';"
```

---

## 📚 Additional Resources

### Bible Chapters (Full Requirements)
- [Bible Index](./docs/bible/00%20-%20Bible%20Index.md)
- [Beta 2.0.1 Roadmap Status](./BETA_2.0.1_ROADMAP.md)
- [Core Data Model](./docs/data/canonical-model.md)

### Operation Guides
- [Operations Manual](./docs/bible/05%20-%20Operations%20Manual.md)
- [Go-Live Checklist](./docs/GO_LIVE_CHECKLIST.md)
- [Cutover Runbook](./docs/CutoverRunbook.md)

### Testing & QA
- [Acceptance Criteria](./docs/Acceptance.md)
- [Testing Tracker](./TESTING_TRACKER_README.md)
- [Stripe Testing Guide](./docs/STRIPE_TESTING_GUIDE.md)

---

## ✅ Launch Checklist

- [ ] All 4 databases created
- [ ] All 47 active migrations applied successfully
- [ ] Database contains 50+ tables
- [ ] API smoke tests passing
- [ ] Governance (SIR) system working
- [ ] Artist payout fields verified
- [ ] Stripe Connect configured
- [ ] Multi-platform OAuth keys configured
- [ ] Monitoring logs accessible
- [ ] Beta testers notified
- [ ] Emergency rollback plan documented
- [ ] Post-launch monitoring active

---

## 🎉 Ready to Launch?

When all checks are complete:

```bash
# Final verification
php bin/launch_verification.php

# If all green → LAUNCH
echo "✅ Beta 2.0.1 is LIVE"
```

**Questions?** Check [BETA_2.0.1_ROADMAP.md](./BETA_2.0.1_ROADMAP.md) for feature details or [docs/migrations/README.md](./docs/migrations/README.md) for technical setup.

---

**Status**: ✅ READY FOR LAUNCH
**Verified**: 2026-01-27 by Phase 4 Migration Audit
**All Systems**: GO
