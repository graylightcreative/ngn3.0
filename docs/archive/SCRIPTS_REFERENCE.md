# Migration Scripts Reference

All scripts are located in the `/scripts/` directory and are ready to use.

## Overview

These scripts automate the complete database migration, restoration, validation, and export process for NGN 2.0.1 beta.

---

## Scripts Created This Session

### 1. RESTORE_COMPLETE_SCHEMA.php
**Purpose:** Apply all pending core migrations to restore the complete 2.0.1 beta schema

**Usage:**
```bash
php scripts/RESTORE_COMPLETE_SCHEMA.php
```

**What it does:**
- Scans `/migrations/active/` for all pending migrations
- Applies 66 migrations in alphabetical order
- Creates 169 tables across all 4 databases
- Outputs migration success/failure status
- Shows final table inventory

**When to use:**
- After creating new empty databases
- When you need to reset schema to baseline
- To ensure all tables are present

---

### 2. MIGRATE_SPINS_DATA.php
**Purpose:** Migrate legacy spindata to new station_spins table with artist/station mapping

**Usage:**
```bash
php scripts/MIGRATE_SPINS_DATA.php
```

**What it does:**
- Reads 1,089 legacy spin records
- Maps artist names to artist IDs using fuzzy matching
- Maps station IDs to new station references
- Inserts into station_spins with full metadata
- Outputs detailed mapping statistics
- Shows top artists by spin count

**When to use:**
- After RESTORE_COMPLETE_SCHEMA (requires schema in place)
- To populate spins_2025 database with radio spin data
- When migrating from legacy spindata format

---

### 3. EXPORT_ALL_DATABASES.php
**Purpose:** Export all 4 databases to SQL files (initial version - has memory issues)

**Status:** Deprecated - use EXPORT_ALL_DATABASES_STREAMING instead

---

### 4. EXPORT_ALL_DATABASES_STREAMING.php
**Purpose:** Memory-efficient export of all 4 databases in chunks

**Usage:**
```bash
php scripts/EXPORT_ALL_DATABASES_STREAMING.php
```

**What it does:**
- Exports each database to individual SQL file
- Processes data in 1,000-row chunks (memory efficient)
- Generates proper DROP TABLE and CREATE TABLE statements
- Includes all INSERT statements for data
- Creates files in `storage/exports/`
- Outputs file sizes and row counts
- Shows total export statistics

**Output Files:**
- `ngn_2025_export_YYYY-MM-DD_HH-MM-SS.sql`
- `ngn_rankings_2025_export_YYYY-MM-DD_HH-MM-SS.sql`
- `ngn_smr_2025_export_YYYY-MM-DD_HH-MM-SS.sql`
- `ngn_spins_2025_export_YYYY-MM-DD_HH-MM-SS.sql`

**When to use:**
- When you need production-ready SQL files
- For backup purposes
- Before uploading to production via phpMyAdmin
- To verify export completeness

---

### 5. VALIDATE_SPINS_RANKINGS.php
**Purpose:** Validate spins and rankings data completeness (has some SQL syntax errors in edge cases)

**Status:** Partial - use VALIDATE_ALL_DATA instead for comprehensive validation

---

### 6. VALIDATE_ALL_DATA.php
**Purpose:** Comprehensive validation of all migrated data across all 4 databases

**Usage:**
```bash
php scripts/VALIDATE_ALL_DATA.php
```

**What it does:**
- Validates spins migration (count, date range, top artists)
- Validates rankings data (windows, items, completeness)
- Validates SMR chart data (artist mapping rate, date range)
- Validates core ngn_2025 entities (posts, artists, labels, etc.)
- Checks cross-database referential integrity
- Outputs comprehensive report

**Key Metrics Shown:**
- Record counts per table
- Artist/station mapping statistics
- Date ranges of data
- Data quality percentages
- Entity counts and distributions

**When to use:**
- After any data migration
- Before production deployment
- To verify data integrity
- To document data completeness

---

## Complete Migration Workflow

To perform a complete migration from scratch:

```bash
# Step 1: Restore all 2.0.1 beta schema
php scripts/RESTORE_COMPLETE_SCHEMA.php

# Step 2: Migrate spins data
php scripts/MIGRATE_SPINS_DATA.php

# Step 3: Validate all data
php scripts/VALIDATE_ALL_DATA.php

# Step 4: Export databases for production
php scripts/EXPORT_ALL_DATABASES_STREAMING.php

# Result: Production-ready SQL files in storage/exports/
```

---

## Advanced Usage

### Migrating Specific Databases

To migrate only specific databases, modify the scripts:

```php
// Edit EXPORT_ALL_DATABASES_STREAMING.php
$databases = [
    'ngn_2025' => 'Main application database'
    // Comment out other databases
];
```

### Batch Operations

All scripts respect the MigrationService class which:
- Tracks applied migrations in a `migrations` table
- Prevents re-running already applied migrations
- Handles DELIMITER statements in SQL
- Continues on non-fatal errors (logs them)

### Memory Management

The streaming export uses:
- Chunk size: 1,000 rows per batch
- Automatic memory clearing between chunks
- No in-memory data structures for large tables

---

## Troubleshooting

### Script Error: "Base table or view not found"
- Run RESTORE_COMPLETE_SCHEMA.php first
- Ensures all required tables exist before data operations

### Script Error: "Allowed memory size exhausted"
- Use EXPORT_ALL_DATABASES_STREAMING.php instead (handles memory efficiently)
- Or increase PHP memory_limit in php.ini to 512M+

### Missing database
- Create empty database first: `CREATE DATABASE name;`
- Or ensure databases exist in /migrations/active/core/001_create_2025_databases.sql

### Data mapping issues
- Check VALIDATE_ALL_DATA.php output for unmapped records
- Review fuzzy matching in MIGRATE_SPINS_DATA.php (currently uses similar_text with 85% threshold)
- Manually map edge cases if needed

---

## Database Connection

All scripts use the configuration from:
- `/lib/bootstrap.php` - Loads Config and DB services
- `Config` class reads from environment/config files
- `ConnectionFactory::write()` provides PDO connection

To verify connection:
```bash
php -r "require 'lib/bootstrap.php'; echo 'Connected!';"
```

---

## Performance Notes

- RESTORE_COMPLETE_SCHEMA: ~30-60 seconds (66 migrations)
- MIGRATE_SPINS_DATA: ~10 seconds (1,089 records with fuzzy matching)
- VALIDATE_ALL_DATA: ~5 seconds (reads all tables)
- EXPORT_ALL_DATABASES_STREAMING: ~45-90 seconds (481,970 total rows)

Total workflow time: ~2-3 minutes

---

## Files Generated

After running the full workflow:

```
storage/exports/
├── ngn_2025_export_2026-01-30_17-14-14.sql (135.9 MB)
├── ngn_rankings_2025_export_2026-01-30_17-14-14.sql (33.5 MB)
├── ngn_smr_2025_export_2026-01-30_17-14-14.sql (6.21 MB)
└── ngn_spins_2025_export_2026-01-30_17-14-14.sql (283.06 KB)

Total: 175.89 MB of production-ready SQL
```

---

## Source Code Files Referenced

- `/migrations/active/core/*.sql` - Core schema migrations
- `/migrations/active/commerce/*.sql` - Commerce tables
- `/migrations/active/analytics/*.sql` - Analytics tables
- `/migrations/active/engagement/*.sql` - Engagement tables
- `/migrations/active/governance/*.sql` - Governance tables
- `/migrations/active/infrastructure/*.sql` - Infrastructure tables
- `/migrations/active/seeds/*.sql` - Seed data migrations
- `/lib/DB/MigrationService.php` - Database migration executor
- `/lib/DB/ConnectionFactory.php` - Database connection management

---

## Documentation

See also:
- `MIGRATION_COMPLETE.md` - Complete migration status and results
- `/docs/bible/02 - Core Data Model.md` - Schema specifications
- `/migrations/active/` - All migration files with inline documentation

---

Created: 2026-01-30
Last Updated: 2026-01-30
Status: Production Ready
