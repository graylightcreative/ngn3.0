# "The Fortress" - Quick Usage Guide

## Overview
The Fortress is the public-facing certificate verification page that allows anyone to verify the ownership and integrity of content registered in the NGN Digital Safety Seal.

**URL:** `https://yourdomain.com/legal/certificate.php`

---

## How to Access a Certificate

### Option 1: By Certificate ID (Recommended)
```
/legal/certificate.php?id=CRT-20260206-A3F8D91E
```
- Certificate ID comes from the ledger entry (`content_ledger.certificate_id`)
- Format: `CRT-YYYYMMDD-XXXXXXXX` (8 random hex characters)
- Most user-friendly for sharing

### Option 2: By Content Hash
```
/legal/certificate.php?hash=abc123def456...
```
- Hash is the SHA-256 of the uploaded file
- 64 hexadecimal characters
- Useful for technical verification

---

## What You'll See

### 1. Seal Animation (0-1.5 seconds)
- Spinning lock icon (🔐)
- "Verifying..." text
- Professional security theater

### 2. Verification Status Badge
- **Green (✓ VERIFIED)** - Content is registered and verified
- **Red (✗ DISPUTED)** - Content has been disputed
- **Orange (⚠ REVOKED)** - Certificate has been revoked

### 3. Digital DNA Section
- **SHA-256 Hash** displayed in monospace font
- Color-coded by status (green/red/orange)
- User can select and copy the hash

