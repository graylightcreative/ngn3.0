# Engagement System - Complete Implementation

## Overview
The NGN 2.0 Engagement System provides a comprehensive social interaction framework for all entity types. Users can like, share, comment, and send sparks (micro-transactions) to artists, labels, venues, stations, posts, videos, releases, tracks, and shows.

## Architecture

### Backend (Complete)
- **Schema**: 4 tables with triggers and materialized views
  - `cdm_engagements` - Individual engagement records
  - `cdm_engagement_counts` - Aggregated counts + EQS scores
  - `cdm_engagement_replies` - Nested comment replies
  - `cdm_engagement_notifications` - Real-time notifications

- **Service Layer**: `EngagementService.php`
  - Full CRUD operations
  - EQS (Engagement Quality Score) calculation
  - Notification system
  - Duplicate prevention

- **API Endpoints**: 7 RESTful endpoints
  - `POST /api/v1/engagements` - Create engagement
  - `GET /api/v1/engagements/:entity_type/:entity_id` - List engagements
  - `GET /api/v1/engagements/counts/:entity_type/:entity_id` - Get counts
  - `DELETE /api/v1/engagements/:id` - Delete engagement
  - `GET /api/v1/engagements/check/:entity_type/:entity_id/:type` - Check if engaged
  - `GET /api/v1/engagements/notifications` - Get notifications
  - `PUT /api/v1/engagements/notifications/:id/read` - Mark notification read

### Frontend (Complete)

#### Reusable Components

**1. JavaScript Component** (`/frontend/src/components/engagement.js`)
- Class-based component: `NGNEngagement`
- Features:
  - JWT authentication integration
  - Optimistic UI updates
  - Real-time count formatting (1K, 1M)
  - Comment rendering with relative timestamps
  - Social sharing integration
  - Spark micro-transaction handling
  - XSS prevention with HTML escaping

**2. PHP Partial** (`/lib/partials/engagement-ui.php`)
- Reusable engagement UI for any entity
- Components:
  - Engagement button bar (like, share, comment, spark)
  - Comment section with composer and list
  - Share modal (Facebook, Twitter, Reddit, Copy Link)
  - Spark modal with preset/custom amounts
  - Real-time preview of spark fees (10% platform, 90% artist)
- Fully styled with dark theme
- Bootstrap Icons integration
- Responsive design

#### Profile Pages

**1. Label Profile** (`/label-profile.php`)
- Full label information display
- Roster artists grid
- Recent releases
- Complete engagement features

**2. Venue Profile** (`/venue-profile.php`)
- Venue details with capacity and address
- Upcoming shows calendar
- Past shows archive
- Complete engagement features

**3. Station Profile** (`/station-profile.php`)
- Station information (frequency, format)
- Recently played tracks (from SMR data)
- Complete engagement features

**4. Artist Profile** (`/artist-profile.php`) - Already existed
- Comprehensive artist information
- Releases, shows, videos
- Full engagement features

## Usage

### Adding Engagement to Any Page

```php
<?php
// Set these variables before including the partial
$entity_type = 'artist'; // or 'label', 'venue', 'station', 'post', etc.
$entity_id = 123;
$entity_name = 'Artist Name';
$show_comments = true; // optional, default: true
$show_sparks = true; // optional, default: true

// Include the engagement UI
include __DIR__ . '/lib/partials/engagement-ui.php';
?>
```

### JavaScript API

```javascript
// Initialize engagement component
const engagement = new NGNEngagement({
  entityType: 'artist',
  entityId: 123,
  apiBase: '/api/v1'
});

// Initialize when DOM is ready
engagement.init();

// Available methods
engagement.handleLike();              // Like/unlike
engagement.submitComment();            // Post comment
engagement.shareOn('facebook');        // Share on platform
engagement.sendSparks(100);            // Send sparks
engagement.loadCounts();               // Refresh counts
engagement.loadComments();             // Refresh comments
```

## EQS Weighting

Engagement Quality Score formula:
- **Like**: 1.0 point
- **Share**: 10.0 points
- **Comment**: 3.0 points
- **Spark**: 15.0 points per spark

