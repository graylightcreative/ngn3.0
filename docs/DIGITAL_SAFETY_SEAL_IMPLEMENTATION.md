# NGN 2.0.2: Digital Safety Seal Implementation Guide

## 📋 Overview

This document describes the Digital Safety Seal system implemented in NGN 2.0.2. The system creates an immutable ledger of all uploaded content, linking file hashes to verified owners with cryptographic certificates.

**Status**: ✅ Implementation Complete (Code committed to main)
**Target Deployment**: beta.nextgennoise.com
**Database**: ngn_2025 (migration script: `scripts/2026_02_06_content_ledger.sql`)

---

## 🏗️ Architecture

### Core Components

```
┌─────────────────────────────────────────────────────────────┐
│                     Content Ledger System                    │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  Upload Flows                  Services              Storage   │
│  ──────────                    ────────              ────────  │
│  • Station Content       ContentLedgerService       Ledger DB  │
│  • SMR Ingestion    ──►  (registerContent)      ──►  Entries  │
│  • Assistant Upload       (lookupByHash)           & Certs    │
│  • Upload Service         (verifyContent)                     │
│                                                                │
│                        DigitalCertificateService               │
│                        (generateCertificateHtml)              │
│                                ▼                              │
│                          QR Code Generator                    │
│                          (verification URL)                   │
│                                                                │
│  Public Verification API                                      │
│  /api/v1/legal/verify?certificate_id=... or ?hash=...       │
│  → Returns owner + content info                              │
│  → Logs verification to audit table                          │
│  → No authentication required (CORS enabled)                 │
│                                                                │
└─────────────────────────────────────────────────────────────┘
```

### Data Flow

```
User Upload
    ↓
[Station|SMR|Assistant] Upload Handler
    ↓
Calculate SHA-256 hash of file
    ↓
Store file to disk
    ↓
ContentLedgerService.registerContent()
    ├─ Generate metadata hash (canonical JSON)
    ├─ Generate certificate ID (CRT-YYYYMMDD-XXXXXXXX)
    ├─ INSERT into content_ledger table
    └─ Return ledger record
    ↓
DigitalCertificateService.generateCertificateHtml()
    ├─ Generate QR code (verification URL)
    ├─ Build professional HTML template
    └─ Save to /storage/certificates/
    ↓
Update source table with certificate_id
    ↓
Return certificate info to user
    ↓
User can print or download certificate
    ↓
Scan QR or visit verification URL
    ↓
Public API verifies and returns ownership details
```

---

## 📦 Files Implemented

### New Files

#### 1. `lib/Legal/ContentLedgerService.php` (454 lines)
Core service for managing the content ownership ledger.

**Key Methods**:
- `registerContent()` - Register new content with ownership proof
- `lookupByHash()` - Find ledger entry by SHA-256 hash
- `lookupByCertificateId()` - Find ledger entry by certificate ID
- `verifyContent()` - Verify metadata integrity
- `generateMetadataHash()` - Create canonical JSON hash
- `incrementVerificationCount()` - Track API verification calls
- `isDuplicate()` - Prevent duplicate registrations
- `getUserLedgerHistory()` - Get user's upload history

**Pattern**: Constructor injection of PDO + Config + Logger
**Dependencies**: PDO, Config, Monolog\Logger
**Exception Handling**: Custom validation exceptions + structured logging

#### 2. `lib/Legal/DigitalCertificateService.php` (498 lines)
Generates professional HTML certificates with embedded QR codes.

**Key Methods**:
- `generateCertificateHtml()` - Create printable certificate with QR code
- `generateQrCodeBase64()` - Generate QR code as base64 PNG
- `getVerificationUrl()` - Build verification API URL

**Features**:
- Professional design with watermark and seal
- Print CSS for optimal physical output
- QR code links to verification endpoint
- Responsive layout (desktop, tablet, mobile, print)
- Embedded as base64 PNG (no external dependencies)

**Dependencies**: chillerlan/php-qrcode (already in composer.json)

#### 3. `public/api/v1/legal/verify.php` (180 lines)
Public REST API for third-party verification.

**Endpoints**:
```
GET /api/v1/legal/verify?hash=<sha256>
GET /api/v1/legal/verify?certificate_id=CRT-20260206-A3F8D91E
```

