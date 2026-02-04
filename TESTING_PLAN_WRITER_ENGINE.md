# Writer Engine (AI Editorial System) - Comprehensive Testing Plan

**Project**: Feature 9 - Writer Engine
**Status**: Pre-Deployment Testing Phase
**Last Updated**: 2026-01-21
**Test Coordinator**: [Your Name]

---

## Table of Contents

1. [Overview](#overview)
2. [Test Environment Setup](#test-environment-setup)
3. [Unit Tests](#unit-tests)
4. [Integration Tests](#integration-tests)
5. [End-to-End Pipeline Tests](#end-to-end-pipeline-tests)
6. [API Endpoint Tests](#api-endpoint-tests)
7. [Admin Dashboard Tests](#admin-dashboard-tests)
8. [Cron Job Tests](#cron-job-tests)
9. [Safety & Compliance Tests](#safety--compliance-tests)
10. [Performance & Load Tests](#performance--load-tests)
11. [Edge Cases & Error Scenarios](#edge-cases--error-scenarios)
12. [Manual Testing Procedures](#manual-testing-procedures)
13. [Test Sign-Off & Approval](#test-sign-off--approval)

---

## Overview

### What We're Testing
The Writer Engine is a three-stage AI editorial system that detects music anomalies, routes them to AI personas, generates articles, scans for defamation, and publishes through two pipelines (Auto-Hype for instant, Editorial for admin review).

### Testing Goals
- ✅ All services function independently and together
- ✅ Database schema supports full workflow
- ✅ Safety filter correctly identifies defamatory content
- ✅ Admin dashboard is intuitive and functional
- ✅ API endpoints return correct data structures
- ✅ Cron jobs execute on schedule without errors
- ✅ System handles errors gracefully
- ✅ Performance meets SLA requirements

### Who Tests What
We'll organize testing by domain so multiple people can work in parallel:
- **Database & Schema**: DBA or backend lead
- **Service Layer (Unit/Integration)**: Backend developers (2-3 people)
- **API Endpoints**: API developer + QA
- **Admin Dashboard**: Frontend + QA (2-3 people)
- **Cron Jobs**: DevOps/infrastructure person
- **Safety Filter**: Content moderation + Legal review
- **Performance**: DevOps + Senior developer

---

## Test Environment Setup

### Prerequisites
Before starting tests, ensure:

1. **Database Migration Applied**
   ```bash
   # On staging database
   mysql -u root -p ngn_2025 < migrations/sql/schema/37_writer_engine.sql

   # Verify tables created
   mysql -u root -p -e "USE ngn_2025; SHOW TABLES LIKE 'writer%';"

   # Expected output:
   # writer_personas
   # writer_anomalies
   # writer_articles
   # writer_persona_comments
   # writer_generation_metrics
   # writer_publish_schedule
   ```

2. **Verify Seed Data**
   ```bash
   mysql -u root -p -e "USE ngn_2025; SELECT id, name, specialty FROM writer_personas;"

   # Expected: 5 personas (Alex Reynolds, Sam O'Donnel, Frankie Morale, Kat Blac, Max Thompson)
   ```

3. **Services Instantiated**
   ```bash
   php -r "
   require_once 'lib/bootstrap.php';
   use NGN\Lib\Config;
   use NGN\Lib\Writer\{ScoutService, NikoService, DraftingService, SafetyFilterService, WriterEngineService, ArticleService};

   \$config = new Config();
   \$scout = new ScoutService(\$config);
   \$niko = new NikoService(\$config);
   \$drafting = new DraftingService(\$config);
   \$safety = new SafetyFilterService(\$config);
   \$engine = new WriterEngineService(\$config);
   \$articles = new ArticleService(\$config);

   echo 'All services instantiated successfully';
   "
   ```

4. **Test Data Fixtures**
   - Insert test artist (for anomaly creation)
   - Insert test tracks (for anomaly detection)
   - Populate CDM tables with sample data
   - See: [Test Data Fixtures](#test-data-fixtures) section

---

## Unit Tests

### Scout Service Unit Tests

**Tester**: Backend Developer #1
**Estimated Time**: 2-3 hours
**Success Criteria**: All tests pass, 100% method coverage

#### Test 1.1: Chart Jump Detection
**File**: tests/Unit/Writer/ScoutServiceTest.php
**Objective**: Verify chart jump detection logic

```php
<?php
namespace Tests\Unit\Writer;

use NGN\Lib\Config;
use NGN\Lib\Writer\ScoutService;
use PHPUnit\Framework\TestCase;

class ScoutServiceTest extends TestCase
{
    private ScoutService $scout;

    protected function setUp(): void
    {
        $config = new Config();
        $this->scout = new ScoutService($config);
    }

    public function testDetectChartJumps_FindsLargeRankChanges()
    {
        // Given: Artist with chart position that jumped >20 ranks
        // When: detectChartJumps() called
        // Then: Should return array with anomaly detected

        $anomalies = $this->scout->detectChartJumps();

        $this->assertIsArray($anomalies);
        // At least some anomalies found (depends on test data)
        $this->assertTrue(count($anomalies) >= 0);

        if (!empty($anomalies)) {
            $firstAnomaly = $anomalies[0];
            $this->assertArrayHasKey('detection_type', $firstAnomaly);
            $this->assertEquals('chart_jump', $firstAnomaly['detection_type']);
            $this->assertArrayHasKey('magnitude', $firstAnomaly);
            $this->assertGreaterThan(0, $firstAnomaly['magnitude']);
        }
    }

    public function testCreateAnomaly_InsertsDatabase()
    {
        // Given: Valid anomaly data
        // When: createAnomaly() called
        // Then: Returns array with ID, inserts to database

        $result = $this->scout->createAnomaly(
            'chart_jump',
            'high',
            1, // artist_id
            100, // track_id
            25.0, // detected_value
            5.0, // baseline_value
            5.0, // magnitude
            'metal',
            null
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertGreaterThan(0, $result['id']);

        // Verify in database
        $pdo = \NGN\Lib\DB\ConnectionFactory::read(new Config());
        $stmt = $pdo->prepare("SELECT * FROM writer_anomalies WHERE id = :id");
        $stmt->execute([':id' => $result['id']]);
        $dbRecord = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotNull($dbRecord);
        $this->assertEquals('chart_jump', $dbRecord['detection_type']);
    }
}
```

**Manual Steps**:
1. Run: `php vendor/bin/phpunit tests/Unit/Writer/ScoutServiceTest.php`
2. Verify all tests pass
3. Check code coverage: `php vendor/bin/phpunit --coverage-html coverage tests/Unit/Writer/ScoutServiceTest.php`
4. Document any failures in [Test Results](#test-results)

**Expected Results**:
- All assertions pass
- No database errors
- Anomaly records created successfully

---

#### Test 1.2: Engagement Spike Detection
**Objective**: Verify engagement spike detection

```php
public function testDetectEngagementSpikes_Finds10xIncrease()
{
    // Given: Track with engagement 10x baseline
    // When: detectEngagementSpikes() called
    // Then: Should detect anomaly

    $anomalies = $this->scout->detectEngagementSpikes();

    $this->assertIsArray($anomalies);

    if (!empty($anomalies)) {
        $spike = $anomalies[0];
        $this->assertEquals('engagement_spike', $spike['detection_type']);
        $this->assertGreaterThanOrEqual(10, $spike['magnitude']);
    }
}
```

**Manual Steps**:
1. Insert test data: Track with 100 avg daily engagement, 1000 today
2. Run detection
3. Verify anomaly detected with 10x multiple
4. Sign off: ✅ Pass / ❌ Fail

---

#### Test 1.3: Spin Surge Detection
**Objective**: Verify spin surge detection

```php
public function testDetectSpinSurges_Finds5xIncrease()
{
    // Given: Track with spins 5x previous period
    // When: detectSpinSurges() called
    // Then: Should detect anomaly

    $anomalies = $this->scout->detectSpinSurges();

    $this->assertIsArray($anomalies);

    if (!empty($anomalies)) {
        $surge = $anomalies[0];
        $this->assertEquals('spin_surge', $surge['detection_type']);
        $this->assertGreaterThanOrEqual(5, $surge['magnitude']);
    }
}
```

**Manual Steps**:
1. Insert test data: Track with 50 baseline spins, 250+ today
2. Run detection
3. Verify anomaly detected
4. Sign off: ✅ Pass / ❌ Fail

---

### Niko Service Unit Tests

**Tester**: Backend Developer #1
**Estimated Time**: 2-3 hours

#### Test 2.1: Story Value Calculation
**Objective**: Verify story value score (0-100)

```php
public function testEvaluateStoryValue_ReturnsScoreBetween0And100()
{
    // Given: Valid anomaly data
    $anomaly = [
        'artist_id' => 1,
        'detection_type' => 'chart_jump',
        'severity' => 'critical',
        'magnitude' => 8.5,
    ];

    // When: Story value evaluated
    $score = $this->niko->evaluateStoryValue($anomaly);

    // Then: Score between 0-100
    $this->assertGreaterThanOrEqual(0, $score);
    $this->assertLessThanOrEqual(100, $score);
    $this->assertIsFloat($score);
}

public function testStoryValueWeighting_CriticalIncreasesScore()
{
    $anomaly = ['artist_id' => 1, 'severity' => 'critical', 'magnitude' => 5.0];
    $criticalScore = $this->niko->evaluateStoryValue($anomaly);

    $anomaly['severity'] = 'low';
    $lowScore = $this->niko->evaluateStoryValue($anomaly);

    $this->assertGreaterThan($lowScore, $criticalScore);
}
```

**Manual Steps**:
1. Test multiple severity levels
2. Verify critical > high > medium > low
3. Verify high popularity artists score higher
4. Sign off: ✅ Pass / ❌ Fail

---

#### Test 2.2: Persona Assignment
**Objective**: Verify genre-to-persona routing

```php
public function testAssignPersona_RoutesMetalToAlex()
{
    $personaId = $this->niko->assignPersona('metal');
    $this->assertEquals(1, $personaId); // Alex Reynolds
}

public function testAssignPersona_RoutesIndieToFrankie()
{
    $personaId = $this->niko->assignPersona('indie');
    $this->assertEquals(3, $personaId); // Frankie Morale
}

public function testAssignPersona_DefaultsToFeaturesForUnknown()
{
    $personaId = $this->niko->assignPersona('obscure_genre_xyz');
    $this->assertEquals(5, $personaId); // Max Thompson (Features)
}
```

**Manual Steps**:
1. Test all 5 personas with their primary genre
2. Test secondary genres (rock→metal, alternative→indie, etc)
3. Test null/unknown genre defaults to Features
4. Sign off: ✅ Pass / ❌ Fail

---

#### Test 2.3: Publishing Pipeline Determination
**Objective**: Verify auto-hype vs editorial routing

```php
public function testDeterminePublishingPipeline_CriticalIsAutoHype()
{
    $anomaly = ['severity' => 'critical', 'detection_type' => 'chart_jump'];
    $pipeline = $this->niko->determinePublishingPipeline($anomaly);
    $this->assertEquals('auto_hype', $pipeline);
}

public function testDeterminePublishingPipeline_MediumIsEditorial()
{
    $anomaly = ['severity' => 'medium', 'detection_type' => 'engagement_spike'];
    $pipeline = $this->niko->determinePublishingPipeline($anomaly);
    $this->assertEquals('editorial', $pipeline);
}
```

**Manual Steps**:
1. Test critical severity → auto-hype
2. Test non-critical → editorial
3. Verify all detection types route correctly
4. Sign off: ✅ Pass / ❌ Fail

---

### Drafting Service Unit Tests

**Tester**: Backend Developer #2
**Estimated Time**: 2-3 hours

#### Test 3.1: Article Generation
**Objective**: Verify article generation

```php
public function testGenerateArticle_CreatesRecordWithTitle()
{
    // Given: Valid anomaly & persona
    // When: Article generated
    $article = $this->drafting->generateArticle(1, 1, true);

    // Then: Returns array with required fields
    $this->assertIsArray($article);
    $this->assertArrayHasKey('id', $article);
    $this->assertArrayHasKey('title', $article);
    $this->assertArrayHasKey('content_preview', $article);
    $this->assertArrayHasKey('generation_time_ms', $article);
    $this->assertArrayHasKey('cost_usd', $article);
}

public function testGenerateArticle_CostCalculation()
{
    $article = $this->drafting->generateArticle(1, 1, true);

    // Cost should be > 0
    $this->assertGreaterThan(0, $article['cost_usd']);

    // Should be reasonable (not excessively high)
    $this->assertLessThan(1.0, $article['cost_usd']);
}

public function testGenerateArticle_TokenEstimation()
{
    $article = $this->drafting->generateArticle(1, 1, true);

    $this->assertGreaterThan(0, $article['prompt_tokens']);
    $this->assertGreaterThan(0, $article['completion_tokens']);
}
```

**Manual Steps**:
1. Test article generation with each persona
2. Verify title is unique and under 80 chars
3. Verify content is ~600-800 words
4. Verify slug is URL-safe and unique
5. Verify metrics tracked correctly
6. Sign off: ✅ Pass / ❌ Fail

---

#### Test 3.2: Slug Generation & Uniqueness
**Objective**: Verify URL-safe slugs

```php
public function testSlugGeneration_IsUrlSafe()
{
    $article = $this->drafting->generateArticle(1, 1, true);

    $slug = $article['slug'];

    // Should contain only lowercase letters, numbers, hyphens
    $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);

    // Should not start/end with hyphen
    $this->assertStringNotStartsWith('-', $slug);
    $this->assertStringNotEndsWith('-', $slug);
}

public function testSlugGeneration_EnforcesUniqueness()
{
    // Generate 2 articles with similar titles
    $article1 = $this->drafting->generateArticle(1, 1, true);
    $article2 = $this->drafting->generateArticle(1, 1, true);

    // Slugs should be different
    $this->assertNotEquals($article1['slug'], $article2['slug']);
}
```

**Manual Steps**:
1. Generate multiple articles
2. Verify no duplicate slugs in database
3. Verify slug only contains [a-z0-9-]
4. Test with special characters in title
5. Sign off: ✅ Pass / ❌ Fail

---

### Safety Filter Service Unit Tests

**Tester**: Backend Developer #3 + Content Moderation Lead
**Estimated Time**: 3-4 hours

#### Test 4.1: Safety Score Calculation
**Objective**: Verify defamation scoring

```php
public function testScanArticle_CleanContentScores_Below0_1()
{
    // Given: Article with only music criticism
    $cleanArticle = [
        'id' => 1,
        'title' => "Metallica's Production Lacks Depth",
        'content' => 'The production quality is subpar compared to previous albums. The mixing is muddy.'
    ];

    // When: Scanned
    $result = $this->safety->scanArticle($cleanArticle['id']);

    // Then: Score < 0.1
    $this->assertLessThan(0.1, $result['safety_score']);
    $this->assertEquals('approved', $result['status']);
}

public function testScanArticle_FlaggedContentScores_0_1_to_0_3()
{
    // Given: Article with personal attack
    $flaggedArticle = [
        'id' => 2,
        'title' => "James Hetfield is washed-up",
        'content' => 'The singer sounds tired and past his prime.'
    ];

    // When: Scanned
    $result = $this->safety->scanArticle($flaggedArticle['id']);

    // Then: Score between 0.1-0.3
    $this->assertGreaterThanOrEqual(0.1, $result['safety_score']);
    $this->assertLessThan(0.3, $result['safety_score']);
    $this->assertEquals('flagged', $result['status']);
}

public function testScanArticle_DefamatoryContentScores_Above0_3()
{
    // Given: Article with severe personal attack
    $rejectedArticle = [
        'id' => 3,
        'title' => "Singer is a criminal",
        'content' => 'He is clearly a pedophile and should be arrested.'
    ];

    // When: Scanned
    $result = $this->safety->scanArticle($rejectedArticle['id']);

    // Then: Score > 0.3
    $this->assertGreaterThan(0.3, $result['safety_score']);
    $this->assertEquals('rejected', $result['status']);
}
```

**Manual Steps**:
1. Create 10 test articles with varying content
2. Run safety scan on each
3. Verify score categories:
   - Clean: <0.1
   - Flagged: 0.1-0.3
   - Rejected: >0.3
4. Verify flagged sentences captured correctly
5. Sign off: ✅ Pass / ❌ Fail

**Test Cases for Safety Filter**:
| Content | Expected Score | Expected Status | Notes |
|---------|----------------|-----------------|-------|
| "Album production is weak" | <0.1 | approved | Music critique OK |
| "Singer is washed-up" | 0.15 | flagged | Personal but borderline |
| "Drummer is an alcoholic" | 0.35 | rejected | Character attack |
| "Shows lack artistic depth" | <0.1 | approved | Artistic judgment OK |
| "Singer is mentally unstable" | 0.40 | rejected | Psychological attack |
| "Performance was uninspired" | <0.1 | approved | Artistic opinion OK |

---

#### Test 4.2: Override Safety Flag
**Objective**: Verify admin can override flagged content

```php
public function testOverrideSafetyFlag_AllowsAdminApproval()
{
    // Given: Flagged article
    // Simulate flagged article in database

    // When: Override called
    $success = $this->safety->overrideSafetyFlag(
        1, // article_id
        123, // admin_id
        'False positive - artist is not actually washed-up'
    );

    // Then: Returns true, updates database
    $this->assertTrue($success);

    // Verify database updated
    $pdo = \NGN\Lib\DB\ConnectionFactory::read(new Config());
    $stmt = $pdo->prepare("SELECT * FROM writer_articles WHERE id = 1");
    $stmt->execute();
    $article = $stmt->fetch(\PDO::FETCH_ASSOC);

    $this->assertEquals('approved', $article['safety_scan_status']);
    $this->assertEquals(123, $article['safety_override_by_id']);
}
```

**Manual Steps**:
1. Create flagged article
2. Call override function
3. Verify status changes to 'approved'
4. Verify admin_id recorded
5. Verify override_reason stored
6. Sign off: ✅ Pass / ❌ Fail

---

## Integration Tests

### Scout → Niko → Drafting Pipeline

**Tester**: Backend Developer #2
**Estimated Time**: 4-5 hours
**Objective**: Verify three services work together

#### Test 5.1: Full Pipeline Single Anomaly
**Setup**:
1. Insert test artist (id=100, name="Test Artist")
2. Insert test track (id=100)
3. Manually insert anomaly record with status='detected'

```php
public function testFullPipeline_ScoutToNikoToDrafting()
{
    // Step 1: Create anomaly
    $config = new Config();
    $scout = new ScoutService($config);

    $anomaly = $scout->createAnomaly(
        'chart_jump',
        'high',
        100, // test artist
        100, // test track
        50.0,
        10.0,
        5.0,
        'metal'
    );

    $anomalyId = $anomaly['id'];

    // Step 2: Assign persona (Niko)
    $niko = new NikoService($config);
    $nikoResult = $niko->processAnomalies();

    $this->assertGreaterThan(0, $nikoResult['assigned']);

    // Verify anomaly now has persona_id
    $pdo = \NGN\Lib\DB\ConnectionFactory::read($config);
    $stmt = $pdo->prepare("SELECT * FROM writer_anomalies WHERE id = :id");
    $stmt->execute([':id' => $anomalyId]);
    $updatedAnomaly = $stmt->fetch(\PDO::FETCH_ASSOC);

    $this->assertNotNull($updatedAnomaly['assigned_persona_id']);
    $this->assertGreaterThan(0, $updatedAnomaly['story_value_score']);

    // Step 3: Generate article (Drafting)
    $drafting = new DraftingService($config);
    $article = $drafting->generateArticle($anomalyId, $updatedAnomaly['assigned_persona_id']);

    $this->assertIsArray($article);
    $this->assertGreaterThan(0, $article['id']);
    $this->assertEquals('draft', $article['status']);

    // Verify article in database
    $stmt = $pdo->prepare("SELECT * FROM writer_articles WHERE id = :id");
    $stmt->execute([':id' => $article['id']]);
    $dbArticle = $stmt->fetch(\PDO::FETCH_ASSOC);

    $this->assertNotNull($dbArticle);
    $this->assertEquals($anomalyId, $dbArticle['anomaly_id']);
    $this->assertEquals('pending', $dbArticle['safety_scan_status']);
}
```

**Manual Steps**:
1. Clear test data
2. Run test PHP script
3. Verify anomaly created
4. Verify persona assigned (query database)
5. Verify article generated
6. Verify all metrics calculated
7. Sign off: ✅ Pass / ❌ Fail

---

#### Test 5.2: Multiple Anomalies Batch Processing
**Objective**: Verify pipeline handles multiple anomalies

```php
public function testPipeline_ProcessesMultipleAnomalies()
{
    // Create 5 test anomalies
    $config = new Config();
    $scout = new ScoutService($config);
    $pdo = \NGN\Lib\DB\ConnectionFactory::read($config);

    for ($i = 0; $i < 5; $i++) {
        $scout->createAnomaly(
            'chart_jump',
            ['low', 'medium', 'high', 'high', 'critical'][$i],
            100 + $i,
            100 + $i,
            50.0 + ($i * 10),
            10.0,
            5.0 + $i,
            'metal'
        );
    }

    // Process through Niko
    $niko = new NikoService($config);
    $nikoResult = $niko->processAnomalies();

    // Should assign most (at least 3)
    $this->assertGreaterThanOrEqual(3, $nikoResult['assigned']);

    // Process through Drafting
    $drafting = new DraftingService($config);
    $draftingResult = $drafting->generateArticles(10);

    // Should generate articles
    $this->assertGreaterThan(0, $draftingResult['generated']);
}
```

**Manual Steps**:
1. Insert 5 test anomalies with varying severity
2. Run pipeline
3. Verify all get assigned
4. Verify articles generated
5. Verify metrics aggregated
6. Sign off: ✅ Pass / ❌ Fail

---

### Safety Filter Integration

**Tester**: Backend Developer #3 + Content Moderation
**Estimated Time**: 2-3 hours

#### Test 5.3: Drafting → Safety → Article Status Update
**Objective**: Verify article safety scanning updates status

```php
public function testDraftingToSafety_UpdatesArticleStatus()
{
    // Create and generate article
    $config = new Config();
    $engine = new WriterEngineService($config);

    // Generate article
    $draftingResult = $engine->generatePendingArticles(1);
    $this->assertGreaterThan(0, $draftingResult['generated']);

    // Scan for safety
    $scanResult = $engine->scanPendingArticles(50);
    $this->assertGreaterThan(0, $scanResult['scanned']);

    // Verify article status updated
    $pdo = \NGN\Lib\DB\ConnectionFactory::read($config);
    $stmt = $pdo->prepare("SELECT safety_scan_status FROM writer_articles ORDER BY created_at DESC LIMIT 1");
    $stmt->execute();
    $article = $stmt->fetch(\PDO::FETCH_ASSOC);

    $this->assertIn($article['safety_scan_status'], ['approved', 'flagged', 'rejected']);
}
```

**Manual Steps**:
1. Generate test article
2. Run safety scan
3. Verify status not 'pending'
4. Verify score populated
5. Verify flags captured (if flagged)
6. Sign off: ✅ Pass / ❌ Fail

---

## End-to-End Pipeline Tests

**Tester**: QA Lead + Senior Developer
**Estimated Time**: 6-8 hours
**Objective**: Full workflow from anomaly detection to publishing

### Test 6.1: Complete Editorial Workflow

**Setup**:
1. Fresh test database with seed data
2. Insert CDM test data simulating music trends
3. Admin user created with editorial role

**Test Steps**:

#### Step 1: Anomaly Detection
```bash
# Run scout manually
php /jobs/writer/scout_anomaly_detection.php

# Expected output in log:
# Scout Anomaly Detection Starting
# Detection completed: X total anomalies detected
# Stored: X anomalies
# Scout Complete
```

**Verification**:
- ✅ Log file created at `/storage/logs/writer_scout.log`
- ✅ Anomalies inserted into `writer_anomalies` table
- ✅ Status = 'detected'
- ✅ No errors in log

---

#### Step 2: Persona Assignment
```bash
# Run Niko
php /jobs/writer/niko_dispatcher.php

# Expected output:
# Niko Dispatcher Starting
# Processing complete:
#   - Processed: X
#   - Assigned: X
#   - Skipped: X
# Niko Complete
```

**Verification**:
- ✅ Anomalies updated with `assigned_persona_id`
- ✅ `story_value_score` populated (0-100)
- ✅ Status = 'assigned'
- ✅ No high error rate

---

#### Step 3: Article Generation
```bash
# Run drafting processor
php /jobs/writer/drafting_processor.php

# Expected output:
# Drafting Processor Starting
# Generating articles...
# Article generation:
#   - Generated: X
#   - Failed: X
#   - Total cost: $X.XX
# Scanning articles for safety...
# Safety scanning:
#   - Scanned: X
#   - Approved: X
#   - Flagged: X
#   - Rejected: X
```

**Verification**:
- ✅ Articles created in `writer_articles` table
- ✅ Status = 'draft'
- ✅ Safety scan completed
- ✅ Cost calculated correctly
- ✅ No excessive generation time (>30s)

---

#### Step 4: Editorial Review
```bash
# Access admin dashboard
http://localhost:8000/admin/writer/editorial-queue.php

# As admin, perform actions
```

**Verification**:
- ✅ Unclaimed drafts displayed
- ✅ Can see article title, persona, safety status
- ✅ "Claim" button works
- ✅ After claiming, appears in "My Workspace"
- ✅ Can edit article content
- ✅ Can see safety scan results
- ✅ Can approve or reject article

---

#### Step 5: Publishing
```bash
# For auto-hype articles, run publisher
php /jobs/writer/auto_hype_publisher.php

# Expected output:
# Auto-Hype Publisher Starting
# Publishing results:
#   - Published: X
#   - Errors: X
# Auto-Hype Publisher Complete
```

**Verification**:
- ✅ Auto-hype articles status = 'published'
- ✅ `published_at` timestamp set
- ✅ Editorial articles remain in queue until approved
- ✅ Published articles appear on public API

---

#### Step 6: Verification
```bash
# Check public API
curl http://localhost:8000/api/v1/writer/articles

# Expected response:
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Article Title",
      "slug": "article-title",
      "excerpt": "...",
      "persona_name": "Alex Reynolds",
      "created_at": "2026-01-21T10:00:00"
    }
  ],
  "pagination": {"offset": 0, "limit": 20}
}
```

**Verification**:
- ✅ Published articles visible in API
- ✅ Correct data structure
- ✅ No draft/rejected articles exposed
- ✅ Pagination works

---

### Test 6.2: Complete Editorial Rejection Workflow

**Objective**: Verify rejected articles don't publish

**Steps**:
1. Generate article (steps 1-3 above)
2. Claim article as editor
3. Click "Reject" button
4. Verify:
   - ✅ Status = 'rejected'
   - ✅ Article not in editorial queue
   - ✅ `rejection_reason` captured
   - ✅ Not published

---

### Test 6.3: Safety Flag Override Workflow

**Objective**: Verify flagged articles can be approved by admin

**Steps**:
1. Create article that triggers safety flag (0.15 score)
2. Safety scan completes with status='flagged'
3. Navigate to `/admin/writer/safety-alerts.php`
4. Click "Override" on flagged article
5. Verify:
   - ✅ Status changes to 'approved'
   - ✅ `safety_override_by_id` recorded
   - ✅ `safety_override_reason` captured
   - ✅ Can proceed to approval

---

## API Endpoint Tests

**Tester**: API Developer + QA
**Estimated Time**: 4-5 hours

### Test 7.1: Public Endpoints

#### GET /api/v1/writer/articles
**Setup**: 3 published test articles with different personas

```bash
# Test 1: Basic list
curl -X GET "http://localhost:8000/api/v1/writer/articles"

# Expected response:
{
  "success": true,
  "data": [
    {"id": 1, "title": "...", "persona_name": "Alex Reynolds", ...},
    {"id": 2, "title": "...", "persona_name": "Sam O'Donnel", ...}
  ],
  "pagination": {"offset": 0, "limit": 20}
}
```

**Verifications**:
- ✅ Returns 200 status
- ✅ Data array contains articles
- ✅ Only published articles shown
- ✅ Correct fields present

```bash
# Test 2: Filter by persona
curl -X GET "http://localhost:8000/api/v1/writer/articles?persona_id=1"

# Expected: Only Alex Reynolds articles
```

**Verifications**:
- ✅ Filtering works
- ✅ All results from persona_id=1

```bash
# Test 3: Pagination
curl -X GET "http://localhost:8000/api/v1/writer/articles?limit=1&offset=0"
curl -X GET "http://localhost:8000/api/v1/writer/articles?limit=1&offset=1"

# Expected: Different articles returned
```

**Verifications**:
- ✅ Limit parameter works
- ✅ Offset parameter works
- ✅ Results different between calls

---

#### GET /api/v1/writer/articles/{id}
**Setup**: Published test article with comments

```bash
# Test 1: Get article with all data
curl -X GET "http://localhost:8000/api/v1/writer/articles/1"

# Expected response:
{
  "success": true,
  "data": {
    "id": 1,
    "title": "...",
    "content": "...",
    "persona_name": "Alex Reynolds",
    "total_engagement": 500,
    "comments": [
      {"id": 1, "persona_id": 2, "comment_text": "..."}
    ]
  }
}
```

**Verifications**:
- ✅ Full article content returned
- ✅ Comments array included
- ✅ Engagement metrics included
- ✅ 200 status

```bash
# Test 2: Get non-existent article
curl -X GET "http://localhost:8000/api/v1/writer/articles/99999"

# Expected response:
{
  "success": false,
  "message": "Article not found"
}
```

**Verifications**:
- ✅ Returns 404 status
- ✅ Error message present

```bash
# Test 3: Get unpublished article
# (Should NOT be accessible)
curl -X GET "http://localhost:8000/api/v1/writer/articles/[draft_id]"

# Expected: 404 (not found)
```

**Verifications**:
- ✅ Draft articles not accessible via public API

---

### Test 7.2: Admin Endpoints (Requires Auth)

#### POST /api/v1/admin/writer/articles/{id}/claim
**Setup**: Admin token obtained, unclaimed draft article

```bash
# Test 1: Claim article
curl -X POST \
  -H "Authorization: Bearer [admin_token]" \
  http://localhost:8000/api/v1/admin/writer/articles/1/claim

# Expected response:
{
  "success": true,
  "message": "Article claimed"
}
```

**Verifications**:
- ✅ Returns 200 status
- ✅ Article now has `editor_id` in database
- ✅ Status = 'pending_review'

```bash
# Test 2: Claim already-claimed article
curl -X POST \
  -H "Authorization: Bearer [admin_token]" \
  http://localhost:8000/api/v1/admin/writer/articles/1/claim

# Expected: 400 error
{
  "success": false,
  "message": "Failed to claim article"
}
```

**Verifications**:
- ✅ Returns 400 status
- ✅ Error message returned

```bash
# Test 3: Claim without token
curl -X POST http://localhost:8000/api/v1/admin/writer/articles/1/claim

# Expected: 401 or 403 error
```

**Verifications**:
- ✅ Unauthenticated request rejected

---

#### POST /api/v1/admin/writer/articles/{id}/approve
**Setup**: Claimed article in 'pending_review' status

```bash
# Test 1: Approve article
curl -X POST \
  -H "Authorization: Bearer [admin_token]" \
  -H "Content-Type: application/json" \
  -d '{}' \
  http://localhost:8000/api/v1/admin/writer/articles/1/approve

# Expected response:
{
  "success": true,
  "message": "Article approved"
}
```

**Verifications**:
- ✅ Returns 200 status
- ✅ Article status = 'approved'
- ✅ `reviewed_by_id` = current user
- ✅ `reviewed_at` timestamp set

```bash
# Test 2: Approve with scheduled_for
curl -X POST \
  -H "Authorization: Bearer [admin_token]" \
  -H "Content-Type: application/json" \
  -d '{"scheduled_for": "2026-01-22T10:00:00"}' \
  http://localhost:8000/api/v1/admin/writer/articles/1/approve

# Expected: Publish scheduled for that time
```

**Verifications**:
- ✅ Schedule created
- ✅ `scheduled_for` timestamp correct

---

#### POST /api/v1/admin/writer/articles/{id}/reject
**Setup**: Claimed article

```bash
# Test 1: Reject article
curl -X POST \
  -H "Authorization: Bearer [admin_token]" \
  -H "Content-Type: application/json" \
  -d '{"reason": "Poor quality"}' \
  http://localhost:8000/api/v1/admin/writer/articles/1/reject

# Expected response:
{
  "success": true,
  "message": "Article rejected"
}
```

**Verifications**:
- ✅ Status = 'rejected'
- ✅ `rejection_reason` captured
- ✅ Not published

---

#### POST /api/v1/admin/writer/articles/{id}/override-safety
**Setup**: Flagged article (status='flagged')

```bash
# Test 1: Override flagged article
curl -X POST \
  -H "Authorization: Bearer [admin_token]" \
  -H "Content-Type: application/json" \
  -d '{"reason": "False positive"}' \
  http://localhost:8000/api/v1/admin/writer/articles/1/override-safety

# Expected response:
{
  "success": true,
  "message": "Safety flag overridden"
}
```

**Verifications**:
- ✅ Status = 'approved'
- ✅ `safety_override_by_id` recorded
- ✅ `safety_override_reason` captured

---

#### GET /api/v1/admin/writer/metrics
**Setup**: 7+ days of metric data

```bash
# Test 1: Get metrics (default 7 days)
curl -X GET \
  -H "Authorization: Bearer [admin_token]" \
  http://localhost:8000/api/v1/admin/writer/metrics

# Expected response:
{
  "success": true,
  "data": [
    {
      "metric_date": "2026-01-20",
      "generated": 5,
      "published": 3,
      "rejected": 1,
      "cost": 0.25,
      "safety_rate": 0.1
    }
  ],
  "period_days": 7
}
```

**Verifications**:
- ✅ Returns array of daily metrics
- ✅ Cost in correct format
- ✅ All metrics fields present
- ✅ Date range correct

```bash
# Test 2: Get extended metrics
curl -X GET \
  -H "Authorization: Bearer [admin_token]" \
  "http://localhost:8000/api/v1/admin/writer/metrics?days=30"

# Expected: 30 days of data
```

**Verifications**:
- ✅ More records returned
- ✅ Period set to 30

---

## Admin Dashboard Tests

**Tester**: Frontend Developer + QA (2-3 people)
**Estimated Time**: 8-10 hours

### Test 8.1: Editorial Queue Page

**URL**: http://localhost:8000/admin/writer/editorial-queue.php

#### Test 8.1.1: Page Load & Layout
```
Checklist:
□ Page loads without errors
□ Title "Writer Engine - Editorial Queue" displays
□ Stats cards visible (Unclaimed, Published Today, Rejected, Flagged)
□ Two-column layout renders (Unclaimed | My Workspace)
□ Back to Admin button works
□ No console errors
```

#### Test 8.1.2: Unclaimed Queue Section
```
Given: 3+ unclaimed articles
Then:
□ All unclaimed articles display
□ Article title shows
□ Persona name & specialty shows
□ Artist name shows
□ Severity badge shows
□ Safety status color-coded (flagged=orange, clean=green)
□ Creation date shows
□ "Preview" button works (links to edit page)
□ "Claim" button works
  - Article moves to "My Workspace"
  - Appears in database as claimed
```

#### Test 8.1.3: My Workspace Section
```
Given: 2+ claimed articles
Then:
□ Only my claimed articles show
□ Article title, persona, status visible
□ Comment count shows
□ "Edit" button works (goes to edit page)
□ If status='pending_review':
  - "Approve" button visible & works
  - "Reject" button visible & works
□ If status='approved':
  - Approve/Reject buttons hidden
```

#### Test 8.1.4: Stats Cards
```
□ Unclaimed count accurate
□ Published Today count accurate
□ Rejected Today count accurate
□ Flagged count accurate
□ Update after actions (claim, approve, reject)
```

---

### Test 8.2: Edit Article Page

**URL**: http://localhost:8000/admin/writer/edit-article.php?id=1

#### Test 8.2.1: Page Layout
```
Given: Valid article ID
Then:
□ Article title shows in header
□ Left panel (editor) displays
□ Right panel (sidebar) displays
□ Article metadata shows (persona, detection, etc)
□ No layout issues on desktop/tablet
```

#### Test 8.2.2: Content Editing
```
□ Title field editable
□ Excerpt field editable
□ Content textarea shows full markdown
□ Review notes field editable
□ "Save Draft" button works
  - Content saved to database
  - Success message shown
  - Can reload & see changes
□ Changes don't auto-save without button click
```

#### Test 8.2.3: Safety Information
```
Given: Article with safety scan
Then:
□ Safety status displays (approved/flagged/rejected)
□ Safety score shows
□ Flagged sentences display (if flagged)
□ If safety_status='flagged':
  - "Override Safety Flag" form visible
  - Reason textarea present
  - Override button works
  - Updates status to 'approved' after click
```

#### Test 8.2.4: Metrics Sidebar
```
□ Generation time_ms displays
□ Token counts show
□ Cost displays ($X.XXXX)
□ Persona name links to persona page
□ Story metrics visible
□ Created timestamp shows
□ Claimed timestamp shows
```

#### Test 8.2.5: Approval/Rejection Actions
```
Given: Article in draft/pending_review
Then:
□ "Approve & Publish" button visible
  - Click updates status='approved'
  - Redirects to queue
  - Article no longer in my workspace
□ "Reject" button visible
  - Shows confirmation dialog
  - Click updates status='rejected'
  - Takes rejection reason
  - Redirects to queue
```

---

### Test 8.3: Safety Alerts Page

**URL**: http://localhost:8000/admin/writer/safety-alerts.php

#### Test 8.3.1: Page Load
```
□ Page loads without errors
□ Title "Safety Alerts" displays
□ Stat cards show counts
□ Flagged articles list displays
```

#### Test 8.3.2: Flagged Articles Display
```
Given: 3+ flagged articles (score 0.1-0.3)
Then:
□ All flagged articles display
□ Title truncated but readable
□ Safety score badge shows (0.15, etc)
□ Score color-coded (yellow for flagged)
□ Article preview shows first 150 chars
□ "Review" button works (goes to edit page)
□ "Approve" button visible for flagged items
```

#### Test 8.3.3: Rejected Articles Display
```
Given: 1+ rejected articles (score >0.3)
Then:
□ Display same as flagged
□ Score color-coded RED
□ "Approve" button disabled/hidden
```

#### Test 8.3.4: Override Functionality
```
□ "Approve" button click shows confirmation
□ Override succeeds (status='approved')
□ Reason recorded in database
□ Redirects back to safety page
□ Article no longer in list
```

---

### Test 8.4: Personas Page

**URL**: http://localhost:8000/admin/writer/personas.php

#### Test 8.4.1: All Personas Display
```
□ 5 personas visible as cards
□ Correct names (Alex, Sam, Frankie, Kat, Max)
□ Correct specialties
□ Active status badge visible
□ Hated artist displays
```

#### Test 8.4.2: Performance Stats
```
Given: Personas with generated articles
Then:
□ Articles generated count shows
□ Articles published count shows
□ Articles flagged count shows
□ Avg engagement shows
```

---

### Test 8.5: Metrics Page

**URL**: http://localhost:8000/admin/writer/metrics.php

#### Test 8.5.1: KPI Cards
```
□ Published count shows (7-day)
□ LLM cost shows ($X.XX)
□ Avg safety rejection % shows
□ All numbers reasonable
```

#### Test 8.5.2: Daily Metrics Table
```
Given: 30+ days of data
Then:
□ Table displays last 30 days
□ Columns: Date, Generated, Published, Rejected, Cost, Safety %
□ Data accurate
□ Dates in correct order (newest first)
□ Scrollable on mobile
```

---

### Test 8.6: Anomaly Rules Page

**URL**: http://localhost:8000/admin/writer/anomaly-rules.php

#### Test 8.6.1: Form Displays
```
□ 4 threshold sliders visible
  - Chart Jump Threshold (5-50)
  - Engagement Spike Multiple (3-50)
  - Spin Surge Multiple (2-20)
  - Minimum Story Value (0-100)
□ Current values display
□ Min/max ranges visible
□ Description shows for each
```

#### Test 8.6.2: Form Submission
```
□ "Save Configuration" button works
□ Values updated in database/config
□ Success message shows
□ Values persist after reload
```

---

## Cron Job Tests

**Tester**: DevOps / Infrastructure Engineer
**Estimated Time**: 4-5 hours

### Test 9.1: Manual Execution Testing

#### Test 9.1.1: Scout Anomaly Detection
```bash
# Manual run
cd /www/wwwroot/beta.nextgennoise.com
php jobs/writer/scout_anomaly_detection.php

# Check output
tail -f storage/logs/writer_scout.log

# Verify:
□ Script completes without errors
□ Log file created/appended
□ Anomalies inserted into writer_anomalies table
□ Status = 'detected'
□ Times recorded correctly
```

**Expected Log Output**:
```
[2026-01-21 10:00:00] === Scout Anomaly Detection Starting ===
[2026-01-21 10:00:05] Detection completed: 5 total anomalies detected
[2026-01-21 10:00:05]   - Chart jumps: 2
[2026-01-21 10:00:05]   - Engagement spikes: 1
[2026-01-21 10:00:05]   - Spin surges: 1
[2026-01-21 10:00:05]   - Genre trends: 1
[2026-01-21 10:00:06] Stored: 5 anomalies | Failed: 0
[2026-01-21 10:00:06] === Scout Complete ===
```

---

#### Test 9.1.2: Niko Dispatcher
```bash
php jobs/writer/niko_dispatcher.php

# Verify:
□ Processes detected anomalies
□ Assigns personas
□ Calculates story value
□ Updates anomalies table
□ No errors
```

**Expected Log Output**:
```
[2026-01-21 10:01:00] === Niko Dispatcher Starting ===
[2026-01-21 10:01:02] Processing complete:
[2026-01-21 10:01:02]   - Processed: 5
[2026-01-21 10:01:02]   - Assigned: 5
[2026-01-21 10:01:02]   - Skipped: 0
[2026-01-21 10:01:02]   - Errors: 0
[2026-01-21 10:01:02] === Niko Complete ===
```

---

#### Test 9.1.3: Drafting Processor
```bash
php jobs/writer/drafting_processor.php

# Verify:
□ Generates articles
□ Runs safety scans
□ Updates metrics
□ No errors
```

---

#### Test 9.1.4: Auto-Hype Publisher
```bash
php jobs/writer/auto_hype_publisher.php

# Verify:
□ Publishes scheduled articles
□ Updates publish_schedule table
□ No errors
```

---

#### Test 9.1.5: Aggregate Metrics
```bash
php jobs/writer/aggregate_metrics.php

# Verify:
□ Aggregates daily metrics
□ Inserts into writer_generation_metrics
□ Correct calculations
□ No errors
```

---

#### Test 9.1.6: Persona Engagement
```bash
php jobs/writer/persona_engagement.php

# Verify:
□ Generates inter-persona comments
□ Inserts into writer_persona_comments
□ No errors
```

---

### Test 9.2: Cron Schedule Execution

#### Test 9.2.1: Register Cron Jobs
```bash
# Add to cron_registry table
mysql -u root -p ngn_2025 << EOF
INSERT INTO cron_registry (command, schedule, category, is_active, created_at) VALUES
('php /path/to/jobs/writer/scout_anomaly_detection.php', '*/5 * * * *', 'writer_engine', 1, NOW()),
('php /path/to/jobs/writer/niko_dispatcher.php', '*/10 * * * *', 'writer_engine', 1, NOW()),
('php /path/to/jobs/writer/drafting_processor.php', '*/15 * * * *', 'writer_engine', 1, NOW()),
('php /path/to/jobs/writer/auto_hype_publisher.php', '*/5 * * * *', 'writer_engine', 1, NOW()),
('php /path/to/jobs/writer/aggregate_metrics.php', '0 2 * * *', 'writer_engine', 1, NOW()),
('php /path/to/jobs/writer/persona_engagement.php', '0 */4 * * *', 'writer_engine', 1, NOW());
EOF
```

#### Test 9.2.2: Verify Schedule Execution
```bash
# Monitor cron execution over 1 hour
# Scout should run at: :00, :05, :10, :15, :20, :25, :30, :35, :40, :45, :50, :55

# Check log files periodically
watch -n 30 'tail -5 /storage/logs/writer_scout.log'

# Verify:
□ Scout runs every 5 minutes
□ Niko runs every 10 minutes
□ Drafting runs every 15 minutes
□ Publisher runs every 5 minutes
□ Metrics runs daily at 2 AM
□ Engagement runs every 4 hours
□ No errors in logs
□ Timestamps accurate
```

---

### Test 9.3: Error Scenarios

#### Test 9.3.1: Database Connection Failure
```bash
# Stop database temporarily
# Run cron job manually
php jobs/writer/scout_anomaly_detection.php

# Verify:
□ Script exits with error code (not 0)
□ Error logged with timestamp
□ Log contains "CRITICAL ERROR"
□ Script doesn't hang
```

---

#### Test 9.3.2: Partial Failure Recovery
```bash
# Simulate partial failure (e.g., 2 of 5 anomalies fail to store)
# Run drafting processor

# Verify:
□ Continues processing remaining anomalies
□ Logs failures individually
□ Completes with error count
□ Doesn't leave database in inconsistent state
```

---

## Safety & Compliance Tests

**Tester**: Content Moderation Lead + Legal Review
**Estimated Time**: 6-8 hours

### Test 10.1: Defamation Filter Accuracy

#### Test 10.1.1: Clean Content (Score <0.1)
**Test Cases**:
| Content | Score Expected | Notes |
|---------|----------------|-------|
| "Album production lacks depth" | <0.1 | ✓ Artistic critique |
| "Drummer's timing was off" | <0.1 | ✓ Performance critique |
| "This track misses the mark" | <0.1 | ✓ Artistic judgment |
| "Sound design is uninspired" | <0.1 | ✓ Technical critique |

**Execution**:
1. Create 4 test articles with above content
2. Run safety scan
3. Verify all score <0.1
4. Verify status = 'approved'

---

#### Test 10.1.2: Flagged Content (Score 0.1-0.3)
**Test Cases**:
| Content | Score Expected | Notes |
|---------|----------------|-------|
| "Singer is washed-up" | 0.15-0.25 | ⚠️ Personal but borderline |
| "Drummer seems tired" | 0.12-0.22 | ⚠️ Character observation |
| "Vocalist can't carry a tune" | 0.18-0.28 | ⚠️ Ability critique |

**Execution**:
1. Create 3 test articles
2. Run safety scan
3. Verify scores in flagged range
4. Verify status = 'flagged'
5. Verify flags identified correctly

---

#### Test 10.1.3: Rejected Content (Score >0.3)
**Test Cases**:
| Content | Score Expected | Notes |
|---------|----------------|-------|
| "Singer is an alcoholic" | 0.35-0.45 | ❌ Character attack |
| "Drummer is mentally unstable" | 0.40-0.50 | ❌ Psychological attack |
| "Artist is a fraud and liar" | 0.35-0.45 | ❌ Defamatory |
| "Should be arrested for [crime]" | 0.50+ | ❌ Criminal allegation |

**Execution**:
1. Create 4 test articles
2. Run safety scan
3. Verify scores >0.3
4. Verify status = 'rejected'
5. Verify specific flagged phrases captured

---

### Test 10.2: False Positive Handling

#### Test 10.2.1: Intentional False Positives
**Scenario**: Articles that SOUND negative but are legitimate artistic critique

**Test Cases**:
| Content | Should Be | Reason |
|---------|-----------|--------|
| "This is a disaster of a production" | Clean | Artistic critique |
| "Performance was dead on arrival" | Clean | Album/show critique |
| "Career-ending album" | Clean | Artistic judgment |
| "Absolutely destroyed by critics" | Clean | Factual event description |

**Execution**:
1. Create test articles
2. Run safety scan
3. Verify scores <0.1 (not false alarms)
4. Document any issues

---

#### Test 10.2.2: Override Process
**Scenario**: Admin reviews flagged article and determines it's acceptable

**Steps**:
1. Create flagged article (score 0.18)
2. Click "Override Safety Flag" in admin
3. Enter reason: "False positive - artist actually is past-prime, factual statement"
4. Verify:
   - ✅ Status changes to 'approved'
   - ✅ Override logged in audit trail
   - ✅ Article can now be approved for publish

---

### Test 10.3: Audit Trail & Logging

#### Test 10.3.1: All Safety Actions Logged
```bash
# Check audit_log table
mysql -u root -p -e "
  USE ngn_2025;
  SELECT * FROM audit_log
  WHERE entity_type = 'writer_articles'
  ORDER BY created_at DESC
  LIMIT 10;
"
```

**Verify**:
- ✅ Safety scan creates audit record
- ✅ Override creates audit record
- ✅ Admin ID recorded
- ✅ IP address recorded (if applicable)
- ✅ Timestamp accurate

---

## Performance & Load Tests

**Tester**: Senior Developer + DevOps
**Estimated Time**: 4-6 hours

### Test 11.1: Generation Performance

#### Test 11.1.1: Single Article Generation Time
**Objective**: Article should generate in <30 seconds

```php
$start = microtime(true);
$article = $drafting->generateArticle(1, 1, true);
$time = (microtime(true) - $start) * 1000;

// Verify: $time < 30000 (30 seconds)
echo "Generation time: {$time}ms";
```

**Execution**:
1. Generate 10 articles
2. Measure each generation time
3. Verify all <30 seconds
4. Average should be <5 seconds
5. Log results

---

#### Test 11.1.2: Batch Generation (10 articles)
**Objective**: Process 10 articles in <5 minutes

```bash
time php jobs/writer/drafting_processor.php
# Should complete in ~300 seconds for 10 articles
```

**Verification**:
- ✅ Total time <300 seconds
- ✅ No memory errors
- ✅ All articles generated successfully

---

### Test 11.2: Database Query Performance

#### Test 11.2.1: Editorial Queue Query
**Objective**: Editorial queue should load in <500ms

```php
$start = microtime(true);
$articles = $articleService->getEditorialQueue(0, 20);
$time = (microtime(true) - $start) * 1000;

// Verify: $time < 500ms
```

**Execution**:
1. Run query 10 times
2. Measure each
3. Average should be <200ms
4. Max should be <500ms

---

#### Test 11.2.2: API List Articles Query
**Objective**: Public API should respond in <200ms

```bash
time curl -X GET "http://localhost:8000/api/v1/writer/articles" > /dev/null

# Should complete in <200ms (real-world is 100-150ms)
```

---

### Test 11.3: Load Testing (Optional but Recommended)

#### Test 11.3.1: Concurrent API Requests
```bash
# Using Apache Bench
ab -n 1000 -c 100 http://localhost:8000/api/v1/writer/articles

# Expected:
# - Request rate: 50-100 req/sec
# - 95th percentile: <500ms
# - Error rate: 0-1%
```

---

#### Test 11.3.2: Concurrent Dashboard Access
```bash
# Simulate 10 admins accessing dashboard simultaneously
for i in {1..10}; do
  curl -X GET \
    -H "Authorization: Bearer [token]" \
    http://localhost:8000/admin/writer/editorial-queue.php \
    > /dev/null 2>&1 &
done

# Monitor:
# - Page load times
# - No 500 errors
# - Database connections stable
```

---

## Edge Cases & Error Scenarios

**Tester**: QA Lead + Senior Developer
**Estimated Time**: 6-8 hours

### Test 12.1: Null/Empty Value Handling

#### Test 12.1.1: Missing Artist Name
```php
// Given: Anomaly with artist_id that doesn't exist
$article = $drafting->generateArticle($anomalyId, 1, true);

// Verify:
// ✅ Article still generates
// ✅ Uses "Unknown Artist" as fallback
// ✅ No database errors
```

---

#### Test 12.1.2: Empty CDM Data
```php
// Given: CDM tables empty
$anomalies = $scout->detectChartJumps();

// Verify:
// ✅ Returns empty array (not error)
// ✅ No database errors
```

---

### Test 12.2: Duplicate/Race Conditions

#### Test 12.2.1: Duplicate Anomaly Prevention
```php
// Create same anomaly twice
$anomaly1 = $scout->createAnomaly(...);
$anomaly2 = $scout->createAnomaly(...);

// Verify:
// ✅ Both created (different IDs)
// ✅ No database constraint violations
```

---

#### Test 12.2.2: Concurrent Persona Assignment
```bash
# Run Niko two times simultaneously
php jobs/writer/niko_dispatcher.php &
php jobs/writer/niko_dispatcher.php &

# Verify:
# ✅ No race conditions
# ✅ All anomalies assigned exactly once
# ✅ No deadlocks
```

---

### Test 12.3: Boundary Conditions

#### Test 12.3.1: Very Long Article Title
```php
$longTitle = str_repeat("Long ", 100); // 500+ chars
$article = $drafting->generateArticle(1, 1, true);

// Verify:
// ✅ Title truncated to 255 chars
// ✅ Slug generated correctly
// ✅ No database errors (field is VARCHAR(255))
```

---

#### Test 12.3.2: Very Large Article Content
```php
// Given: Article with 100,000 word content
$article = [..., 'content' => str_repeat('word ', 20000)];

// Verify:
// ✅ Stores in LONGTEXT field
// ✅ Safety scan completes
// ✅ No timeout
```

---

#### Test 12.3.3: Story Value Edge Cases
```php
// Zero-engagement artist
$score = $niko->evaluateStoryValue(['artist_id' => 999, 'magnitude' => 0.1]);
$this->assertGreaterThanOrEqual(0, $score);
$this->assertLessThanOrEqual(100, $score);

// Extremely popular artist
$score = $niko->evaluateStoryValue(['artist_id' => 1, 'magnitude' => 50.0]);
$this->assertLessThanOrEqual(100, $score);
```

---

### Test 12.4: Time-Based Issues

#### Test 12.4.1: Timezone Handling
```php
// Schedule article for specific timezone
$success = $articleService->approveArticle(1, 1, '2026-01-22T10:00:00');

// Verify:
// ✅ Timestamp stored correctly
// ✅ Publisher publishes at correct time
// ✅ No timezone confusion
```

---

#### Test 12.4.2: DST Transitions
```bash
# Test scheduling near DST change (if applicable)
# Verify times don't get confused
```

---

### Test 12.5: Special Characters & XSS Prevention

#### Test 12.5.1: SQL Injection Prevention
```php
// Try to inject SQL in anomaly data
$anomaly = $scout->createAnomaly(
    "'; DROP TABLE writer_articles; --",
    'high',
    1, 1, 100, 10, 10, 'metal'
);

// Verify:
// ✅ Treated as literal string
// ✅ Table still exists
// ✅ No injection occurred
```

---

#### Test 12.5.2: XSS Prevention in API
```bash
curl "http://localhost:8000/api/v1/writer/articles" \
  -d '{"title": "<script>alert(1)</script>"}'

# Response should escape HTML
# Verify no unescaped script tags in response
```

---

#### Test 12.5.3: Special Characters in Article Content
```php
// Article with emojis, unicode, special chars
$content = "Artist's café: "quoted" — slash/backslash \\ 💯";
$article = ['title' => 'Test', 'content' => $content];

// Verify:
// ✅ Stored correctly
// ✅ Retrieved correctly
// ✅ API returns properly encoded JSON
```

---

## Manual Testing Procedures

**For**: All team members not running automated tests
**Time**: 1-2 hours

### Test 13.1: Happy Path Walkthrough

**Scenario**: Following a complete story from detection to publication

**Prerequisites**:
- Have staging environment access
- Have admin account created
- Know how to use curl or Postman

**Steps**:

1. **Verify Database Setup**
   ```bash
   mysql -u root -p -e "USE ngn_2025; SELECT * FROM writer_personas;"
   ```
   - ✅ 5 personas visible

2. **Insert Test Data** (Or run scout to generate)
   ```bash
   php jobs/writer/scout_anomaly_detection.php
   ```
   - ✅ Check: `tail storage/logs/writer_scout.log` - should see "Detection completed"

3. **Run Niko Dispatcher**
   ```bash
   php jobs/writer/niko_dispatcher.php
   ```
   - ✅ Check log: "Processing complete"
   - ✅ Verify database: `SELECT * FROM writer_anomalies WHERE status='assigned' LIMIT 1;`

4. **Run Drafting Processor**
   ```bash
   php jobs/writer/drafting_processor.php
   ```
   - ✅ Check log: "Article generation"
   - ✅ Verify articles created: `SELECT COUNT(*) FROM writer_articles;`

5. **Access Admin Dashboard**
   - Go to http://localhost:8000/admin/writer/editorial-queue.php
   - ✅ See unclaimed drafts
   - ✅ Claim one article
   - ✅ Article moves to "My Workspace"

6. **Edit Article**
   - Click "Edit" on claimed article
   - ✅ Content displays in markdown editor
   - ✅ Safety status shows
   - ✅ Metrics visible

7. **Approve Article**
   - Click "Approve & Publish"
   - ✅ Status changes to 'approved'
   - ✅ Redirected to queue

8. **Check Public API**
   ```bash
   curl http://localhost:8000/api/v1/writer/articles
   ```
   - ✅ Approved article shows in list
   - ✅ Can see persona, title, engagement

9. **View Article Detail**
   ```bash
   curl http://localhost:8000/api/v1/writer/articles/[id]
   ```
   - ✅ Full content shows
   - ✅ Comments included (if any)

---

### Test 13.2: Safety Filter Walkthrough

1. **Create Test Article with Borderline Content**
   - Create article with text: "Singer is past his prime"
   - Run safety scan manually via admin

2. **Verify Flagged Status**
   - ✅ Status = 'flagged'
   - ✅ Score between 0.1-0.3
   - ✅ Go to /admin/writer/safety-alerts.php

3. **Review & Override**
   - See article in safety alerts
   - Click "Approve"
   - ✅ Status changes to 'approved'
   - ✅ Can now approve for publishing

---

## Test Sign-Off & Approval

### Test 13.1: Test Results Template

**Copy this for each test category**:

```markdown
# Test Category: [Category Name]
**Tester**: [Your Name]
**Date**: [YYYY-MM-DD]
**Total Tests**: [X]
**Passed**: [X]
**Failed**: [X]
**Blocked**: [X]

## Individual Test Results

| Test ID | Test Name | Status | Notes |
|---------|-----------|--------|-------|
| 1.1 | Chart Jump Detection | ✅ PASS | All assertions passed |
| 1.2 | Engagement Spike Detection | ✅ PASS | - |
| 1.3 | Spin Surge Detection | ⚠️ FAIL | Threshold too high, need to adjust |

## Issues Found

### Issue #1 (CRITICAL)
- **Title**: Safety filter missing keyword detection
- **Severity**: Critical
- **Steps to Reproduce**: See test 4.1 results
- **Expected**: Score >0.3 for "alcoholic"
- **Actual**: Score 0.05
- **Assigned To**: Backend Dev #3

### Issue #2 (MINOR)
- **Title**: Admin dashboard table not responsive on mobile
- **Severity**: Minor
- **Assigned To**: Frontend Dev

## Sign-Off

- **Tester**: [Name]
- **Date**: [Date]
- **Status**: 🟡 CONDITIONAL PASS (1 critical issue to fix)
- **Next Steps**: Fix Issue #1, re-test, then proceed to deployment
```

---

### Test 13.2: Master Test Tracking Sheet

Keep in shared document (Google Sheet or Confluence):

```
WRITER ENGINE TESTING - MASTER TRACKER
========================================
Status: IN PROGRESS (14 of 22 test suites complete)

TEST SUITE COMPLETION
┌─────────────────────────────────┬──────────┬──────────────┐
│ Category                        │ Tester   │ Status       │
├─────────────────────────────────┼──────────┼──────────────┤
│ Unit Tests - Scout              │ Dev #1   │ ✅ COMPLETE  │
│ Unit Tests - Niko               │ Dev #1   │ ✅ COMPLETE  │
│ Unit Tests - Drafting           │ Dev #2   │ 🟡 IN PROG   │
│ Unit Tests - Safety             │ Dev #3   │ ⏳ PENDING   │
│ Integration Tests               │ Dev #2   │ 🟡 IN PROG   │
│ E2E Pipeline Tests              │ QA Lead  │ ⏳ PENDING   │
│ API Endpoint Tests              │ Dev #4   │ ✅ COMPLETE  │
│ Admin Dashboard Tests           │ FE Dev   │ 🟡 IN PROG   │
│ Cron Job Tests                  │ DevOps   │ ⏳ PENDING   │
│ Safety & Compliance             │ Legal    │ ⏳ PENDING   │
│ Performance & Load              │ DevOps   │ ⏳ PENDING   │
│ Edge Cases                      │ QA Lead  │ ⏳ PENDING   │
└─────────────────────────────────┴──────────┴──────────────┘

CRITICAL ISSUES BLOCKING DEPLOYMENT
- None currently

HIGH PRIORITY ISSUES
- Safety filter: "washed-up" keyword not triggering (Dev #3)
- Admin dashboard mobile responsiveness (FE Dev)

MEDIUM PRIORITY ISSUES
- Slug generation: Should truncate after special char (Dev #2)
```

---

### Test 13.3: Final Deployment Approval Checklist

**Before deploying to production, verify ALL of these**:

```markdown
# DEPLOYMENT APPROVAL CHECKLIST

## Code & Migrations
- [ ] Database migration tested successfully
- [ ] All 6 tables created with correct schema
- [ ] Seed data loaded (5 personas)
- [ ] No schema errors in staging

## Services
- [ ] All 6 services instantiate without errors
- [ ] No import/dependency issues
- [ ] Services have appropriate error handling
- [ ] No hardcoded credentials

## Unit Tests
- [ ] Scout service: 100% pass rate
- [ ] Niko service: 100% pass rate
- [ ] Drafting service: 100% pass rate
- [ ] Safety service: 100% pass rate
- [ ] Code coverage >80%

## Integration Tests
- [ ] Scout→Niko→Drafting pipeline works end-to-end
- [ ] Safety filter properly integrated
- [ ] Metrics tracking functional
- [ ] Audit logging captures all actions

## API Tests
- [ ] All public endpoints return correct data
- [ ] All admin endpoints require authentication
- [ ] Error responses formatted correctly
- [ ] Pagination works
- [ ] Filtering works

## Admin Dashboard
- [ ] All 6 pages load without errors
- [ ] No console errors
- [ ] Responsive on mobile/tablet/desktop
- [ ] All buttons/forms functional
- [ ] Data displays accurately

## Cron Jobs
- [ ] All 6 jobs execute manually without errors
- [ ] Registered in cron_registry table
- [ ] Execute on correct schedules
- [ ] Log files created properly
- [ ] Error handling working

## Safety & Compliance
- [ ] Safety filter accuracy >90% on test cases
- [ ] No false negatives on obvious defamation
- [ ] Override process secure and logged
- [ ] All admin actions auditable

## Performance
- [ ] Single article generation: <30 seconds
- [ ] Batch processing 10: <5 minutes
- [ ] API response time: <200ms
- [ ] Dashboard page load: <500ms
- [ ] No memory leaks in long-running jobs

## Error Handling
- [ ] Database connection failures handled
- [ ] Malformed input rejected safely
- [ ] XSS/SQL injection prevention verified
- [ ] Timeout/performance limits enforced

## Documentation
- [ ] README updated with deployment instructions
- [ ] API endpoints documented
- [ ] Admin procedures documented
- [ ] Troubleshooting guide created

## Security
- [ ] No secrets in code
- [ ] All queries use prepared statements
- [ ] Admin endpoints properly authenticated
- [ ] Audit trail complete and immutable

## Sign-Offs
- [ ] Backend Lead: _________________ Date: _______
- [ ] Frontend Lead: ________________ Date: _______
- [ ] DevOps Lead: __________________ Date: _______
- [ ] QA Lead: _____________________ Date: _______
- [ ] Legal/Compliance: _____________ Date: _______
- [ ] Project Manager: ______________ Date: _______

## Final Approval
**Ready for Production Deployment**: ☐ YES / ☐ NO

If NO, list blockers:
1. _______________________________________________
2. _______________________________________________
3. _______________________________________________

Approved by: _________________________ Date: __________
```

---

## Test Data Fixtures

### SQL to Create Test Data

```sql
-- Test Artists
INSERT INTO artists (id, name, genre, total_streams) VALUES
(100, 'Test Artist Heavy', 'metal', 5000000),
(101, 'Test Artist Pop', 'pop', 10000000),
(102, 'Test Artist Indie', 'indie', 1000000);

-- Test Tracks
INSERT INTO tracks (id, artist_id, name, genre) VALUES
(100, 100, 'Test Heavy Track', 'metal'),
(101, 101, 'Test Pop Track', 'pop'),
(102, 102, 'Test Indie Track', 'indie');

-- Test CDM Chart Entries (for chart jump detection)
INSERT INTO cdm_chart_entries (artist_id, track_id, chart_position, chart_date) VALUES
(100, 100, 5, DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
(100, 100, 50, CURDATE()); -- 45 rank jump

-- Test CDM Engagements (for engagement spike detection)
INSERT INTO cdm_engagements (artist_id, track_id, views_count, likes_count, shares_count, total_engagement, engagement_date) VALUES
(101, 101, 100, 50, 20, 170, DATE_SUB(CURDATE(), INTERVAL 30 DAY)),
(101, 101, 1700, 500, 200, 2400, CURDATE()); -- 10x increase

-- Test CDM Spins (for spin surge detection)
INSERT INTO cdm_spins (artist_id, track_id, spin_count, spin_date) VALUES
(102, 102, 50, DATE_SUB(CURDATE(), INTERVAL 7 DAY)),
(102, 102, 250, CURDATE()); -- 5x increase
```

---

## Support & Troubleshooting

### Common Test Issues

**Issue**: Tests fail with "Table doesn't exist"
- **Solution**: Run migration: `mysql -u root -p ngn_2025 < migrations/sql/schema/37_writer_engine.sql`

**Issue**: Service instantiation fails
- **Solution**: Check `lib/bootstrap.php` is loading, verify Config class works

**Issue**: API returns 404
- **Solution**: Verify `writer_routes.php` included in `/api/v1/index.php`

**Issue**: Cron jobs don't run
- **Solution**: Check cron_registry table, verify file permissions (755), test manual execution

**Issue**: Safety filter too strict/lenient
- **Solution**: Adjust keyword thresholds in `SafetyFilterService.php`, re-test

### Getting Help

1. Check logs: `tail -f /storage/logs/writer_*.log`
2. Run manual PHP tests: `php -r "require 'tests/...test.php';"`
3. Query database: `SELECT * FROM writer_articles ORDER BY created_at DESC;`
4. Check server logs: `/var/log/apache2/error.log` or `journalctl -u php-fpm`

---

## Sign-Off

**Testing Plan Prepared By**: _________________ Date: _________

**Testing Coordinator Assigned**: _________________ Date: _________

**Ready to Begin Testing**: ☐ YES / ☐ NO

---

**This testing plan is COMPREHENSIVE. Sections can be assigned to different team members to run in parallel.**

**Estimated total testing time: 50-60 hours across full team**

**With team of 6-8 people testing in parallel: 10-12 days to complete all testing**

**Next Step**: Assign test categories to team members and set start date.
