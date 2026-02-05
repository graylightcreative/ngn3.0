# Sound Check Notifications - Testing Documentation

**Feature:** iOS Sound Check Notifications (Sprint 3.1)
**Status:** Ready for Testing
**Last Updated:** 2026-02-04

---

## Test Plan Overview

This document outlines the testing scenarios for the Sound Check Notification system, ensuring artists receive timely and relevant updates about their audio processing events.

### 1. Database Schema Verification
- [ ] Table `sound_check_events` exists with correct columns.
- [ ] Table `sound_check_preferences` exists with default values.
- [ ] Table `sound_check_notifications` exists for history tracking.

### 2. Event Recording API
- [ ] **POST /api/v1/sound-check/notify**
  - [ ] Valid request creates event and queues notification.
  - [ ] Missing fields returns 400 Bad Request.
  - [ ] Invalid artist ID handles gracefully.

### 3. Preference Filtering
- [ ] Notification is suppressed if `notifications_enabled` is 0.
- [ ] Notification triggers only for enabled status types (started, completed, failed).
- [ ] Updates via Admin UI persist correctly in database.

### 4. Notification Batching & Delivery
- [ ] Notifications stay in `queued` status for first 5 minutes.
- [ ] Cron job processes notifications older than 5 minutes.
- [ ] Status updates to `sent` after delivery.
- [ ] iOS-specific data (haptic, deep link) included in payload.

### 5. Priority & UI Testing
- [ ] `failed` status triggers HIGH priority (9) delivery.
- [ ] Admin dashboard displays all artists and their current settings.
- [ ] One-tap save works for preference updates.

---

## Manual Test Scenarios

### Scenario 1: Successful Completion
1. Trigger event via API with status `completed`.
2. Verify record in `sound_check_events`.
3. Verify record in `sound_check_notifications` with status `queued`.
4. Wait 5 minutes and run process job.
5. Verify notification sent to `PushNotificationService`.

### Scenario 2: Error Handling
1. Trigger event via API with status `failed`.
2. Verify notification queued with priority 9.
3. Check notification body includes the failure reason.

### Scenario 3: User Opt-out
1. Disable notifications for an artist in Admin UI.
2. Trigger event via API.
3. Verify NO notification is queued in `sound_check_notifications`.
