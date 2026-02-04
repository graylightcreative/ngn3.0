# Data Validation - Quick Reference

Your tried and true method for verifying 2025 data integrity.

## Three Core Commands

### 1. Quick Validation Report (5 min)
```bash
php scripts/validate-migration.php
```
- Schema validation (all tables exist with correct columns)
- Record counts (legacy vs migrated)
- Data integrity (nulls, duplicates, orphaned records)
- Issues summary

**Best for:** Initial sanity check before detailed review

---

### 2. Spot-Check Specific Tables (5-10 min)
```bash
php scripts/compare-data.php users 20        # Compare 20 users
php scripts/compare-data.php artists 15      # Compare 15 artists
php scripts/compare-data.php posts 10        # Compare 10 posts
php scripts/compare-data.php stations 5      # Compare 5 stations
```
- Side-by-side legacy vs migrated data
- Visual inspection of field mappings
- Sample record analysis

**Best for:** Confirming data transformed correctly

---

### 3. Comprehensive Audit Report (2 min)
```bash
php scripts/audit-report.php text    # Text format (console output)
php scripts/audit-report.php html    # HTML format (for sharing/archival)
php scripts/audit-report.php json    # JSON format (for automation)
```
- Full database overview
- Complete record counts
- Data quality metrics
- Exported to `storage/logs/audit_report_*.{txt,html,json}`

**Best for:** Creating permanent records and sharing with team

---

## One-Shot Validation Suite

Run all three in sequence:

```bash
#!/bin/bash
echo "=== FULL MIGRATION VALIDATION ==="
echo ""
echo "1. Quick validation..."
php scripts/validate-migration.php
echo ""
echo "2. Sample data comparison..."
php scripts/compare-data.php users 5
php scripts/compare-data.php posts 5
echo ""
echo "3. Audit report..."
php scripts/audit-report.php html
echo ""
echo "✓ Complete. Review storage/logs/ for HTML report"
```

Save as `scripts/validate-all.sh` and run: `bash scripts/validate-all.sh`

---

## What to Look For

### Green Light (✓)
- All tables exist with correct columns
- Record counts match between legacy and migrated
- No NULL emails, no duplicate emails
- All posts have valid authors
- All spins have valid artists/stations
- All records have timestamps
- 0 issues in summary

### Yellow Light (⚠)
- Record count mismatch (usually OK if some records were cleaned up)
- Small number of orphaned records (review manually)
- Missing timestamps (backfill if needed)
- Check [mapping docs](../docs/data/mapping-legacy-to-cdm.md) for context

### Red Light (✗)
- Tables missing entirely → Run migrations: `php scripts/run-migrations.php`
- Large data loss → Investigate migration logs
- Systematic NULL values → Check transformation logic
- Many orphaned records → May indicate schema incompatibility

---

## Database Addresses

Your 7 databases during 2025 migration:

```
Primary:
  ngn_2025             → Users, posts, artists, labels, media
  ngn_migrations       → Schema versioning

Specialized:
  ngn_rankings_2025    → NGN rankings/charts
  ngn_smr_2025         → SMR market charts
  ngn_spins_2025       → Station spin events
  ngn_notes_2025       → Internal notes
  ngn_schema_migrations → Schema versioning
```

---

## Canonical Tables Reference

From `docs/data/canonical-model.md`:

| Table | Purpose | Key Fields |
|-------|---------|-----------|
| cdm_users | User accounts | id, email, status, created_at |
| cdm_artists | Artist profiles | id, name, slug, legacy_id |
| cdm_labels | Label info | id, name, slug, legacy_id |
| cdm_stations | Radio stations | id, call_sign, market, legacy_id |
| cdm_posts | Blog/content | id, title, author_user_id, status |
| cdm_media | Images/videos | id, type, url, width, height |
| cdm_spins | Play events | id, artist_id, station_id, occurred_at |
| cdm_chart_entries | Rankings | id, chart_slug, rank, week_start |
| cdm_notes | Internal notes | id, subject_type, author_user_id, body |

---

## Typical Workflow

### Day of Migration
1. Run migrations: `php scripts/run-migrations.php`
2. Quick validation: `php scripts/validate-migration.php`
3. If green → proceed to testing

### Pre-Deployment Checklist
1. Full validation suite: `bash scripts/validate-all.sh`
2. Spot-check critical tables: `php scripts/compare-data.php users 30`
3. Generate HTML report: `php scripts/audit-report.php html`
4. Archive report to shared drive

### During Deployment
- Keep validation reports accessible
- Reference them if users report missing data
- Use `compare-data.php` to debug issues

### Post-Deployment
- Archive audit reports with deployment notes
- Keep validation scripts in codebase
- Use for future audits or rollbacks

---

## Troubleshooting

**"Table X not found"**
- Run migrations: `php scripts/run-migrations.php`

**"Legacy database connection not available"**
- For compare-data.php to work, need legacy database configured
- Can skip if legacy database is archived

**"Data mismatch"**
- Review mapping: `docs/data/mapping-legacy-to-cdm.md`
- Check transformation columns in compare output
- Compare sample IDs manually if needed

**"Orphaned records"**
- Expected if legacy had cascading deletes or data cleanup
- Run this to find them:
  ```sql
  SELECT * FROM cdm_posts WHERE author_user_id NOT IN (SELECT id FROM cdm_users);
  ```
- Decide whether to fix or accept as-is

---

## File Locations

```
scripts/
  ├── validate-migration.php      ← Core validation tool
  ├── compare-data.php            ← Row-by-row comparison
  ├── audit-report.php            ← Report generator
  ├── run-migrations.php           ← Run schema + legacy migrations
  └── VALIDATION_QUICK_REFERENCE.md

docs/
  ├── MIGRATION_VALIDATION.md     ← Full guide
  ├── data/canonical-model.md     ← Your "bible"
  └── data/mapping-legacy-to-cdm.md ← Transform rules

storage/logs/
  └── audit_report_*.{txt,html,json}  ← Generated reports
```

---

## Questions?

Refer to:
- Full guide: `docs/MIGRATION_VALIDATION.md`
- Canonical model: `docs/data/canonical-model.md`
- Mapping rules: `docs/data/mapping-legacy-to-cdm.md`
