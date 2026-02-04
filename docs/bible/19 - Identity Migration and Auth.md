19. Identity Migration & Auth Bridge

19.1 Overview

In NGN 1.0, "Users" were not general consumers; they were strictly business stakeholders (Artists, Labels, and the Platform Admin). NGN 2.0 introduces a decoupled Canonical User (cdm_users) model to support Fans and VIPs, but we must allow existing stakeholders to migrate their accounts seamlessly.

19.2 The "Stakeholder-First" Context

Legacy Scope: The legacy users table contains only Artists, Labels, and the Admin.

JIT Migration: We do not perform a bulk import of passwords. Instead, we use a Just-In-Time (JIT) strategy that migrates accounts during their first successful login attempt on the new platform.

Fresh Starts: Because general "Fans" did not have legacy accounts, all consumer-level users will be "Fresh Starts" in NGN 2.0, keeping the migration bridge focused strictly on revenue-generating stakeholders.

19.3 The JIT Migration Workflow

When a legacy stakeholder attempts to log in to NGN 2.0:

Phase 1: CDM Check

The API checks cdm_users for the provided email.

If found: Proceed with standard JWT authentication.

Phase 2: Legacy Bridge (The Handshake)

If not found in cdm_users, the API queries the Legacy Database.

It retrieves the legacy password hash associated with that email.

Phase 3: Verification

The system uses a legacy-compatible verification service to check the plain-text password against the legacy hash (e.g., MD5 or early Bcrypt).

Phase 4: Provision & Upgrade

Upon successful verification, the system creates a new cdm_users record (UUID).

Security Upgrade: The password is re-hashed using Argon2id (the PHP 8.4 standard).

Entity Linking: The new user_id is linked to the existing artist_id or label_id via a metadata mapping table.

Cleanup: The legacy record is marked as is_migrated = 1.

19.4 Technical Implementation (PHP 8.4)

The AuthService will implement a fallback mechanism to handle the legacy hashing:

public function attemptLogin(string $email, string $password): ?User
{
    // 1. Check NGN 2.0 (CDM)
    $user = $this->userRepo->findByEmail($email);
    
    if ($user && password_verify($password, $user->password_hash)) {
        return $user;
    }

    // 2. Check Legacy Bridge
    if (!$user) {
        $legacyUser = $this->legacyRepo->findUserByEmail($email);
        
        if ($legacyUser && $this->verifyLegacyPassword($password, $legacyUser->hash)) {
            // Provision new CDM account and upgrade hash
            return $this->migrateUser($legacyUser, $password);
        }
    }

    return null;
}


19.5 Admin & Security Features

Multi-Factor Promotion: Since legacy accounts now manage Stripe Connect revenue, the first login to NGN 2.0 will trigger a prompt to enable MFA.

Manual Linkage: If a stakeholder creates a new account using a different email than their legacy record, the Admin (Erik) has a dashboard tool to manually merge the legacy entity (Artist/Label) to the new User UUID.

Account Recovery: Password resets performed on NGN 2.0 will only affect the new cdm_users record, effectively "cutting the cord" from the legacy hash.