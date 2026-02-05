# Phase 1: Quick Start Guide

**Goal:** Get audio streaming working end-to-end

## Step 1: Run the Database Migration

```bash
# Navigate to project root
cd /path/to/ngn2.0

# Run the migration
mysql -u root -p ngn_2025 < migrations/active/infrastructure/040_audio_storage.sql
```

**Verify it worked:**
```sql
-- Check new columns on tracks table
DESCRIBE ngn_2025.tracks;

-- Check stream_tokens table exists
SHOW TABLES LIKE 'stream_tokens';

-- Check playback_events columns
DESCRIBE ngn_2025.playback_events;
```

## Step 2: Prepare a Test Audio File

Place an MP3 file at:
```
/storage/audio/tracks/1/1/test-track.mp3
```

Get the file size and SHA-256 hash:
```bash
ls -lah /storage/audio/tracks/1/1/test-track.mp3
sha256sum /storage/audio/tracks/1/1/test-track.mp3
```

## Step 3: Update Track Database Record

```sql
UPDATE ngn_2025.tracks SET
  audio_path = '/tracks/1/1/test-track.mp3',
  audio_hash = 'YOUR_SHA256_HASH_HERE',
  audio_size_bytes = FILE_SIZE_IN_BYTES,
  audio_bitrate = 320,
  audio_format = 'mp3'
WHERE id = 1;  -- Adjust track ID as needed
```

## Step 4: Test Token Generation

```bash
# Get a token
curl -X GET "http://localhost/api/v1/tracks/1/token" \
  -H "Content-Type: application/json" | jq .
```

**Expected output:**
```json
{
  "success": true,
  "data": {
    "token": "abc123def456...",
    "url": "http://localhost/api/v1/tracks/1/stream?token=abc123def456...",
    "expires_at": "2026-02-01T06:20:00+00:00",
    "expires_in_seconds": 900,
    "track": {
      "id": 1,
      "slug": "test-track",
      "title": "Test Track",
      "duration_bytes": 4500000
    }
  }
}
```

## Step 5: Test Streaming

```bash
# Extract the token from above
TOKEN="abc123def456..."

# Stream the audio
curl -X GET "http://localhost/api/v1/tracks/1/stream?token=$TOKEN" \
  --output audio.mp3 \
  -v

# Verify it's valid
file audio.mp3
ffprobe audio.mp3  # If ffmpeg installed
```

## Step 6: Test Range Requests (Seeking)

```bash
TOKEN="abc123def456..."

# Get first 1MB only
curl -X GET "http://localhost/api/v1/tracks/1/stream?token=$TOKEN" \
  -H "Range: bytes=0-1048575" \
  --output audio_chunk.mp3 \
  -i

# Should see:
# HTTP/1.1 206 Partial Content
# Content-Range: bytes 0-1048575/4500000
```

## Step 7: Verify Token Expiry

```bash
# Generate token
TOKEN=$(curl -s -X GET "http://localhost/api/v1/tracks/1/token" | jq -r '.data.token')

# Wait 15+ minutes (or update expires_at in DB for testing)
# Then try again
curl -X GET "http://localhost/api/v1/tracks/1/stream?token=$TOKEN"

# Should get 401 Unauthorized
```

## Step 8: Verify One-Time Use

```bash
TOKEN=$(curl -s -X GET "http://localhost/api/v1/tracks/1/token" | jq -r '.data.token')

# First use - succeeds
curl -X GET "http://localhost/api/v1/tracks/1/stream?token=$TOKEN" \
  --output audio1.mp3

# Check DB - token should be marked as used
SELECT * FROM ngn_2025.stream_tokens WHERE token = 'TOKEN_VALUE';

# Second use with same token - should fail with 401
curl -X GET "http://localhost/api/v1/tracks/1/stream?token=$TOKEN"
```

## Testing Checklist

- [ ] Migration runs without errors
- [ ] New tables/columns created
- [ ] Token generation returns valid token
- [ ] Token has 15-minute expiry
- [ ] Token URL is properly formatted
- [ ] Audio streams successfully (200 OK)
- [ ] Range requests work (206 Partial Content)
- [ ] Invalid token rejected (401)
- [ ] Expired token rejected (401)
- [ ] One-time use enforced (2nd use fails)
- [ ] Disputed tracks blocked (403)
- [ ] Direct file access blocked (via .htaccess)
- [ ] Playback event logged in database
- [ ] Token marked as used in database

## Troubleshooting

### "Class not found: StreamingService"
- Check `/lib/Services/Media/` directory exists
- Verify StreamingService.php is in correct location
- Clear any autoloader cache

### "Invalid or expired token" (immediate)
- Check database migration ran
- Verify stream_tokens table exists
- Check token wasn't already used

### Audio file not streaming
- Verify file exists at `/storage/audio/tracks/1/1/test-track.mp3`
- Check file permissions (644)
- Verify audio_path matches in database
- Check .htaccess is in place

### Range requests not working
- Verify `Accept-Ranges: bytes` header is sent
- Check Range header format: `Range: bytes=0-1048575`
- Some clients may not support Range

### Token not expiring
- Verify expires_at timestamp is < NOW()
- Check server time is correct
- Tokens expire exactly 15 minutes after creation

### Direct file access returns "Deny from all"
- This is expected behavior ✅
- Verify .htaccess is in `/storage/audio/`
- Only access files via `/api/v1/tracks/{id}/stream?token=`

## Quick Debugging

```sql
-- Check all tokens
SELECT * FROM ngn_2025.stream_tokens ORDER BY created_at DESC LIMIT 10;

-- Check playback events
SELECT * FROM ngn_2025.playback_events ORDER BY started_at DESC LIMIT 10;

-- Check if track has audio configured
SELECT id, title, audio_path, audio_hash, audio_size_bytes
FROM ngn_2025.tracks WHERE id = 1;

-- Check rights ledger status
SELECT * FROM ngn_2025.cdm_rights_ledger WHERE track_id = 1;
```

## Next Steps

Once Phase 1 is verified working:

1. **Phase 2:** Build modern player with queue management
2. **Phase 3:** Implement 30-second qualified listen tracking & royalties
3. **Phase 4:** Add station radio streaming
4. **Phase 5:** Service Worker & background audio

---

**Estimated testing time:** 30 minutes
**Need help?** Check error logs in `/storage/logs/`
