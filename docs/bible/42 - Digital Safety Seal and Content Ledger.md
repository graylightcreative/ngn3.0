# Chapter 42: Digital Safety Seal and Content Ledger

**Author**: Engineering Team
**Version**: 1.0.0
**Date**: 2026-02-06
**Status**: ACTIVE - NGN 2.0.2

---

## 1. Overview

The **Digital Safety Seal** system is NGN's immutable content ownership ledger. It cryptographically links file hashes to verified owners with timestamps, enabling artists and labels to prove content ownership through a public verification API.

**Purpose**: Bridge the gap between file hashing (used for deduplication and integrity) and ownership signing (proving "I uploaded this content").

**Key Innovation**: Unlike blockchain-only solutions, the Digital Safety Seal is operational today on the NGN platform, providing immediate value while remaining blockchain-agnostic for future integration.

---

## 2. Core Architecture

### 2.1 The Content Ledger

**Database Table**: `content_ledger`

Every uploaded file is registered in the ledger with:
- **Content Hash** (SHA-256): Unique fingerprint of the file
- **Metadata Hash** (SHA-256): Canonical JSON of title, artist, credits, rights_split
- **Owner ID**: Foreign key to `users` table
- **Certificate ID**: Unique identifier in format `CRT-YYYYMMDD-XXXXXXXX`
- **Upload Source**: Identifier of source system (station_content, smr_ingestion, smr_assistant, api_upload, admin_upload)
- **Source Record ID**: Reference to original record in source system

**Key Constraints**:
- UNIQUE on `content_hash` — prevents duplicate registrations of same file
- UNIQUE on `certificate_id` — ensures 1:1 mapping between content and certificates
- Foreign key on `owner_id` — maintains referential integrity with user ownership

### 2.2 The Verification Ledger

**Database Table**: `content_ledger_verification_log`

Every verification via the public API is logged with:
- Ledger ID reference
- Verification type (public_api, certificate_scan, third_party, internal, admin)
- Verification result (match, mismatch, not_found, error)
- Request IP address
- User agent
- Referer header
- Timestamp

**Purpose**: Audit trail for compliance, analytics, and fraud detection.

---

## 3. Services and Components

### 3.1 ContentLedgerService

**File**: `lib/Legal/ContentLedgerService.php`

Manages all ledger operations with these public methods:

#### registerContent()
```php
public function registerContent(
    int $ownerId,
    string $contentHash,
    string $uploadSource,
    array $metadata,      // title, artist_name, credits, rights_split
    array $fileInfo,      // size_bytes, mime_type, filename
    ?int $sourceRecordId = null
): array
```

**Workflow**:
1. Validate all inputs (hash format, owner ID, file info)
2. Check for duplicate hash (isDuplicate())
3. Generate metadata hash for integrity verification
4. Generate unique certificate ID
5. INSERT into content_ledger
6. LOG registration event
7. Return ledger record with certificate_id

**Error Handling**: Throws InvalidArgumentException on validation failure, RuntimeException on database failure. Caller should wrap in try-catch.

#### lookupByHash()
Retrieve ledger entry by SHA-256 file hash. Returns null if not found.

**Use Case**: Third-party distributors verifying file authenticity before adding to platform.

#### lookupByCertificateId()
Retrieve ledger entry by certificate ID. Returns null if not found.

**Use Case**: Artists sharing certificate IDs with labels for proof of ownership.

#### verifyContent()
Verify that content metadata matches ledger entry.

```php
public function verifyContent(string $contentHash, array $metadata): array
// Returns: { verified, status, message, ledger_record }
```

**Use Case**: Internal system validation to detect metadata tampering.

#### generateMetadataHash()
Create canonical JSON hash for integrity verification.

**Algorithm**:
1. Sort JSON keys alphabetically
2. Encode with JSON_SORT_KEYS | JSON_UNESCAPED_SLASHES
3. Hash with SHA-256
4. Compare with constant-time hash_equals()