### 4. Artist Information
- **Title** of the content
- **Artist Name** and verified status badge
- **NGN Score** (numerical value)
- **Artist Ranking** (e.g., #127 Artist)

### 5. Metadata Display
- **File Size** (formatted as B, KB, MB, or GB)
- **File Type** (MIME type)
- **Certificate ID** (unique identifier)
- **Registration Date** (ISO 8601 UTC timestamp)

### 6. Verification Code (QR Code)
- **Scannable QR code** that links back to this certificate
- Can be scanned from Instagram stories, printed materials, etc.
- Self-referential verification

### 7. Social Sharing
- **Copy URL button** - Copies certificate link to clipboard
- **Share on Twitter/X** - Pre-filled message with verification URL
- **Share on Facebook** - Opens share dialog with URL

### 8. NGN 90/10 Mandate Section
- Explains the 90/10 artist revenue split
- "Artist retains 90%, 10% supports operations"
- Link to learn more about the mandate

### 9. Action Buttons
- **Copy URL** - Copies link to clipboard
- **Print Certificate** - Opens print dialog (optimized for PDF)

---

## User Actions

### Copy URL
1. Click "Copy Certificate URL" button
2. Link is copied to your clipboard
3. Paste anywhere you want to share the verification

### Share on Social Media
1. Click "Share on Twitter" or "Share on Facebook"
2. Pre-written message appears in social media
3. Edit if desired and post
4. Followers can click link to verify certificate

### Print Certificate
1. Click "Print Certificate" button
2. Print dialog opens
3. Select your printer (or "Save as PDF")
4. Certificate prints with high contrast, optimized layout
5. QR code prints at 4cm x 4cm (can be scanned with phone)

### Scan QR Code
1. Open camera or QR scanner on phone
2. Point at QR code on certificate
3. Link opens this certificate page in browser
4. Anyone can verify the content ownership

---

## Mobile Experience

### iPhone/iOS
- Full-screen responsive layout
- Instagram Story dimensions (9:16) optimized
- Share buttons work with iOS share sheet
- Print to Photos or PDF works seamlessly

### Android
- Full-screen responsive layout
- Share buttons integrate with Android sharing
- Print to PDF via system printer dialog

### Tablet
- Optimized for tablet sizes (768px+)
- Metadata grid displays in columns
- Larger text for easier reading

---

## Verification Information

### What Gets Verified?
- ✓ Content ownership (who uploaded it)
- ✓ Content integrity (file hash matches)
- ✓ Artist verification status (human or bot)
- ✓ Registration timestamp (when it was uploaded)
- ✓ NGN Score (artist credibility)

### What's NOT Shown?
- ✗ Artist email address (privacy protected)
- ✗ Uploaded file itself (only metadata)
- ✗ Artist personal information
- ✗ Payment or financial data

### Verification Count
- Displayed on the certificate
- Increments every time someone loads the page
- Shows popularity/interest in the content
- Updated in real-time

---

## Technical Details for Developers

### Database Records
Each certificate pulls data from multiple tables:

**1. content_ledger**
```
id, certificate_id, content_hash, owner_id, title,
artist_name, file_size_bytes, mime_type, created_at,
verification_count, status
```

**2. artists**
```
id, name, slug, verified, claimed, bio, image_url
```

**3. entity_scores**
```
entity_type, entity_id, score, ranking
```

### Query Parameters
- `?id={certificate_id}` - Lookup by certificate ID (recommended)
- `?hash={sha256}` - Lookup by content hash (technical)

### Response Codes
- **200** - Certificate found and displayed
- **400** - Invalid parameter format
- **404** - Certificate not found in ledger
- **500** - Server error (check logs)

---

## Error Handling

### Invalid Certificate ID
**Error:** "Invalid certificate ID format"
- **Cause:** Certificate ID doesn't match `CRT-YYYYMMDD-XXXXXXXX` format
- **Solution:** Copy certificate ID from source and verify format

### Invalid Hash
**Error:** "Invalid hash format"
- **Cause:** Hash is not 64 hexadecimal characters
- **Solution:** Verify hash is SHA-256 format

### Certificate Not Found
**Error:** 404 - "Certificate not found"
- **Cause:** Certificate ID or hash doesn't exist in ledger
- **Solution:** Verify you have the correct certificate ID/hash

### Disputed Certificate
**Status:** Red badge with "DISPUTED"
- **Meaning:** Certificate has been flagged as disputed
- **Action:** Contact NGN support for clarification

### Revoked Certificate
**Status:** Orange badge with "REVOKED"
- **Meaning:** Certificate has been revoked
- **Action:** Content may have been removed from NGN

---

## Sharing Strategies

### Artists Sharing Their Content
1. Upload content to NGN
2. Receive certificate ID
3. Share certificate URL on:
   - Social media (Instagram, Twitter)
   - Email to fans/labels/distributors
   - Discord/Slack communities
   - Website (embed QR code)

### Labels/Distributors Verifying Content
1. Request certificate ID from artist
2. Scan QR code or visit certificate link
3. Verify artist is authentic
4. Check NGN Score and verification count
5. Confirm ownership before licensing

### Fans Verifying Artist Content
1. Find artist's post with certificate link
2. Click link or scan QR code
3. See artist's verified status
4. Build trust in authentic content

---

## Embedding on External Sites

Certificates can be embedded on external websites using an iframe:

```html
<iframe
  src="https://yourdomain.com/legal/certificate.php?id=CRT-20260206-A3F8D91E"
  width="100%"
  height="600"
  frameborder="0"
  style="border-radius: 8px;">
</iframe>
```

This allows artists to display their verified certificate directly on their website.

---

## Privacy & Security

### Data Protection
- No email addresses exposed
- Artist IP/user agent not tracked publicly
- Verification logs stored securely
- SHA-256 hashing for data integrity

### Security Features
- Regex validation on all inputs
- Prepared statements for database queries
- HTML escaping on all output
- Constant-time hash comparison
- HTTPS recommended for deployment

---

## Troubleshooting

### Certificate Loads Slowly
- Check database connection
- Verify Artist table is populated
- Check network latency
- Review server logs at `storage/logs/`

### QR Code Doesn't Scan
- Ensure printer quality is sufficient (300 DPI)
- QR code should be at least 2cm x 2cm
- Try scanning from different angle
- Update phone's camera/scanner app

### Social Share Not Working
- Enable pop-ups in browser settings
- Check if social media account is logged in
- Try different browser
- Check JavaScript is enabled

### Print Layout Broken
- Use Chrome, Safari, or Firefox (best support)
- Check print preview before printing
- Ensure "Background graphics" is enabled
- Try "Margins: None" in print settings

---

## Support & Contact

For issues with the certificate page or verification:
1. Check the error message displayed
2. Review this guide for common issues
3. Contact NGN support at: support@nextgennoise.com
4. Include certificate ID in support ticket

---

## Quick Reference

| Action | Command/Button |
|--------|---|
| View certificate by ID | `/legal/certificate.php?id=CRT-...` |
| View certificate by hash | `/legal/certificate.php?hash=abc123...` |
| Copy certificate URL | Click "Copy URL" button |
| Share to Twitter | Click "Share on Twitter" button |
| Share to Facebook | Click "Share on Facebook" button |
| Print certificate | Click "Print Certificate" button |
| Scan QR code | Use camera app or QR scanner |
| Embed on website | Use iframe HTML code |

---

## Legal Disclaimer

The NGN Digital Safety Seal verifies that:
- Content is registered in the NGN ledger
- Certificate ID matches stored record
- Content owner's identity has been validated

However, it does NOT:
- Verify copyright ownership
- Guarantee exclusive rights
- Establish legal proof of ownership (consult attorney)
- Replace traditional copyright registration

For legal disputes, contact NGN legal team or seek professional legal advice.

---

**Version:** NGN 2.0.2.1 - "The Fortress"
**Last Updated:** February 7, 2026
**Status:** Production Ready
