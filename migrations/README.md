# 🗂️ NGN 2.0 Migrations - Reorganized Structure

**Last Updated**: 2026-01-27
**Status**: ✅ Beta v2.0.1 Ready

---

## Directory Structure

### 📁 `active/` - Beta v2.0.1 Migrations (47 files)

**All migrations for beta launch.** These create the core schema and features required for BETA_2.0.1_ROADMAP.md.

```active/
├── core/                    # Core database tables (users, artists, posts, tracks, rankings, spins)
│   ├── 001_create_2025_databases.sql
│   ├── 002_ngn_2025_core.sql
│   ├── 003_releases_tracks.sql
│   ├── 004_ngn_2025_features.sql
│   ├── 005_create_rankings.sql
│   ├── 006_rankings_2025.sql
│   ├── 007_smr_2025.sql
│   ├── 008_smr_2025_additions.sql
│   └── 009_spins_2025.sql
│
├── commerce/                # E-commerce, payments, royalties
│   ├── 010_commerce_schema.sql      # Products, orders
│   ├── 011_donations_schema.sql      # Donations
│   ├── 012_investments_schema.sql    # Investment notes (Ch 32)
│   ├── 013_pricing_commission_schema.sql # Royalty splits
│   ├── 014_sparks_ledger_schema.sql  # Fan tips economy (Ch 13)
│   └── 015_subscription_tiers.sql    # Membership tiers (Ch 7)
│
├── engagement/              # Social features, discovery, retention
│   ├── 031_earned_reach_schema.sql       # Social metrics (Ch 22)
│   ├── 032_station_spins_enhancements.sql # Spin analytics
│   ├── 033_discovery_engine.sql                  # Discovery algorithms (Ch 18)
│   ├── 034_social_feed_algorithm.sql             # Feed ranking (Ch 22)
│   ├── 035_retention_loops.sql                   # Daily utility (Ch 23)
│   └── 036_create_engagements.sql                   # Engagements, follows, favorites
│
├── governance/              # Governance system (Chapter 31 - Directorate SIR)
│   ├── 037_sir_governance_schema.sql     # SIR requests, voting
│   └── 038_directorate_sir_registry.sql          # SIR registry, audit trail
│
├── analytics/               # Metrics, rankings, audit logs
│   ├── 039_analytics_schema.sql          # Post analytics (Ch 12)
│   ├── 040_api_metrics_schema.sql        # API tracking
│   ├── 041_leaderboards_monthly.sql              # Ranking caches
│   ├── 042_post_engagement_analytics.sql         # Post metrics
│   ├── 043_ngn_score_audit.sql                   # Ranking audit (Ch 3)
│   ├── 044_audit_log.sql                         # System audit trail
│   └── 045_dashboard_indexes.sql                 # Performance indexes
│
└── infrastructure/          # Admin, payments, URLs, writer engine
    ├── 016_admin_users_schema.sql        # Admin authentication
    ├── 017_ngn_2025_admin_migration_runs.sql # Migration tracking
    ├── 018_oauth_analytics.sql           # OAuth integrations (Ch 16)
    ├── 019_pending_claims.sql            # Investment claims (Ch 32)
    ├── 020_add_stripe_connect_to_users.sql   # Stripe payouts (Ch 8)
    ├── 021_cogs_columns.sql                      # Cost tracking (Ch 13)
    ├── 022_enhance_cogs_schema.sql               # Enhanced costs
    ├── 023_station_content.sql                   # Content management (Ch 9)
    ├── 024_url_routes_and_slugs.sql              # URL routing
    ├── 025_fairness_receipts.sql                 # Transparency logs (Ch 17)
    ├── 026_writer_engine.sql                     # Writer assignments (Ch 10)
    ├── 027_writer_testing_tracker.sql            # A/B testing
    ├── 028_smr_bounty_system.sql                 # SMR bounties (Ch 24)
    ├── 029_create_api_config.sql                    # API configuration
    ├── 030_create_royalty_ledger.sql                # Royalty accounting (Ch 13)
    ├── 2026_01_16_002608_create_live_streams_tables.php    # Live streaming
    └── 2026_01_16_002700_create_php_migrations_table.php   # Migration tracking
```

