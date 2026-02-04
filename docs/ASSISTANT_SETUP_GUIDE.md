# Assistant Access Setup Guide

**Last Updated:** January 15, 2026
**Purpose:** Configure authenticated access for Erik Baker's assistant to upload SMR data

---

## Overview

This system provides a simplified, role-based portal for Erik Baker's assistant to upload Station Music Reports (SMR) data without granting full admin access. The assistant has access **only** to the upload interface and cannot access QA approvals, artist mappings, or other admin functions.

---

## Architecture

### Authentication Flow

```
1. Assistant visits: /admin/assistant-login.php
2. Enters username + password
3. Credentials validated against admin_users table
4. Session created with role='assistant'
5. Redirected to: /admin/assistant-upload.php
6. All requests protected by /admin/assistant-auth.php middleware
```

### Role Hierarchy

| Role | Access Level | Pages Available |
|------|--------------|-----------------|
| **assistant** | SMR Upload Only | `assistant-upload.php` |
| **viewer** | Read-only Admin | TBD |
| **admin** | Full Admin Access | All admin pages |

---

## Initial Setup

### Step 1: Run Database Migration

Execute the SQL schema to create authentication tables:

```bash
mysql -u root -p ngn_2025 < /path/to/ngn2.0/migrations/sql/schema/15_admin_users.sql
```

This creates:
- `admin_users` table with role-based access
- `admin_login_log` table for security monitoring
- Default assistant user: `erik_assistant`

### Step 2: Verify Default Credentials

The migration creates a default assistant account:

```
Username: erik_assistant
Password: changeme123
Role: assistant
```

**⚠️ CRITICAL:** Change this password immediately!

### Step 3: Generate Secure Password

Use PHP to generate a secure password hash:

```bash
php -r "echo password_hash('YourSecurePassword123!', PASSWORD_DEFAULT) . PHP_EOL;"
```

Example output:
```
$2y$10$eZGJXQs8KpJ7HqN9kQvZ9.rHb5Vj3M2LpN8QzKlW9xN4jP6Ym2xC6
```

### Step 4: Update Password in Database

```sql
UPDATE ngn_2025.admin_users
SET password_hash = '$2y$10$eZGJXQs8KpJ7HqN9kQvZ9.rHb5Vj3M2LpN8QzKlW9xN4jP6Ym2xC6'
WHERE username = 'erik_assistant';
```

### Step 5: Test Login

1. Navigate to: `https://yourdomain.com/admin/assistant-login.php`
2. Enter username: `erik_assistant`
3. Enter your new password
4. Should redirect to upload portal

---

## File Reference

### Authentication Files

| File | Purpose |
|------|---------|
| `/admin/assistant-login.php` | Login page for assistant role |
| `/admin/assistant-auth.php` | Authentication middleware (include in protected pages) |
| `/admin/assistant-logout.php` | Session destruction handler |
| `/admin/assistant-upload.php` | SMR upload portal (protected) |

### Database Tables

| Table | Purpose |
|-------|---------|
| `ngn_2025.admin_users` | User credentials and roles |
| `ngn_2025.admin_login_log` | Login attempt tracking |

---

## Security Features

### 1. Password Hashing

- Uses PHP `password_hash()` with `PASSWORD_DEFAULT`
- Currently uses bcrypt algorithm (cost factor 10)
- Automatic algorithm updates on PHP version upgrades

### 2. Role Validation

- Session must contain `role='assistant'`
- Middleware checks role on every page load
- Invalid roles immediately destroyed

### 3. Session Timeout

- **4-hour** automatic timeout
- Last activity tracked on every request
- Timeout triggers automatic logout

### 4. Login Attempt Logging

All login attempts (successful and failed) are logged with:
- User ID (if authenticated)
- Username attempt
- IP address
- User agent
- Timestamp
- Success status

Query recent failed attempts:

```sql
SELECT * FROM ngn_2025.admin_login_log
WHERE success = 0
AND login_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY login_at DESC;
```

### 5. Duplicate Upload Prevention

- SHA-256 hash calculated for every upload
- Duplicate files rejected automatically
- Prevents accidental re-uploads

---

## Creating Additional Assistant Accounts

To create another assistant user:

```sql
-- Generate password hash first using:
-- php -r "echo password_hash('SecurePassword456!', PASSWORD_DEFAULT) . PHP_EOL;"

INSERT INTO ngn_2025.admin_users (username, password_hash, role, name, email, active)
VALUES (
    'assistant2',
    '$2y$10$YOUR_GENERATED_HASH_HERE',
    'assistant',
    'Second Assistant Name',
    'assistant2@nextgennoise.com',
    1
);
```

---

## Monitoring & Auditing

### Monitor Login Activity

