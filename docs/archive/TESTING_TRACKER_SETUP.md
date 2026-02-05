# Writer Engine Testing Tracker - Setup & Usage Guide

## Quick Start

### 1. Apply the Database Migration

```bash
# Run the testing tracker schema migration
mysql -u root -p ngn_2025 < migrations/sql/schema/38_writer_testing_tracker.sql

# Verify tables created
mysql -u root -p -e "USE ngn_2025; SHOW TABLES LIKE 'writer_test%';"

# Expected output:
# writer_test_suites
# writer_test_cases
# writer_test_runs
```

### 2. Access the Dashboard

Once migration is complete, navigate to:
```
http://localhost:8000/admin/writer/testing-tracker.php
```

You should see:
- ✅ Overall progress statistics
- ✅ Test suites overview with pass rates
- ✅ Individual test cases with status tracking
- ✅ Deployment readiness checklist

---

## How to Use

### Overview Tab (Main Dashboard)

**What You See:**
- 📊 **Overall Progress**: Total tests, passed, failed, blocked, issues found
- 📈 **Pass Rate**: Percentage with progress bar
- 📋 **Test Suites**: List of all test categories on left
- 🧪 **Test Cases**: Individual tests in selected suite on right
- 🚀 **Deployment Readiness**: Checklist and summary

### Selecting a Test Suite

1. Look at the left panel "Test Suites"
2. Click on any suite to view its test cases
3. Filter by status using the dropdown on the right

**Example**: Click on "Unit Tests - Scout Service" to see:
- Test 1.1: Chart Jump Detection
- Test 1.2: Engagement Spike Detection
- Test 1.3: Spin Surge Detection

---

### Updating Test Results

#### Step 1: Open Test Update Modal
Click the **"📝 Update Result"** button on any test

#### Step 2: Fill In Information
```
Your Name: [Your name or email]
Test Status: [not_started | in_progress | passed | failed | blocked | skipped]
Notes & Results: [Detailed observations]
Found an Issue?: [Check if test failed and found a bug]
```

#### Step 3: If Issue Found
If you check "Found an issue", additional fields appear:
```
Issue Title: [Brief description of the bug]
Issue Severity: [Low | Medium | High | Critical]
```

#### Step 4: Save
Click **"Save Result"** button

---

## Test Status Explanations

| Status | Meaning | Use When |
|--------|---------|----------|
| **Not Started** | Haven't tested yet | Default state |
| **In Progress** | Currently testing | Started but not done |
| **Passed** ✅ | Test succeeded | Everything worked as expected |
| **Failed** ❌ | Test did not pass | Expected behavior didn't happen |
| **Blocked** ⏸ | Can't test yet | Waiting on dependency, DB issue, etc |
| **Skipped** ⊘ | Test not applicable | Not relevant for this build |

---

## Dashboard Metrics Explained

### Overall Statistics

```
📊 Overview Card

Total Tests: 50
  The complete count of all test cases across all suites

Passed: 45 ✅
  Tests that ran successfully

Failed: 3 ❌
  Tests that did not pass (bugs found)

Blocked: 2 ⏸
  Tests waiting for something (DB setup, dependency, etc)

Issues Found: 3 🔴
  Tests where you documented a bug

Pass Rate: 90%
  (45 / 50) × 100 = Percentage of passing tests
```

### Pass Rate Target
```
🎯 Target for Deployment: 95%+ pass rate

Status Indicators:
- ✅ Ready (90%+)
- ⚠️ Getting Close (80-89%)
- ❌ Needs Work (<80%)
```

### Deployment Readiness Checklist

```
✅ Pass Rate ≥ 95%: Are we at 95% or higher?
✅ All Critical Tests Passing: Are failed tests non-critical?
✅ No Blocked Tests: Are we waiting on anything?
✅ All Tests Started/Completed: Did we skip tests?
```

All 4 must be ✅ before deployment.

---

## Common Workflows

### Workflow 1: Running a Test Suite

**Scenario**: You're assigned "Unit Tests - Scout Service"

**Steps**:
1. Go to http://localhost:8000/admin/writer/testing-tracker.php
2. Click on "Unit Tests - Scout Service" in left panel
3. You see 3 tests:
   - Test 1.1: Chart Jump Detection
   - Test 1.2: Engagement Spike Detection
   - Test 1.3: Spin Surge Detection
4. Click "📝 Update Result" on Test 1.1
5. Fill in:
   - Your Name: "Alice"
   - Status: "in_progress" (while testing)
   - Notes: "Running the PHP test now..."
6. Click "Save Result"
7. Run the test
8. Update again with final status and results

---

### Workflow 2: Finding an Issue

**Scenario**: A test failed and you found a bug

