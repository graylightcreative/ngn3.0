# NGN Score Audit & Verification Engine

## Overview

The NGN Score Audit & Verification Engine provides an immutable audit trail for NGN score calculations, enabling historical verification of scoring accuracy, fraud detection, and compliance reporting. This system addresses critical requirements for royalty integrity and regulatory compliance.

**Ref**: Bible Chapter 17.3 - Compliance & Audit Trail

## Architecture

```
User Engagement
       ↓
RankingService::calculateNGNScore()
       ↓
NGNScoreAuditService::recordScoreCalculation()
       ↓
ngn_score_history (IMMUTABLE)
       ↓
      ┌────────────────────────────────────────────┐
      │  Ongoing Verification & Monitoring          │
      └────────────────────────────────────────────┘
      ↓                    ↓                    ↓
ScoreVerificationService  Artist Disputes   Lineage Check
      ↓                    ↓                    ↓
Recalculation         File Dispute         Integrity Verify
      ↓                    ↓                    ↓
ngn_score_verification  ngn_score_disputes  ngn_score_lineage
      │                    │                    │
      └────────────────────┴────────────────────┘
              ↓
      ngn_audit_reports (Compliance)
```

## Core Concepts

### Immutable Audit Trail

The `ngn_score_history` table is **immutable**:
- Records can ONLY be inserted, never updated or deleted
- Contains complete calculation metadata (formula, factors, modifiers)
- No UPDATE/DELETE permissions granted to application role
- All historical calculations preserved for compliance

### Data Lineage

The `ngn_score_lineage` table tracks which source data fed into each score:
- Snapshots of spins, plays, engagements used for calculation
- SHA256 hash of each data point for integrity verification
- Allows detection of post-calculation source data modification
- Supports forensic analysis if score discrepancies discovered

### Score Verification

The verification process recalculates scores from raw data:
- Fetches original raw data (spins, plays, views, engagements, sparks)
- Applies same formula and weights as original calculation
- Compares original vs recalculated scores
- Flags discrepancies if percent difference > 0.01%

### Dispute Resolution

Artists can file disputes for suspected calculation errors:
- File with evidence and impact estimate
- Admin investigates and verifies claim
- If verified, correction is created
- Dual authorization (requested + approved) required
- All adjustments tracked in ngn_score_corrections

## Database Schema

### Tables

#### 1. ngn_score_history (Immutable)
Complete immutable record of every NGN score calculation.

**Key Columns**:
- `id` - Unique history record
- `artist_id` - Artist being scored
- `score_value` - Final calculated score (0-100)
- `period_type` - daily, weekly, monthly, yearly
- `period_start`, `period_end` - Score calculation period
- `spins_count`, `plays_count`, `views_count` - Raw inputs
- `engagements_count`, `sparks_count` - Engagement metrics
- `{factor}_factor` - Individual factor contributions
- `fraud_rate`, `reputation_multiplier` - Applied modifiers
- `formula_used` - JSON containing exact weights/version
- `calculated_by` - Service that calculated (cron, api, manual)
- `calculated_at` - When calculation occurred

**Constraints**:
- Primary Key: `id`
- Foreign Key: `artist_id` → artists.id
- Indexes: artist_id, period (type+start), score_value, calculated_at

#### 2. ngn_score_verification
Records verification checks and audit results.

**Key Columns**:
- `id` - Verification record ID
- `history_id` - FK to ngn_score_history
- `verification_type` - recalculation, spot_check, full_audit, dispute_resolution
- `verification_status` - pending, in_progress, passed, failed, discrepancy_found
- `original_score` - From history
- `recalculated_score` - Recalculated from raw data
- `percent_difference` - Deviation percentage
- `issues_found` - JSON array of problems detected
- `auditor_id` - Admin who ran verification
- `audit_notes` - Human notes
- `action_recommended` - none, recalculate, investigate, etc

**Constraints**:
- Foreign Keys: history_id, auditor_id
- Indexes: artist_id, status, type, auditor

#### 3. ngn_score_disputes
Track artist-filed disputes and resolutions.