**Response Format**:
```json
{
  "verified": true,
  "certificate_id": "CRT-20260206-A3F8D91E",
  "content_hash": "abc123...",
  "owner": { "user_id": 42, "name": "Artist Name" },
  "content": {
    "title": "Track Title",
    "artist_name": "Artist Name",
    "file_size_bytes": 5242880,
    "mime_type": "audio/mpeg"
  },
  "registered_at": "2026-02-06T12:00:00+00:00",
  "verification_count": 15,
  "last_verified_at": "2026-02-06T14:30:00+00:00",
  "status": "active",
  "message": "Content verified - Registered in NGN ledger"
}
```

**Features**:
- No authentication required (public endpoint)
- CORS headers for third-party embedding
- Increments verification counter on each call
- Logs all verifications to audit table (IP, user agent, referer)
- Constant-time hash comparison (hash_equals())
- 5-minute cache headers

#### 4. `scripts/2026_02_06_content_ledger.sql` (180 lines)
Database migration script with complete schema.

**Tables**:
- `content_ledger` - Core ledger entries with certificate IDs
- `content_ledger_verification_log` - Audit trail of all verifications

**Schema Updates**:
- `smr_uploads` - Add certificate_id column
- `station_content` - Add certificate_id column

---

### Modified Files

#### 1. `lib/Stations/StationContentService.php` (→ uploadContent method)
**Integration Point**: Line 107, after `$this->storeContent()`

