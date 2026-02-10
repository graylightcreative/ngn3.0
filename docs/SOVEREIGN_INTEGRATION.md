# Graylight Sovereign API Integration (Tenant Mandate)

**Status:** ✅ BRIDGE OPERATIONAL
**Architectural Role:** Client-Side Tenant (NGN 2.0)
**Integration Base:** `https://graylightcreative.com/api/v1`

## 1. The Mandate
NGN has been transitioned from a monolithic application to a Tenant of the Graylight Creative Foundry. All core services for Identity, Storage, and Financial Settlement are now "Pulled" from the Graylight Sovereign API.

## 2. The Service Bridge
The `GraylightServiceClient` is the centralized conduit for all outbound communication. It implements strict HMAC SHA-256 signing for every request to ensure tenant integrity.

**Bridge Location:** `lib/Services/Graylight/GraylightServiceClient.php`

## 3. Integrated Workflows

### [BEACON] Identity & Auth
*   **Status:** IMPLEMENTED (`IdentityService.php`)
*   **Logic:** NGN no longer owns or stores user passwords. Authentication is offloaded to the Graylight Beacon.
*   **Result:** Local sessions store only the `sovereign_id` and `jwt` returned by the Mothership.

### [VAULT] Secure Storage
*   **Status:** IMPLEMENTED (`VaultStorageService.php`)
*   **Logic:** NGN does not write bytes to local disks. It initiates a handshake with the Graylight Vault.
*   **Result:** NGN pulls a pre-signed `upload_url` for the client and stores only the resulting `vault_id`.

### [LEDGER] 90/10 Splits
*   **Status:** IMPLEMENTED (`SettleService.php`)
*   **Logic:** Financial mathematics and Stripe settlement are decommissioned in NGN.
*   **Result:** NGN calls the Graylight Ledger to execute 90/10 splits and logs the returned `integrity_hash`.

## 4. Environment Requirements
The following keys must be present in the `.env` file:
*   `GL_API_KEY`: Tenant identification key.
*   `GL_SECRET_KEY`: Private signing key for HMAC signatures.
*   `GL_BASE_URL`: API gateway (Default: `https://graylightcreative.com/api/v1`).

## 5. Audit Trail
*   **Bridge Implemented:** Feb 10, 2026
*   **HMAC Signing Verified:** YES
*   **Smoke Test Script:** `scripts/smoke-test-graylight.php`
