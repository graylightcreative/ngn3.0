# Appendix C: Database Schema Summary

## Overview

This appendix provides a **business-focused summary** of NGN's core database tables and their purpose. For detailed technical schemas, column definitions, and SQL specifications, see the Technical Bible (Chapter 02 - Core Data Model).

The NGN database uses **MySQL 8.0** with a **Canonical Data Model (CDM)** approach:
- Single source of truth for all music/artist/engagement data
- Normalized design (reduce redundancy, ensure consistency)
- Optimized for ranking calculations and real-time queries
- Currently: ~50GB, supports 2,847 artists, 50K songs, 1M monthly events
- Target 2027: ~500GB (sharding strategy planned for Q2 2026)

---

## Core Entity Tables

### 1. Artists (`cdm_artists`)

**Purpose**: Master artist record (name, bio, image, verification status)

**Key Fields**:
- `artist_id` (PK): Unique identifier
- `artist_name`: Display name (indexed for search)
- `genre`: Primary genre (house, hip-hop, pop, etc.)
- `is_verified`: Verification badge (blue checkmark)
- `follower_count`: Real-time follower count (updated daily)
- `created_at`: Onboarding date
- `profile_image_url`: Avatar/profile picture
- `bio`: Artist biography (max 500 chars)

**Why It Matters**:
- Central hub for all artist data
- Verification status builds trust
- Genre enables discovery/recommendations
- Follower count drives ranking signals

**Relationships**: Links to songs, earnings, rights records, engagement events

---

### 2. Songs (`cdm_songs`)

**Purpose**: Master music record (title, artist, metadata, audio analysis)

**Key Fields**:
- `song_id` (PK): Unique identifier (ISRC-based)
- `artist_id` (FK): Link to artist
- `title`: Song title
- `release_date`: Official release date
- `genre`: Genre classification
- `duration_seconds`: Song length
- `isrc`: International Standard Recording Code (unique globally)
- `audio_fingerprint`: Shazam-style fingerprint (detect duplicates)
- `explicit_flag`: Parental advisory flag
- `created_at`: Upload date

**Why It Matters**:
- ISRC is international identifier (required for licensing)
- Audio fingerprint prevents duplicate uploads
- Duration impacts ad inventory and engagement metrics
- Release date drives chart eligibility

**Relationships**: Links to engagement events, rights records, chart positions

---

### 3. Engagement Events (`cdm_engagement_events`)

**Purpose**: Individual listener actions (plays, Spark tips, adds, shares)

**Key Fields**:
- `event_id` (PK): Unique identifier
- `song_id` (FK): Which song
- `listener_id` (FK): Who listened/tipped
- `event_type`: play, spark_tip, playlist_add, share, comment
- `timestamp`: When it happened
- `duration_seconds`: How long they listened (for plays)
- `spark_amount`: Tip amount if event_type = spark_tip
- `platform`: web, ios, android, radio_smr
- `geolocation`: Country/region
- `user_agent`: Device/browser info

**Why It Matters**:
- Raw input for NGN Score calculation
- Enables real-time chart updates
- Multi-signal design (plays + tips + adds + shares)
- Geographic data drives regional charts
- Platform attribution enables growth analysis

**Volume**: ~1M events/month (2024), target 50M/month (2027)

---

### 4. Users (`cdm_users`)

**Purpose**: Listener/fan accounts (authentication, profile, preferences)

**Key Fields**:
- `user_id` (PK): Unique identifier
- `email`: Email address (indexed, unique)
- `password_hash`: Hashed password (Bcrypt)
- `profile_name`: Display name (can differ from email)
- `account_type`: listener, artist, label, admin
- `is_email_verified`: Email confirmation status
- `created_at`: Signup date
- `last_login`: Recent activity indicator
- `subscription_tier`: free, pro, label
- `payment_method`: stripe_id (Stripe customer ID)

**Why It Matters**:
- Authentication backbone (JWT tokens issued per user)
- Account type determines feature access
- Subscription tier gates premium features
- Payment method linked to Stripe Connect (payouts)

**Relationships**: Links to engagement events, Spark transactions, subscription history

---

## Financial Tables

### 5. Royalty Transactions (`cdm_royalty_transactions`)

**Purpose**: Individual payout record (who gets paid, how much, when)

