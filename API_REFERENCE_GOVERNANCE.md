# Governance API Reference - Complete Guide

**Base URL**: https://your-domain.com/api/v1
**Authentication**: Bearer JWT Token
**Response Format**: JSON
**Version**: 1.0 (Chapter 31)

---

## 🔐 Authentication

All governance endpoints require JWT Bearer token in the `Authorization` header.

### Request Header
```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
Content-Type: application/json
```

### Getting a Token
```bash
curl -X POST https://your-domain.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "your_password"
  }'

# Response:
{
  "success": true,
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "user": { "id": 1, "name": "Jon Brock Lamb" }
  }
}
```

---

## 📊 ENDPOINTS

### 1. POST /governance/sir - Create SIR

**Create a new Standardized Input Request (Chairman only)**

```bash
curl -X POST https://your-domain.com/api/v1/governance/sir \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "objective": "Verify Escrow Compliance",
    "context": "Ensure Rights Ledger dispute handling meets institutional standards before Series A pitch",
    "deliverable": "One-page technical critique confirming escrow logic satisfies bank requirements",
    "threshold": "Before Jan 26 Tax Activation",
    "threshold_date": "2026-01-26",
    "assigned_to_director": "brandon",
    "priority": "critical",
    "notes": "Reference: NGN_The_Jimmy_Scenarios.md section on multi-party splits"
  }'
```

**Response (201 Created)**:
```json
{
  "success": true,
  "data": {
    "sir_id": 42,
    "sir_number": "SIR-2026-042",
    "status": "open",
    "assigned_to": "Brandon Lamb",
    "assigned_to_director": "brandon",
    "objective": "Verify Escrow Compliance",
    "notification_sent": true,
    "created_at": "2026-01-25T10:30:00Z"
  },
  "message": "SIR created successfully. Notification sent to assigned director."
}
```

**Parameters**:
| Param | Type | Required | Notes |
|-------|------|----------|-------|
| objective | string | ✅ | One-sentence goal (max 255 chars) |
| context | string | ✅ | Why it matters (max 2000 chars) |
| deliverable | string | ✅ | What "done" looks like (max 2000 chars) |
| threshold | string | ⚠️ | Deadline description (e.g., "Before Jan 26") |
| threshold_date | date | ⚠️ | Format: YYYY-MM-DD |
| assigned_to_director | string | ✅ | "brandon", "pepper", or "erik" |
| priority | string | ⚠️ | "critical", "high", "medium", "low" (default: "medium") |
| notes | string | ⚠️ | Additional context or attachments |

**Errors**:
```json
{
  "success": false,
  "error": "Invalid director. Must be: brandon, pepper, or erik",
  "code": 400
}
```

---

### 2. GET /governance/sir - List SIRs

**Retrieve list of SIRs with optional filters**

