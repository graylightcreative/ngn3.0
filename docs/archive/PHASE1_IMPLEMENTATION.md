# Phase 1: Audio Infrastructure & Streaming API - Implementation Complete

**Status:** ✅ Ready for Testing
**Date:** 2026-01-31
**Scope:** Secure audio storage, streaming infrastructure, and API integration

## Deliverables

### 1. Audio Storage Infrastructure ✅

**Directory Structure Created:**
```
/storage/audio/
├── tracks/          # Catalog tracks ({artist_id}/{release_id}/{track_id}_{slug}.mp3)
├── byos/            # Station BYOS content (Bring Your Own Songs)
├── temp/            # Upload staging area
└── .htaccess        # Deny direct HTTP access to audio files
```

**Permissions:**
- Directory: `755` (rwxr-xr-x) - readable but not directly executable
- Files: `644` (rw-r--r--) - readable by all, writable by owner only
- Direct file access blocked by `.htaccess`: `Deny from all`

### 2. Database Schema Enhancements ✅

**File:** `/migrations/active/infrastructure/040_audio_storage.sql`

#### Tracks Table Extensions
```sql
ALTER TABLE tracks ADD COLUMN audio_path VARCHAR(512);
ALTER TABLE tracks ADD COLUMN audio_hash CHAR(64);
ALTER TABLE tracks ADD COLUMN audio_size_bytes BIGINT UNSIGNED;
ALTER TABLE tracks ADD COLUMN audio_bitrate INT UNSIGNED;
ALTER TABLE tracks ADD COLUMN audio_format VARCHAR(20) DEFAULT 'mp3';
```

**Purpose:** Store audio file metadata for streaming service

#### Stream Tokens Table (NEW)
```sql
CREATE TABLE stream_tokens (
  id BIGINT UNSIGNED PRIMARY KEY,
  track_id BIGINT UNSIGNED (FK to tracks),
  user_id BIGINT UNSIGNED (nullable, FK to users),
  token VARCHAR(256) UNIQUE,
  ip_address VARCHAR(45),
  created_at DATETIME,
  expires_at DATETIME,
  used_at DATETIME,
  is_used BOOLEAN DEFAULT FALSE
);
```

**Purpose:** Secure streaming tokens with 15-minute expiry and one-time use tracking

**Features:**
- SHA-256 signed tokens
- Optional IP binding for added security
- One-time use enforcement (first request marks as used)
- Automatic expiry (15 minutes)
- Indexes for efficient token lookups

#### Playback Events Table Enhancements
```sql
ALTER TABLE playback_events ADD COLUMN session_id VARCHAR(64);
ALTER TABLE playback_events ADD COLUMN is_qualified_listen BOOLEAN DEFAULT FALSE;
ALTER TABLE playback_events ADD COLUMN territory CHAR(2) DEFAULT 'XX';
ALTER TABLE playback_events ADD COLUMN source_type ENUM('on_demand','station_stream','playlist','radio','other');
ALTER TABLE playback_events ADD COLUMN source_id BIGINT UNSIGNED;
ALTER TABLE playback_events ADD COLUMN ip_address VARCHAR(45);
ALTER TABLE playback_events ADD COLUMN user_agent VARCHAR(512);
ALTER TABLE playback_events ADD COLUMN royalty_processed BOOLEAN DEFAULT FALSE;
ALTER TABLE playback_events ADD COLUMN royalty_processed_at DATETIME;
```

**Purpose:** Enhanced analytics and royalty tracking

### 3. StreamingService Class ✅

**File:** `/lib/Services/Media/StreamingService.php`

**Public Methods:**

#### generateStreamToken($trackId, $userId, $ipAddress)
Generates a secure, time-limited streaming token

**Parameters:**
- `int $trackId` - Track to stream
- `?int $userId` - User ID (nullable for guests)
- `?string $ipAddress` - Client IP for optional binding

**Returns:**
```json
{
  "token": "sha256_hash...",
  "url": "https://nextgennoise.com/api/v1/tracks/{id}/stream?token={token}",
  "expires_at": "2026-02-01T06:20:00+00:00",
  "expires_in_seconds": 900,
  "track": {
    "id": 123,
    "slug": "track-slug",
    "title": "Track Title",
    "duration_bytes": 4500000
  }
}
```

**Security Checks:**
- Verifies track exists and has audio file
- Checks `cdm_rights_ledger.is_royalty_eligible` (blocks disputed tracks)
- Generates SHA-256 token with random data
- Stores token in database with creation timestamp

**Exceptions:**
- `RuntimeException` if track not found
- `RuntimeException` if track has no audio file
- `RuntimeException` if rights are disputed

#### streamTrack($trackId, $token, $ipAddress, $rangeStart, $rangeEnd)
Validates token and streams audio file with Range support

**Parameters:**
- `int $trackId` - Track to stream
- `string $token` - Signed token from generateStreamToken()
- `string $ipAddress` - Client IP address
- `?int $rangeStart` - Byte offset for Range requests (seeking)
- `?int $rangeEnd` - Byte offset end for Range requests