**Key Fields**:
- `transaction_id` (PK): Unique identifier
- `artist_id` (FK): Recipient artist
- `total_amount`: Gross payout before fees
- `ngn_fee`: NGN's 10% cut
- `artist_net`: Amount artist receives
- `calculation_period`: Month covered (e.g., "2026-01")
- `status`: pending, processed, paid, disputed
- `payout_date`: When money was sent
- `stripe_payout_id`: Link to Stripe payout
- `created_at`: When transaction was calculated

**Why It Matters**:
- Audit trail for every penny paid
- Status tracking prevents double-payouts
- Calculation period clarity (which month does this cover)
- Stripe link enables reconciliation

**Volume**: 3,000-5,000/month (2024), 10,000+/month (2027)

---

### 6. Rights Ledger (`cdm_rights_ledger`)

**Purpose**: Ownership & royalty entitlement (who owns each song, splits)

**Key Fields**:
- `ledger_id` (PK): Unique identifier
- `song_id` (FK): Which song
- `contributing_artist_id` (FK): Feature artist, producer, etc.
- `rights_percentage`: Ownership stake (0-100)
- `role`: primary_artist, featured_artist, producer, songwriter
- `status`: pending_verification, verified, disputed, rejected
- `verified_at`: Timestamp of verification
- `notes`: Dispute notes, resolution documentation

**Why It Matters**:
- Prevents "wrong person gets paid" errors
- Supports multiple contributors per song
- Verification status ensures accuracy
- Dispute resolution audit trail

---

### 7. Rights Splits (`cdm_rights_splits`)

**Purpose**: Detailed split agreement (who splits what % with whom)

**Key Fields**:
- `split_id` (PK): Unique identifier
- `ledger_id` (FK): Link to rights record
- `recipient_artist_id` (FK): Who receives this split
- `percentage`: % of this artist's rights going to recipient
- `split_type`: revenue_share, collaboration, contract
- `start_date`: When split takes effect
- `end_date`: When split expires (if applicable)
- `created_at`: When agreement was made

**Why It Matters**:
- Supports complex multi-party royalty arrangements
- Time-based splits (e.g., 30% to investor for 2 years)
- Revenue share modeling (75/25 deal with label)

---

## Chart & Ranking Tables

### 8. Chart Entries (`cdm_chart_entries`)

**Purpose**: Historical chart positions (snapshot of rankings at calculation time)

**Key Fields**:
- `chart_id` (PK): Unique identifier
- `song_id` (FK): Which song
- `chart_type`: hourly, daily, weekly, monthly, genre, regional
- `calculation_timestamp`: When this chart was calculated
- `chart_position`: 1-100 ranking
- `ngn_score`: Weighted numerical score
- `total_engagements`: Sum of all signals (plays + tips + adds + shares)
- `trend`: up, down, new_entry
- `previous_position`: Position in previous chart period

