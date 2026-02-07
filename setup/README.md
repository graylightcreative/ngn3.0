# Admin v2 Setup Scripts

## Quick Start

### 1️⃣ Create Database Tables
```bash
cd /Users/brock/Documents/Projects/ngn_202
php setup/create_admin_tables.php
```

**What it does:**
- Creates 7 tables (smr_ingestions, smr_records, cdm_rights_ledger, etc.)
- Adds indexes for performance
- Sets up foreign key relationships
- Verifies creation with table count

**Output:**
```
✅ smr_ingestions
✅ smr_records
✅ cdm_identity_map
✅ cdm_chart_entries
✅ cdm_rights_ledger
✅ cdm_rights_splits
✅ cdm_rights_disputes

Results: 7 created, 0 failed
```

### 2️⃣ Test All Workflows
```bash
php setup/test_admin_workflows.php
```

**What it tests:**
- ✅ Database tables exist
- ✅ SMR Pipeline: Create → Store → Map → Finalize
- ✅ Rights Ledger: Create → Get → Split → Verify → Dispute
- ✅ Services work correctly

**Expected Output:**
```
✅ Table: smr_ingestions exists
✅ Create SMR ingestion record
✅ Store SMR records
✅ Get unmatched artists
✅ Map artist identity
✅ Get review records
✅ Finalize SMR ingestion
✅ Create rights registration
✅ Get rights registry
... (10 more tests)

📊 TEST RESULTS
✅ Passed: 20
❌ Failed: 0
📈 Success Rate: 100.0%

🎉 ALL TESTS PASSED! Admin v2 is ready to use.
```

---

## Prerequisites

- PHP 8.x with PDO MySQL support
- MySQL 5.7+ or MariaDB
- `/lib/bootstrap.php` loaded (config, database connection)
- Admin JWT token working

---

## What Each Script Does

### `create_admin_tables.php`
Creates all 7 required tables with:
- Proper column types and constraints
- Indexes on frequently queried columns
- Foreign key relationships
- Default timestamps

**Tables created:**
1. `smr_ingestions` - Upload metadata
2. `smr_records` - Individual parsed records
3. `cdm_identity_map` - Artist alias resolution
4. `cdm_chart_entries` - Finalized chart data
5. `cdm_rights_ledger` - Ownership registrations
6. `cdm_rights_splits` - Multi-contributor ownership
7. `cdm_rights_disputes` - Dispute tracking

---

### `test_admin_workflows.php`
Runs 20 integration tests covering:

**Database Tests (5):**
- All tables exist
- Can connect to each

**SMR Pipeline Tests (6):**
- Create ingestion record
- Store parsed records
- Get unmatched artists
- Map artist identity
- Get review records
- Finalize ingestion → commit to chart

**Rights Ledger Tests (9):**
- Create registration
- Get registry list
- Get summary counts
- Add ownership split
- Get splits
- Verify ISRC format
- Mark verified
- Mark disputed
- Generate Digital Safety Seal certificate

---

## Troubleshooting

### Table Already Exists Error
This is normal on second run. Script uses `CREATE TABLE IF NOT EXISTS`.

### Permission Denied
Make sure you have MySQL user with these privileges:
```sql
GRANT CREATE, ALTER, DROP, SELECT, INSERT, UPDATE, DELETE
ON ngn_2025.* TO 'your_user'@'localhost';
```

### Foreign Key Constraint Error
Script temporarily disables foreign key checks. If error persists:
```sql
SET FOREIGN_KEY_CHECKS=0;
-- Run script again
SET FOREIGN_KEY_CHECKS=1;
```

### Connection Error
Verify `lib/bootstrap.php` is loading correctly and database is accessible.

---

## Next Steps

After running both scripts successfully:

1. **Verify in Database**
   ```sql
   SHOW TABLES LIKE '%smr_%' OR LIKE '%cdm_%' OR LIKE '%ngn_%';
   SELECT COUNT(*) FROM smr_ingestions;
   SELECT COUNT(*) FROM cdm_rights_ledger;
   ```

2. **Test SMR Workflow Manually**
   - Upload test CSV via `/admin-v2/` UI
   - Verify records appear in `smr_records`
   - Test identity mapping
   - Finalize and check `cdm_chart_entries`

3. **Test Rights Ledger Manually**
   - Create right via `/admin-v2/` UI
   - Verify appears in registry
   - Add split, resolve dispute
   - Generate certificate

4. **Start Phase 3**
   - Create `RoyaltyService.php`
   - Add royalty endpoints
   - Build payout UI

---

## Manual Table Creation

If scripts don't work, run DDL directly in MySQL:

```bash
mysql -u root -p ngn_2025 < docs/DATABASE_SCHEMA_ADMIN_V2.md
```

Or copy DDL from `/docs/DATABASE_SCHEMA_ADMIN_V2.md` and paste into MySQL Workbench.

---

## Notes

- Scripts are idempotent (safe to run multiple times)
- Test script creates sample data (The Beatles, Pink Floyd, etc.)
- All timestamps in UTC
- Foreign keys set to CASCADE on delete
- Indexes optimized for common queries

---

**Last Updated:** 2026-02-07