**Key Columns**:
- `id` - Dispute ID
- `artist_id` - Artist filing dispute
- `history_id` - Score being disputed
- `dispute_type` - calculation_error, missing_data, fraud_suspicion, etc
- `severity` - low, medium, high, critical
- `description` - What artist is disputing
- `alleged_impact` - Claimed score difference
- `status` - open, investigating, resolved, closed
- `investigation_notes` - Admin notes from investigation
- `action_taken` - none, recalculated, adjusted, etc
- `final_score_after` - Score after resolution

**Constraints**:
- Foreign Keys: artist_id, history_id
- Indexes: status, type, severity

#### 4. ngn_score_lineage
Data lineage: which raw data fed into which scores.

**Key Columns**:
- `id` - Lineage record ID
- `history_id` - FK to ngn_score_history
- `source_table` - spins, plays, posts, cdm_engagements, etc
- `source_id` - ID of source record
- `record_count` - Number of records included
- `data_snapshot` - JSON snapshot of data used
- `hash_value` - SHA256 hash of data (for integrity)
- `validation_status` - valid, modified, deleted, suspicious
- `created_at` - When lineage recorded

**Constraints**:
- Unique: (history_id, source_table, source_id)
- Foreign Key: history_id
- Indexes: history_id, source, hash_value

#### 5. ngn_score_corrections
Manual score corrections and adjustments.

**Key Columns**:
- `id` - Correction ID
- `artist_id`, `history_id` - What's being corrected
- `correction_type` - manual_adjustment, calculation_error_fix, fraud_reversal, etc
- `reason` - Why correction made
- `original_score`, `corrected_score` - Before/after
- `adjustment_amount` - Change amount
- `requested_by`, `approved_by` - Dual authorization
- `evidence_url` - Link to supporting documentation
- `is_reversible` - Can be undone if needed
- `reversal_id` - If this corrects a previous correction

**Constraints**:
- Foreign Keys: artist_id, requested_by, approved_by (RESTRICT)
- Indexes: artist_id, history_id, type, requested_by, approved_by

#### 6. ngn_audit_reports
Compliance and audit reports (periodic or on-demand).

**Key Columns**:
- `id` - Report ID
- `report_type` - periodic, on_demand, dispute_resolution, compliance, fraud_investigation
- `scope_type` - single_artist, label, sample, full_database
- `artist_id` - If scope is single artist
- `period_start`, `period_end` - Report date range
- `total_artists_audited` - Count audited
- `scores_verified` - Count verified
- `discrepancies_found` - Count with issues
- `pass_rate` - % of scores passing
- `summary_findings` - JSON array of key findings
- `recommendations` - Recommended actions
- `generated_by` - Admin who generated
- `report_file_url` - Link to downloadable file

**Constraints**:
- Foreign Keys: artist_id, generated_by
- Indexes: report_type, scope_type, generated_by

## Service Layer

### NGNScoreAuditService

Located at `lib/Rankings/NGNScoreAuditService.php`

**Purpose**: Records score calculations, tracks data lineage, manages disputes and corrections

**Key Methods**:

#### recordScoreCalculation()
```php
public function recordScoreCalculation(
    int $artistId,
    float $scoreValue,
    string $periodType,
    string $periodStart,
    string $periodEnd,
    array $rawData,
    array $factors,
    array $modifiers,
    array $formula,
    string $calculatedBy
): int
```
Creates immutable history record with complete calculation details.

**Parameters**:
- `artistId` - Artist being scored
- `scoreValue` - Final score (0-100)
- `periodType` - daily, weekly, monthly, yearly
- `periodStart`, `periodEnd` - Calculation period (YYYY-MM-DD)
- `rawData` - Array of raw inputs (spins, plays, etc)
- `factors` - Factor contributions (spins_factor, plays_factor, etc)
- `modifiers` - Applied modifiers (fraud_rate, reputation_multiplier)
- `formula` - Exact weights/formula version used
- `calculatedBy` - Service that calculated (e.g., 'cron_weekly')

**Returns**: history_id (auto-increment)