**Steps**:
1. Click "📝 Update Result"
2. Set Status: "failed"
3. Notes: "Chart jump threshold not working. Tested with 25 rank change, no anomaly detected. Should detect this."
4. Check "Found an issue"
5. Issue Title: "Scout service missing large rank changes"
6. Issue Severity: "Critical" (because it's core functionality)
7. Click "Save Result"

**Result**:
- Test marked as failed ❌
- Issue appears in "Issues Found" section
- Shows in red/critical color
- Visible to all team members

---

### Workflow 3: Blocking a Test

**Scenario**: You can't test because database isn't set up

**Steps**:
1. Click "📝 Update Result"
2. Status: "blocked"
3. Notes: "Cannot test - writer_anomalies table missing. Database migration didn't run?"
4. Save

**Result**:
- Test shows as ⏸ Blocked
- Blocks deployment readiness (must be 0 blocked)
- Alerts team that this needs to be fixed

---

### Workflow 4: Checking Deployment Status

**Before Deployment**: Check the "🚀 Deployment Readiness" section

```
Deployment Checklist:
  ✅ Pass Rate ≥ 95%: YES (96%)
  ✅ All Critical Tests Passing: YES
  ✅ No Blocked Tests: YES
  ✅ All Tests Started/Completed: YES

Result: ✅ READY FOR DEPLOYMENT
```

If any are ❌, you're not ready. Fix those issues first.

---

## Tips & Best Practices

### 1. Update as You Go
Don't wait until the end of the day. Update test status immediately:
- Start of test: Status = "in_progress"
- After test: Status = "passed" or "failed"

### 2. Be Detailed in Notes
```
❌ BAD: "failed"
✅ GOOD: "Failed on line 45 of ScoutServiceTest.php.
         Expected 5 anomalies, got 3.
         Chart jump detection not working for gaps < 20 ranks."
```

### 3. Log Issues Properly
When you find a bug:
- ✅ Check "Found an issue"
- ✅ Set severity correctly (Critical vs High vs Medium)
- ✅ Include reproduction steps in notes
- ✅ Mention who should fix it (in notes, or assign after)

### 4. Use Filter Dropdown
To see only:
- Tests you haven't started: Select "Not Started"
- Your current work: Select "In Progress"
- What passed: Select "Passed" (to celebrate! 🎉)
- What failed: Select "Failed" (to fix)

### 5. Check Pass Rate Regularly
You'll see progress as the bar fills up. Aim for:
- Day 1-2: 30-50% (early in testing)
- Day 3-5: 70-85% (fixing issues)
- Day 5-6: 95%+ (ready to deploy)

---

## Common Issues & Solutions

### Issue: "Page doesn't load"
**Solution**:
1. Make sure migration ran: `SHOW TABLES LIKE 'writer_test%'`
2. Check MySQL is running
3. Verify you have admin access
4. Clear browser cache

### Issue: "Data not saving"
**Solution**:
1. Click "Save Result" button (not enter key)
2. Check for error message at top
3. Verify database connectivity
4. Check browser console for errors (F12)

### Issue: "Pass rate not updating"
**Solution**:
1. Refresh page (Ctrl+R)
2. Check that you clicked "Save Result"
3. Verify status changed in modal
4. Try updating a different test to confirm

### Issue: "Can't see my test results"
**Solution**:
1. Make sure you're looking at the right suite (left panel)
2. Check filter dropdown - not hiding your status
3. Scroll down - might be below the fold
4. Try different filter, then back to "All Statuses"

---

## Database Schema Reference

### writer_test_suites
```
id: Unique identifier
name: "Unit Tests - Scout Service"
category: unit|integration|e2e|api|dashboard|cron|safety|performance|edge_cases|manual
assigned_to: "Backend Dev #1"
estimated_hours: 3.0
status: not_started|in_progress|blocked|completed
```

### writer_test_cases
```
id: Unique identifier
suite_id: Foreign key to suite
test_id: "1.1", "1.2" (for grouping)
name: "Chart Jump Detection"
status: not_started|in_progress|passed|failed|blocked|skipped
tester_name: "Alice"
result_notes: Detailed test observations
issue_found: 0|1
issue_title: Description of bug
issue_severity: critical|high|medium|low
```

### writer_test_runs
```
id: Unique identifier
run_name: "Round 1", "Round 2 (post-fixes)"
start_date: When testing started
target_end_date: Target completion
status: planned|in_progress|paused|completed
approval_status: pending|approved|rejected
```

---

## Reports You Can Pull

### For Project Manager

```sql
-- Overall progress
SELECT
  COUNT(*) as total,
  SUM(CASE WHEN status = 'passed' THEN 1 ELSE 0 END) as passed,
  ROUND(100.0 * SUM(CASE WHEN status = 'passed' THEN 1 ELSE 0 END) / COUNT(*), 1) as pass_rate
FROM writer_test_cases;

-- Issues by severity
SELECT issue_severity, COUNT(*) as count
FROM writer_test_cases
WHERE issue_found = 1
GROUP BY issue_severity
ORDER BY FIELD(issue_severity, 'critical', 'high', 'medium', 'low');

-- Tests by person
SELECT tester_name, COUNT(*) as total,
  SUM(CASE WHEN status = 'passed' THEN 1 ELSE 0 END) as passed
FROM writer_test_cases
WHERE tester_name IS NOT NULL
GROUP BY tester_name;
```

---

## Access Permissions

Only admin users can access: `/admin/writer/testing-tracker.php`

Grant access in admin_users table or through your auth system.

---

## Going Live Checklist

Before clicking "Deploy":

```
□ Dashboard shows 95%+ pass rate
□ All blocked tests resolved
□ All not_started tests completed
□ Critical issues fixed or documented
□ High issues triaged
□ Medium/Low issues logged for post-deployment
□ Test run marked as "completed"
□ Project manager approved
□ Team agreed: "Ready to ship!"
```

---

## Need Help?

1. **Dashboard questions**: See sections above
2. **Database issues**: Check TESTING_TRACKER_SETUP.md → "Common Issues"
3. **Test failures**: See TESTING_PLAN_WRITER_ENGINE.md for detailed test steps
4. **General questions**: Ask in #writer-engine-testing Slack channel

---

**Last Updated**: 2026-01-21
**Version**: 1.0