**Features:**
- Validates token signature
- Checks token expiry (throws if expired)
- Enforces one-time use (marks token as used)
- Optional IP binding verification
- HTTP 206 Partial Content support for seeking
- Proper Content-Type headers based on audio format
- Range request support (for seekable playback)

**Response Headers:**
```
Content-Type: audio/mpeg (or audio/aac, audio/flac, etc.)
Accept-Ranges: bytes
Cache-Control: no-cache, no-store, must-revalidate
Content-Length: {bytes}
Content-Disposition: inline; filename="audio.mp3"
```

**For Range Requests:**
```
HTTP/1.1 206 Partial Content
Content-Range: bytes {start}-{end}/{total}
Content-Length: {end-start+1}
```

#### deleteExpiredTokens()
Cleanup method for expired tokens (daily via cron)

**Returns:** Number of tokens deleted

**Usage:** Daily at 3 AM
```bash
0 3 * * * php -r "require 'lib/bootstrap.php'; (new StreamingService(new Config()))->deleteExpiredTokens();"
```

### 4. API Endpoints ✅

**File:** `/public/api/v1/index.php`

#### Endpoint 1: Generate Streaming Token
```
GET /api/v1/tracks/{id}/token
```

**Request:**
```bash
curl -X GET "https://nextgennoise.com/api/v1/tracks/123/token" \
  -H "Authorization: Bearer {optional_jwt_token}"
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "token": "sha256_hash_token_value",
    "url": "https://nextgennoise.com/api/v1/tracks/123/stream?token=sha256_hash_token_value",
    "expires_at": "2026-02-01T06:20:00+00:00",
    "expires_in_seconds": 900,
    "track": {
      "id": 123,
      "slug": "track-slug",
      "title": "Track Title",
      "duration_bytes": 4500000
    }
  }
}
```

**Error Responses:**
- `400 Bad Request` - Missing track ID or track has no audio file
- `403 Forbidden` - Track rights disputed
- `404 Not Found` - Track not found
- `500 Internal Server Error` - Service error

#### Endpoint 2: Stream Audio with Token
```
GET /api/v1/tracks/{id}/stream?token={token}
```

**Request:**
```bash
# Simple playback
curl -X GET "https://nextgennoise.com/api/v1/tracks/123/stream?token=sha256_hash" \
  -o audio.mp3

# With Range support for seeking (e.g., 1MB offset)
curl -X GET "https://nextgennoise.com/api/v1/tracks/123/stream?token=sha256_hash" \
  -H "Range: bytes=1048576-" \
  -o audio.mp3

# Partial content (bytes 0-5MB)
curl -X GET "https://nextgennoise.com/api/v1/tracks/123/stream?token=sha256_hash" \
  -H "Range: bytes=0-5242879" \
  -o audio.mp3
```

**Response (200 OK):**
```
Binary audio stream with proper Content-Type header
```

**Response (206 Partial Content):**
```
HTTP/1.1 206 Partial Content
Content-Range: bytes 1048576-5242879/10485760
Content-Length: 4194304
Content-Type: audio/mpeg

[Binary audio chunk]
```

**Error Responses:**
- `400 Bad Request` - Missing token or track ID
- `401 Unauthorized` - Invalid or expired token
- `403 Forbidden` - IP mismatch (if bound)
- `404 Not Found` - Track or audio file not found
- `500 Internal Server Error` - Streaming error

### 5. Integration with Existing Systems ✅

**API Integration:**
- StreamingService instantiated in `/public/api/v1/index.php`
- Uses existing `$config` for configuration
- Integrates with existing exception handling
- Follows existing JsonResponse pattern

**Database Integration:**
- Uses existing PDO connection from `ConnectionFactory`
- Migration follows existing pattern in `/migrations/active/infrastructure/`
- Tables use existing database naming convention (`ngn_2025`)
- Foreign keys respect existing constraints

**Security Integration:**
- Optional JWT authentication support
- IP-based security for token binding
- Rights ledger integration (respects disputed tracks)
- One-time use tokens prevent sharing

## Testing & Validation

### Prerequisites
1. Run migration: `040_audio_storage.sql`
2. Upload test MP3 file to `/storage/audio/tracks/1/1/test_track.mp3`
3. Update track record in database:
```sql
UPDATE ngn_2025.tracks SET
  audio_path = '/tracks/1/1/test_track.mp3',
  audio_hash = 'sha256hash...',
  audio_size_bytes = 4500000,
  audio_bitrate = 256,
  audio_format = 'mp3'
WHERE id = 123;
```

### Test Cases

#### Test 1: Generate Token
```bash
curl -X GET "http://localhost/api/v1/tracks/123/token"
```
**Expected:** 200 OK with token, URL, and expiry

#### Test 2: Stream with Valid Token
```bash
RESPONSE=$(curl -X GET "http://localhost/api/v1/tracks/123/token" -s)
TOKEN=$(echo $RESPONSE | jq -r '.data.token')
curl -X GET "http://localhost/api/v1/tracks/123/stream?token=$TOKEN" -o audio.mp3
file audio.mp3
```
**Expected:** Valid MP3 file