**Example**:
```php
$historyId = $auditService->recordScoreCalculation(
    artistId: 123,
    scoreValue: 75.5,
    periodType: 'weekly',
    periodStart: '2026-01-15',
    periodEnd: '2026-01-21',
    rawData: [
        'spins' => 150,
        'plays' => 3000,
        'views' => 5000,
        'engagements' => ['like' => 200, 'share' => 50, 'comment' => 25],
        'sparks' => 125.50,
        'followers' => 5000
    ],
    factors: [
        'spins_factor' => 18.75,
        'plays_factor' => 18.75,
        'views_factor' => 12.5,
        'engagement_factor' => 18.75,
        'sparks_factor' => 6.25,
        'momentum_factor' => 6.25
    ],
    modifiers: [
        'fraud_rate' => 0.0,
        'reputation_multiplier' => 1.1
    ],
    formula: [
        'method' => 'v1.0',
        'factors' => [
            'spins' => 0.25,
            'plays' => 0.25,
            'engagements' => 0.25,
            'sparks' => 0.15,
            'momentum' => 0.10
        ]
    ],
    calculatedBy: 'cron_daily'
);
```

#### recordLineage()
```php
public function recordLineage(
    int $historyId,
    string $sourceTable,
    int $sourceId,
    int $recordCount,
    array $dataSnapshot,
    string $hashValue
): void
```
Records which source data was used for score calculation.

#### verifyLineageIntegrity()
```php
public function verifyLineageIntegrity(int $historyId): array
```
Checks if source data has been modified since calculation.

**Returns**:
```php
[
    'valid' => 5,                    // Number of valid data sources
    'total_sources' => 5,            // Total tracked sources
    'issues' => [
        [
            'source_table' => 'spins',
            'source_id' => 456,
            'status' => 'valid',     // or modified, deleted, suspicious
            'message' => 'Data unchanged since calculation'
        ]
    ]
]
```

#### createDispute()
```php
public function createDispute(
    int $artistId,
    int $historyId,
    string $disputeType,
    string $description,
    ?float $allegedImpact = null
): int
```
Files a dispute for artist-suspected calculation error.

**Dispute Types**: calculation_error, missing_data, fraud_suspicion, formula_question, adjustment_request

**Returns**: dispute_id

#### getDisputes()
```php
public function getDisputes(int $artistId, ?string $status = null): array
```
Retrieves disputes for an artist.

#### updateDisputeStatus()
```php
public function updateDisputeStatus(
    int $disputeId,
    string $newStatus,
    string $resolution,
    ?int $investigatedBy = null,
    ?array $investigationNotes = null
): void
```
Updates dispute status and resolution.

#### recordCorrection()
```php
public function recordCorrection(
    int $artistId,
    int $historyId,
    string $correctionType,
    string $reason,
    float $originalScore,
    float $correctedScore,
    int $requestedBy,
    int $approvedBy,
    ?string $evidenceUrl = null
): int
```
Records dual-authorized score correction.

**Requires**:
- `requestedBy` ≠ `approvedBy` (different admins)
- Evidence URL for compliance
- Clear reason for change

**Returns**: correction_id

#### calculateIntegrityMetrics()
```php
public function calculateIntegrityMetrics(
    int $artistId,
    string $periodStart,
    string $periodEnd
): array
```
Calculates integrity score and metrics for artist's scores.

**Returns**:
```php
[
    'integrity_score' => 92.5,           // 0-100
    'total_scores_audited' => 48,
    'verification_pass_rate' => 95.8,    // %
    'correction_rate' => 2.1,            // % of scores corrected
    'dispute_rate' => 3.5,               // % with active disputes
    'lineage_integrity' => 98.2,         // % with valid lineage
    'status' => 'healthy'                // healthy, warning, critical
]
```

#### getScoreHistory()
```php
public function getScoreHistory(
    int $artistId,
    ?string $periodType = null,
    int $limit = 50
): array
```
Retrieves score history for artist.

### ScoreVerificationService

Located at `lib/Rankings/ScoreVerificationService.php`

**Purpose**: Recalculates scores from raw data and verifies accuracy

**Key Methods**:

#### verifyScore()
```php
public function verifyScore(int $historyId): array
```
Recalculates historical score and compares to original.

**Returns**:
```php
[
    'status' => 'passed',                    // passed or failed
    'verification_id' => 1,
    'original_score' => 75.5,
    'recalculated_score' => 75.48,
    'score_match' => true,
    'percent_difference' => 0.03,
    'lineage_valid' => true,
    'lineage_issues' => [],
    'data_completeness' => 100.0,
    'calculation_method' => 'v1.0'
]
```

#### recalculateScore()
```php
public function recalculateScore(
    int $artistId,
    string $periodStart,
    string $periodEnd,
    ?array $formula = null
): array
```
Recalculates NGN score from raw data.

**Parameters**:
- `formula` - If null, uses default v1.0 formula
- Can pass specific formula to recalculate with different weights

**Returns**:
```php
[
    'score' => 75.5,
    'base_score' => 68.6,
    'factors' => [
        'spins_factor' => 18.75,
        'plays_factor' => 18.75,
        // ... other factors
    ],
    'modifiers' => [
        'fraud_rate' => 0.0,
        'reputation_multiplier' => 1.1
    ],
    'raw_data' => [...],
    'data_completeness' => 100.0,
    'formula_used' => [...]
]
```

#### runBulkVerification()
```php
public function runBulkVerification(
    string $periodStart,
    string $periodEnd,
    int $limit = 100
): array
```
Verifies multiple scores in a period.

**Returns**:
```php
[
    'total_verified' => 95,
    'passed' => 92,
    'failed' => 3,
    'issues' => [
        [
            'artist_id' => 123,
            'verification_id' => 1,
            'percent_difference' => 2.15
        ]
    ]
]
```

## API Endpoints

### POST /api/v1/audit/verify-score

Verify NGN scores and manage disputes.

**Authentication**: Required (admin, artist, or label manager)

**Actions**:

#### verify_period
Bulk verify scores for a period.

**Request**:
```json
{
    "action": "verify_period",
    "period_start": "2026-01-15",
    "period_end": "2026-01-21",
    "limit": 100
}
```

**Response**:
```json
{
    "action": "verify_period",
    "verification_results": {
        "total_verified": 95,
        "passed": 92,
        "failed": 3,
        "issues": [...]
    },
    "pass_rate": 96.84
}
```

#### verify_score
Verify specific score by history ID.

**Request**:
```json
{
    "action": "verify_score",
    "history_id": 1,
    "artist_id": 123
}
```

**Response**:
```json
{
    "action": "verify_score",
    "verification": {
        "status": "passed",
        "verification_id": 1,
        "original_score": 75.5,
        "recalculated_score": 75.48,
        "percent_difference": 0.03,
        "lineage_valid": true,
        "data_completeness": 100.0
    }
}
```

#### get_history
Retrieve artist's score history.

**Request**:
```json
{
    "action": "get_history",
    "artist_id": 123,
    "period_type": "weekly",
    "limit": 50
}
```

**Response**:
```json
{
    "action": "get_history",
    "artist_id": 123,
    "scores": [
        {
            "id": 1,
            "score_value": 75.5,
            "period_type": "weekly",
            "period_start": "2026-01-15",
            "period_end": "2026-01-21",
            "calculated_at": "2026-01-22T00:15:00Z"
        }
    ],
    "total": 50
}
```

#### get_integrity_metrics
Get artist's integrity metrics.

**Request**:
```json
{
    "action": "get_integrity_metrics",
    "artist_id": 123,
    "period_start": "2026-01-01",
    "period_end": "2026-01-31"
}
```

**Response**:
```json
{
    "action": "get_integrity_metrics",
    "artist_id": 123,
    "metrics": {
        "integrity_score": 92.5,
        "total_scores_audited": 48,
        "verification_pass_rate": 95.8,
        "correction_rate": 2.1,
        "dispute_rate": 3.5,
        "status": "healthy"
    }
}
```

#### file_dispute
Artist files dispute for score.