```bash
# Basic request
curl -X GET https://your-domain.com/api/v1/governance/sir \
  -H "Authorization: Bearer YOUR_TOKEN"

# With filters
curl -X GET "https://your-domain.com/api/v1/governance/sir?status=open&director=brandon&overdue=false&limit=50&offset=0" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response (200 OK)**:
```json
{
  "success": true,
  "data": {
    "pagination": {
      "total": 47,
      "limit": 50,
      "offset": 0,
      "returned": 47
    },
    "sirs": [
      {
        "sir_id": 42,
        "sir_number": "SIR-2026-042",
        "objective": "Verify Escrow Compliance",
        "assigned_to": "Brandon Lamb",
        "assigned_to_director": "brandon",
        "status": "in_review",
        "priority": "critical",
        "registry_division": "saas_fintech",
        "issued_at": "2026-01-25T10:30:00Z",
        "days_open": 1,
        "is_overdue": false
      },
      {
        "sir_id": 41,
        "sir_number": "SIR-2026-041",
        "objective": "Review D&B Credit Tier Path",
        "assigned_to": "Pepper Gomez",
        "assigned_to_director": "pepper",
        "status": "open",
        "priority": "high",
        "registry_division": "strategic_ecosystem",
        "issued_at": "2026-01-24T14:00:00Z",
        "days_open": 2,
        "is_overdue": false
      }
    ]
  }
}
```

**Query Parameters**:
| Param | Type | Options | Default |
|-------|------|---------|---------|
| status | string | open, in_review, rant_phase, verified, closed | (all) |
| director | string | brandon, pepper, erik | (all) |
| priority | string | critical, high, medium, low | (all) |
| registry | string | saas_fintech, strategic_ecosystem, data_integrity | (all) |
| overdue | boolean | true, false | (all) |
| limit | number | 1-100 | 50 |
| offset | number | 0+ | 0 |

---

### 3. GET /governance/sir/{id} - Get SIR Details

**Retrieve full details of a specific SIR**

```bash
curl -X GET https://your-domain.com/api/v1/governance/sir/42 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response (200 OK)**:
```json
{
  "success": true,
  "data": {
    "sir_id": 42,
    "sir_number": "SIR-2026-042",
    "objective": "Verify Escrow Compliance",
    "context": "Ensure Rights Ledger dispute handling meets institutional standards before Series A pitch",
    "deliverable": "One-page technical critique confirming escrow logic satisfies bank requirements",
    "threshold": "Before Jan 26 Tax Activation",
    "threshold_date": "2026-01-26",
    "assigned_to_director": "brandon",
    "director_name": "Brandon Lamb",
    "registry_division": "saas_fintech",
    "status": "in_review",
    "priority": "critical",
    "issued_at": "2026-01-25T10:30:00Z",
    "claimed_at": "2026-01-25T11:00:00Z",
    "rant_started_at": null,
    "verified_at": null,
    "closed_at": null,
    "days_until_threshold": 1,
    "days_open": 1,
    "is_overdue": false,
    "feedback_count": 0,
    "notes": "Reference: NGN_The_Jimmy_Scenarios.md section on multi-party splits"
  }
}
```

---

### 4. PATCH /governance/sir/{id}/status - Update Status

**Update SIR status (follows workflow: OPEN → IN_REVIEW → RANT_PHASE → VERIFIED → CLOSED)**

```bash
curl -X PATCH https://your-domain.com/api/v1/governance/sir/42/status \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "new_status": "verified"
  }'
```

**Response (200 OK)**:
```json
{
  "success": true,
  "message": "SIR status updated to verified",
  "data": {
    "sir_id": 42,
    "sir_number": "SIR-2026-042",
    "old_status": "rant_phase",
    "new_status": "verified",
    "verified_at": "2026-01-25T15:30:00Z",
    "notification_sent": true
  }
}
```

**Valid Status Transitions**:
- OPEN → IN_REVIEW (director claims)
- IN_REVIEW → RANT_PHASE (director adds feedback)
- IN_REVIEW → VERIFIED (director approves directly)
- RANT_PHASE → VERIFIED (after feedback exchange)
- VERIFIED → CLOSED (chairman archives)

**Error (Invalid Transition)**:
```json
{
  "success": false,
  "error": "Cannot transition from 'verified' to 'open'. VERIFIED status is terminal.",
  "code": 400
}
```

---

### 5. POST /governance/sir/{id}/verify - One-Tap Verification

**Quick mobile verification (director only)**

```bash
curl -X POST https://your-domain.com/api/v1/governance/sir/42/verify \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response (200 OK)**:
```json
{
  "success": true,
  "message": "SIR-2026-042 verified successfully",
  "data": {
    "sir_number": "SIR-2026-042",
    "verified_at": "2026-01-25T15:30:00Z",
    "director": "Brandon Lamb",
    "status": "verified"
  }
}
```

**Mobile Verification Flow**:
1. Director receives push notification
2. Taps "Verify" action in notification
3. One-tap verification triggers this endpoint
4. SIR immediately marked as VERIFIED
5. No additional UI needed

---

### 6. POST /governance/sir/{id}/feedback - Add Feedback

**Add feedback during Rant Phase**

```bash
curl -X POST https://your-domain.com/api/v1/governance/sir/42/feedback \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "feedback_text": "The escrow logic looks solid but we need to add a 7-day dispute window. Current implementation assumes instant finality which won'\''t satisfy label legal teams.",
    "feedback_type": "director_comment"
  }'