**Why It Matters**:
- Immutable history (can't retroactively change)
- Multiple chart types (not just #1-#100)
- Score transparency (artists can see calculation)
- Trend detection (new entries vs climbers vs fallers)

**Volume**: 100 entries × 24 charts/day = 2,400 entries/day (growing to 10K/day with more charts)

---

### 9. Identity Map (`cdm_identity_map`)

**Purpose**: Link different artist identifiers (NGN ID vs ISRC vs Spotify ID vs Apple Music ID)

**Key Fields**:
- `map_id` (PK): Unique identifier
- `ngn_artist_id` (FK): NGN's identifier
- `external_id`: Spotify, Apple, ISRC database ID
- `external_platform`: spotify, apple_music, isrc_registry, soundexchange
- `confidence_score`: 0-100 (how sure are we this is the same artist?)
- `verified_at`: Manual verification timestamp
- `created_at`: When mapping was created

**Why It Matters**:
- Prevents duplicate artist profiles (reduce confusion)
- Enables cross-platform artist matching
- Used for radio SMR reconciliation
- Critical for licensing (ISRC is legal requirement)

---

## Radio/SMR Integration Tables

### 10. SMR Ingestions (`smr_ingestions`)

**Purpose**: Batch upload tracking (radio station reports artist spins)

**Key Fields**:
- `ingestion_id` (PK): Unique identifier
- `station_id` (FK): Which radio station
- `upload_filename`: Original CSV filename
- `total_records`: # of songs reported
- `unmatched_count`: # with artist name ambiguity
- `status`: pending_review, unmatched_artists, ready_to_finalize, finalized
- `uploaded_at`: When file was uploaded
- `finalized_at`: When chart was updated

**Why It Matters**:
- Batch processing (50+ records per upload)
- Status tracking prevents data loss
- Unmatched count identifies problems
- Final audit trail (who uploaded when)

---

### 11. SMR Records (`smr_records`)

**Purpose**: Individual song record from radio ingestion (spins, adds, audience size)

**Key Fields**:
- `record_id` (PK): Unique identifier
- `ingestion_id` (FK): Which batch upload
- `ngn_song_id` (FK): Matched NGN song (or NULL if unmatched)
- `reported_artist_name`: What station reported
- `reported_song_title`: Song title from station
- `spins`: Number of times played
- `adds`: Number of times added to rotation
- `audience_reach`: Estimated listeners
- `is_matched`: Boolean (was artist name resolved?)
- `match_confidence`: 0-100 (how sure of the match?)

**Why It Matters**:
- Multi-signal input (spins + adds + audience reach)
- Match quality tracking (prevents bad mappings)
- Audience reach weights SMR signals (bigger station = more weight)

---

## Moderation & Safety Tables

### 12. Takedown Requests (`content_takedowns`)

**Purpose**: DMCA/copyright claims and resolutions

**Key Fields**:
- `takedown_id` (PK): Unique identifier
- `song_id` (FK): Which song
- `claimant_artist_id` (FK): Who claims ownership
- `claim_type`: copyright, derivative_work, impersonation
- `status`: received, verified, appealed, resolved
- `resolution`: removal, metadata_update, split_rights
- `resolved_at`: When matter was settled

**Why It Matters**:
- Legal compliance (must handle DMCA properly)
- Artist protection (prevent fake profiles stealing songs)
- Audit trail for disputes

---

## Summary: Entity Relationships

```
Artists
├─ Has: Songs (1:Many)
├─ Has: Earnings/Royalty Transactions (1:Many)
└─ Has: Rights Records (1:Many)

Songs
├─ Has: Engagement Events (1:Many)
├─ Has: Chart Entries (1:Many)
├─ Has: Rights Ledger entries (1:Many)
└─ Has: SMR Records (1:Many)

Users (Listeners)
└─ Has: Engagement Events (1:Many)

Rights Ledger
├─ Links: Song to Contributing Artists
├─ Has: Rights Splits (1:Many)
└─ Has: Disputes/Resolutions (1:Many)

Engagement Events
└─ Links: Users to Songs (2-way signal)

SMR Ingestions
└─ Has: SMR Records (1:Many)
```

---

## Current Scale & Scalability

### 2024 (Current)
- **Artists**: 2,847
- **Songs**: 50K
- **Users**: 100K+
- **Engagement Events**: 1M/month
- **Database Size**: ~50GB
- **Query Performance**: <100ms p99

### 2026 Target (Post-Series A)
- **Artists**: 5K-10K
- **Songs**: 250K+
- **Users**: 500K+
- **Engagement Events**: 20M/month
- **Database Size**: ~200GB
- **Query Performance**: <200ms p99 (still acceptable)

### 2027 Target (Series B)
- **Artists**: 25K
- **Songs**: 500K+
- **Users**: 1M+
- **Engagement Events**: 50M/month
- **Database Size**: ~500GB
- **Query Performance**: Requires sharding (partition by artist_id)

### Scaling Strategy (2026-2027)

**Database Sharding Plan**:
- **Current**: Single MySQL 8.0 instance
- **2026 Q2**: Horizontal sharding by artist_id (10 shards)
- **2027 Q4**: Geographic sharding (US, EU, APAC)
- **Read replicas**: Add for chart/analytics queries (not writes)

**Performance Optimizations**:
- Indexing strategy: All foreign keys + rankings
- Caching layer: Redis for hot data (charts, top artists)
- Query optimization: Eliminate N+1 queries
- Denormalization: Materialized views for reports

---

## For Investors: Why This Matters

✅ **Normalized design** = Data quality & integrity (critical for payments)
✅ **Audit trails** = Financial compliance (every transaction tracked)
✅ **Verified identities** = Legal compliance (ownership disputes resolved)
✅ **Multi-signal ranking** = Gaming-resistant (can't fake popularity)
✅ **Scalable architecture** = Growth ready (handles 100K+ artists)

---

## Related Documentation

- **Technical Bible, Chapter 02**: Core Data Model (full SQL schemas)
- **Chapter 09**: Ranking Engine (how chart_entries are calculated)
- **Chapter 14**: Rights Ledger (detailed business rules)
- **Chapter 13**: Royalty System (how transactions flow through DB)

---

*For detailed schema, SQL statements, and migration guides, see Technical Bible.*