**Benefit**: Any change to metadata (even spaces or key order) will produce different hash, making tampering detectable.

#### incrementVerificationCount()
Called whenever content is verified via public API.

**Parameters**:
- ledger_id: Primary key of content_ledger entry
- verificationType: 'public_api', 'certificate_scan', etc.
- verificationResult: 'match', 'mismatch', 'not_found', 'error'
- requestInfo: IP, user-agent, referer, etc.

**Operations**:
1. Increment verification_count on content_ledger
2. Update last_verified_at timestamp
3. INSERT into content_ledger_verification_log

#### getUserLedgerHistory()
Retrieve all entries for a specific user (artist).

**Use Case**: Artist dashboard showing all their registered content with verification counts.

### 3.2 DigitalCertificateService

**File**: `lib/Legal/DigitalCertificateService.php`

Generates professional HTML certificates with embedded QR codes.

#### generateCertificateHtml()
Creates printable certificate markup.

**Features**:
- Professional design with watermark and seal graphic
- Print CSS for optimal A4 output
- Embedded QR code as base64 PNG (no external dependencies)
- Responsive layout (mobile, tablet, desktop, print)
- Print button with auto-open dialog
- Shows certificate ID, owner name, track title, artist, file hash, registration date

**Output**: Complete HTML5 document suitable for:
- Printing to PDF
- Email transmission
- Web display
- Frame as NFT metadata

#### generateQrCodeBase64()
Creates QR code pointing to verification API.

**QR Content**: `https://baseurl/api/v1/legal/verify?certificate_id=CRT-...`

**Error Handling**: Falls back to placeholder PNG if QR generation fails. Non-blocking approach ensures certificate HTML still generates even if QR creation fails.

---

## 4. Integration Points

### 4.1 Station Content Upload

**File**: `lib/Stations/StationContentService.php` (uploadContent method)

**Integration Point**: After `storeContent()` call

**Steps**:
1. Get station owner from `stations.user_id`
2. Instantiate ContentLedgerService
3. Call registerContent() with upload metadata
4. Update station_content.certificate_id
5. Generate certificate HTML → /storage/certificates/
6. Return certificate_id and certificate_url in response

**Non-Breaking**: Wrapped in try-catch. Upload succeeds even if ledger fails.

### 4.2 SMR CSV Ingestion

**File**: `public/admin/smr-ingestion.php`

**Integration Point**: After `$uploadId = $pdo->lastInsertId()`

**Steps**:
1. Instantiate ContentLedgerService
2. Register CSV file with owner_id from session
3. Update smr_uploads.certificate_id
4. Non-blocking error handling

**Metadata**: Minimal (title = "SMR Data Upload: filename")

### 4.3 Assistant Upload

**File**: `public/admin/assistant-upload.php`

**Integration Point**: After `$uploadedFileId = $pdo->lastInsertId()`

**Steps**:
1. Instantiate ContentLedgerService
2. Register file with owner_id from session
3. Update smr_uploads.certificate_id

### 4.4 Upload Service

**File**: `lib/Smr/UploadService.php` (handleMultipart method)

**Integration Point**: After file move, before record writing

**Key Addition**: Calculate SHA-256 file hash (previously missing in this service)

**Steps**:
1. hash_file('sha256', $target)
2. Instantiate ContentLedgerService
3. Register with non-blocking error handling
4. Add hash and certificate_id to returned record

---

## 5. Public Verification API

### 5.1 Endpoint

**Path**: `/api/v1/legal/verify`
**Method**: GET
**Authentication**: None (public)
**CORS**: Enabled (Access-Control-Allow-Origin: *)

### 5.2 Parameters

Either `hash` OR `certificate_id` required:

```
GET /api/v1/legal/verify?hash=abc123...
GET /api/v1/legal/verify?certificate_id=CRT-20260206-A3F8D91E
```

### 5.3 Success Response (200 OK)