```

**Response (201 Created)**:
```json
{
  "success": true,
  "data": {
    "feedback_id": 125,
    "sir_id": 42,
    "author": "Brandon Lamb",
    "author_role": "director",
    "feedback_type": "director_comment",
    "created_at": "2026-01-25T14:15:00Z"
  }
}
```

**Feedback Types**:
- `director_comment` - Director provides feedback
- `chairman_response` - Chairman responds to director
- `rant_phase` - General discussion during RANT_PHASE
- `clarification` - Clarifying question or answer

---

### 7. GET /governance/sir/{id}/feedback - Get Feedback Thread

**Retrieve all feedback for a SIR (threaded discussion)**

```bash
curl -X GET https://your-domain.com/api/v1/governance/sir/42/feedback \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response (200 OK)**:
```json
{
  "success": true,
  "data": {
    "sir_id": 42,
    "feedback_count": 3,
    "feedback": [
      {
        "feedback_id": 123,
        "feedback_type": "director_comment",
        "feedback_text": "Reviewing the Stripe Connect logic now...",
        "author": "Brandon Lamb",
        "author_role": "director",
        "created_at": "2026-01-25T11:00:00Z"
      },
      {
        "feedback_id": 124,
        "feedback_type": "rant_phase",
        "feedback_text": "The escrow logic needs refinement to include dispute window.",
        "author": "Brandon Lamb",
        "author_role": "director",
        "created_at": "2026-01-25T14:15:00Z"
      },
      {
        "feedback_id": 125,
        "feedback_type": "chairman_response",
        "feedback_text": "Updated escrow with 7-day dispute window. Please re-review.",
        "author": "Jon Brock Lamb",
        "author_role": "chairman",
        "created_at": "2026-01-25T15:00:00Z"
      }
    ]
  }
}
```

---

### 8. GET /governance/dashboard - Dashboard Statistics

**Get real-time governance dashboard stats**

```bash
# Overall stats
curl -X GET https://your-domain.com/api/v1/governance/dashboard \
  -H "Authorization: Bearer YOUR_TOKEN"

# Filter by director
curl -X GET "https://your-domain.com/api/v1/governance/dashboard?director=brandon" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response (200 OK)**:
```json
{
  "success": true,
  "data": {
    "overview": {
      "total_sirs": 47,
      "open": 12,
      "in_review": 18,
      "rant_phase": 8,
      "verified": 7,
      "closed": 2,
      "overdue": 3
    },
    "by_director": {
      "brandon": {
        "total": 18,
        "open": 5,
        "in_review": 7,
        "rant_phase": 2,
        "verified": 4,
        "closed": 0,
        "avg_days_to_verify": 3.2
      },
      "pepper": {
        "total": 15,
        "open": 4,
        "in_review": 6,
        "rant_phase": 3,
        "verified": 2,
        "closed": 0,
        "avg_days_to_verify": 5.1
      },
      "erik": {
        "total": 14,
        "open": 3,
        "in_review": 5,
        "rant_phase": 3,
        "verified": 1,
        "closed": 2,
        "avg_days_to_verify": 7.8
      }
    },
    "overdue_sirs": [
      {
        "sir_number": "SIR-2026-005",
        "objective": "Review D&B Credit Tier Path",
        "assigned_to": "Brandon Lamb",
        "days_open": 16,
        "status": "open"
      }
    ],
    "recent_activity": [
      {
        "sir_number": "SIR-2026-042",
        "action": "status_changed",
        "from": "in_review",
        "to": "rant_phase",
        "timestamp": "2026-01-25T14:15:00Z",
        "actor": "Brandon Lamb"
      }
    ]
  }
}
```

---

### 9. GET /governance/sir/{id}/audit - Get Audit Trail (Admin Only)

**Retrieve immutable audit log for a SIR**

```bash
curl -X GET https://your-domain.com/api/v1/governance/sir/42/audit \
  -H "Authorization: Bearer ADMIN_TOKEN"