**All migrations in `active/` must be applied for beta launch.**

---

### 📁 `future/` - Post-Beta Features (2 files + placeholder dirs)

**Features planned for after beta v2.0.1.** These migrations should NOT be applied to beta.

```
future/
├── ppv/                      # Pay-per-view monetization
│   └── 28_ppv_expenses.sql
│
├── touring/                  # Tours & booking system
│   └── 36_tours_and_bookings.sql
│
├── discovery_advanced/       # Advanced discovery (vector search, AI)
├── messaging/                # Direct messaging system
├── ad_tech/                  # Ad platform tables
├── internationalization/     # i18n, multi-currency
└── compliance/               # GDPR, encryption
```

**Do NOT apply these migrations for beta v2.0.1.**

---

### 📁 `legacy_inactive/` - Deprecated Migrations (54 files)

**Old/archived migrations from NGN 1.0 and earlier beta versions.**

These are kept for:
- Historical reference
- Understanding database evolution
- Emergency rollback procedures

**Do NOT apply these migrations.** They create old table structures that have been replaced by `active/` migrations.

---

### 📁 `sql/etl/` - One-Time Data Loading (21 files)

**Data seeding and ETL scripts** for populating initial data.

Examples:
- `10_seed_artists.sql` - Populate artists table
- `11_seed_labels.sql` - Populate labels table
- `12_seed_stations.sql` - Populate stations
- `20_seed_rankings_*.sql` - Populate ranking data
- etc.

**These are safe to run on fresh databases for data initialization.**

---

### 📁 `sql/checks/` - Validation Queries (1 file)

Data validation and row count checking queries.

---

## Migration Execution Order

### For Beta v2.0.1 Deployment

Execute migrations in this order:

```bash
# 1. Core schema (databases, main tables)
mysql ngn_2025_beta < migrations/active/core/001_create_2025_databases.sql
mysql ngn_2025_beta < migrations/active/core/002_ngn_2025_core.sql
mysql ngn_2025_beta < migrations/active/core/003_releases_tracks.sql
mysql ngn_2025_beta < migrations/active/core/004_ngn_2025_features.sql
mysql ngn_2025_beta < migrations/active/core/005_create_rankings.sql
mysql ngn_2025_beta < migrations/active/core/006_rankings_2025.sql
mysql ngn_2025_beta < migrations/active/core/007_smr_2025.sql
mysql ngn_2025_beta < migrations/active/core/008_smr_2025_additions.sql
mysql ngn_2025_beta < migrations/active/core/009_spins_2025.sql

# 2. Commerce (payments, royalties) - Adjusted order due to dependencies
mysql ngn_2025_beta < migrations/active/commerce/010_commerce_schema.sql
mysql ngn_2025_beta < migrations/active/commerce/011_donations_schema.sql
mysql ngn_2025_beta < migrations/active/commerce/012_investments_schema.sql
mysql ngn_2025_beta < migrations/active/commerce/013_pricing_commission_schema.sql
mysql ngn_2025_beta < migrations/active/commerce/014_sparks_ledger_schema.sql
mysql ngn_2025_beta < migrations/active/commerce/015_subscription_tiers.sql

# 3. Infrastructure (admin, auth, URLs, payments)
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

# 4. Engagement (social, discovery, retention)
mysql ngn_2025_beta < migrations/active/engagement/031_earned_reach_schema.sql
mysql ngn_2025_beta < migrations/active/engagement/032_station_spins_enhancements.sql
mysql ngn_2025_beta < migrations/active/engagement/033_discovery_engine.sql
mysql ngn_2025_beta < migrations/active/engagement/034_social_feed_algorithm.sql
mysql ngn_2025_beta < migrations/active/engagement/035_retention_loops.sql
mysql ngn_2025_beta < migrations/active/engagement/036_create_engagements.sql

# 5. Governance (SIR system)
mysql ngn_2025_beta < migrations/active/governance/037_sir_governance_schema.sql
mysql ngn_2025_beta < migrations/active/governance/038_directorate_sir_registry.sql

# 6. Analytics (dashboards, audit logs)
mysql ngn_2025_beta < migrations/active/analytics/039_analytics_schema.sql
mysql ngn_2025_beta < migrations/active/analytics/040_api_metrics_schema.sql
mysql ngn_2025_beta < migrations/active/analytics/041_leaderboards_monthly.sql
mysql ngn_2025_beta < migrations/active/analytics/042_post_engagement_analytics.sql
mysql ngn_2025_beta < migrations/active/analytics/043_ngn_score_audit.sql
mysql ngn_2025_beta < migrations/active/analytics/044_audit_log.sql
mysql ngn_2025_beta < migrations/active/analytics/045_dashboard_indexes.sql

# 7. Data seeding (populate initial data)
sql/etl/*.sql
```

