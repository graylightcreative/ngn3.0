22. Social Feed & Engagement Algorithm

22.1 The "Earned Reach" Philosophy

In NGN 2.0, visibility is a currency that cannot be purchased. While Advertisers can buy specific "Sponsored" slots (Ch. 18.5), the primary Social Feed is governed by an Earned Reach algorithm. Access to a user's feed is determined by relevance, popularity, and engagement velocity.

22.2 Content Sources

The feed aggregates content from five primary "Trusted Entities":

Artists: Riffs, studio updates, tour announcements, and personal posts.

Labels: Roster news, signing announcements, and curated playlists.

Stations: Live broadcast highlights, DJ picks, and chart countdowns.

Venues: Event galleries, setlists, and "Tonight's Lineup" hype.

Editorial (Niko/Writers): AI-generated news, deep dives, and chart analysis.

22.3 Distribution Tiers

Content visibility moves through three concentric circles based on performance:

Tier 1: The Core Circle (Followers)

Logic: 100% of an entity's followers are eligible to see the post.

Filter: Chronological or "Recent Top Engagement" depending on user settings.

Tier 2: The Affinity Circle (Discovery)

Logic: If a post achieves a high Engagement Velocity (EV) within Tier 1, it is pushed to non-followers with high Genre Affinity.

Example: A "Technical Death Metal" riff that gets 50 shares in 10 minutes is shown to all users who follow at least three other Technical Death Metal artists.

Tier 3: The Global Circle (Trending)

Logic: Content that maintains high EV across Tier 2 moves to the "Global Trending" feed.

The "Moneyball" Gate: Visibility here is strictly tied to the NGN Score (Ch. 3). Only the most culturally relevant content reaches the global audience.

22.4 The Engagement Velocity (EV) Formula

Reach expansion is triggered by the EV score:

$$EV = \frac{(Likes \times 1) + (Comments \times 3) + (Shares \times 10) + (Sparks \times 15)}{TimeSincePost}$$

Weighting: Shares and Sparks are the heaviest weights. A "View" without interaction is a low-value metric.

Verification: Only interactions from Verified Humans (Ch. 21.7) contribute to the EV. Bot-driven spikes are neutralized before they can trigger Tier 2/3 expansion.

22.5 Anti-Payola Guardrails

No "Promoted" Organic Posts: An Artist cannot pay USD or Sparks to "Boost" an organic post into a non-follower's feed. They can only buy Ad Inventory (Sidebar/Banners), which is clearly labeled and excluded from the organic algorithm.

The "Baseline" Test: To prevent a "rich get richer" loop, every new post from a Verified Artist is granted a "Seed Visibility" (shown to a random 5% of non-follower users in their genre) to test its EV potential.

Stale Content Decay: Content visibility decays rapidly over 48 hours to ensure the feed remains a "Now" experience.

---

## 22.6 Technical Implementation

### 22.6.1 Engagement System Architecture

The engagement infrastructure is built on four core components:

**Database Layer:**
- `cdm_engagements` - Individual engagement records (likes, shares, comments, sparks)
- `cdm_engagement_counts` - Materialized view for fast count queries
- `cdm_engagement_replies` - Nested comment thread support
- `cdm_engagement_notifications` - Real-time notifications for entity owners

**Service Layer:**
- `EngagementService.php` - Business logic for CRUD operations
- Automatic EQS recalculation via database triggers
- Duplicate engagement prevention
- Soft-delete support

**API Layer:**
Seven RESTful endpoints (see Ch. 4 - API Reference):
- `POST /api/v1/engagements` - Create engagement
- `GET /api/v1/engagements/:entity_type/:entity_id` - List engagements
- `GET /api/v1/engagements/counts/:entity_type/:entity_id` - Get counts
- `DELETE /api/v1/engagements/:id` - Delete engagement
- `GET /api/v1/engagements/check/:entity_type/:entity_id/:type` - Check if engaged
- `GET /api/v1/engagements/notifications` - Get notifications
- `PUT /api/v1/engagements/notifications/:id/read` - Mark notification read

**Frontend Layer:**
- `engagement.js` - Reusable JavaScript component class
- `engagement-ui.php` - Drop-in PHP partial with full UI
- Optimistic updates for instant feedback
- JWT authentication integration

### 22.6.2 EQS Calculation (Engagement Quality Score)

The EQS is a weighted sum of all engagement types:

```
EQS = (Likes × 1.0) + (Comments × 3.0) + (Shares × 10.0) + (Sparks × 15.0)
```

**Weighting Rationale:**
- **Likes (1.0)**: Low-friction signal of interest
- **Comments (3.0)**: High-value contribution to discussion
- **Shares (10.0)**: Strong endorsement with network amplification
- **Sparks (15.0)**: Highest signal - financial commitment from fan

EQS contributes directly to NGN Score (Ch. 3) and is recalculated automatically via database triggers.

### 22.6.3 Adding Engagement to Any Entity

To add engagement features to any page, use the reusable partial:

