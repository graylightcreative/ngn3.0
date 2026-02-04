### NGN 2.0 Canonical Data Model (CDM)

This document defines the canonical data model for NGN 2.0. It standardizes entity names, identifiers, relationships, and non-functional constraints so we can migrate cleanly from legacy databases while maintaining legacy I/O compatibility during the transition.

Guiding principles:
- Naming: snake_case for table and column names.
- Charset/Collation: utf8mb4 / utf8mb4_unicode_ci.
- Timestamps: `created_at`, `updated_at` everywhere; optional `deleted_at` for soft deletes.
- IDs: `INT UNSIGNED AUTO_INCREMENT` primary keys for now; UUIDs optional as secondary identifiers where useful.
- Integrity: add foreign keys for core relationships; where legacy write performance is a concern, we can relax to soft-FKs with validation.

---

#### Users and Identity
- Table: `cdm_users`
  - id PK (int unsigned AI)
  - uuid (char(36), nullable)
  - email (varchar(255), unique, not null)
  - password_hash (varchar(255), nullable) — may remain unused when legacy auth is primary
  - display_name (varchar(150), nullable)
  - status (enum: active|disabled|pending) default active
  - created_at, updated_at, deleted_at

- RBAC (defined in identity migrations already in repo): `roles`, `permissions`, `role_permissions`, `user_roles`.

Notes:
- Continue to support legacy `users` table for login until cutover; dual-checks allowed.

#### Artists
- Table: `cdm_artists`
  - id PK
  - legacy_id (int unsigned, nullable)
  - slug (varchar(255), unique)
  - name (varchar(255), not null)
  - created_at, updated_at, deleted_at

#### Labels
- Table: `cdm_labels`
  - id PK
  - legacy_id (int unsigned, nullable)
  - slug (varchar(255), unique)
  - name (varchar(255), not null)
  - created_at, updated_at, deleted_at

#### Stations
- Table: `cdm_stations`
  - id PK
  - legacy_id (int unsigned, nullable)
  - slug (varchar(255), unique)
  - call_sign (varchar(64), nullable)
  - market (varchar(128), nullable)
  - created_at, updated_at, deleted_at

#### Posts
- Table: `cdm_posts`
  - id PK
  - legacy_id (int unsigned, nullable)
  - slug (varchar(255), unique)
  - title (varchar(255), not null)
  - body (longtext, nullable)
  - author_user_id (FK -> cdm_users.id)
  - status (enum: draft|published|archived) default draft
  - published_at (datetime, nullable)
  - created_at, updated_at, deleted_at

#### Media (images, videos, files)
- Table: `cdm_media`
  - id PK
  - legacy_id (int unsigned, nullable)
  - type (enum: image|video|audio|file)
  - url (varchar(1024), not null)
  - width (int unsigned, nullable)
  - height (int unsigned, nullable)
  - duration_ms (int unsigned, nullable)
  - created_at, updated_at, deleted_at

#### Post ↔ Media (many-to-many)
- Table: `cdm_post_media`
  - post_id (FK -> cdm_posts.id)
  - media_id (FK -> cdm_media.id)
  - sort_order (int unsigned, default 0)
  - PRIMARY KEY (post_id, media_id)

#### Spins (play events)
- Table: `cdm_spins`
  - id PK
  - legacy_id (int unsigned, nullable)
  - station_id (FK -> cdm_stations.id)
  - artist_id (FK -> cdm_artists.id)
  - track_title (varchar(255))
  - occurred_at (datetime, not null)
  - source (varchar(64), nullable) — e.g., ngnspins
  - created_at, updated_at

#### Rankings / Charts
- Table: `cdm_chart_entries`
  - id PK
  - legacy_id (int unsigned, nullable)
  - chart_slug (varchar(100), not null)
  - week_start (date, not null)
  - rank (int unsigned, not null)
  - artist_id (FK)
  - station_id (FK, nullable when chart is global)
  - score (decimal(12,4), nullable)
  - created_at, updated_at

#### Notes (internal)
- Table: `cdm_notes`
  - id PK
  - legacy_id (int unsigned, nullable)
  - subject_type (enum: user|artist|label|station|post|media)
  - subject_id (int unsigned, not null)
  - author_user_id (FK -> cdm_users.id)
  - body (text)
  - created_at, updated_at, deleted_at

---

Indexes & performance:
- Add natural unique keys: slugs, composite uniques where applicable (e.g., `chart_slug+week_start+artist_id`).
- Covering indexes for hot queries (to be finalized after gap analysis).

Security & PII:
- Mask email and sensitive data in logs.
- Keep API responses minimal and versioned (v1 legacy, v2 CDM-native). 

Compatibility strategy:
- Dual-write during migration (legacy write-paths also persist to CDM).
- Adapter layer can serve legacy shapes from CDM for read-paths until consumers migrate.
