# Server Migration Deployment

## One-Command Legacy Database Migration

This script migrates all legacy data to the 2025 database in a single command.

### Prerequisites

- SSH access to server
- All legacy SQL backup files in place
- 2025 database created and accessible
- At least 100 MB free disk space

### Files Required

```
storage/uploads/legacy\ backups/
  ├── 032925.sql (6.5 MB)
  ├── RANKINGS_032925.sql (29.9 MB)
  ├── SMR-032925.sql (3.2 MB)
  └── SPINS_032925.sql (0.1 MB)
```

## Run Migration

### Step 1: SSH to Server
```bash
ssh user@your-server.com
cd /path/to/ngn2.0
```

### Step 2: Execute Master Migration
```bash
php scripts/MASTER_MIGRATION.php
```

### Expected Output
```
████████████████████████████████████████████████████████████████████████████████
NGN 2.0 MASTER MIGRATION - Complete Legacy Data Import
████████████████████████████████████████████████████████████████████████████████

PHASE 1: POSTS MIGRATION
✓ Loaded 100 legacy posts
✓ Transformed 100 posts to new schema

PHASE 2: WRITER RELATIONSHIPS
✓ Created post_writers linking table
✓ Linked 62 posts to writers

PHASE 3: ARTIST IMAGES
✓ Matched and linked 15 artist images

PHASE 4: VERIFICATION
MIGRATED DATA:
  Posts: 100
  Post-Writer Links: 62
  Artists: 913
  Labels: 293
  Venues: 149
  Stations: 6
  Videos: 29

✓✓✓ MIGRATION COMPLETE ✓✓✓

All legacy data has been successfully migrated to the 2025 database.
```

### Step 3: Verify Success

Check the migration results file:
```bash
ls -lh storage/logs/migration_results_*.json
cat storage/logs/migration_results_*.json
```

Expected result file contains:
```json
{
  "success": true,
  "phases": {
    "posts": { "status": "success", "loaded": 100, "migrated": 100 },
    "writers": { "status": "success", "linked": 62 },
    "artist_images": { "status": "success", "matched": 15 },
    "verification": { "status": "success", "posts": 100, ... }
  },
  "errors": []
}
```

## After Migration

### What's Done
- ✓ All 100 posts migrated to 2025 database
- ✓ Writer relationships created (62/100 posts linked)
- ✓ Artist images backfilled (15/913)
- ✓ Temporary staging tables cleaned up
- ✓ No legacy database dependency

### Optional: Cleanup

Remove legacy SQL backup files (keep for at least 30 days):
```bash
rm -rf storage/uploads/legacy\ backups/
```

Or archive to cold storage:
```bash
tar -czf legacy_backups_$(date +%Y%m%d).tar.gz storage/uploads/legacy\ backups/
```

## Troubleshooting

### Missing Writers
If the script reports missing writer IDs (6, 7, 31, 1286):

**Option A: Create missing writers**
```sql
INSERT INTO writers (id, name, slug) VALUES
(6, 'Writer 6', 'writer-6'),
(7, 'Writer 7', 'writer-7'),
(31, 'Writer 31', 'writer-31'),
(1286, 'Writer 1286', 'writer-1286');
```

Then re-run Phase 2:
```bash
php scripts/complete-migration.php
```

**Option B: Map to existing writers**
```sql
UPDATE posts SET author_id = 1
WHERE Id IN (
  SELECT DISTINCT pl.Id FROM posts_legacy pl
  WHERE pl.Author IN (6, 7, 31, 1286)
);
```

### Legacy SQL File Not Found
Ensure backup files are in:
```
storage/uploads/legacy\ backups/032925.sql
```

Check path:
```bash
ls -la storage/uploads/legacy\ backups/
```

### Database Connection Failed
Verify credentials in `.env`:
```bash
grep DB_ .env
```

Test connection:
```bash
mysql -h [HOST] -u [USER] -p[PASS] [DATABASE] -e "SELECT 1;"
```

## Rollback (If Needed)

If something goes wrong:

### Option 1: Restore from Backup (Before Migration)
```bash
mysql -h [HOST] -u [USER] -p[PASS] [DATABASE] < backup_before_migration.sql
```

### Option 2: Truncate Migration Data
```sql
TRUNCATE TABLE posts;
TRUNCATE TABLE post_writers;
DROP TABLE IF EXISTS posts_legacy;
```

## Success Checklist

- [ ] SSH connection to server works
- [ ] Legacy backup files present in `storage/uploads/legacy\ backups/`
- [ ] Run: `php scripts/MASTER_MIGRATION.php`
- [ ] Migration completes successfully
- [ ] Results file generated in `storage/logs/`
- [ ] Verify data in 2025 database
  - `SELECT COUNT(*) FROM posts;` → should be 100
  - `SELECT COUNT(*) FROM post_writers;` → should be 62+
  - `SELECT COUNT(*) FROM artists;` → should be 913
- [ ] Archive or delete legacy SQL files
- [ ] System is now legacy-free ✓

## Next Steps

After successful migration:

1. **Update Application Code**
   - Remove any legacy database references
   - Update documentation
   - Update deployment scripts

2. **Monitor**
   - Watch application logs for any issues
   - Verify all posts/writers/images display correctly
   - Test all user-facing features

3. **Backup New Database**
   - Create fresh backup of 2025 database
   - Store in secure location
   - Update backup schedule

4. **Decommission Legacy**
   - After 30-day retention period
   - Delete legacy database users
   - Document migration process
   - Archive migration logs

---

**Migration Script**: `scripts/MASTER_MIGRATION.php`
**Documentation**: `docs/MIGRATION_PROCESS.md`
**Status**: Production Ready ✓