```php
<?php
// Set entity context
$entity_type = 'artist'; // or: label, venue, station, post, video, release, track, show
$entity_id = 123;
$entity_name = 'Artist Name';

// Optional: Configure features
$show_comments = true;  // default: true
$show_sparks = true;    // default: true

// Include the engagement UI
include __DIR__ . '/lib/partials/engagement-ui.php';
?>
```

This renders a complete engagement UI with:
- Like/share/comment/spark buttons with live counts
- Share modal (Facebook, Twitter, Reddit, Copy Link)
- Comment section with composer and thread
- Spark modal with preset amounts and fee preview

### 22.6.4 JavaScript API

The `NGNEngagement` class provides programmatic access:

```javascript
// Initialize
const engagement = new NGNEngagement({
  entityType: 'artist',
  entityId: 123,
  apiBase: '/api/v1'
});

engagement.init();

// Available methods
engagement.handleLike();              // Like/unlike
engagement.submitComment();            // Post comment
engagement.shareOn('facebook');        // Share on platform
engagement.sendSparks(100);            // Send sparks
engagement.loadCounts();               // Refresh counts
engagement.loadComments();             // Refresh comments
```

### 22.6.5 Spark Economy Integration

**Conversion Rate:** 100 sparks = $1.00 USD

**Fee Structure:**
- Platform Fee: 10%
- Creator Receives: 90%
- Minimum: 10 sparks ($0.10)

**Preset Amounts:**
- 100 sparks ($1.00)
- 500 sparks ($5.00)
- 1000 sparks ($10.00)
- Custom amounts (≥10 sparks)

Spark transactions are recorded in the royalty ledger (Ch. 13) and trigger immediate EQS recalculation. The 90/10 split ensures creator sustainability while covering platform costs.

### 22.6.6 Security & Anti-Gaming

**Authentication:**
- All engagement mutations require JWT Bearer token
- Token validation extracts `userId` and `role` claims
- Users can only delete their own engagements

**Duplicate Prevention:**
- Database unique constraints on (user_id, entity_type, entity_id, type)
- Prevents spam likes, shares, or comments
- Allows legitimate re-engagement after deletion

**XSS Prevention:**
- All user input (comments, metadata) is HTML-escaped
- Server-side sanitization before rendering
- No eval() or innerHTML in JavaScript component

**Bot Mitigation:**
- Only engagements from verified users contribute to EV (see Ch. 21.7)
- Bot-driven spikes are neutralized before triggering tier expansion
- Rate limiting on API endpoints

### 22.6.7 Performance Optimization

**Database:**
- Indexes on (entity_type, entity_id, user_id, type)
- Materialized view for counts (sub-millisecond queries)
- Triggers update counts automatically (no cron jobs)
- Soft deletes (`deleted_at`) for audit trails

**Frontend:**
- Optimistic UI updates (no loading spinners)
- Count formatting (1K, 1M) client-side
- Debounced API calls on input fields
- Lazy-load comments (20 at a time)

**Caching:**
- Engagement counts cached in materialized view
- No N+1 queries on entity listings
- Single query for all engagement data per entity

### 22.6.8 Supported Entity Types

The engagement system supports all nine entity types:

1. **artist** - Artist profiles, releases, tracks
2. **label** - Label profiles, roster updates
3. **venue** - Venue profiles, show listings
4. **station** - Radio station profiles, spin data
5. **post** - News articles, blog posts
6. **video** - Music videos, interviews
7. **release** - Albums, EPs, singles
8. **track** - Individual songs
9. **show** - Concert/event pages

Each entity type inherits the same engagement features and EQS calculation, ensuring consistent UX across the platform.

### 22.6.9 Integration Checklist

When adding engagement to a new entity type:

1. ✅ Database: Entity table exists in CDM
2. ✅ Backend: EntityService fetches entity data
3. ✅ API: GET endpoint returns entity JSON
4. ✅ Frontend: Profile page created
5. ✅ Engagement: Include `engagement-ui.php` partial
6. ✅ Testing: Verify counts, comments, sparks work
7. ✅ SEO: Add Open Graph meta tags
8. ✅ Monitoring: Track engagement metrics in analytics

**Example Implementation (Label Profile):**

```php
// Fetch label data
$label = $labelService->getBySlug($slug);

// Set engagement variables
$entity_type = 'label';
$entity_id = $label['id'];
$entity_name = $label['name'];

// Render profile header
echo renderProfileHeader($label);

// Add engagement UI (automatic)
include __DIR__ . '/lib/partials/engagement-ui.php';

// Continue with roster, releases, etc.
```

This pattern ensures engagement features are consistent across all entity types while remaining easy to implement.

---

**Implementation Status:** ✅ Complete (Jan 2026)

All engagement infrastructure is production-ready:
- 4 database tables with triggers
- 7 API endpoints
- Reusable frontend components
- Full documentation

**Files:**
- `/lib/Engagement/EngagementService.php` - Backend service
- `/frontend/src/components/engagement.js` - JavaScript component
- `/lib/partials/engagement-ui.php` - UI partial
- `/docs/ENGAGEMENT_SYSTEM.md` - Technical documentation