```

**Response (200 OK)**:
```json
{
  "success": true,
  "data": {
    "sir_id": 42,
    "sir_number": "SIR-2026-042",
    "audit_trail": [
      {
        "audit_id": 1,
        "action": "created",
        "actor": "Jon Brock Lamb",
        "actor_role": "chairman",
        "actor_user_id": 1,
        "created_at": "2026-01-25T10:30:00Z",
        "ip_address": "192.168.1.1",
        "user_agent": "Mozilla/5.0..."
      },
      {
        "audit_id": 2,
        "action": "status_change",
        "old_status": "open",
        "new_status": "in_review",
        "actor": "Brandon Lamb",
        "actor_role": "director",
        "actor_user_id": 2,
        "created_at": "2026-01-25T11:00:00Z"
      },
      {
        "audit_id": 3,
        "action": "feedback_added",
        "actor": "Brandon Lamb",
        "actor_role": "director",
        "created_at": "2026-01-25T14:15:00Z"
      }
    ]
  }
}
```

---

## 🔍 COMMON EXAMPLES

### Example 1: Complete SIR Workflow

```bash
# 1. Create SIR (Chairman)
RESPONSE=$(curl -s -X POST https://your-domain.com/api/v1/governance/sir \
  -H "Authorization: Bearer CHAIRMAN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "objective": "Verify Escrow Compliance",
    "context": "Technical validation needed",
    "deliverable": "One-page critique",
    "assigned_to_director": "brandon",
    "priority": "critical"
  }')

SIR_ID=$(echo $RESPONSE | jq -r '.data.sir_id')
echo "Created SIR: $SIR_ID"

# 2. Director claims (status → IN_REVIEW)
curl -s -X PATCH https://your-domain.com/api/v1/governance/sir/$SIR_ID/status \
  -H "Authorization: Bearer DIRECTOR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"new_status": "in_review"}'

# 3. Add feedback (status → RANT_PHASE)
curl -s -X PATCH https://your-domain.com/api/v1/governance/sir/$SIR_ID/status \
  -H "Authorization: Bearer DIRECTOR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"new_status": "rant_phase"}'

curl -s -X POST https://your-domain.com/api/v1/governance/sir/$SIR_ID/feedback \
  -H "Authorization: Bearer DIRECTOR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "feedback_text": "Need 7-day dispute window",
    "feedback_type": "director_comment"
  }'

# 4. One-tap verify (status → VERIFIED)
curl -s -X POST https://your-domain.com/api/v1/governance/sir/$SIR_ID/verify \
  -H "Authorization: Bearer DIRECTOR_TOKEN"

# 5. Close (status → CLOSED)
curl -s -X PATCH https://your-domain.com/api/v1/governance/sir/$SIR_ID/status \
  -H "Authorization: Bearer CHAIRMAN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"new_status": "closed"}'
```

---

## ✅ STATUS CODES

| Code | Meaning | Example |
|------|---------|---------|
| 200 | OK - Request successful | GET /governance/sir |
| 201 | Created - Resource created | POST /governance/sir |
| 400 | Bad Request | Missing required field |
| 403 | Forbidden - Permission denied | Non-chairman creating SIR |
| 404 | Not Found | SIR doesn't exist |
| 409 | Conflict - Invalid transition | VERIFIED → OPEN |
| 500 | Server Error | Database error |

---

## 🚀 QUICK START

```bash
# 1. Get your token
TOKEN=$(curl -s -X POST https://your-domain.com/api/v1/auth/login \
  -d '{"email":"user@example.com","password":"pass"}' | jq -r '.data.token')

# 2. List SIRs
curl -X GET https://your-domain.com/api/v1/governance/sir \
  -H "Authorization: Bearer $TOKEN"

# 3. Get dashboard stats
curl -X GET https://your-domain.com/api/v1/governance/dashboard \
  -H "Authorization: Bearer $TOKEN"

# 4. Create SIR
curl -X POST https://your-domain.com/api/v1/governance/sir \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "objective": "Your goal",
    "context": "Why it matters",
    "deliverable": "What success looks like",
    "assigned_to_director": "brandon"
  }'
```

---

**API Reference Status**: Complete
**Last Updated**: 2026-01-25
**Version**: 1.0 (Chapter 31)