```json
{
  "verified": true,
  "certificate_id": "CRT-20260206-A3F8D91E",
  "content_hash": "abc123...",
  "owner": {
    "user_id": 42,
    "name": "Artist Name"
  },
  "content": {
    "title": "Track Title",
    "artist_name": "Artist Name",
    "file_size_bytes": 5242880,
    "mime_type": "audio/mpeg",
    "original_filename": "track.mp3"
  },
  "registered_at": "2026-02-06T12:00:00Z",
  "verification_count": 15,
  "last_verified_at": "2026-02-06T14:30:00Z",
  "status": "active",
  "message": "Content verified - Registered in NGN ledger"
}
```

**Note**: Email address intentionally omitted for privacy.

### 5.4 Not Found Response (404)

```json
{
  "verified": false,
  "status": "not_found",
  "message": "No ledger entry found for the provided hash or certificate ID"
}
```

### 5.5 Invalid Request Response (400)

```json
{
  "verified": false,
  "status": "invalid_hash_format",
  "message": "Hash must be a 64-character hexadecimal SHA-256"
}
```

### 5.6 Caching

- Cache-Control: `public, max-age=300` (5 minute cache)
- Reduces database load for repeated verification requests
- Suitable for browser caching and CDN caching

### 5.7 Logging

Every verification request is logged to `content_ledger_verification_log`:
- IP address for tracking
- User-Agent for client identification
- Referer for understanding where verification originated
- Timestamp for audit trail
- Verification result (match/not_found/error)

---

## 6. Security Considerations

### 6.1 Hash Validation

**Pattern**: `/^[a-f0-9]{64}$/i`

Validated before any database query to prevent injection attacks.

### 6.2 SQL Injection Prevention

- All queries use prepared statements
- Named placeholders (`:content_hash`) for parameters
- No dynamic SQL construction
- Parameter binding via PDO execute()

### 6.3 Constant-Time Comparison

```php
hash_equals($currentMetadataHash, $ledgerMetadataHash)
```

Prevents timing attacks that could leak information about valid hashes.

### 6.4 Duplicate Prevention

UNIQUE constraint on `content_hash` ensures:
- No duplicate registrations of same file
- Database enforces constraint at storage layer
- Reduces likelihood of registration loop attacks

### 6.5 Data Privacy

**Exposed via Public API**:
- Certificate ID
- Content hash
- Owner name (NOT email)
- Content metadata (title, artist)
- Registration date
- Verification statistics

**NOT Exposed**:
- Owner email address
- Owner account details
- File paths
- Internal record IDs (except certificate_id)
- IP addresses of previous verifiers

### 6.6 CORS Configuration

Allows third-party embedding:
- Labels can embed verification on their websites
- Distributors can verify content in their workflows
- Reduces single-platform dependency

---

## 7. Data Model

### 7.1 content_ledger Table

| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| id | BIGINT UNSIGNED | PRIMARY KEY AUTO_INCREMENT | Ledger entry identifier |
| content_hash | VARCHAR(64) | UNIQUE NOT NULL | SHA-256 of file |
| metadata_hash | VARCHAR(64) | NOT NULL | SHA-256 of metadata |
| owner_id | BIGINT UNSIGNED | FK users(id), NOT NULL | Content owner |
| upload_source | VARCHAR(64) | NOT NULL | Source system |
| source_record_id | BIGINT UNSIGNED | NULL | Reference to original |
| title | VARCHAR(255) | NULL | Track/content title |
| artist_name | VARCHAR(255) | NULL | Primary artist |
| credits | JSON | NULL | Contributor details |
| rights_split | JSON | NULL | Rights holder % |
| file_size_bytes | BIGINT UNSIGNED | NOT NULL | File size |
| mime_type | VARCHAR(128) | NOT NULL | File type |
| original_filename | VARCHAR(512) | NOT NULL | Upload filename |
| certificate_id | VARCHAR(64) | UNIQUE NOT NULL | CRT-YYYYMMDD-XXXXXXXX |
| certificate_issued_at | TIMESTAMP | DEFAULT NOW | Issue time |
| verification_count | BIGINT UNSIGNED | DEFAULT 0 | API call count |
| last_verified_at | TIMESTAMP | NULL | Last verification |
| blockchain_tx_hash | VARCHAR(128) | NULL | For 2.0.3 blockchain |
| blockchain_anchored_at | TIMESTAMP | NULL | For 2.0.3 blockchain |
| status | ENUM | DEFAULT 'active' | active/disputed/revoked/transferred |
| dispute_notes | TEXT | NULL | Dispute reason |
| created_at | TIMESTAMP | DEFAULT NOW | Registration date |
| updated_at | TIMESTAMP | ON UPDATE NOW | Last update |

