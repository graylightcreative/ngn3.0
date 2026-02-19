# Chapter 08: Core Data Model

## Executive Summary

NGN's data model is organized around **five core entities**: **(1) Users** (artists, fans, venues, labels), **(2) Music** (songs, tracks, releases), **(3) Engagement** (spins, likes, comments, Sparks), **(4) Financials** (royalties, payouts, rights), **(5) Configuration** (settings, weights, policies). This normalized structure ensures data integrity, enables rapid reporting, and prevents payment disputes. Unlike Spotify (which doesn't track rights at all), NGN's data model is compliance-first and audit-ready.

---

## 1. Business Context

### 1.1 Why Data Model Matters

**Poor data model consequences**:
- Artist payments are wrong (data integrity issues)
- Charts are inconsistent (data conflicts)
- Rights disputes are unresolvable (no clear ownership)
- Compliance audits fail (can't prove what we did)

**Good data model advantages**:
- Accurate payments (trust with artists)
- Consistent rankings (trust with fans)
- Resolvable disputes (clear ownership trails)
- Audit-ready (ready for institutional investors)

**NGN's data model is designed to prevent payment disputes before they happen.**

### 1.2 Five Core Entities

```
┌─────────────────────────────────────────────┐
│         NGN Data Model Overview             │
├─────────────────────────────────────────────┤
│                                             │
│  USERS                MUSIC                 │
│  ├─ Artists          ├─ Songs               │
│  ├─ Fans             ├─ Tracks              │
│  ├─ Venues           └─ Albums              │
│  └─ Labels           |                      │
│         |            |                      │
│         └────────────┴──────┐               │
│                             ↓               │
│                       ENGAGEMENT            │
│                       ├─ Plays/Spins        │
│                       ├─ Likes              │
│                       ├─ Comments           │
│                       └─ Sparks             │
│                             |               │
│         ┌───────────────────┘               │
│         ↓                                   │
│      FINANCIALS              CONFIGURATION  │
│      ├─ Rights               ├─ Settings    │
│      ├─ Royalties            ├─ Policies    │
│      ├─ Payouts              └─ Weights     │
│      └─ Escrow               (for rankings) │
│                                             │
└─────────────────────────────────────────────┘
```

---

## 2. Entity 1: Users (Identity & Access)

### 2.1 User Types

NGN supports four user types:

#### Type 1: Artists
- Can post music
- Can receive Sparks
- Can see earnings dashboard
- Can claim events/tour dates

#### Type 2: Fans
- Can discover music
- Can send Sparks
- Can message artists
- Can buy tickets

#### Type 3: Venue Operators
- Can list venues/events
- Can process ticket sales
- Can view event analytics
- Can integrate with artist profiles

#### Type 4: Labels / Aggregators
- Can manage artist rosters
- Can bulk upload rights data
- Can access B2B API
- Can integrate with own platforms

### 2.2 User Account Structure

**Every user has**:
- Unique email (login credential)
- Secure password (hashed with Argon2id)
- Role/permission level
- Account status (active, disabled, pending verification)
- Tax/payment information (for payouts)

**Artists additionally have**:
- Public profile (bio, photo, links)
- Verification badge (if verified)
- Stripe account (for payments)
- Label affiliation (if applicable)

**Business advantage**: Flexible user system supports many business models (independent artists, label rosters, venue networks).

---

## 3. Entity 2: Music (Tracks & Releases)

### 3.1 Music Hierarchy

```
Album
├─ Track 1
│  ├─ ISRC: USUM71234567
│  ├─ Artist: Sarah
│  ├─ Producer: John
│  └─ Length: 3:45
├─ Track 2
│  ├─ ISRC: USUM71234568
│  ├─ Artist: Sarah
│  └─ Length: 4:12
└─ Track 3
```

### 3.2 Key Identifiers

**ISRC (International Standard Recording Code)**:
- 12-character code unique to every recording
- Assigned by distributor when track is created
- Example: `USUM71234567` = "New Song" by Sarah
- Used to link with Spotify/Apple/YouTube (industry standard)

**Purpose of ISRC**:
- Prevents duplicate claims (same ISRC = same recording)
- Enables global tracking (can find song anywhere)
- Enables rights verification (proves ownership)

**Business advantage**: ISRC is industry-standard identifier, enables integrations with all major platforms.

### 3.3 Release Metadata

**Every track has**:
- Title
- Artist(s)
- Release date
- Genre
- Duration
- Album/EP affiliation

**Metadata enables**:
- Searching (find song by title)
- Categorization (genre-specific charts)
- Discovery (similar songs)
- Rights verification (matches ISRC)

**Business advantage**: Rich metadata enables sophisticated search and discovery.

---

## 4. Entity 3: Engagement (How People Interact)

### 4.1 Engagement Types

NGN tracks five engagement signals:

#### Signal 1: Spins (Radio Airplay)
- Source: Radio stations, SMR ingestion
- Data: Which song, on which station, when
- Weight: High (real-world validation)

#### Signal 2: Likes (Positive Sentiment)
- Source: Fans on NGN platform
- Data: Fan, song, timestamp
- Weight: Medium (low-friction engagement)

#### Signal 3: Comments (High-Intent Engagement)
- Source: Fans on NGN platform
- Data: Fan, song, comment text, timestamp
- Weight: High (effort required to comment)

#### Signal 4: Shares (Viral Signal)
- Source: Fans sharing on NGN or external socials
- Data: Who shared, what song, where, when
- Weight: Very high (strongest viral signal)

#### Signal 5: Sparks (Direct Tipping)
- Source: Fans tipping artists
- Data: Fan, artist, amount, timestamp, message (optional)
- Weight: Critical (proves fan paid money)

### 4.2 Engagement Aggregation

**Engagement is aggregated for ranking**:

Example (weekly basis):
- Song X has: 50 spins, 200 likes, 30 comments, 15 shares, 100 Sparks
- Each signal is weighted and normalized
- EQS score calculated from all signals
- Song X's rank determined

**Business advantage**: Multi-signal approach prevents gaming (can't fake all signals; too expensive).

### 4.3 Engagement Privacy

**NGN respects fan privacy**:
- Likes/comments are optional (fans don't have to identify)
- Can like songs anonymously
- Spark tippers' names are optional (can tip anonymously)

**But for Sparks**:
- Need name for artist relationship (artist needs to know who supported them)
- Optional: Fan can choose "Anonymous Supporter"

**Business advantage**: Privacy builds trust; fans feel safe engaging.

---

## 5. Entity 4: Financials (Money Tracking)

### 5.1 Rights Ledger

**Every song has rights record**:

```
Song: "New Song" (ISRC: ABC123)
Status: ACTIVE (royalty-eligible)

Split Agreement:
├─ Sarah (Artist): 60%
├─ John (Producer): 30%
└─ Label: 10%

All parties have digitally signed ✓
No disputes on record ✓
Ready for payout ✓
```

**Status values**:
- **DRAFT**: Metadata uploaded, splits not yet defined
- **PENDING**: Splits defined, waiting for signatures
- **ACTIVE**: All parties signed, eligible for royalties
- **DISPUTED**: Conflicting claims, frozen until resolved

**Business advantage**: Clear status prevents accidental mispayments.

### 5.2 Royalty Transactions

**Every payout is a transaction**:

```
Transaction Record:
├─ ID: TXN-2026-001847
├─ Song: "New Song"
├─ Artist: Sarah
├─ Type: spark_tip
├─ Gross amount: $5.00
├─ Platform fee (10%): $0.50
├─ Artist receives: $4.50
├─ Status: cleared
├─ Settled date: Feb 10, 2026
└─ Stripe payout ID: ch_1234567890
```

**Transaction status flow**:
1. **Pending**: Money received, waiting for verification
2. **Cleared**: Verified, ready for artist payout
3. **Paid out**: Artist has received funds
4. **Failed**: Error during processing (needs manual review)

**Business advantage**: Immutable transaction ledger proves what happened when.

### 5.3 Escrow System

**Disputed royalties held in escrow**:

```
Song X has conflicting claims:
├─ Claimant A: "I produced this, should get 50%"
└─ Claimant B: "I produced this, should get 50%"

Status: DISPUTED

Royalties generated: $1,000
├─ Held in escrow account: $1,000
├─ Earning interest
├─ Both parties provide proof of ownership
└─ Admin reviews evidence, resolves

Outcome: Claimant A proven owner
├─ Release $1,000 to Claimant A
├─ Reject Claimant B's claim
└─ Issue refund to Claimant B's submitted evidence
```

**Business advantage**: Escrow protects NGN from liability and protects rightful owner from theft.

---

## 6. Entity 5: Configuration (System Policies)

### 6.1 Ranking Weights

**Weights are versioned and immutable**:

```
2026 Q1 Ranking Weights (v1.0):
├─ Radio spins: 2.0x multiplier
├─ SMR spins: 1.5x multiplier
├─ Likes: 1.0x multiplier
├─ Comments: 3.0x multiplier
├─ Shares: 10.0x multiplier
└─ Sparks: 5.0x multiplier (critical)

2026 Q2 Ranking Weights (v1.1):
├─ Radio spins: 2.0x (unchanged)
├─ SMR spins: 1.8x (increased from 1.5)
├─ Likes: 1.0x (unchanged)
├─ Comments: 3.0x (unchanged)
├─ Shares: 10.0x (unchanged)
└─ Sparks: 5.0x (unchanged)
```

**Why versioning matters**:
- Historical charts are reproducible (can calculate old charts with old weights)
- Artists can see why they ranked differently
- Transparent policy changes (weights aren't secret)

**Business advantage**: Transparency builds artist trust ("I understand why my song ranked #3").

### 6.2 Fee Structure

**Platform fees are configurable**:

```
Platform Fees (2026):
├─ Spark tips: 5%
├─ Subscriptions: 15%
├─ Ticketing: 2.5% + $1.50
├─ Merch sales: 10%
└─ B2B data licensing: 50%
```

**Why configurable?**
- Can optimize by geography (different fees in different countries)
- Can optimize by payment method (credit card vs local payment)
- Can offer promotions (lower fees for new artists)
- Can track cost of business (what % goes to payment processing, infrastructure, etc.)

**Business advantage**: Flexibility to optimize unit economics as business scales.

### 6.3 Payment Policies

**Payout schedules configured per account type**:

```
Artist Account (Standard):
├─ Spark tips: Real-time
├─ Monthly pool (EQS): 5th of month following
└─ Minimum payout: $0 (no minimum withdrawal)

Label Account (Pro):
├─ All payments: Weekly
├─ Reporting: Detailed analytics
└─ Features: API access, bulk uploads

Venue Account (Enterprise):
├─ Ticketing payouts: Daily
├─ Event reporting: Real-time
└─ Features: Dedicated support
```

**Business advantage**: Flexible policies serve different customer types without code changes.

---

## 7. Data Relationships & Integrity

### 7.1 Foreign Keys (Referential Integrity)

**NGN enforces relationships**:

```
Artists table
├─ artist_id (PK)
├─ user_id (FK → users.id)
└─ label_id (FK → labels.id, nullable)

Songs table
├─ song_id (PK)
├─ artist_id (FK → artists.id)
├─ isrc (unique)
└─ album_id (FK → albums.id)

Rights_ledger table
├─ rights_id (PK)
├─ song_id (FK → songs.id)
├─ status (enum)
└─ created_at

Royalty_transactions table
├─ txn_id (PK)
├─ song_id (FK → songs.id)
├─ artist_id (FK → artists.id)
├─ amount
└─ status
```

**Benefit**: Database enforces consistency (can't delete artist if they have songs).

### 7.2 Immutability & Audit Trails

**Financial records are immutable**:

```
Original transaction:
├─ ID: TXN-001
├─ Artist: Sarah
├─ Amount: $100
├─ Created: 2026-01-15
└─ Status: paid_out ✓

Correction (if needed):
├─ Creates NEW transaction: TXN-001-CORRECTION
├─ Reversal amount: -$100
├─ New amount: $105
├─ Created: 2026-02-01
└─ Reason: "Calculation error fixed"

Audit trail shows:
├─ Original: TXN-001 ($100)
├─ Correction: TXN-001-CORRECTION (-$100 + $105)
├─ Net effect: $105
└─ All transactions visible
```

**Business advantage**: Audit trail proves what happened when. Settlements with artists are transparent.

---

## 8. Data Privacy & Compliance

### 8.1 Personal Data Handling

**NGN collects minimal personal data**:

- Email (for login + payments)
- Name (for public profile)
- Payment information (bank account or Stripe ID)
- Tax information (1099 for US tax reporting)

**Never collected**:
- Credit card numbers (Stripe handles this)
- Social security numbers (not needed)
- Browsing behavior (no tracking)
- Personal messages (not stored long-term)

**Business advantage**: Minimal data collection = lower GDPR/CCPA compliance burden.

### 8.2 Data Retention Policies

```
Financial Records:
├─ Transactions: Keep forever (audit trail)
├─ Payouts: Keep forever (proof of payment)
└─ Tax records: Keep 7 years (US requirement)

User Accounts:
├─ Active accounts: Keep forever
├─ Deleted accounts: Anonymize after 90 days
└─ Personal data: Delete upon request (GDPR right)

Rights Ledgers:
├─ Active rights: Keep forever
├─ Disputed rights: Keep 5 years after resolution
└─ Rejected claims: Keep 3 years
```

**Business advantage**: Clear policies satisfy regulators and protect users.

### 8.3 Data Backups & Recovery

**NGN maintains multiple backups**:

```
Backup Schedule:
├─ Hourly snapshots (last 24 hours)
├─ Daily backups (last 30 days)
├─ Weekly backups (last 12 weeks)
├─ Monthly backups (last 24 months)
└─ Quarterly archives (7-year retention)

Backup locations:
├─ Primary: AWS (same region as prod)
├─ Secondary: AWS (different region)
├─ Tertiary: Cold storage (offline backup)
```

**Recovery capability**:
- Recover single transaction: < 1 hour
- Recover entire database: < 4 hours
- Recovery tested quarterly

**Business advantage**: Proven disaster recovery = customer confidence.

---

## 9. Data Scaling Strategy

### 9.1 Current Scale (2024)

```
Users: 5,000 (2,847 artists + 2,153 fans)
Songs: 50,000
Engagements/month: 1M
Transactions/month: 100K
Database size: ~50 GB
```

### 9.2 Projected Scale (2027)

```
Users: 50,000 (10,000 artists + 40,000 fans)
Songs: 500,000 (10x growth)
Engagements/month: 50M (50x growth)
Transactions/month: 5M (50x growth)
Database size: ~500 GB
```

### 9.3 Scaling Strategy

**At 500GB scale, database needs optimization**:

**Technique 1: Partitioning**
- Split large tables by range (e.g., transactions by date)
- Queries only scan relevant partition
- Improves query speed 10-100x

**Technique 2: Sharding**
- Split data across multiple database instances (by artist_id)
- Artist X's data on DB1, Artist Y's data on DB2
- Allows horizontal scaling (add more servers)

**Technique 3: Caching**
- Frequently accessed data (charts) cached in Redis
- Reduces database load 80-90%
- Enables sub-100ms response times

**Timeline**: Implement by 2027 when database reaches 300-500GB

**Business advantage**: Data model is designed for scale from day 1.

---

## 10. Data-Driven Decision Making

### 10.1 Reporting & Analytics

**NGN data model enables reporting**:

```
Real-time Reports:
├─ Revenue by day (Sparks, subscriptions, ticketing)
├─ Top artists (by engagement)
├─ Top songs (by charts)
├─ Geographic distribution (fans by location)
├─ Payment metrics (payout success rate, average payout)
└─ Platform health (uptime, error rate)

Monthly Reports:
├─ Artist earnings distribution
├─ Revenue per user type
├─ Churn analysis (who left, why)
├─ Engagement trends
└─ Competitive benchmarking
```

**Business advantage**: Data-driven decision making (know what's working, optimize what isn't).

### 10.2 B2B Intelligence

**NGN's data is valuable to partners**:

- **Labels want**: Which artists are emerging (early detection)
- **Aggregators want**: Real engagement data (vs algorithm proxy)
- **Venues want**: Fan geography (where to book shows)
- **Investors want**: Artist performance trends (investment decisions)

**NGN sells this data** (with artist consent):
- $500-5,000/month per B2B customer
- $XXM potential revenue by 2027
- Artists benefit from data licensing revenue share

**Business advantage**: Data asset generates additional revenue.

---

## 11. Data Model vs Spotify

| Aspect | Spotify | NGN |
|--------|---------|-----|
| **Ownership Tracking** | None (relies on labels) | Ledger-based ✅ |
| **Rights Verification** | Manual (off-platform) | Cryptographic (on-platform) ✅ |
| **Artist Royalties** | Opaque calculation | Transparent breakdown ✅ |
| **Engagement Signals** | Streams only | Multi-signal ✅ |
| **Payment Auditing** | Limited | Complete trail ✅ |
| **Dispute Resolution** | External courts | Built-in escrow ✅ |

**NGN's data model solves problems Spotify ignores.**

---

## 12. Conclusion: Data Model Is Competitive Advantage

**NGN's data model is not just internal infrastructure—it's a defensible asset.**

✅ Enables transparent payments (artist trust)
✅ Prevents disputes (cost savings)
✅ Powers B2B intelligence (revenue generation)
✅ Scales efficiently (supports 50K+ artists)
✅ Auditable (institutional credibility)

**Once artists build data on NGN** (earnings history, fan relationships, rights records), switching costs become very high. Data becomes lock-in.

---

## 13. Read Next

- **Chapter 09**: Ranking Engine (How data drives rankings)
- **Chapter 13**: Royalty System (How data drives payments)
- **Appendix C**: Database Schema (Technical reference)

---

*Related Chapters: 07 (Technology Stack), 09 (Ranking Engine), 13 (Royalty System), 14 (Rights Ledger), 15 (Spark Economy)*
