# Post Analytics - Engagement Source Breakdown

## Overview

Post Analytics tracks engagement sources (authenticated vs anonymous) for posts, enabling creators to understand engagement quality for EQS (Engagement Quality Score) calculations. This addresses **User Story A.15**: creators can now view engagement split between logged-in users and anonymous/session traffic.

**Ref**: User Story A.15 (Engagement Source Breakdown)

## Architecture

### Database Schema

Five new tables track post engagement analytics:

1. **post_engagement_analytics** - Real-time snapshot of post engagement by source
2. **post_engagement_events** - Detailed event log with source tracking
3. **post_analytics_daily** - Daily aggregations for trending
4. **post_analytics_summary** - Weekly/monthly summaries
5. **post_analytics_fraud_flags** - Suspicious engagement pattern tracking

### Core Service: PostAnalyticsService

Located at `lib/Analytics/PostAnalyticsService.php`

**Key Methods**:
- `trackEngagementEvent(int $postId, string $engagementType, array $data)` - Track individual engagement
- `updatePostAnalytics(int $postId)` - Recalculate post analytics
- `getPostAnalytics(int $postId)` - Get current analytics
- `getEngagementSourceBreakdown(int $postId)` - Get detailed breakdown
- `getFraudFlags(int $postId)` - Get suspicious patterns
- `getDailyAnalytics(int $postId, $start, $end)` - Get time-series data
- `detectDuplicateEngagements(int $postId)` - Find duplicate engagement

## Tracking Flow

```
User Engages on Post
        ↓
EngagementService::create()
        ↓
PostAnalyticsService::trackEngagementEvent()
        ↓
post_engagement_events inserted
        ↓
post_engagement_analytics updated (real-time)
        ↓
Fraud detection checks run
        ↓
Fraud flags created if needed
```

## Metrics Collected

### Per-Engagement Tracking
- **is_authenticated** - Whether user was logged in
- **engagement_type** - Type (view, like, share, comment, spark)
- **session_id** - For anonymous user tracking
- **user_agent** - Browser/device info
- **ip_hash** - Hashed IP for duplicate detection
- **device_type** - mobile, tablet, desktop, unknown

### Aggregated Metrics
- **authentication_rate** - % of engagement from authenticated users (target: >60%)
- **fraud_suspicion_score** - 0-1 scale for suspicious patterns
- **breakdown by type** - Views, likes, shares, comments, sparks split by source

## Fraud Detection

The service automatically detects and flags suspicious engagement patterns:

### Fraud Flags

1. **high_anonymous_ratio** - >80% engagement from anonymous sources
   - Threshold: <20% authentication rate + >50 anonymous engagements
   - Severity: HIGH

2. **bot_pattern** - Multiple suspicious indicators
   - Triggered by high fraud score (>0.7)
   - Severity: CRITICAL

3. **duplicate_engagement** - Same user engaging multiple times
   - Detected via IP hash + user ID matching
   - Window: 5 minute default

4. **unusual_spike** - Engagement 5x normal daily average
   - Compared against 7-day rolling average
   - Severity: MEDIUM

5. **geo_anomaly** - Geolocation-based anomalies
   - Requires geo-IP enrichment
   - Severity: LOW-MEDIUM

6. **behavioral_anomaly** - Unusual engagement patterns
   - Requires engagement history
   - Severity: MEDIUM

## API Endpoints

### GET /api/v1/posts/{id}/analytics

Returns comprehensive analytics for a post.

**Authentication**: Required (creator or admin)

**Query Parameters**:
- `include_fraud_flags` (bool) - Include fraud flag details
- `start_date` (date) - For daily analytics range
- `end_date` (date) - For daily analytics range

**Response**:
```json
{
  "post_id": 123,
  "analytics": {
    "total_authenticated_engagement": 845,
    "total_anonymous_engagement": 234,
    "authentication_rate": 78.3,
    "fraud_suspicion_score": 0.15,
    "last_updated": "2026-01-22T10:15:00Z"
  },
  "engagement_breakdown": {
    "total_authenticated": 845,
    "total_anonymous": 234,
    "authentication_rate": 78.3,
    "breakdown": {
      "views": { "authenticated": 600, "anonymous": 150 },
      "likes": { "authenticated": 180, "anonymous": 65 },
      "shares": { "authenticated": 45, "anonymous": 15 },
      "comments": { "authenticated": 18, "anonymous": 4 },
      "sparks": { "authenticated": 2.5, "anonymous": 0.0 }
    }
  },
  "fraud_flags": [...],
  "daily_analytics": [...]
}
```

## Admin Dashboard

**Location**: `/admin/analytics/posts.php`

**Features**:
- Post lookup by ID
- Real-time analytics snapshot
- Engagement source breakdown visualization
- Fraud flag review panel
- Daily trend analysis (30-day chart)
- Top suspicious posts list
- Duplicate detection trigger

## Integration Points

### EngagementService Hook