**Changes**:
- Get station owner ID from database
- Instantiate ContentLedgerService
- Call registerContent() with upload metadata
- Generate certificate HTML
- Update station_content.certificate_id
- Return certificate_id and certificate_url in response
- Wrapped in try-catch (failures logged but don't block upload)

#### 2. `public/admin/smr-ingestion.php`
**Integration Point**: Line 79, after `$uploadId = $pdo->lastInsertId()`

**Changes**:
- Instantiate ContentLedgerService
- Register CSV file in ledger
- Update smr_uploads.certificate_id
- Non-blocking (errors logged only)

#### 3. `public/admin/assistant-upload.php`
**Integration Point**: Line 116, after `$uploadedFileId = $pdo->lastInsertId()`

**Changes**:
- Instantiate ContentLedgerService
- Register assistant upload in ledger
- Update smr_uploads.certificate_id
- Non-blocking (errors logged only)

#### 4. `lib/Smr/UploadService.php`
**Integration Point**: Line 92, after file move

**Changes**:
- Calculate SHA-256 file hash (previously missing)
- Instantiate ContentLedgerService
- Register in ledger
- Add hash and certificate_id to record
- Non-blocking (errors logged only)

---

## 🗄️ Database Schema

### Table: `content_ledger`

```sql
CREATE TABLE `ngn_2025`.`content_ledger` (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Content identification
    content_hash VARCHAR(64) NOT NULL UNIQUE,  -- SHA-256
    metadata_hash VARCHAR(64) NOT NULL,        -- SHA-256 of metadata

    -- Ownership
    owner_id BIGINT UNSIGNED NOT NULL,
    upload_source VARCHAR(64) NOT NULL,        -- source system identifier
    source_record_id BIGINT UNSIGNED NULL,     -- reference to original record

    -- Metadata
    title VARCHAR(255),
    artist_name VARCHAR(255),
    credits JSON,
    rights_split JSON,

    -- File info
    file_size_bytes BIGINT UNSIGNED NOT NULL,
    mime_type VARCHAR(128) NOT NULL,
    original_filename VARCHAR(512) NOT NULL,

    -- Certificate & verification
    certificate_id VARCHAR(64) NOT NULL UNIQUE,  -- CRT-YYYYMMDD-XXXXXXXX
    certificate_issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verification_count BIGINT UNSIGNED DEFAULT 0,
    last_verified_at TIMESTAMP NULL,

    -- Future blockchain
    blockchain_tx_hash VARCHAR(128) NULL,
    blockchain_anchored_at TIMESTAMP NULL,

    -- Status
    status ENUM('active', 'disputed', 'revoked', 'transferred') DEFAULT 'active',
    dispute_notes TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_owner_id (owner_id),
    INDEX idx_metadata_hash (metadata_hash),
    INDEX idx_upload_source (upload_source, source_record_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);
```

### Table: `content_ledger_verification_log`

```sql
CREATE TABLE `ngn_2025`.`content_ledger_verification_log` (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    ledger_id BIGINT UNSIGNED NOT NULL,
    verified_by VARCHAR(128) NULL,
    verification_type ENUM('public_api', 'certificate_scan', 'third_party', 'internal', 'admin'),
    verification_result ENUM('match', 'mismatch', 'not_found', 'error'),

    request_ip VARCHAR(45) NULL,
    request_user_agent VARCHAR(512) NULL,
    request_referer VARCHAR(512) NULL,
    request_metadata JSON NULL,

    verified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (ledger_id) REFERENCES content_ledger(id) ON DELETE CASCADE,
    INDEX idx_ledger_id (ledger_id),
    INDEX idx_verified_at (verified_at),
    INDEX idx_verification_type (verification_type)
);
```

### Column Additions

```sql
-- smr_uploads.certificate_id
ALTER TABLE `ngn_2025`.`smr_uploads`
ADD COLUMN certificate_id VARCHAR(64) NULL,
ADD INDEX idx_certificate_id (certificate_id);

-- station_content.certificate_id
ALTER TABLE `ngn_2025`.`station_content`
ADD COLUMN certificate_id VARCHAR(64) NULL,
ADD INDEX idx_certificate_id (certificate_id);
```

---

## 🚀 Deployment Instructions

### Step 1: Apply Database Migration

```bash
# From remote server
mysql -h server.starrship1.com -u ngn_2025 -p ngn_2025 < scripts/2026_02_06_content_ledger.sql
```

### Step 2: Deploy Code

```bash
# Push to GitHub
git push origin main

# Or manually deploy to beta.nextgennoise.com
scp -r lib/Legal/ root@209.59.156.82:/www/wwwroot/beta.nextgennoise.com/lib/
scp public/api/v1/legal/verify.php root@209.59.156.82:/www/wwwroot/beta.nextgennoise.com/public/api/v1/legal/
```

### Step 3: Verify Permissions

```bash
# Ensure certificate storage directory is writable
chmod 775 /www/wwwroot/beta.nextgennoise.com/storage/certificates/
chown -R www-data:www-data /www/wwwroot/beta.nextgennoise.com/storage/certificates/
```

### Step 4: Test Deployment

```bash
# Test verification API
curl "https://beta.nextgennoise.com/api/v1/legal/verify?certificate_id=CRT-20260206-TEST"

# Upload test file via station UI
# Verify certificate_id appears in response
# Test certificate download and QR code scan
```

---

## 🔒 Security Considerations

### Hash Validation
- ✅ Regex validation: `/^[a-f0-9]{64}$/i` before database queries
- ✅ UNIQUE constraint on content_hash prevents duplicates
- ✅ Constant-time comparison with hash_equals()

### SQL Injection Prevention
- ✅ All queries use prepared statements
- ✅ Named placeholders (`:content_hash`) for all parameters
- ✅ No dynamic SQL construction

### API Security
- ✅ No sensitive data exposed (email not returned)
- ✅ CORS headers configured for third-party access
- ✅ GET-only endpoint (no state modifications)
- ✅ 5-minute cache headers to reduce database load

### Logging & Audit
- ✅ All verification attempts logged with IP and user agent
- ✅ Structured logging with context arrays
- ✅ Separate logger for content verification API

### Future Enhancements
- [ ] Rate limiting on verification endpoint (100 req/hour per IP)
- [ ] HMAC signature on certificate HTML for offline verification
- [ ] Blockchain anchoring for immutable proof (NGN 2.0.3)

---

## 📝 Integration Examples

### Station Content Upload Response

```json
{
  "success": true,
  "id": 42,
  "message": "File uploaded successfully. Pending admin review.",
  "certificate_id": "CRT-20260206-A3F8D91E",
  "certificate_url": "/storage/certificates/CRT-20260206-A3F8D91E.html"
}
```

### Certificate HTML Response

Professional printable certificate with:
- Seal icon and watermark
- Content title, artist, owner
- Certificate ID and registration date
- File hash preview and complete hash
- QR code (150×150px)
- Print button (mobile-friendly)
- Print CSS for optimal output

### Verification API Response

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
  "registered_at": "2026-02-06T12:00:00+00:00",
  "verification_count": 15,
  "last_verified_at": "2026-02-06T14:30:00+00:00",
  "status": "active",
  "message": "Content verified - Registered in NGN ledger"
}
```

---

## 🧪 Testing Checklist

### Unit Tests (Manual)
- [ ] ContentLedgerService.registerContent() with valid data
- [ ] ContentLedgerService.isDuplicate() returns true for existing hash
- [ ] ContentLedgerService.lookupByHash() finds entries
- [ ] ContentLedgerService.generateMetadataHash() creates consistent hash
- [ ] DigitalCertificateService generates valid HTML
- [ ] QR code base64 decodes to PNG

### Integration Tests
- [ ] Station upload → certificate_id in response
- [ ] SMR CSV upload → ledger entry created
- [ ] Assistant upload → smr_uploads.certificate_id updated
- [ ] Upload service → file_hash calculated

### API Tests
- [ ] GET /api/v1/legal/verify?certificate_id=... returns 200
- [ ] GET /api/v1/legal/verify?hash=... returns 200
- [ ] GET with invalid hash returns 404
- [ ] GET with malformed hash returns 400
- [ ] verification_count increments on each call
- [ ] CORS headers present in response
- [ ] verification_log entries created

### User Experience Tests
- [ ] Certificate HTML displays correctly in browser
- [ ] QR code scans to verification URL
- [ ] Print button opens print dialog
- [ ] Certificate prints cleanly on A4 paper
- [ ] Responsive on mobile (320px), tablet (768px), desktop

---

## 📊 Performance Expectations

### Database Queries
- `lookupByHash()`: ~1ms (indexed on content_hash)
- `lookupByCertificateId()`: ~1ms (indexed on certificate_id)
- `registerContent()`: ~10ms (INSERT + indexes)
- `incrementVerificationCount()`: ~2ms (UPDATE + INSERT to log)

### API Response Times
- Verification lookup: <50ms (cached for 5 minutes)
- Certificate generation: ~200ms (QR code generation cost)

### Storage
- Certificate HTML: ~50KB each (gzip ~10KB)
- Database row: ~1KB per ledger entry
- Verification log: ~500 bytes per entry

---

## 🔄 Future Enhancements (NGN 2.0.3+)

### Blockchain Integration
- Merkle root submission to Ethereum/Polygon
- Store blockchain_tx_hash in ledger
- Immutable timestamp proof

### NFT Certificates
- Mint ERC-721 tokens with content hash
- Store certificate metadata on IPFS
- Transfer to artist's wallet

### Rights Management
- Multi-party signing for collaborative works
- Percentage validation (must sum to 100%)
- Royalty split calculations

### Dispute Resolution
- Admin interface for reviewing disputed content
- Evidence submission system
- Multi-signature verification for transfers

### Cross-Platform Integration
- Mobile app with QR scanner
- Browser extension for auto-verification
- Webhook API for label/distributor integration

---

## 📞 Support & Questions

For questions or issues with the Digital Safety Seal system:
1. Check logs: `storage/logs/content_ledger.log`
2. Verify database migration: `SHOW TABLES LIKE 'content_ledger%'`
3. Test API endpoint: `GET /api/v1/legal/verify?hash=test`

---

## 📄 Files Reference

| File | Lines | Purpose |
|------|-------|---------|
| lib/Legal/ContentLedgerService.php | 454 | Core ledger service |
| lib/Legal/DigitalCertificateService.php | 498 | Certificate HTML generator |
| public/api/v1/legal/verify.php | 180 | Public verification API |
| scripts/2026_02_06_content_ledger.sql | 180 | Database schema |
| lib/Stations/StationContentService.php | +80 | Integration |
| public/admin/smr-ingestion.php | +40 | Integration |
| public/admin/assistant-upload.php | +40 | Integration |
| lib/Smr/UploadService.php | +80 | Integration |

**Total New Code**: ~1,493 lines
**Total Modified**: 240 lines across 4 files

---

**Implementation Date**: February 6, 2026
**Version**: NGN 2.0.2
**Status**: ✅ Complete & Committed
