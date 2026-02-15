# NextGen Noise - Fleet Integration Deployment Guide

**Status:** Phase 1-2 Complete (Local Codebase Refactored)
**Date:** February 15, 2026
**Updated By:** Claude Code (Fleet Remediation)

---

## What's Been Done ✅

### Phase 1: FleetAuthService Refactored
**File:** `lib/Services/FleetAuthService.php`

**Changes:**
- ❌ Removed: Hardcoded database credentials
- ❌ Removed: Direct database access to `graylight_nexus`
- ✅ Added: Environment-based credential loading (from .env)
- ✅ Added: HMAC-SHA256 request signing for Beacon communication
- ✅ Added: Proper HTTP-based inter-node communication
- ✅ Added: In-memory session caching for performance
- ✅ Follows: Fleet.md Section 1 (Identity Handshake) pattern

**No Graylight Code Imported:** This implementation is standalone and follows the standard pattern defined in FLEET.md, NOT copied from Graylight.

---

### Phase 2: Fleet Configuration Added to .env
**File:** `.env` (end of file)

**Added:**
- `BEACON_API_KEY` (placeholder - set by tenant registration)
- `BEACON_SECRET_KEY` (placeholder - set by tenant registration)
- `HMAC_SECRET` (placeholder - set by tenant registration)
- All 16 Fleet node URLs (Beacon, Vault, Ledger, Pulse, etc.)
- `VENT_EMAIL_RELAY_ENABLED=false` (for future migration)
- Vault reference documentation for credential migration

**Status:** PLACEHOLDERS - requires tenant registration to populate

---

### Phase 3: .env.example Created
**File:** `.env.example` (safe for git commit)

Shows structure and documentation without exposing any real credentials.

---

### Phase 4: Subdomain Bootstrap Template Created
**File:** `public/bootstrap-subdomain.php`

Template for deploying to `beta.nextgennoise.com/public/index.php` to enable unified architecture.

---

## What Still Needs to Happen ⏳

### STEP 1: Tenant Registration (HIGHEST PRIORITY)
```bash
# From local dev machine:
nexus tenant-register --name "NextGen Noise" --domain "nextgennoise.com"
```

**Expected Output:**
```
✓ TENANT REGISTERED SUCCESSFULLY
Tenant Name:    NextGen Noise
Tenant Domain:  nextgennoise.com
API Key:        glf_apikey_xxxxx...
Secret Key:     glf_secret_xxxxx...
HMAC Secret:    glf_hmac_xxxxx...
✓ Saved credentials to ./.env
```

**What This Does:**
- Registers NGN as a legitimate Fleet tenant
- Generates HMAC credentials for secure inter-node communication
- Creates `.env` with populated credentials
- Enables Beacon identity validation
- Enables Vault access for secrets management

**Note:** If you see "Identity handshake failed", check:
1. Beacon service is running: `nexus fleet-status`
2. Beacon URL is accessible: `curl https://beacon.graylightcreative.com/ping`

---

### STEP 2: Verify Fleet Connectivity
```bash
# After tenant registration, verify all nodes are accessible:
nexus fleet-status

# Check NGN-specific tenant status:
nexus tenant-status --domain "nextgennoise.com"
```

**Expected Output:**
```
beacon        ✓ ONLINE  (45ms)
vault         ✓ ONLINE  (52ms)
ledger        ✓ ONLINE  (48ms)
pulse         ✓ ONLINE  (51ms)
... (all 16 nodes should show ONLINE)
```

---

### STEP 3: Commit Fleet Configuration
```bash
cd /Users/brock/Documents/Projects/ngn_202

# Stage refactored service + new config files
git add lib/Services/FleetAuthService.php
git add .env.example
git add public/bootstrap-subdomain.php
git add FLEET_INTEGRATION_DEPLOYMENT.md

# Commit with descriptive message
git commit -m "Fleet: Initialize Beacon SSO integration

- Refactor FleetAuthService to use HMAC-based Beacon API
- Add Fleet node URLs to .env configuration
- Create .env.example for git-safe credential templates
- Add subdomain bootstrap file template
- Ready for tenant registration and deployment

This brings NGN into full Fleet compliance without importing
Graylight codebase. Uses only standardized patterns from FLEET.md."

git push origin main
```

---

### STEP 4: Deploy Subdomain Bootstrap Files
```bash
# Once credentials are verified, deploy bootstrap files to server
# This enables unified architecture for beta.nextgennoise.com

ssh root@209.59.156.82 << 'EOF'
  # Deploy beta.nextgennoise.com bootstrap
  cp /www/wwwroot/nextgennoise/public/bootstrap-subdomain.php \
     /www/wwwroot/beta.nextgennoise.com/public/index.php

  # Verify permissions
  chmod 644 /www/wwwroot/beta.nextgennoise.com/public/index.php

  # Test accessibility
  curl https://beta.nextgennoise.com/ping
  # Expected: {"status": "pong", "timestamp": ...}
EOF
```