#### Test 3: Stream with Range Request
```bash
TOKEN=$(curl -X GET "http://localhost/api/v1/tracks/123/token" -s | jq -r '.data.token')
curl -X GET "http://localhost/api/v1/tracks/123/stream?token=$TOKEN" \
  -H "Range: bytes=0-1048576" \
  -i
```
**Expected:** HTTP 206 with Content-Range header

#### Test 4: Token Expiry (after 15 minutes)
```bash
# Generate token
TOKEN=$(curl -X GET "http://localhost/api/v1/tracks/123/token" -s | jq -r '.data.token')

# Wait 15+ minutes, then attempt stream
curl -X GET "http://localhost/api/v1/tracks/123/stream?token=$TOKEN"
```
**Expected:** HTTP 401 Unauthorized

#### Test 5: Disputed Track Blocking
```bash
# Update track's rights status to disputed
UPDATE ngn_2025.cdm_rights_ledger SET is_royalty_eligible = FALSE WHERE track_id = 123;

# Attempt token generation
curl -X GET "http://localhost/api/v1/tracks/123/token"
```
**Expected:** HTTP 403 Forbidden

#### Test 6: Invalid Token
```bash
curl -X GET "http://localhost/api/v1/tracks/123/stream?token=invalid_token_123456"
```
**Expected:** HTTP 401 Unauthorized

#### Test 7: One-Time Use
```bash
TOKEN=$(curl -X GET "http://localhost/api/v1/tracks/123/token" -s | jq -r '.data.token')

# First use - should succeed
curl -X GET "http://localhost/api/v1/tracks/123/stream?token=$TOKEN" -o audio1.mp3

# Second use - should fail
curl -X GET "http://localhost/api/v1/tracks/123/stream?token=$TOKEN" -o audio2.mp3
```
**Expected:** First succeeds (200), second fails (401)

## Deployment Checklist

- [x] Create audio storage directories
- [x] Set directory permissions (755)
- [x] Create .htaccess to block direct file access
- [x] Create database migration (040_audio_storage.sql)
- [x] Implement StreamingService class
- [x] Add API endpoints to index.php
- [x] Update .gitignore for audio files
- [x] Verify PHP syntax
- [ ] Run database migration
- [ ] Test token generation
- [ ] Test audio streaming
- [ ] Test Range requests (seeking)
- [ ] Test token expiry
- [ ] Load test: 100 concurrent streams
- [ ] Monitor error logs during testing

## Performance Metrics

**Expected Performance:**
- Token generation: < 100ms
- Token validation: < 50ms
- Stream startup: < 500ms (first byte)
- Concurrent streams supported: 1000+
- Token cleanup: Daily, < 5 seconds

**Database Queries:**
- Token generation: 2 queries (verify track + store token)
- Token validation: 1 query (fetch token)
- Stream: 1 query (verify and mark as used)
- Cleanup: 1 query (delete expired)

## Security Considerations

### Token Security
✅ **SHA-256 Signing** - Cryptographically secure
✅ **15-Minute Expiry** - Time-limited tokens
✅ **One-Time Use** - Prevents token sharing/replay
✅ **Optional IP Binding** - Additional layer for stationary clients
✅ **Unique Tokens** - Database UNIQUE constraint

### File Security
✅ **Direct Access Blocked** - .htaccess denies HTTP access
✅ **Path Traversal Prevention** - No path parameters in URL
✅ **Rights Verification** - Checks rights_ledger before token generation
✅ **Signed URLs** - No direct file paths exposed

### Network Security
✅ **HTTPS Enforcement** - Tokens in URL (should use HTTPS only)
✅ **IP Logging** - Tracks source IP for audit
✅ **User Agent Logging** - Detects clients, aids forensics
✅ **Session Tracking** - Links plays to user sessions

## Future Enhancements (Phase 2+)

### Phase 2: Modern Player
- ES6 NGNPlayer class with queue management
- Media Session API integration
- State persistence (localStorage)
- 30-second qualified listen tracking

### Phase 3: Royalty Triggers
- Process qualified listens
- Calculate royalty amounts
- Trigger payments via RoyaltyLedgerService
- Cron job for batch processing

### Phase 4: Station Radio
- Station playlist streaming
- BYOS + catalog track mixing
- Spin logging and analytics

### Phase 5: Background Audio
- Service Worker keep-alive
- Offline queue persistence (IndexedDB)
- Lock screen controls (iOS/Android)

### Post-Launch: Optimization
- CDN integration (CloudFront/Cloudflare)
- Transcoding pipeline (multiple bitrates)
- Offline playback caching
- Real-time analytics dashboard

## Notes

- Token generation uses existing PDO connection from Config
- Service respects existing database constraints and naming conventions
- All errors are logged via LoggerFactory
- API follows existing response pattern (JsonResponse)
- Range request support enables seeking without re-downloading
- One-time use tracking prevents token sharing between users
- IP binding optional - useful for WiFi networks but not mobile

---

**Phase 1 Status:** ✅ COMPLETE
**Next Phase:** Phase 2 - Modern Player with Queue Management
**Implementation Timeline:** 8 weeks total (Phase 1 = Week 1-2)
