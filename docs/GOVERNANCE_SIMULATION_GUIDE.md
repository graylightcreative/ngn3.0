# 🏛️ NGN Governance Loop Simulation Guide

This guide details how to verify and simulate the Chapter 31 Governance Workflow (Standardized Input Requests - SIRs). This is critical for ensuring the board-level decision-making system is operational.

## 🎯 The Workflow (The 5 States)

The SIR system follows a strict state machine to ensure an immutable paper trail:

1.  **OPEN**: Chairman (User 1) issues a request with the "Four Pillars".
2.  **IN REVIEW**: Director (e.g., Erik Baker) claims the task.
3.  **RANT PHASE**: Director provides technical feedback or objections.
4.  **VERIFIED**: Director "Signs Off" on the logic/deliverable.
5.  **CLOSED**: Chairman archives and locks the SIR.

## 🛠️ Simulation & Verification

To verify that the entire database and service layer is functioning correctly, you can run the automated simulation script.

### Running the Simulation

```bash
php scripts/SIMULATE_GOVERNANCE_LOOP.php
```

### Expected Output

A successful run will show the following sequence:
```text
--- NGN Governance Loop Simulation ---

[Step 1] Chairman creating SIR for Erik Baker...
✓ SIR Created: SIR-2026-XXX (ID: #)

[Step 2] Erik Baker claiming SIR...
✓ Status updated to: IN_REVIEW

[Step 3] Erik Baker adding feedback (The Rant)...
✓ Feedback added. Status automatically moved to: RANT_PHASE

[Step 4] Erik Baker verifying the SIR...
✓ Status updated to: VERIFIED

[Step 5] Chairman closing the SIR...
✓ Status updated to: CLOSED

--- Simulation Complete: 100% Success ---
```

## 📂 System Architecture

### Core Services
- `NGN\Lib\Governance\SirRegistryService`: Main CRUD and state machine logic.
- `NGN\Lib\Governance\SirAuditService`: Immutable audit trail provider.
- `NGN\Lib\Governance\SirNotificationService`: Handles mobile/system alerts.
- `NGN\Lib\Governance\DirectorateRoles`: Maps slugs (brandon, pepper, erik) to User IDs.

### Database Tables (ngn_2025)
- `directorate_sirs`: The main registry.
- `sir_feedback`: Threaded discussion logs.
- `sir_audit_log`: Immutable action history.
- `sir_notifications`: Queue for push notifications.

## 🔍 Manual Verification (SQL)

To manually inspect the state of the governance system on the production server:

```sql
-- Check SIR Status
SELECT sir_number, status, objective FROM ngn_2025.directorate_sirs ORDER BY id DESC;

-- View Audit Trail for a specific SIR
SELECT * FROM ngn_2025.sir_audit_log WHERE sir_id = 1;

-- View Feedback Thread
SELECT author_role, feedback_text FROM ngn_2025.sir_feedback WHERE sir_id = 1;
```

## 🚨 Troubleshooting

- **"Failed to generate unique SIR number"**: Check if the `directorate_sirs` table exists and is accessible.
- **"Column not found"**: Ensure the `INSTALL_GOVERNANCE_TABLES.php` script has been run to align the schema with the latest service requirements.
- **"Invalid status transition"**: The system prevents illegal moves (e.g., OPEN -> VERIFIED without review). Follow the 5-state sequence.

---
**Status**: Verified Operational (2026-02-06)
**Bible Reference**: Chapter 31