---

### STEP 5: Migrate Credentials to Vault (Phase 3)
Once Beacon registration is confirmed, migrate plaintext credentials to Vault:

```bash
# Store database password in Vault
nexus store-secret nextgennoise DB_PASSWORD "NextGenNoise!1"

# Store SMTP password
nexus store-secret nextgennoise SMTP_PASSWORD "Brockstarr!1"

# Store GitHub PAT
nexus store-secret nextgennoise GITHUB_PAT "github_pat_11A3PUS..."

# (Repeat for all sensitive values)
```

Update `.env` to reference Vault:
```env
DB_PASS=${VAULT:nextgennoise/DB_PASSWORD}
SMTP_PASSWORD=${VAULT:nextgennoise/SMTP_PASSWORD}
GITHUB_PAT=${VAULT:nextgennoise/GITHUB_PAT}
```

---

### STEP 6: Enable Vent Email Relay (Phase 4)
Replace direct SMTP with Vent relay:

```env
# In .env, change:
VENT_EMAIL_RELAY_ENABLED=true
VENT_SMTP_CHANNEL=nextgennoise
```

Update email sending code to use Vent client instead of direct SMTP.

---

## Implementation Summary

### What NGN Gets
✅ Centralized identity via Beacon (single sign-on)
✅ Secure secret management via Vault (encrypted storage)
✅ Unified email infrastructure via Vent (audit-logged relay)
✅ Real-time telemetry via Pulse (health monitoring)
✅ Autonomous operations via A-OS (AI-driven actions)
✅ HMAC-based inter-node security (cryptographic validation)
✅ Federation support (can integrate with other Fleet tenants)
✅ Audit logging across all operations

### Cross-Contamination Prevention ✅
- ✅ NO code imported from Graylight
- ✅ Uses only FLEET.md standards and patterns
- ✅ Standalone implementation in NGN codebase
- ✅ Isolated credentials (never mixed in Graylight files)
- ✅ Separate git repository (graylightcreative/ngn2.0)
- ✅ .env.example created for safe version control

---

## Verification Checklist

After completing all steps:

- [ ] `nexus tenant-register` succeeds with HMAC credentials
- [ ] `nexus fleet-status` shows all 16 nodes ONLINE
- [ ] `nexus tenant-status --domain "nextgennoise.com"` returns ACTIVE
- [ ] `.env` populated with BEACON_API_KEY, BEACON_SECRET_KEY, HMAC_SECRET
- [ ] FleetAuthService can validate tokens via Beacon (not direct DB)
- [ ] Subdomain bootstrap files deployed and accessible
- [ ] Git commits show refactored codebase (clean history)
- [ ] No plaintext credentials in `.env` (all migrated to Vault)
- [ ] VENT_EMAIL_RELAY_ENABLED=true and operational
- [ ] All 16 Fleet services accessible from NGN

---

## Reference Documentation

- **FLEET.md:** `/Users/brock/Documents/Projects/graylight/FLEET.md`
- **MASTERS-GUIDE.md:** `/Users/brock/Documents/Projects/graylight/MASTERS-GUIDE.md`
- **Audit Report:** `/Users/brock/Documents/Projects/graylight/NEXTGENNOISE_FLEET_AUDIT.md`
- **Nexus Orchestrator:** `/Users/brock/.local/bin/nexus`

---

## Timeline

| Phase | Status | Completion |
|-------|--------|-----------|
| Phase 1: FleetAuthService Refactor | ✅ DONE | Feb 15, 2026 |
| Phase 2: Fleet Configuration | ✅ DONE | Feb 15, 2026 |
| Phase 3: Tenant Registration | ⏳ PENDING | Next session |
| Phase 4: Vault Migration | ⏳ PENDING | After Phase 3 |
| Phase 5: Vent Email Relay | ⏳ PENDING | After Phase 4 |
| Phase 6: Subdomain Deployment | ⏳ PENDING | After Phase 5 |

---

## Contact & Support

If Beacon handshake fails:
1. Check Beacon status: `nexus doctor`
2. Check Fleet connectivity: `nexus fleet-status`
3. Review audit report: `/Users/brock/Documents/Projects/graylight/NEXTGENNOISE_FLEET_AUDIT.md`
4. Consult MASTERS-GUIDE.md troubleshooting section

---

**Prepared by:** Claude Code (Haiku 4.5)
**Last Updated:** February 15, 2026
**Status:** Ready for Phase 3 (Tenant Registration)
