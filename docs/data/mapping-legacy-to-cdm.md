### Legacy → NGN 2.0 CDM Mapping

This document maps legacy tables/columns to the NGN 2.0 Canonical Data Model (CDM). It also identifies fields to deprecate and normalization/transformation rules. Charset standardization: utf8mb4 for CDM. Naming: snake_case.

Legend:
- Status: mapped | keep-as-is | deprecated
- Transform: rename/type/enum normalization; defaulting; derived fields

---

#### Primary DB: `nextgennoise`

- Table: `users` → `cdm_users` (Status: mapped)
  - `users.Id` → `cdm_users.id`
  - `users.Email` → `cdm_users.email` (normalize to lowercase; unique)
  - `users.Password` → `cdm_users.password_hash` (retain; may rehash on login)
  - `users.Title`/`OwnerTitle` → `cdm_users.display_name` (choose first non-empty)
  - `users.StatusId` → `cdm_users.status` (map: 1→active; else→disabled unless defined)
  - `users.Slug` → deprecated (user slugs will be derived from display_name as needed)
  - Timestamps: backfill `created_at`/`updated_at` using best-available fields (if absent, set to import time)

- Table: `userroles` → `roles` (Status: mapped)
  - `userroles.Id` → seed-only (not used in CDM)
  - `userroles.Slug` (lowercased) → `roles.slug`
  - `userroles.Title` → `roles.title`
  - Backfill: `users.RoleId` → `user_roles(user_id, role_id)` via `userroles.Slug`

- Table: `Posts` (or legacy posts table) → `cdm_posts` (Status: mapped)
  - `Posts.Id` → `cdm_posts.id` (legacy_id optional if not moving PKs)
  - `Posts.Title` → `cdm_posts.title`
  - `Posts.Slug` → `cdm_posts.slug`
  - `Posts.Body` → `cdm_posts.body`
  - `Posts.AuthorId` (or `UserId`) → `cdm_posts.author_user_id`
  - `Posts.Published`/`Status` → `cdm_posts.status` (map to draft|published|archived)
  - `Posts.PublishedAt` → `cdm_posts.published_at`
  - Timestamps → `created_at`/`updated_at`

- Table: Media/Images (varies by legacy; e.g., `images`, `media`) → `cdm_media` (Status: mapped)
  - `Url`/`Path` → `cdm_media.url`
  - Dimensions/Duration → `width`/`height`/`duration_ms`
  - `Type` or inferred MIME → `cdm_media.type` enum (image|video|audio|file)
  - M2M linking table (if present) → `cdm_post_media(post_id, media_id, sort_order)`

Other primary tables (artists, labels, ads, etc.) will be mapped in subsequent domain passes to `cdm_artists`, `cdm_labels`, and related join tables.

---

#### NGN Rankings DB: `ngnrankings` (Status: in progress)
- Chart/Ranking tables → `cdm_chart_entries`
  - Source chart identifiers → `chart_slug`
  - Week/Period → `week_start`
  - Rank/Position → `rank`
  - Artist/Station references → map to `cdm_artists.id`/`cdm_stations.id` via legacy keys

---

#### SMR Rankings DB: `smr_charts` (Status: in progress)
- Series and entries → `cdm_chart_entries` with `chart_slug='smr:<series>'`
- Station/Market info → `cdm_stations` (populate call_sign/market)

---

#### NGN Spins DB: `ngnspins` (Status: in progress)
- Event rows → `cdm_spins`
  - Station → `station_id` (lookup by legacy station key)
  - Artist/Track → `artist_id`, `track_title`
  - Timestamp → `occurred_at`

---

#### NGN Notes DB: `ngnnotes` (Status: mapped)
- Internal notes → `cdm_notes`
  - Subject typed ID → `subject_type` + `subject_id`
  - Author linkage → `author_user_id`
  - Body → `body`

---

### Transforms and Conventions
- Collation/Charset: convert all CDM tables to utf8mb4_unicode_ci; legacy latin1 text converted on ingest.
- Slugs: ensure uniqueness in CDM; normalize using lowercased, hyphenated values.
- Enums: replace ad-hoc integers with clear enums in CDM (`status`, `type`).
- Timestamps: for legacy rows without timestamps, set `created_at` to backfill run time and `updated_at` = `created_at`.
- Soft deletes: where legacy data implies removal, prefer setting `deleted_at` instead of hard deletion during migration.

### Status Summary
- Primary: users, userroles, posts, media — mapped
- NGN Notes: mapped
- Spins, Rankings (NGN/SMR): in progress (requires value dictionary lookups and volume-aware indexing)

Next steps:
- Enumerate remaining tables per DB and attach statuses (mapped | deprecated | keep-as-is).
- Prepare ETL backfill specs per domain with idempotent upserts and validation steps.