**Request**:
```json
{
    "action": "file_dispute",
    "artist_id": 123,
    "history_id": 1,
    "dispute_type": "calculation_error",
    "description": "Score calculation doesn't match my engagement metrics",
    "alleged_impact": 5.5
}
```

**Response**:
```json
{
    "action": "file_dispute",
    "dispute_id": 1,
    "status": "open"
}
```

#### get_disputes
Retrieve artist's disputes.

**Request**:
```json
{
    "action": "get_disputes",
    "artist_id": 123,
    "status": "open"
}
```

**Response**:
```json
{
    "action": "get_disputes",
    "artist_id": 123,
    "disputes": [
        {
            "id": 1,
            "dispute_type": "calculation_error",
            "severity": "medium",
            "status": "open",
            "description": "Score calculation error",
            "created_at": "2026-01-22T10:00:00Z"
        }
    ],
    "total": 1
}
```

## Admin Dashboard

**Location**: `/admin/audit/scores.php`

**Tabs**:

### Dashboard
Real-time KPI metrics and recent failures.
- Total scores in system
- Verification pass rate
- Score discrepancies count
- Open disputes count
- Recent verification failures (10)
- Open artist disputes (5)

### Score History
Search and browse score history.
- Filter by artist, period type, date range
- View all score details
- Verify individual scores
- File disputes

### Verification Results
Review verification audit results.
- All verification records
- Status breakdown (passed/failed)
- Percent difference analysis
- Review detailed findings

### Disputes
Manage artist-filed disputes.
- View all disputes by status/severity
- Read artist description
- Approve investigation
- Resolve with notes
- Adjust score if needed

### Corrections
Review all score corrections.
- Show original vs corrected score
- Display who approved/requested
- Show approval date
- Track adjustment reason

## Integration Points

### Integration with RankingService

When calculating NGN scores, immediately record in audit system:

```php
// In RankingService::calculateNGNScore()
$score = $this->calculateScore($artistId, $period);

// Record in audit trail
$auditService->recordScoreCalculation(
    artistId: $artistId,
    scoreValue: $score['final_score'],
    periodType: $period['type'],
    periodStart: $period['start'],
    periodEnd: $period['end'],
    rawData: $score['raw_data'],
    factors: $score['factors'],
    modifiers: $score['modifiers'],
    formula: $score['formula'],
    calculatedBy: 'ranking_service'
);
```

### Integration with API Authentication

Admin dashboard requires admin role:
```php
if ($auth['role'] !== 'admin') {
    header('Location: /login');
    exit;
}
```

API endpoints allow:
- Admins: verify any scores, manage disputes
- Artists: verify own scores, file disputes
- Label managers: verify roster artist scores

## Workflows

### Dispute Filing & Resolution

```
1. Artist Files Dispute
   ├─ Via API: POST /api/v1/audit/verify-score (file_dispute)
   └─ Via Dashboard: Artists cannot access, must use API

2. Dispute Recorded
   ├─ Status: open
   ├─ Severity: assigned (low/medium/high/critical)
   └─ Assigned: null (waiting for admin pickup)

3. Admin Reviews
   ├─ Reviews dispute description
   ├─ Checks score history
   ├─ Verifies score with ScoreVerificationService
   └─ Status: investigating

4. Admin Investigates
   ├─ Checks lineage integrity
   ├─ Verifies raw data accuracy
   ├─ Reviews artist's engagement patterns
   └─ Documents findings

5. Admin Resolves
   ├─ If calculation was correct: status = closed
   ├─ If error found: create correction record
   │  ├─ Requires approval from different admin
   │  ├─ Stores evidence URL
   │  └─ Logs adjustment reason
   ├─ If fraud suspected: flag artist account
   └─ Status: resolved

6. Notification
   ├─ Email artist with resolution
   ├─ If score adjusted: notify payouts team
   └─ If fraud: alert security team
```

### Verification Workflow