```sql
-- Recent successful logins
SELECT u.username, u.name, l.ip_address, l.login_at
FROM ngn_2025.admin_login_log l
JOIN ngn_2025.admin_users u ON u.id = l.user_id
WHERE l.success = 1
ORDER BY l.login_at DESC
LIMIT 50;

-- Failed login attempts (potential security issue)
SELECT username, ip_address, COUNT(*) as attempts, MAX(login_at) as last_attempt
FROM ngn_2025.admin_login_log
WHERE success = 0
AND login_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY username, ip_address
HAVING attempts >= 3
ORDER BY attempts DESC;
```

### Monitor Upload Activity

```sql
-- Recent uploads by assistant role
SELECT u.filename, u.report_date, u.status, u.uploaded_at
FROM ngn_2025.smr_uploads u
WHERE u.uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY u.uploaded_at DESC;
```

---

## Troubleshooting

### Issue: "Invalid username or password"

**Possible causes:**
1. Password hash mismatch
2. User not active (`active=0`)
3. User role not 'assistant'
4. Username misspelled

**Solution:**

```sql
-- Check user status
SELECT id, username, role, active FROM ngn_2025.admin_users WHERE username = 'erik_assistant';

-- If active=0, enable user
UPDATE ngn_2025.admin_users SET active = 1 WHERE username = 'erik_assistant';

-- Reset password
UPDATE ngn_2025.admin_users
SET password_hash = '$2y$10$NEW_HASH_HERE'
WHERE username = 'erik_assistant';
```

### Issue: "Session timeout" or "Unauthorized"

**Possible causes:**
1. Session expired after 4 hours
2. Role changed while logged in
3. PHP session configuration issue

**Solution:**
- Simply log in again
- Check PHP session settings in `php.ini`:
  - `session.gc_maxlifetime` should be >= 14400 (4 hours)
  - `session.cookie_lifetime` should be 0 (browser close)

### Issue: "File already uploaded"

**Cause:** Duplicate SHA-256 hash detected

**Solution:**
- Verify file hasn't been uploaded before
- Check upload history in assistant portal
- If file is genuinely different, contact admin to investigate hash collision

### Issue: Login page doesn't load

**Possible causes:**
1. Web server not serving `/admin/` directory
2. PHP not configured
3. Database connection failed

**Solution:**

```bash
# Test PHP configuration
php -v

# Test database connection
mysql -u root -p -e "SELECT 1 FROM ngn_2025.admin_users LIMIT 1;"

# Check web server error logs
tail -f /var/log/nginx/error.log
# or
tail -f /var/log/apache2/error.log
```

---

## Password Policy Recommendations

1. **Minimum Length:** 12 characters
2. **Complexity:** Mixed case, numbers, symbols
3. **Rotation:** Change every 90 days
4. **No Reuse:** Don't reuse last 3 passwords
5. **Storage:** Use password manager (1Password, LastPass, Bitwarden)

---

## IP Whitelisting (Optional)

For enhanced security, consider restricting assistant login to specific IP addresses:

```php
// Add to assistant-login.php after session_start()
$allowedIPs = ['203.0.113.5', '198.51.100.42']; // Erik's office IPs
$userIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (!in_array($userIP, $allowedIPs)) {
    die('Access denied from this IP address.');
}
```

---

## Disaster Recovery

### Lost Password Reset

```sql
-- Generate new hash using:
-- php -r "echo password_hash('NewTempPassword!', PASSWORD_DEFAULT) . PHP_EOL;"

UPDATE ngn_2025.admin_users
SET password_hash = '$2y$10$NEW_HASH_HERE'
WHERE username = 'erik_assistant';
```

### Account Locked (Too Many Failed Attempts)

```sql
-- Clear failed login attempts
DELETE FROM ngn_2025.admin_login_log
WHERE username = 'erik_assistant' AND success = 0;

-- Ensure account is active
UPDATE ngn_2025.admin_users SET active = 1 WHERE username = 'erik_assistant';
```

### Emergency Disable Assistant Access

```sql
-- Temporarily disable without deleting data
UPDATE ngn_2025.admin_users SET active = 0 WHERE role = 'assistant';
```

---

## Related Documentation

- [Bible Ch. 5: Data Integrity](bible/05%20-%20Data%20Integrity.md)
- [Bible Ch. 28: Chart Integrity](bible/28%20-%20Chart%20Integrity.md)
- [SMR Ingestion & QA Guide](SMR_INGESTION_GUIDE.md)
- [Admin Panel Overview](ADMIN_PANEL_GUIDE.md)

---

## Support Contact

For technical issues or security concerns:
- **Email:** admin@nextgennoise.com
- **Emergency:** Contact system administrator directly

---

## Changelog

| Date | Change |
|------|--------|
| 2026-01-15 | Initial setup guide created |
| 2026-01-15 | Added authentication middleware and login system |
| 2026-01-15 | Implemented role-based access control |