When `EngagementService::create()` is called:
1. If entity is a **post**, automatically calls `trackPostAnalytics()`
2. Passes user ID, engagement type, session info
3. Analytics updated in real-time

### Data Collection

Per engagement, we collect:
- Authentication status
- Device type (from user agent)
- IP hash (for duplicate detection)
- Referrer source
- Session ID (for anonymous tracking)

## Cron Jobs

### aggregate_post_analytics.php (Daily at 1 AM)

Rolls up event-level data into daily summaries:
- Aggregates previous day's engagements
- Calculates daily auth rates
- Cleans up events older than 90 days
- Updates post_analytics_daily table

**Execution**: ~2-5 seconds for typical volumes

## Quality Metrics

### Target Thresholds

| Metric | Healthy | Warning | Alert |
|--------|---------|---------|-------|
| Authentication Rate | >70% | 50-70% | <50% |
| Fraud Score | <0.3 | 0.3-0.7 | >0.7 |
| Duplicate Rate | <2% | 2-5% | >5% |
| Engagement Velocity | Normal | 2-3x avg | >5x avg |

## Usage Examples

### Get Post Analytics

```php
<?php
use NGN\Config;
use NGN\Lib\Analytics\PostAnalyticsService;

$config = Config::getInstance();
$analytics = new PostAnalyticsService($config);

$postAnalytics = $analytics->getPostAnalytics($postId);
echo "Authentication Rate: " . $postAnalytics['authentication_rate'] . "%\n";
echo "Fraud Score: " . $postAnalytics['fraud_suspicion_score'] . "\n";
?>
```

### Get Engagement Breakdown

```php
<?php
$breakdown = $analytics->getEngagementSourceBreakdown($postId);

foreach ($breakdown['breakdown'] as $type => $data) {
    $total = $data['authenticated'] + $data['anonymous'];
    $authPct = $total > 0 ? ($data['authenticated'] / $total * 100) : 0;
    echo "$type: $authPct% authenticated\n";
}
?>
```

### Detect Fraud

```php
<?php
$flags = $analytics->getFraudFlags($postId, 'high');

foreach ($flags as $flag) {
    if ($flag['severity'] === 'critical') {
        // Take action (hide post, warn creator, etc)
        email_creator_warning($postId, $flag['description']);
    }
}
?>
```

## Performance Considerations

### Scalability

- **Event tracking**: O(1) insert per engagement
- **Analytics update**: O(n) where n = events for post today
- **Daily aggregation**: ~1000 posts/sec on modern hardware
- **Duplicate detection**: Indexed on user_id, ip_hash, session_id

### Storage

- **Event retention**: 90 days
- **Daily summaries**: Indefinite
- **Typical size**: ~500 bytes per event
- **90-day retention**: ~50MB per 1M events/day

### Queries

- All queries indexed on post_id, date, authentication status
- Fraud score calculations cached in post_engagement_analytics
- Daily aggregations run off previous day's data (no lock contention)

## Troubleshooting

### Low Authentication Rate

**Causes**:
1. Many unauthenticated users
2. Bot/spam activity
3. Tracking error (session_id not captured)

**Solutions**:
1. Check fraud_flags for patterns
2. Review user agents for bot signatures
3. Verify session tracking on frontend

### High Fraud Score

**Causes**:
1. Genuine viral moment (unusual spike)
2. Bot engagement (many anonymous + duplicates)
3. Coordinated fake engagement
4. Geographic anomalies

**Solutions**:
1. Review fraud_flags details
2. Check daily trend for gradual vs sudden spike
3. Investigate duplicate engagement rate
4. Consider content quality vs suspicious metrics

### Missing Analytics

**Causes**:
1. Cron job not running
2. EngagementService hook not firing
3. Analytics service initialization failure

**Solutions**:
1. Check cron logs: `/logs/analytics.log`
2. Verify EngagementService calls trackPostAnalytics
3. Test manually: `new PostAnalyticsService($config)->updatePostAnalytics($postId)`

## Future Enhancements

1. **Geographic Analysis** - Geo-IP enrichment for location patterns
2. **Engagement Velocity** - Time-series momentum tracking
3. **Content Quality** - Correlation with post sentiment/quality
4. **User Segmentation** - Analytics by user type/tier
5. **Predictive Flagging** - ML-based fraud prediction
6. **Export Reports** - CSV/PDF monthly reports for creators
7. **Comparative Analytics** - Benchmark post vs creator average
8. **A/B Testing** - Compare engagement sources across variants

## Related Documentation

- **Engagement Service**: `/lib/Engagement/EngagementService.php`
- **EQS Calculation**: `/docs/Scoring.md`
- **Admin Guide**: `/docs/ADMIN_GUIDE.md`
- **API Reference**: `/docs/API.md`

## Support

For issues:
1. Check admin dashboard at `/admin/analytics/posts.php`
2. Review logs at `/logs/analytics.log`
3. Query `post_analytics_fraud_flags` for patterns
4. Run duplicate detection manually via admin panel