```
1. Cron Job (Daily 2 AM): audit_ngn_scores.php
   ├─ Phase 1: Batch verify scores from last 24h
   ├─ Phase 2: Check data lineage integrity
   ├─ Phase 3: Generate weekly reports (Sundays)
   └─ Phase 4: Cleanup old records

2. Each Score Verification
   ├─ Fetch original score + formula
   ├─ Fetch raw data (spins, plays, views, engagements)
   ├─ Recalculate with same formula
   ├─ Compare original vs recalculated
   ├─ Check lineage integrity
   ├─ Record result in ngn_score_verification
   └─ Status: passed or failed

3. If Failed
   ├─ Discrepancy >= 0.01% triggers flag
   ├─ Log issue in verification record
   ├─ Alert admin in dashboard
   ├─ Store issues as JSON array
   └─ Create audit report finding

4. If Lineage Issues
   ├─ Mark as "modified", "deleted", or "suspicious"
   ├─ Alert security team
   ├─ Create compliance report
   └─ Escalate to admin for investigation
```

## Cron Job: audit_ngn_scores.php

**Schedule**: Daily at 2 AM

**Execution Phases**:

**Phase 1: Batch Verify Scores** (10 min typical)
- Get all unverified scores from last 24h (limit 100)
- For each score: recalculate and compare
- Track pass rate and discrepancies
- Alert if pass rate < 80%

**Phase 2: Check Data Lineage** (5 min typical)
- Get all lineage records from last 7 days
- For each: verify data hasn't been modified
- Compare current data hash to stored hash
- Alert if modified records found

**Phase 3: Generate Weekly Reports** (Sundays only, 2 min)
- Aggregate metrics for past week
- Count artists audited, scores verified
- Calculate pass rate and findings
- Create ngn_audit_reports record
- Analyze for trends and anomalies

**Phase 4: Cleanup Old Records**
- Delete verification records > 90 days
- Delete lineage records > 365 days
- Keep ngn_score_history indefinitely

**Alerts Sent**:
- Pass rate < 80%: "Low verification pass rate"
- Modified records > 5: "Data integrity concern"
- High dispute count: "Unusual dispute activity"
- Execution time > 5 min: "Performance warning"

## Usage Examples

### Example 1: Record Score Calculation

```php
<?php
use NGN\Config;
use NGN\Lib\Rankings\NGNScoreAuditService;

$config = Config::getInstance();
$auditService = new NGNScoreAuditService($config);

// After calculating score in RankingService
$historyId = $auditService->recordScoreCalculation(
    artistId: 123,
    scoreValue: 75.5,
    periodType: 'weekly',
    periodStart: '2026-01-15',
    periodEnd: '2026-01-21',
    rawData: [
        'spins' => 150,
        'plays' => 3000,
        'views' => 5000,
        'engagements' => ['like' => 200, 'share' => 50],
        'sparks' => 125.50
    ],
    factors: [...],
    modifiers: [...],
    formula: ['method' => 'v1.0', ...],
    calculatedBy: 'ranking_service'
);

echo "Score recorded with history ID: $historyId\n";
?>
```

### Example 2: Verify a Score

```php
<?php
use NGN\Config;
use NGN\Lib\Rankings\ScoreVerificationService;

$config = Config::getInstance();
$verificationService = new ScoreVerificationService($config);

$result = $verificationService->verifyScore(historyId: 1);

if ($result['status'] === 'passed') {
    echo "Score verification passed!\n";
    echo "Percent difference: " . $result['percent_difference'] . "%\n";
} else {
    echo "VERIFICATION FAILED!\n";
    echo "Original: " . $result['original_score'] . "\n";
    echo "Recalculated: " . $result['recalculated_score'] . "\n";
    echo "Difference: " . $result['percent_difference'] . "%\n";
}
?>
```

### Example 3: File a Dispute (Artist API)

```bash
curl -X POST https://api.niko/api/v1/audit/verify-score \
  -H "Authorization: Bearer $ARTIST_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "file_dispute",
    "artist_id": 123,
    "history_id": 1,
    "dispute_type": "calculation_error",
    "description": "Score calculation doesn'\''t match my engagement metrics",
    "alleged_impact": 5.5
  }'
```

### Example 4: Get Integrity Metrics

