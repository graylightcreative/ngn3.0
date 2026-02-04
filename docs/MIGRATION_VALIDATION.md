# NGN 2.0 Migration Validation Guide

Your tried and true method for validating that all data migrated correctly from legacy → 2025 databases, verified against your canonical model "bible".

---

## Overview

Three validation tools work together to provide complete confidence in your migration:

1. **validate-migration.php** — Quick overview report
2. **compare-data.php** — Detailed row-by-row comparison
3. **audit-report.php** — Comprehensive audit report (text, HTML, JSON)

---

## 1. Quick Validation: validate-migration.php

Run this first to get a high-level overview of your migration status.

```bash
php scripts/validate-migration.php
```

### What it checks:

**Schema Validation**
- Verifies all canonical model tables exist
- Confirms all required columns are present
- Identifies missing or extra columns

**Record Count Comparison**
- Legacy data count vs. migrated data count
- Percentage of data successfully migrated
- Identifies any complete data loss

**Data Integrity**
- No NULL emails in users table
- No duplicate emails
- No orphaned posts (posts with non-existent authors)
- No orphaned spins (spins with invalid artists/stations)
- All records have timestamps

### Example Output:

```
====== NGN 2.0 Migration Validation Report ======

1. SCHEMA VALIDATION
--------------------------------------------------
  ✓ cdm_users
  ✓ cdm_artists
  ✓ cdm_labels
  ✓ cdm_stations
  ✓ cdm_posts
  ✓ cdm_media
  ✓ cdm_post_media
  ✓ cdm_spins
  ✓ cdm_chart_entries
  ✓ cdm_notes

2. RECORD COUNT COMPARISON
--------------------------------------------------
  ✓ Users
    Legacy: 42 | New: 42
  ✓ Artists
    Legacy: 156 | New: 156
  ✓ Posts
    Legacy: 203 | New: 203

3. DATA INTEGRITY CHECKS
--------------------------------------------------
  ✓ All users have email
  ✓ No duplicate emails
  ✓ All posts have valid authors
  ✓ All spins have valid artists/stations
  ✓ All users have timestamps

4. SUMMARY REPORT
--------------------------------------------------
Migration Statistics:
  Total Legacy Records: 1,234
  Total Migrated Records: 1,234
  Migration Percentage: 100%

✓ No issues detected!
```

---

## 2. Detailed Comparison: compare-data.php

Drill down into specific tables to spot-check the data manually.

```bash
# Show sample of users table
php scripts/compare-data.php users 5

# Compare 20 posts from legacy and migrated
php scripts/compare-data.php posts 20

# Compare artists
php scripts/compare-data.php artists 10

# Compare stations
php scripts/compare-data.php stations 10
```

### Supported Tables:
- `users` — User accounts and profiles
- `artists` — Artist information
- `posts` — Blog posts and content
- `stations` — Radio stations

### Example Output:

```
====== Data Comparison: users ======

LEGACY DATA (from users):
----------------------------------------------------------------------------------------------------
Id         | Email                    | Title                        | StatusId   | created_at
----------------------------------------------------------------------------------------------------
1          | brock@example.com        | Founder                      | 1          | 2020-01-15
2          | alex@example.com         | Admin                        | 1          | 2020-02-01
3          | user3@example.com        | User                         | 1          | 2020-03-10

MIGRATED DATA (from cdm_users):
----------------------------------------------------------------------------------------------------
id         | email                    | display_name                 | status     | created_at
----------------------------------------------------------------------------------------------------
1          | brock@example.com        | Founder                      | active     | 2020-01-15
2          | alex@example.com         | Admin                        | active     | 2020-02-01
3          | user3@example.com        | User                         | active     | 2020-03-10

COMPARISON ANALYSIS:
  Legacy Records: 3
  Migrated Records: 3
  ✓ Record counts match
```

---

## 3. Comprehensive Audit Report: audit-report.php

Generate a detailed, exportable audit report in multiple formats.

```bash
# Generate text report (prints to screen, saves to storage/logs/)
php scripts/audit-report.php text

# Generate HTML report (good for sharing/reviewing)
php scripts/audit-report.php html

# Generate JSON report (for programmatic processing)
php scripts/audit-report.php json
```

### Output Formats:

