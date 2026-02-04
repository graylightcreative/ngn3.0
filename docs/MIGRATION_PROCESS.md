# NGN 2.0 Complete Migration Process

A **tried and true** process for migrating legacy data to the new 2025 database schema. This document ensures consistency, traceability, and reliability across all data migrations.

## Overview

The migration consists of multiple phases that must be executed in order:

1. **Posts Core Data** - Load legacy posts with schema transformation
2. **Post Relationships** - Link posts to writers
3. **Post Media** - Document post images from legacy system
4. **Entity Images** - Backfill artist, label, station images from filesystem
5. **Cross-Database Linking** - Verify rankings, SMR, and spins relationships

## Phase 1: Posts Core Data Migration

### Purpose
Load 100 legacy posts from SQL dump, transform schema, and verify data integrity.

### Process
```bash
php scripts/migrate-legacy-data.php
```

### What It Does
1. Creates `posts_legacy` temporary staging table with legacy schema
2. Loads legacy posts from `storage/uploads/legacy\ backups/032925.sql`
3. Transforms legacy columns to new CDM schema:
   - `Published` (1/0) → `Status` ('published'/'draft')
   - `Created`/`Updated` → `CreatedAt`/`UpdatedAt`
   - `PublishedDate` → `PublishedAt`
4. Verifies 100% data integrity (all posts have required fields)
5. Keeps `posts_legacy` table for reference

### Success Criteria
- ✓ 100 posts loaded into `posts` table
- ✓ All timestamps properly converted
- ✓ All statuses correctly mapped
- ✓ 100% complete with no missing required fields

### Verification
```sql
SELECT COUNT(*) FROM posts WHERE CreatedAt IS NOT NULL;  -- Should be 100
SELECT COUNT(DISTINCT Status) FROM posts;                -- Should be 1+ (published/draft)
```

---

## Phase 2: Post-Writer Relationships

### Purpose
Link posts to their original writers (authors) from legacy system.

### Process
```bash
php scripts/complete-migration.php
```

### What It Does (Phase 2)
1. Reads author IDs from legacy posts (stored in `Author` field)
2. Maps author IDs to current writers in system:
   - Writer ID 1 → "Writer A"
   - Writer ID 3 → "Kat Black"
   - Writer ID 4 → "Frankie Morales"
   - Writer ID 5 → "Max Thompson"
   - Writer ID 2 → "Sam Rivers"
3. Creates `post_writers` linking table
4. Inserts relationships for matching writers
5. Identifies missing writer IDs and reports them

### Current Writer IDs
| ID | Name | Slug |
|----|------|------|
| 1  | Writer A | writer-a |
| 2  | Sam Rivers | sam-rivers |
| 3  | Kat Black | kat-black |
| 4  | Frankie Morales | frankie-morales |
| 5  | Max Thompson | max-thompson |

### Legacy Author Mapping
| Legacy ID | Status | Mapped Writer |
|-----------|--------|---------------|
| 1 | ✓ Mapped | 1 (Writer A) |
| 3 | ✓ Mapped | 3 (Kat Black) |
| 4 | ✓ Mapped | 4 (Frankie Morales) |
| 5 | ✓ Mapped | 5 (Max Thompson) |
| 6 | ✗ Missing | [Not in system] |
| 7 | ✗ Missing | [Not in system] |
| 31 | ✗ Missing | [Not in system] |
| 1286 | ✗ Missing | [Not in system] |

### Success Criteria
- ✓ 62 posts linked to writers (62% mapping rate)
- ✓ `post_writers` table created with proper constraints
- ✓ Missing writers identified and logged
- ✓ No duplicate relationships

### Verification
```sql
SELECT COUNT(DISTINCT post_id) FROM post_writers;     -- Should be 62
SELECT p.Title, w.name FROM posts p
LEFT JOIN post_writers pw ON p.Id = pw.post_id
LEFT JOIN writers w ON pw.writer_id = w.id
LIMIT 5;
```

---

## Phase 3: Post Images

### Purpose
Document and preserve post featured images from legacy system.

### Process
Integrated into `scripts/complete-migration.php` (Phase 1)

### What It Does
1. Reads image filenames from legacy `posts.Image` field
2. All 100 posts have image metadata in legacy system
3. Images stored at: `lib/images/posts/`

### Legacy Image List
Sample post images:
- `SMR-Chart-Shakeup-Sleep-Theorys-Fallout-Threatens-Billy-Morrisons-Reign.jpg`
- `The-SMR-Underdogs-Who-is-Poised-to-Break-Through-Web.jpg`
- `Rock-Radios-Pulse-Can-SMR-Charts-Resuscitate-Airplay-Web.jpg`

### Success Criteria
- ✓ All 100 posts have image filenames in legacy
- ✓ Image files exist in filesystem
- ✓ Image references can be resolved

### Verification
```bash
ls -la lib/images/posts/ | wc -l  # Should show ~100+ files
```

---

## Phase 4: Entity Images

### Purpose
Backfill images for artists, labels, stations from organized filesystem directories.

### Process
Integrated into `scripts/complete-migration.php` (Phases 3-5)

### Artist Images
- **Directory**: `lib/images/users/`
- **Matching**: Slug-based matching (directory name → artist slug)
- **Current Status**: 15/913 artists matched with images
- **Directories Found**: 18 artist directories
  - a-moment-of-violence
  - awake-at-last
  - clozure
  - coldwards
  - dark-remedy
  - dopesick
  - heroes-and-villains
  - malakye-grind
  - molly-dago
  - sevvven
  - the-almas
  - the-hunger
  - the-rage-online
  - the-sound-228
  - transient
  - venrez
  - wake-up-music-rocks
  - westcreek