### 7.2 Indexes

- `PRIMARY KEY (id)` — Fast lookup by ledger ID
- `UNIQUE KEY (content_hash)` — Fast lookup by file hash
- `UNIQUE KEY (certificate_id)` — Fast lookup by certificate
- `INDEX (owner_id)` — Find all content by owner
- `INDEX (metadata_hash)` — Detect metadata modifications
- `INDEX (upload_source, source_record_id)` — Track by source
- `INDEX (status)` — Find disputed/revoked content
- `INDEX (created_at)` — Range queries by date

---

## 8. Certificate ID Generation

**Format**: `CRT-YYYYMMDD-XXXXXXXX`

**Parts**:
- `CRT-` — Prefix for identification
- `YYYYMMDD` — Registration date (e.g., 20260206)
- `XXXXXXXX` — 8 random hex characters (4 random bytes)

**Example**: `CRT-20260206-A3F8D91E`

**Uniqueness**: UNIQUE constraint at database level ensures no duplicates.

**Benefits**:
- Human-readable (includes date)
- Reversible (can identify registration date from ID)
- URL-safe (alphanumeric only)
- Not sequential (prevents enumeration attacks)

---

## 9. Certificate HTML Output

### 9.1 Design Elements

- **Professional Header**: Seal icon (🔐), "Digital DNA Certificate" subtitle
- **Content Info**: Track title, artist name in highlighted boxes
- **Technical Details**: Certificate ID, owner name, registration date, file size
- **Hash Display**: Content hash in monospace font (full + preview)
- **QR Code**: 150×150px embedded PNG (base64), printable
- **Verification Info**: Instructions to scan QR or visit API endpoint
- **Footer**: Status badge, certificate validity statement

### 9.2 CSS Features

- **Print CSS**: Optimized A4 output (40pt margins, no background bleed)
- **Responsive**: Mobile (320px), tablet (768px), desktop (1024px+)
- **Dark Mode**: Works in light and dark system preferences
- **Watermark**: Subtle background gradient for authenticity
- **Typography**: Georgia serif for professional appearance

### 9.3 Functionality

- **Print Button**: Fixed button in top-right corner (removed in print)
- **URL Click**: Verification URL is clickable
- **QR Click**: Copies URL to clipboard when clicked
- **Auto-open**: Optional JavaScript to auto-open print dialog on load

---

## 10. Certificate Storage

**Path**: `/storage/certificates/`

**Naming**: `{certificate_id}.html` (e.g., `CRT-20260206-A3F8D91E.html`)

**File Size**: ~50KB per certificate (gzip: ~10KB)

**Permissions**: 775 (readable by web server and user)

**Retention**: Indefinite (immutable)

**Access**:
- Direct web access via `/storage/certificates/CRT-...html`
- Can be downloaded, emailed, printed
- QR code links to API endpoint, not certificate file (API is source of truth)

---

## 11. Deployment Checklist

- [ ] Database migration applied (`scripts/2026_02_06_content_ledger.sql`)
  - [ ] `content_ledger` table created
  - [ ] `content_ledger_verification_log` table created
  - [ ] `certificate_id` columns added to smr_uploads and station_content
  - [ ] All indexes created
  - [ ] Foreign keys established

