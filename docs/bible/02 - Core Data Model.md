2. THIS: Core Data Model (CDM)

2.1 Philosophy & Overview

The Canonical Data Model (CDM) is the single source of truth for NGN 2.0. It acts as a clean, normalized layer that either sits on top of or replaces the Legacy Database. All NGN 2.0 features must read/write to these tables.

Technical Conventions:

Charset: utf8mb4_unicode_ci

Storage Engine: InnoDB

Keys: id (INT UNSIGNED AUTO_INCREMENT). UUIDs used for public API exposure.

Timestamps: created_at, updated_at, and deleted_at (for soft deletes).

2.2 Identity & Roles (The Core)

ngn_2025.users

Central identity table for all platform personas.

id: PK (BIGINT UNSIGNED AUTO_INCREMENT)

email: Unique, normalized lowercase.

password_hash: Argon2id (PHP 8.4 standard).

role_id: Initial primary role.

status: active, disabled, pending.

cdm_identity_map

Handles the "Naming Problem" by mapping alias strings to canonical IDs.

alias_name: e.g., "Metalica", "Metallica".

canonical_id: FK to cdm_artists.

cdm_artists

legacy_id: Reference to old nextgennoise table.

user_id: FK to cdm_users.

label_id: FK to cdm_labels (Nullable).

name, slug, bio, verified_status.

cdm_labels / cdm_venues / cdm_stations

Labels: Roster owners.

Venues: Event hosts (includes capacity, city, state, stripe_account_id).

Stations: Airplay sources (includes call_sign, market, tier).

2.3 Music & Ranking Signals

cdm_songs / cdm_tracks

isrc: International Standard Recording Code (Required for royalties).

iswc: International Standard Musical Work Code.

artist_id, album_id, title, duration.

ngn_2025.spins

station_id, track_id, occurred_at.

source: api_ingest, manual_entry, smr_import.

smr_ingestions

Logs of batch SMR data uploads (The "Erik" Workflow).

uploader_id, file_hash, raw_data_json, processed_at.

ngn_rankings_2025.ranking_items

Computed, immutable weekly results.

chart_slug: e.g., ngn:weekly.

week_start: Monday date.

rank, score, fairness_summary_hash.

2.4 Revenue & Commerce

cdm_rights_ledger

Ownership splits and verification status (Ch. 14).

status: draft, pending, active, disputed.

splits_json: Map of user IDs to percentages.

cdm_royalty_events

Ledger for commissionable occurrences.

type: spin, tip, merch_sale.

amount_gross, amount_net, settled_at.

cdm_products & cdm_tickets

Products: Managed Merch (Printful IDs) and external buy-links (Amazon/Bandcamp).

Tickets: QR-based entry records linked to cdm_events.

2.5 Community & Content

ngn_2025.posts

The engine for the Social Feed (Ch. 22).

author_type: artist, label, station, writer.

content_body (Markdown), engagement_velocity_score.

cdm_engagements

Weighted signals for the EQS calculation.

type: like, share, comment, spark_boost.

weight_modifier: Based on user verification status.

cdm_sparks

The internal fixed-value currency ledger.

sender_id, receiver_id, amount (Internal units: 100 = $1.00).

cdm_media

Central asset registry for images, riffs, and videos.

2.6 Mapping & Configuration

Legacy Table

CDM Table

Migration Strategy

users

cdm_users

JIT Bridge (Ch. 19)

ngnspins

cdm_spins

Linkage Resolver (Ch. 5)

Posts

cdm_posts

Content Import & Metadata Sanitization

smr_charts

cdm_chart_entries

Archival ingest for history

2.7 Factors Configuration

Ranking weights are stored in versioned JSON files (Factors.json) rather than the database to ensure that historical chart hashes can be reproduced exactly using the logic applicable at the time of calculation.