EQS is automatically recalculated via database triggers and contributes to NGN Score rankings.

## Spark Economy

- **Conversion**: 100 sparks = $1.00 USD
- **Minimum**: 10 sparks ($0.10)
- **Platform Fee**: 10%
- **Artist Receives**: 90%
- **Preset Amounts**: 100 ($1), 500 ($5), 1000 ($10)
- **Custom Amounts**: User can specify any amount ≥10 sparks

## Features

### Like System
- ✅ One-click like/unlike
- ✅ Optimistic UI updates
- ✅ Duplicate prevention
- ✅ Real-time count updates
- ✅ Active state indicators

### Share System
- ✅ Facebook share
- ✅ Twitter share
- ✅ Reddit share
- ✅ Copy link to clipboard
- ✅ Share tracking/analytics
- ✅ Native share dialogs

### Comment System
- ✅ Textarea composer
- ✅ Post/Clear actions
- ✅ Comment list with timestamps
- ✅ Relative time display ("2h ago")
- ✅ Author attribution
- ✅ Empty state handling
- ✅ XSS prevention

### Spark System
- ✅ Preset spark amounts
- ✅ Custom spark input
- ✅ Real-time fee preview
- ✅ Gross/Fee/Net breakdown
- ✅ Integration with royalty system
- ✅ Transaction ledger recording

## Security

- **Authentication**: JWT Bearer token required for all mutations
- **Authorization**: Users can only delete their own engagements
- **XSS Prevention**: All user input is HTML-escaped
- **SQL Injection**: Prepared statements throughout
- **Rate Limiting**: Duplicate engagement prevention
- **CSRF**: Token-based API authentication

## Database Performance

- **Indexes**: On entity_type, entity_id, user_id, type
- **Materialized View**: `cdm_engagement_counts` for fast count queries
- **Triggers**: Automatic count updates on INSERT/DELETE
- **Soft Deletes**: `deleted_at` timestamp instead of hard deletes

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile: iOS Safari 14+, Chrome Android 90+

## Dependencies

- **Backend**: PHP 8.0+, PDO, JWT library
- **Frontend**: Vanilla JavaScript (no framework)
- **Icons**: Bootstrap Icons 1.11.0
- **API**: Axios (in full site), native fetch (in component)

## Future Enhancements

Potential improvements:
- Real-time updates via WebSocket or SSE
- Nested comment replies (schema exists)
- Comment reactions (love, laugh, etc.)
- Engagement notifications UI
- Trending/hot comments sorting
- Comment moderation tools
- Spam detection
- Rich text comments (markdown)
- Image attachments
- @mentions and hashtags

## Files Created

### Frontend Components
- `/frontend/src/components/engagement.js` (586 lines)
- `/lib/partials/engagement-ui.php` (535 lines)

### Profile Pages
- `/label-profile.php` (263 lines)
- `/venue-profile.php` (212 lines)
- `/station-profile.php` (220 lines)

### Documentation
- `/docs/ENGAGEMENT_SYSTEM.md` (this file)

## Testing

Test the engagement system:

1. **Manual Testing**:
   ```
   http://localhost/artist-profile.php?slug=artist-slug
   http://localhost/label-profile.php?slug=label-slug
   http://localhost/venue-profile.php?slug=venue-slug
   http://localhost/station-profile.php?slug=station-slug
   ```

2. **API Testing**:
   ```bash
   # Get counts
   curl http://localhost/api/v1/engagements/counts/artist/1

   # Create like (requires auth)
   curl -X POST http://localhost/api/v1/engagements \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"entity_type":"artist","entity_id":1,"type":"like"}'
   ```

3. **Database Verification**:
   ```sql
   -- Check engagement counts
   SELECT * FROM cdm_engagement_counts WHERE entity_type='artist' AND entity_id=1;

   -- Check engagements
   SELECT * FROM cdm_engagements WHERE entity_type='artist' AND entity_id=1;
   ```

## Status: ✅ COMPLETE

All engagement infrastructure components are fully implemented and ready for production use.