**Text** — Plain text report suitable for logging
```
NGN 2.0 MIGRATION AUDIT REPORT
Generated: 2025-01-28 14:30:45
================================================================================

RECORD COUNTS
--------------------------------------------------
  cdm_users                           42 records
  cdm_posts                          203 records
  cdm_artists                        156 records
  cdm_media                          487 records
  cdm_spins                      125,340 records
  cdm_chart_entries                1,245 records
                                  -----
                                127,473 records (TOTAL)

DATA QUALITY CHECKS
--------------------------------------------------
  ✓ All checks passed!
```

**HTML** — Formatted report for presentation/archival
- Professional styling
- Color-coded status indicators
- Easy to share and review
- Saved to `storage/logs/audit_report_YYYY-MM-DD_HHMMSS.html`

**JSON** — Machine-readable for automation
```json
{
  "timestamp": "2025-01-28T14:30:45",
  "recordCounts": {
    "cdm_users": 42,
    "cdm_posts": 203,
    ...
  },
  "dataQuality": []
}
```

---

## Validation Workflow

### Step 1: Initial Validation (5 minutes)
```bash
php scripts/validate-migration.php
```
Check for any obvious issues. If all green, proceed to Step 2.

### Step 2: Spot-Check Sample Data (10 minutes)
```bash
# Check each major table
php scripts/compare-data.php users 10
php scripts/compare-data.php artists 10
php scripts/compare-data.php posts 10
php scripts/compare-data.php stations 5
```
Visually inspect that data looks correct and properly formatted.

### Step 3: Generate Audit Report (2 minutes)
```bash
# Generate HTML for archival
php scripts/audit-report.php html

# Generate JSON for automated processing
php scripts/audit-report.php json
```
Save these reports for your records. The HTML is good for sharing with stakeholders.

---

## Understanding the Canonical Model

Your "bible" is defined in `docs/data/canonical-model.md` and includes:

- **cdm_users** — User accounts with email, display_name, status, timestamps
- **cdm_artists** — Artist profiles with legacy_id, slug, name
- **cdm_labels** — Label information
- **cdm_stations** — Radio stations with call_sign, market
- **cdm_posts** — Blog/content posts with author_user_id, status, timestamps
- **cdm_media** — Images, videos, files with type and dimensions
- **cdm_post_media** — Many-to-many relationship between posts and media
- **cdm_spins** — Play events with station, artist, timestamp
- **cdm_chart_entries** — Ranking entries with chart_slug, week_start, rank
- **cdm_notes** — Internal notes linked to users, artists, etc.

The validation tools verify:
1. All tables exist with correct structure
2. Data was successfully migrated
3. Relationships are intact (no orphaned records)
4. Data quality meets standards (no nulls, duplicates, missing timestamps)

---

## Troubleshooting

### "Legacy database connection not available"
The `compare-data.php` and `validate-migration.php` scripts try to connect to legacy data for comparison. If you get this warning:
- Ensure legacy database credentials are in `.env` (e.g., `DB_LEGACY_HOST`, `DB_LEGACY_USER`, etc.)
- Or skip that step and just review the migrated data in the new databases

### "Table not found"
If a table doesn't exist:
1. Check the migrations have been run: `php scripts/run-migrations.php`
2. Verify the correct database is selected
3. Check for typos in table names (should be `cdm_` prefix)

### "Data mismatch between legacy and new"
1. Run `php scripts/compare-data.php` for the affected table
2. Review the transformation rules in `docs/data/mapping-legacy-to-cdm.md`
3. Check for transformation logic in the migration that might change data format

### "Orphaned records detected"
If you see orphaned posts or spins:
1. These are usually due to deletions or schema changes in legacy data
2. Review the specific records: `SELECT * FROM cdm_posts WHERE author_user_id NOT IN (SELECT id FROM cdm_users)`
3. Decide whether to manually fix them or allow them as-is

---

## Next Steps

After validation:

1. **Archive the reports** — Save the HTML audit report for your records
2. **Review any issues** — Address warnings from the validation script
3. **Test application** — Run your app against the migrated databases
4. **Monitor logs** — Watch for any data inconsistencies in production
5. **Keep migration scripts** — Don't delete these scripts; they're useful for future migrations

---

## Running All Validations

Quick script to run all validations at once:

```bash
#!/bin/bash
echo "Running full migration validation suite..."
echo ""
php scripts/validate-migration.php
echo ""
echo "Comparing sample data..."
php scripts/compare-data.php users 5
echo ""
echo "Generating audit report..."
php scripts/audit-report.php html
echo ""
echo "All validations complete!"
```

Save this as `scripts/validate-all.sh` and run: `bash scripts/validate-all.sh`