### Label Images
- **Directory**: `lib/images/labels/`
- **Matching**: Slug-based matching
- **Current Status**: 0/293 labels matched
- **Action Needed**: Review label slugs and verify directory structure

### Station Images
- **Directory**: `lib/images/stations/`
- **Matching**: Slug-based matching
- **Current Status**: Station image_url column needs to be added
- **Action Needed**: Add image_url column to stations table

### Success Criteria
- ✓ Artist images backfilled (15+)
- ○ Label images strategy defined
- ○ Station images table schema updated

### Verification
```sql
SELECT COUNT(*) FROM artists WHERE image_url IS NOT NULL;  -- Should be >= 15
```

---

## Phase 5: Cross-Database Verification

### Purpose
Verify rankings, SMR, and spins databases are properly linked.

### Process
```bash
php scripts/cross-reference-data.php
```

### Database Status

#### 2025 Main Database
- **Tables**: 171
- **Records**: 225,794 (approximate)
- **Core Entities**:
  - Posts: 100 ✓
  - Writers: 5 ✓
  - Artists: 913 ✓
  - Labels: 293 ✓
  - Venues: 149 ✓
  - Stations: 6 ✓
  - Videos: 29 ✓
  - Tracks: 10 ✓
  - Releases: 8 ✓
  - Shows: 2 ✓

#### Rankings Database (ngn_rankings_2025)
- **Tables**: 13
- **Records**: 1,900,446
- **Purpose**: Historical ranking data
- **Status**: ✓ Fully migrated

#### SMR Database (ngn_smr_2025)
- **Tables**: 3
- **Records**: 47,049
- **Purpose**: Secondary Market Rock chart data
- **Main Table**: chartdata (23,525 records)
- **Status**: ✓ Fully migrated

#### Spins Database (ngn_spins_2025)
- **Tables**: 2
- **Records**: 1,091
- **Purpose**: Radio spin data
- **Main Table**: spindata (1,089 records)
- **Status**: ✓ Fully migrated

### Success Criteria
- ✓ All secondary databases accessible
- ✓ Record counts verified
- ✓ No orphaned relationships
- ✓ Referential integrity maintained

---

## Complete Migration Checklist

Execute in order:

- [ ] **Phase 1**: Run `php scripts/migrate-legacy-data.php`
  - [ ] Verify 100 posts loaded
  - [ ] Verify all timestamps converted
  - [ ] Verify status mappings correct

- [ ] **Phase 2**: Run `php scripts/complete-migration.php`
  - [ ] Verify 62 posts linked to writers
  - [ ] Verify post_writers table created
  - [ ] Review missing writer IDs

- [ ] **Phase 3**: Post Images
  - [ ] Verify all 100 posts have image metadata
  - [ ] Verify image files exist in filesystem

- [ ] **Phase 4**: Entity Images
  - [ ] Verify 15+ artist images linked
  - [ ] Plan label image backfill
  - [ ] Plan station image schema update

- [ ] **Phase 5**: Cross-Reference
  - [ ] Run `php scripts/cross-reference-data.php`
  - [ ] Verify all databases accessible
  - [ ] Verify all secondary databases have correct record counts

---

## Troubleshooting

### Missing Writers
**Problem**: Posts with author IDs (6, 7, 31, 1286) that don't exist in writers table.

**Solutions**:
1. Create new writer entries for missing IDs
2. Map to closest matching existing writer
3. Leave unlinked and resolve manually

```sql
-- Create missing writers
INSERT INTO writers (id, name, slug) VALUES
(6, 'Writer 6', 'writer-6'),
(7, 'Writer 7', 'writer-7'),
(31, 'Writer 31', 'writer-31'),
(1286, 'Writer 1286', 'writer-1286');

-- Then re-run Phase 2
```

### Image Matching Issues
**Problem**: Artists/labels/stations not matching with image directories.

**Solutions**:
1. Verify slug values in database
2. Check for case sensitivity (slugs should be lowercase)
3. Manual directory review and renaming

```sql
-- Check current artist slugs
SELECT Id, name, slug FROM artists ORDER BY slug;

-- Check which directories exist
SELECT * FROM artists WHERE slug IN (
  'a-moment-of-violence', 'awake-at-last', 'clozure', 'coldwards'
);
```

---

## Ongoing Maintenance

### After Migration
1. **Monitor**: Watch for missing relationships
2. **Audit**: Run cross-reference report quarterly
3. **Backfill**: Continuously add missing writer/image data as it's identified
4. **Document**: Keep this process updated with new phases

### Data Quality Gates
- All posts must have CreatedAt/UpdatedAt timestamps
- All posts must have valid slug
- Post-writer relationships should ideally be > 80%
- Core entity images should reach > 50% coverage

---

## Key Files

| Script | Purpose | Status |
|--------|---------|--------|
| `scripts/migrate-legacy-data.php` | Core posts migration | ✓ Working |
| `scripts/complete-migration.php` | Writers, artists, labels, stations | ✓ Working |
| `scripts/cross-reference-data.php` | Verification & reporting | ✓ Working |
| `scripts/validate-migration.php` | Full database validation | ✓ Working |

---

## Results Summary

### Current Migration State
- **Posts**: 100/100 ✓
- **Writers**: 62/100 posts linked (62%)
- **Artist Images**: 15/913 (1.6%)
- **Secondary DBs**: 2.9M+ total records ✓

### Next Steps
1. Add missing writers (IDs: 6, 7, 31, 1286)
2. Backfill label and station images
3. Add image_url column to remaining entity tables
4. Create post image storage migration plan

---

**Last Updated**: 2026-01-28
**Version**: 1.0 - Initial Complete Process
