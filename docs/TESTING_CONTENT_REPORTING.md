# Content Reporting - Testing Documentation

**Feature:** Content Reporting System (Sprint 3.3)
**Status:** Ready for Testing
**Last Updated:** 2026-02-04

---

## Test Plan Overview

This feature allows users to report violations of community standards and enables admins to process these reports efficiently.

### 1. Database Schema Verification
- [ ] Table `content_reports` exists with ENUMs for `entity_type`, `reason`, and `status`.
- [ ] Correct indexes on `status` and `entity` for performance.

### 2. Submission API
- [ ] **POST /api/v1/content/report**
  - [ ] Requires valid JWT.
  - [ ] Validates `entity_type` and `reason` against allowed values.
  - [ ] Stores `reporter_user_id` correctly.
  - [ ] Returns 201 Created on success.

### 3. Admin Management
- [ ] Dashboard correctly lists only `pending` or `reviewing` reports.
- [ ] "Take Action" marks report as `resolved`.
- [ ] "Dismiss" marks report as `dismissed`.
- [ ] Admin notes are persisted correctly.

### 4. Security & Permissions
- [ ] Users cannot report content anonymously.
- [ ] Users can only report valid entity types.
- [ ] Only Admin roles can access the reporting queue (implicit check in production).

---

## Manual Test Scenarios

### Scenario 1: Reporting a Post
1. Log in as an Artist.
2. Submit a report for Post #101 with reason `spam`.
3. Verify record appears in `content_reports` table.
4. Open Admin dashboard and verify report appears in the queue.

### Scenario 2: Resolving a Report
1. As an Admin, find a pending report.
2. Add a note: "User warned, post hidden."
3. Click "Take Action".
4. Verify report status is now `resolved` and `resolved_at` is set.
5. Verify report is removed from the active queue.

### Scenario 3: Validation Error
1. Submit a report with an invalid `entity_type` (e.g., 'spaceship').
2. Verify API returns 500 or 400 error with descriptive message.