```php
<?php
use NGN\Config;
use NGN\Lib\Rankings\NGNScoreAuditService;

$config = Config::getInstance();
$auditService = new NGNScoreAuditService($config);

$metrics = $auditService->calculateIntegrityMetrics(
    artistId: 123,
    periodStart: '2026-01-01',
    periodEnd: '2026-01-31'
);

echo "Integrity Score: " . $metrics['integrity_score'] . "/100\n";
echo "Verification Pass Rate: " . $metrics['verification_pass_rate'] . "%\n";
echo "Overall Status: " . $metrics['status'] . "\n";

// Take action based on status
if ($metrics['status'] === 'critical') {
    // Alert compliance team
    send_alert_email('compliance@niko.com', "Artist $artistId has integrity issues");
}
?>
```

## Troubleshooting

### Low Verification Pass Rate

**Symptoms**: Dashboard shows pass rate < 80%

**Causes**:
1. Calculation formula changed without updating lineage
2. Raw data modified after score calculation
3. Rounding differences in recalculation
4. Date range calculation differences

**Solutions**:
1. Check `ngn_score_history.formula_used` matches current formula
2. Review data lineage for modified/deleted records
3. Increase percent_difference threshold (> 0.01%)
4. Check timezone handling in period calculations

### Data Lineage Issues Detected

**Symptoms**: Cron log shows "Data integrity concern"

**Causes**:
1. Source data tables truncated/rebuilt
2. Post-calculation data cleanup
3. Testing/staging data overwrites
4. Accidental manual edits to raw data

**Solutions**:
1. Never modify source data after score calculation
2. Implement data immutability policies
3. Use separate testing databases
4. Add audit triggers to source tables

### Disputes Not Being Resolved

**Symptoms**: Disputes stuck in "investigating" status

**Causes**:
1. Admin not reviewing assigned disputes
2. Evidence URL incorrect or inaccessible
3. Approval workflow blocked

**Solutions**:
1. Check admin.disputes dashboard for backlog
2. Verify evidence URLs accessible
3. Ensure approver (different admin) has permission
4. Monitor average resolution time

### Correction Approval Failing

**Symptoms**: Error when creating correction

**Causes**:
1. `requested_by` == `approved_by` (same admin)
2. `approved_by` user doesn't exist
3. Missing evidence URL

**Solutions**:
1. Require different admin to approve
2. Verify both user IDs exist in database
3. Provide evidence URL from dispute or documentation

## Performance Considerations

### Query Optimization

- Batch operations in chunks of 100 records
- Use indexes on artist_id, status, completed_at
- Archive old verification records (> 90 days)
- Cache integrity metrics (expires 24h)

### Cron Job Performance

**Typical Execution Times**:
- Batch verify 100 scores: ~10 seconds
- Check 500 lineage records: ~5 seconds
- Generate weekly report: ~2 seconds
- Cleanup old records: ~1 second

**Scaling**:
- Adjust LIMIT in batch queries if needed
- Consider running Phase 1 & 2 in parallel via separate cron jobs
- Archive ngn_score_history quarterly to separate table

## Related Documentation

- **Scoring Algorithm**: `/docs/Scoring.md`
- **Post Analytics**: `/docs/POST_ANALYTICS.md`
- **API Reference**: `/docs/API.md`
- **Admin Guide**: `/docs/ADMIN_GUIDE.md`
- **Database Schema**: `/migrations/sql/schema/41_ngn_score_audit.sql`

## Support & Escalation

### For Calculation Discrepancies
1. Review verification result in admin dashboard
2. Check data lineage integrity
3. Verify formula version used
4. Escalate to Head of Compliance

### For Artist Disputes
1. Review dispute details in dashboard
2. Investigate with verification service
3. Document findings
4. Resolve with dual-admin approval
5. Notify artist of resolution

### For System Errors
1. Check cron job logs: `/logs/audit.log`
2. Review database for locked tables
3. Verify service connectivity
4. Contact Engineering team with logs

## Support

For issues or questions:
1. Check admin dashboard at `/admin/audit/scores.php`
2. Review logs at `/logs/audit.log`
3. Query ngn_score_verification for recent failures
4. Run manual verification test for specific artist
5. Escalate to Compliance team if critical
