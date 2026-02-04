# Discovery Engine - Implementation Documentation

## Overview

The Discovery Engine is an AI-powered recommendation system that helps users discover new artists based on their engagement patterns, genre preferences, and community behavior. This document provides implementation details, usage examples, and troubleshooting guidance.

**Bible Reference**: Chapter 18.2 - Gap Analysis (Discovery Engine)

## Architecture

### Database Schema

Six new tables were created via migration 39:

1. **user_artist_affinity** - Tracks user affinity scores for individual artists
2. **user_genre_affinity** - Tracks user affinity scores for music genres
3. **artist_similarity** - Stores pre-computed similarity scores between artists
4. **discovery_recommendations** - Caches generated recommendations with TTL
5. **niko_discovery_digests** - Tracks weekly "Niko's Discovery" email digests
6. **genre_clusters** - Enhanced with artist/user count and average NGN score

### Core Services

#### AffinityService (`lib/Discovery/AffinityService.php`)
Tracks and calculates user affinity scores based on engagement patterns.

**Key Methods**:
- `getUserAffinityForArtist(int $userId, int $artistId)` - Get affinity for specific artist
- `getUserTopArtistAffinities(int $userId, int $limit = 20)` - Get user's top affinities
- `calculateArtistAffinity(int $userId, int $artistId)` - Calculate composite score
- `updateAffinityFromEngagement(int $userId, int $artistId, string $type, float $value)` - Update on engagement
- `recalculateAllAffinities(int $userId)` - Recalculate all user affinities

**Affinity Score Formula**:
```
affinity_score = (spark_weight × 0.4) + (engagement_weight × 0.3) + (listen_weight × 0.2) + (follow_weight × 0.1)

where:
- spark_weight = min(total_sparks / 100, 100)
- engagement_weight = min(total_engagements × 2, 100)
- listen_weight = min(play_count × 0.5, 100)
- follow_weight = is_following ? 100 : 0
```

#### SimilarityService (`lib/Discovery/SimilarityService.php`)
Computes and retrieves artist similarity scores using multiple factors.

**Key Methods**:
- `getSimilarArtists(int $artistId, int $limit = 10)` - Get similar artists
- `getArtistSimilarity(int $artistId1, int $artistId2)` - Get similarity between two artists
- `computeSimilarity(int $artistId1, int $artistId2)` - Compute similarity score
- `batchComputeSimilarities(int $artistId)` - Batch compute for artist
- `recomputeAllSimilarities(int $limit = 100)` - Recompute stale similarities

**Similarity Score Formula**:
```
similarity_score = (genre_match × 0.4) + (fanbase_overlap × 0.35) + (engagement_pattern × 0.25)

where:
- genre_match = |intersection(genres)| / |union(genres)|
- fanbase_overlap = |shared_followers| / min(followers1, followers2)
- engagement_pattern = cosine_similarity(engagement_vectors)
```

#### DiscoveryEngineService (`lib/Discovery/DiscoveryEngineService.php`)
Main orchestration service for generating personalized recommendations.

**Key Methods**:
- `getRecommendedArtists(int $userId, int $limit = 10)` - Get personalized recommendations
- `getEmergingArtists(int $userId, int $limit = 10)` - Get rising artists in user's genres
- `getGenreBasedRecommendations(int $userId, string $genreSlug, int $limit = 10)` - Get genre recommendations
- `getCachedRecommendations(int $userId)` - Get cached recommendations
- `cacheRecommendations(int $userId, array $recommendations)` - Cache results
- `invalidateCache(int $userId)` - Clear cache

**Recommendation Algorithm**:
1. Affinity-based (40%): Artists similar to user's top affinities
2. Similarity-based (30%): Artists in same genres as user's favorites
3. Community-based (20%): Popular among similar users
4. Emerging (10%): New artists with rising NGN Score

**Filters Applied**:
- Exclude already following
- Exclude recently recommended (7 days)
- Maximum 40% from single genre
- Minimum NGN Score threshold (30)

#### NikoDiscoveryService (`lib/Discovery/NikoDiscoveryService.php`)
Generates and sends weekly "Niko's Discovery" email digests.

**Key Methods**:
- `generateWeeklyDigest(int $userId)` - Create digest for user
- `selectFeaturedArtists(int $userId)` - Select 3 featured artists
- `sendDigest(int $userId)` - Send digest email
- `sendBatchDigests(array $userIds)` - Send batch digests
- `getDigestRecipients()` - Get eligible users
- `trackDigestSent(int $userId, array $artists)` - Track sent digest
- `getDigestPerformance(string $digestWeek)` - Get digest metrics

**Digest Selection Criteria**:
- 3 artists total
- High affinity match (not following)
- Emerging status (NGN Score rising)
- Genre diversity (max 2 from same genre)
- Recent activity (released content in last 30 days)

## Integration Points

### EngagementService Integration

When a user engages with an artist (like, comment, share, spark), the affinity score is automatically updated:

```php
// In EngagementService::create()
if ($entityType === 'artist') {
    $this->updateAffinityFromEngagement($userId, $entityId, $type, $sparkAmount ?? 1.0);
}
```

### ArtistService Extension

New methods added to ArtistService:
- `getEmergingArtists(int $limit = 10)` - Get emerging artists
- `getArtistsByGenre(string $genre, int $limit = 20)` - Get genre artists
- `getSimilarArtists(int $artistId, int $limit = 10)` - Get similar artists

### FeedService Integration

New method `getFeedWithDiscovery()` injects recommendations into user feed:
- 80% followed artists' content
- 20% discovery recommendations
- Uses 4:1 interleaving ratio

## API Endpoints

### GET /api/v1/discovery/recommendations
Returns personalized recommendations for authenticated user.

**Parameters**:
- `limit` (int, default: 10) - Number of recommendations
- `genre` (string, optional) - Filter by genre
- `min_score` (float, optional) - Minimum affinity threshold

**Response**:
```json
{
  "recommendations": [
    {
      "artist_id": 123,
      "artist_name": "Rising Star Band",
      "genre": "Indie Rock",
      "ngn_score": 75.5,
      "affinity_score": 82.3,
      "reason": "Because you love Artist X",
      "is_emerging": true
    }
  ],
  "total": 10,
  "generated_at": "2026-01-21T10:00:00Z",
  "expires_at": "2026-01-22T10:00:00Z"
}
```

### GET /api/v1/discovery/similar/{artist_id}
Returns artists similar to a given artist.

**Parameters**:
- `limit` (int, default: 10) - Number of similar artists

**Response**:
```json
{
  "artist_id": 456,
  "similar_artists": [
    {
      "artist_id": 789,
      "artist_name": "Similar Band",
      "similarity_score": 0.87,
      "shared_fans": 245,
      "genre_match": 0.8
    }
  ],
  "total": 10
}
```

### GET /api/v1/discovery/affinity
Returns user's affinity scores for artists and genres.

**Parameters**:
- `type` (string) - "artist" or "genre"
- `limit` (int, default: 20) - Number to return

**Response**:
```json
{
  "type": "artist",
  "affinities": [
    {
      "artist_id": 123,
      "artist_name": "Favorite Artist",
      "affinity_score": 95.5,
      "total_sparks": 150.00,
      "total_engagements": 45,
      "is_following": true
    }
  ],
  "total": 20
}
```

### GET /api/v1/discovery/digest-preview
Preview of this week's Niko's Discovery digest.

**Response**:
```json
{
  "week": "2026-03",
  "subject": "Niko's Discovery: 3 Artists You'll Love This Week",
  "featured_artists": [
    {
      "artist_id": 111,
      "artist_name": "Emerging Artist",
      "reason": "Because you love [similar artist]",
      "genre": "Metal",
      "ngn_score": 68.5,
      "affinity_score": 78.2
    }
  ],
  "sent": false
}
```

## Cron Jobs

### update_affinities.php (Every 30 minutes)
Updates user affinity scores based on recent engagement activity.
- Fetches users with engagement in last 30 minutes
- Recalculates affinity for each user-artist pair
- Updates genre affinities

### compute_similarities.php (Daily at 3 AM)
Batch computes artist similarity scores.
- Selects artists with no recent computation (>7 days old)
- Processes up to 100 artists per run
- Alerts if any artist takes >60s to compute

### generate_recommendations.php (Daily at 4 AM)
Pre-generates recommendations for active users.
- Fetches active users (logged in last 7 days)
- Generates recommendations in batches of 1000
- Caches results for 24 hours
- Alerts if cache hit rate <70%

### send_niko_digests.php (Every Monday at 9 AM)
Sends weekly "Niko's Discovery" emails.
- Gets eligible users (active, opted in, no digest this week)
- Generates and sends digests in batches of 500
- Tracks delivery status
- Alerts if failure rate >5%

### update_genre_clusters.php (Daily at 5 AM)
Maintains genre_clusters table with current membership.
- Updates artist and user counts per genre
- Calculates average NGN Score per genre
- Refreshes genre membership lists

## Admin Dashboards

### /admin/discovery/overview.php
High-level metrics and performance dashboard.
- KPIs: Active users, digest open rate, avg affinity score, computations
- Charts: Daily requests, genre distribution
- Recent activity log

### /admin/discovery/recommendations.php
Debug recommendations for specific users.
- User lookup
- Display top affinities
- Show generated recommendations with scores
- Refresh cache button

### /admin/discovery/similarities.php
Manage artist similarity computations.
- Artist lookup
- Display similar artists with component scores
- Recompute similarities button

### /admin/discovery/digests.php
Manage Niko's Discovery digests.
- Weekly digest metrics (sent, open rate, click rate)
- User digest preview
- Test send digest button
- Recent digest history

### /admin/discovery/affinities.php
View and debug user affinity data.
- User lookup
- Artist affinity breakdown with visualization
- Genre affinity breakdown
- Recent engagement history
- Manual recalculation button