**Important**: All files in `active/` must complete successfully before beta launch.

---

## For Development

### Fresh Database Setup

```bash
# Create fresh development database
mysql> CREATE DATABASE ngn_2025_dev;

# Apply all active migrations in order
mysql ngn_2025_dev < migrations/active/core/001_create_2025_databases.sql
mysql ngn_2025_dev < migrations/active/core/002_ngn_2025_core.sql
mysql ngn_2025_dev < migrations/active/core/003_releases_tracks.sql
mysql ngn_2025_dev < migrations/active/core/004_ngn_2025_features.sql
mysql ngn_2025_dev < migrations/active/core/005_create_rankings.sql
mysql ngn_2025_dev < migrations/active/core/006_rankings_2025.sql
mysql ngn_2025_dev < migrations/active/core/007_smr_2025.sql
mysql ngn_2025_dev < migrations/active/core/008_smr_2025_additions.sql
mysql ngn_2025_dev < migrations/active/core/009_spins_2025.sql
mysql ngn_2025_dev < migrations/active/commerce/010_commerce_schema.sql
mysql ngn_2025_dev < migrations/active/commerce/011_donations_schema.sql
mysql ngn_2025_dev < migrations/active/commerce/012_investments_schema.sql
mysql ngn_2025_dev < migrations/active/commerce/013_pricing_commission_schema.sql
mysql ngn_2025_dev < migrations/active/commerce/014_sparks_ledger_schema.sql
mysql ngn_2025_dev < migrations/active/commerce/015_subscription_tiers.sql
mysql ngn_2025_dev < migrations/active/infrastructure/016_admin_users_schema.sql
mysql ngn_2025_dev < migrations/active/infrastructure/017_ngn_2025_admin_migration_runs.sql
mysql ngn_2025_dev < migrations/active/infrastructure/018_oauth_analytics.sql
mysql ngn_2025_dev < migrations/active/infrastructure/019_pending_claims.sql
mysql ngn_2025_dev < migrations/active/infrastructure/020_add_stripe_connect_to_users.sql
mysql ngn_2025_dev < migrations/active/infrastructure/021_cogs_columns.sql
mysql ngn_2025_dev < migrations/active/infrastructure/022_enhance_cogs_schema.sql
mysql ngn_2025_dev < migrations/active/infrastructure/023_station_content.sql
mysql ngn_2025_dev < migrations/active/infrastructure/024_url_routes_and_slugs.sql
mysql ngn_2025_dev < migrations/active/infrastructure/025_fairness_receipts.sql
mysql ngn_2025_dev < migrations/active/infrastructure/026_writer_engine.sql
mysql ngn_2025_dev < migrations/active/infrastructure/027_writer_testing_tracker.sql
mysql ngn_2025_dev < migrations/active/infrastructure/028_smr_bounty_system.sql
mysql ngn_2025_dev < migrations/active/infrastructure/029_create_api_config.sql
mysql ngn_2025_dev < migrations/active/infrastructure/030_create_royalty_ledger.sql
mysql ngn_2025_dev < migrations/active/engagement/031_earned_reach_schema.sql
mysql ngn_2025_dev < migrations/active/engagement/032_station_spins_enhancements.sql
mysql ngn_2025_dev < migrations/active/engagement/033_discovery_engine.sql
mysql ngn_2025_dev < migrations/active/engagement/034_social_feed_algorithm.sql
mysql ngn_2025_dev < migrations/active/engagement/035_retention_loops.sql
mysql ngn_2025_dev < migrations/active/engagement/036_create_engagements.sql
mysql ngn_2025_dev < migrations/active/governance/037_sir_governance_schema.sql
mysql ngn_2025_dev < migrations/active/governance/038_directorate_sir_registry.sql
mysql ngn_2025_dev < migrations/active/analytics/039_analytics_schema.sql
mysql ngn_2025_dev < migrations/active/analytics/040_api_metrics_schema.sql
mysql ngn_2025_dev < migrations/active/analytics/041_leaderboards_monthly.sql
mysql ngn_2025_dev < migrations/active/analytics/042_post_engagement_analytics.sql
mysql ngn_2025_dev < migrations/active/analytics/043_ngn_score_audit.sql
mysql ngn_2025_dev < migrations/active/analytics/044_audit_log.sql
mysql ngn_2025_dev < migrations/active/analytics/045_dashboard_indexes.sql

# Load development data
mysql ngn_2025_dev < sql/etl/10_seed_artists.sql
# ... continue with ETL scripts

### Testing New Features

- Add new beta migrations to `active/` in appropriate subdirectory
- Add post-beta migrations to `future/` with clear naming

### After Beta Launch

When implementing post-beta features:
1. Move migration from `future/category/` to `active/new_category/`
2. Update version number in filename
3. Test on staging database
4. Deploy with new release

---

## Migration Ledger

The system tracks applied migrations in the `migrations` table:

```sql
SELECT * FROM ngn_2025.migrations ORDER BY applied_at DESC;
```

This prevents duplicate execution and enables rollback planning.

---

## Documentation Reference

- **BETA_2.0.1_ROADMAP.md** - What's in beta launch (33 Bible chapters)
- **MIGRATION_AUDIT_COMPLETE.md** - Complete audit and Bible mapping
- **MIGRATION_FIXES_APPLIED.md** - Details of all fixes applied
- **docs/bible/** - 33 chapters defining NGN 2.0 features
- **docs/data/canonical-model.md** - Entity definitions

---

## Bible Chapter Mapping

Every migration in `active/` maps to a Bible chapter:

| Chapter | Feature | Migrations |
|---------|---------|-----------|
| 2 | Core Data Model | `active/core/` |
| 3 | Ranking Engine | `active/core/rankings`, `active/analytics/` |
| 7 | Products | `active/commerce/` |
| 8 | Tickets | `active/infrastructure/stripe_connect` |
| 10 | Writer Engine | `active/infrastructure/writer_*` |
| 12 | Monitoring | `active/analytics/` |
| 13 | Royalty System | `active/commerce/`, `active/infrastructure/cogs` |
| 16 | Multi-Platform | `active/infrastructure/oauth` |
| 17 | Transparency | `active/infrastructure/fairness_receipts` |
| 18 | Discovery | `active/engagement/discovery_engine` |
| 22 | Social Feed | `active/engagement/` |
| 23 | Retention Loops | `active/engagement/retention_loops` |
| 24 | SMR Ruleset | `active/core/smr_*`, `active/infrastructure/bounty` |
| 31 | Governance SIR | `active/governance/` |
| 32 | Investment Notes | `active/commerce/investments` |

---

## Status

- ✅ **47 Active migrations** - Beta v2.0.1 ready
- 📦 **54 Legacy migrations** - Archived, historical reference
- 🔄 **21 ETL scripts** - Available for data seeding
- 🔮 **2 Future migrations** - Post-beta features
- ✅ **All fixes applied** - MySQL 8.0 compatible
- ✅ **All tests passing** - Ready for production

---

**Last Reorganization**: 2026-01-27
**Beta Status**: ✅ READY
**Audit Status**: ✅ COMPLETE