- [ ] Code deployed
  - [ ] `lib/Legal/ContentLedgerService.php` in place
  - [ ] `lib/Legal/DigitalCertificateService.php` in place
  - [ ] `public/api/v1/legal/verify.php` endpoint accessible
  - [ ] All integration points in upload flows enabled

- [ ] Directory permissions
  - [ ] `/storage/certificates/` directory created
  - [ ] 775 permissions set
  - [ ] Web server write access confirmed

- [ ] Testing
  - [ ] Station upload generates certificate_id
  - [ ] Certificate HTML renders in browser
  - [ ] QR code scans to API endpoint
  - [ ] API returns correct data for valid hash/certificate_id
  - [ ] API returns 404 for non-existent entries
  - [ ] verification_count increments on repeat API calls
  - [ ] CORS headers present in API responses

---

## 12. Monitoring and Maintenance

### 12.1 Key Metrics

```sql
-- Registration rate
SELECT COUNT(*) as registrations_today
FROM content_ledger
WHERE created_at > CURDATE();

-- Verification activity
SELECT COUNT(*) as verifications_today
FROM content_ledger_verification_log
WHERE verified_at > CURDATE();

-- Ledger size
SELECT COUNT(*) as total_entries FROM content_ledger;

-- Storage size
SELECT SUM(file_size_bytes) / 1024 / 1024 / 1024 as total_gb
FROM content_ledger;
```

### 12.2 Alerts to Configure

- Ledger registration failures (check logs)
- Duplicate registration attempts (should be rare)
- Verification API errors (500 responses)
- Large spike in verification requests (possible abuse)
- Certificate generation failures

---

## 13. Future Integration: Blockchain (NGN 2.0.3)

The ledger is designed to be blockchain-agnostic. Future versions can add:

### 13.1 Ethereum/Polygon Integration

- Submit Merkle root of daily ledger snapshot
- Store blockchain_tx_hash in ledger
- Enable artists to verify ownership on-chain
- Provide immutable timestamp proof

### 13.2 NFT Certificate Minting

- Automatically mint ERC-721 token for each content
- Store certificate metadata on IPFS
- Transfer NFT to artist's wallet
- Enable secondary market trading

### 13.3 Smart Contract Functions

```solidity
function anchor(bytes32 merkleRoot) — Submit ledger root
function verify(bytes32 contentHash) — Check if hash exists
function getTimestamp(bytes32 contentHash) — Get block time
```

---

## 14. Troubleshooting

### Issue: Certificate generation fails

**Check**:
- Is `/storage/certificates/` writable? `ls -la storage/certificates/`
- Is chillerlan/php-qrcode installed? `composer show | grep qrcode`

**Solution**:
- Ensure directory permissions: `chmod 775 storage/certificates/`
- Ensure web server can write: `chown www-data:www-data storage/certificates/`

### Issue: API returns 500

**Check logs**:
```bash
tail -f storage/logs/content_verification_api.log
tail -f storage/logs/content_ledger.log
```

**Common causes**:
- Database connection failed
- Missing ContentLedgerService.php
- Prepared statement error

### Issue: Duplicate registration error

**Expected**: File cannot be registered twice (prevents duplication)

**Solution**: File is already in ledger. Retrieve existing certificate:
```sql
SELECT certificate_id FROM content_ledger WHERE content_hash = ?;
```

---

## 15. Reference Links

- **Implementation Guide**: `docs/DIGITAL_SAFETY_SEAL_IMPLEMENTATION.md`
- **Deployment Guide**: `DEPLOYMENT_NOTES.md`
- **Service Code**: `lib/Legal/ContentLedgerService.php`
- **API Endpoint**: `public/api/v1/legal/verify.php`
- **Database Schema**: `scripts/2026_02_06_content_ledger.sql`

---

**End of Chapter 42**