## Usage Examples

### Get Recommendations for User

```php
<?php
use NGN\Config;
use NGN\Lib\Discovery\DiscoveryEngineService;

$config = Config::getInstance();
$engine = new DiscoveryEngineService($config);

$recommendations = $engine->getRecommendedArtists($userId, 10);

foreach ($recommendations as $artist) {
    echo $artist['artist_name'] . ": " . $artist['score'] . "\n";
}
?>
```

### Update Affinity on Engagement

```php
<?php
use NGN\Lib\Discovery\AffinityService;

$affinityService = new AffinityService($config);

// When user sparks an artist
$affinityService->updateAffinityFromEngagement($userId, $artistId, 'spark', 25.0);

// When user likes
$affinityService->updateAffinityFromEngagement($userId, $artistId, 'engagement', 1.0);

// When user follows
$affinityService->updateAffinityFromEngagement($userId, $artistId, 'follow', 1.0);
?>
```

### Get Similar Artists

```php
<?php
use NGN\Lib\Discovery\SimilarityService;

$similarityService = new SimilarityService($config);
$similar = $similarityService->getSimilarArtists($artistId, 10);

foreach ($similar as $artist) {
    echo $artist['similar_artist_id'] . ": " . $artist['similarity_score'] . "\n";
}
?>
```

### Send Niko's Discovery Digest

```php
<?php
use NGN\Lib\Discovery\NikoDiscoveryService;

$nikoService = new NikoDiscoveryService($config);

// Send to single user
$success = $nikoService->sendDigest($userId);

// Send to batch
$recipients = $nikoService->getDigestRecipients();
$results = $nikoService->sendBatchDigests($recipients);

echo "Sent: " . $results['sent'] . ", Failed: " . $results['failed'] . "\n";
?>
```

### Get Feed with Discovery

```php
<?php
use NGN\Lib\Feed\FeedService;

$feedService = new FeedService($postService, $engagementService);

// Get feed with 80/20 mix
$feed = $feedService->getFeedWithDiscovery($userId, [], 20);

foreach ($feed as $item) {
    if ($item['type'] === 'discovery') {
        echo "DISCOVERY: " . $item['artist_name'] . "\n";
    } else {
        echo "POST: " . $item['title'] . "\n";
    }
}
?>
```

## Performance Considerations

### Caching Strategy
- Recommendations cached for 24 hours per user
- Artist similarities cached for 7 days
- Cache invalidated on user engagement or artist update

### Database Optimization
- Indexes on all foreign keys and score columns
- JSON columns for flexible metadata
- Denormalized frequently accessed data
- Limit batch operations to 1000 records

### Scalability
- Asynchronous similarity computation via cron
- Pre-generate recommendations for active users
- Lazy-load for inactive users
- Use read replicas for recommendation queries

### Troubleshooting

**Low cache hit rate (<70%)**
- Check recommendation generation job is running
- Verify cron schedule: daily at 4 AM
- Check database for expired entries

**High similarity computation time (>60s per artist)**
- Reduce comparison set size
- Add database indexes on engagement tables
- Run computations in off-peak hours

**Poor recommendation quality**
- Verify affinity scores are calculating correctly
- Check genre mappings are accurate
- Ensure similarity computations are fresh
- Test with debug admin panel

**Digest delivery failures**
- Check EmailService configuration
- Verify user email addresses are valid
- Review error_message in niko_discovery_digests table
- Check failure rate in admin dashboard

## Monitoring

### Key Metrics to Track
- Affinity calculation success rate
- Similarity computation completion rate
- Recommendation generation time (target: <500ms)
- Cache hit rate (target: >70%)
- Digest delivery success rate (target: >95%)
- Digest open rate (target: >20%)
- Digest click rate (target: >5%)

### Logs
- `/logs/discovery.log` - Discovery Engine events
- `/logs/discovery_errors.log` - Errors and exceptions

## Future Enhancements

1. **Machine Learning Integration** - Use TensorFlow for artist embeddings
2. **Collaborative Filtering** - User-to-user similarity matrix
3. **Temporal Patterns** - Time-based recommendation adjustments
4. **A/B Testing** - Algorithm variants for experimentation
5. **User Preferences** - Let users configure recommendation parameters
6. **Diversity Optimization** - Maximize recommendation novelty
7. **Real-time Updates** - WebSocket push for new recommendations
8. **Mobile Notifications** - Native app push for discoveries

## Related Documentation

- **Database Schema**: `migrations/sql/schema/39_discovery_engine.sql`
- **API Guide**: `/docs/API.md`
- **Cron Jobs**: `/docs/CRON_JOBS.md`
- **Admin Guide**: `/docs/ADMIN_GUIDE.md`

## Support

For issues or questions:
1. Check admin dashboards for debugging
2. Review logs in `/logs/discovery.log`
3. Run manual recalculation via admin panel
4. Contact engineering team
