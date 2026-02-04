# NGN 2.0 Deployment Checklist

This document outlines the essential steps and checks required for deploying NGN 2.0 to a production environment. It covers infrastructure setup, security hardening, data migration, and essential smoke tests to ensure a smooth and successful launch.

---

## 1. Infrastructure Setup

### 1.1. Cron Jobs

Ensure cron jobs are configured correctly to maintain NGN 2.0's automated processes. The following entries should be added to the server's crontab (e.g., via `crontab -e`).

Refer to `bin/setup_cron.sh` for the exact lines and instructions:

*   **Ranking Recomputation:** Runs every hour.
    `0 * * * * /usr/bin/php /var/www/ngn/jobs/rankings/recompute.php >> /var/www/ngn/storage/logs/cron-rankings-recompute.log 2>&1`

*   **Weekly Chart Computation:** Runs every Tuesday at 00:01.
    `1 0 * * 2 /usr/bin/php /var/www/ngn/jobs/charts/run_week.php >> /var/www/ngn/storage/logs/cron-charts-run-week.log 2>&1`

*   **SMR Data Ingestion:** Runs every 30 minutes.
    `*/30 * * * * /usr/bin/php /var/www/ngn/jobs/ingestion/ingest_smr.php >> /var/www/ngn/storage/logs/cron-ingest-smr.log 2>&1`

*   **Spins Backfill:** Runs every night at 3:00 AM.
    `0 3 * * * /usr/bin/php /var/www/ngn/jobs/spins/backfill.php >> /var/www/ngn/storage/logs/cron-spins-backfill.log 2>&1`

**Note:** Replace `/usr/bin/php` with the correct path to your PHP executable if it differs, and `/var/www/ngn` with your actual project root directory. Ensure the log directory (`storage/logs/`) exists and is writable by the cron user.

### 1.2. PHP Extensions

Verify that the following essential PHP extensions are installed and enabled on the server:

*   `PDO` (with MySQL driver)
*   `mbstring`
*   `xml`
*   `json`
*   `openssl`
*   `gd` (for image manipulation)
*   `curl` (for API requests)
*   `zip` (for file handling)
*   `intl` (for internationalization)
*   `imagick` (if image processing is heavy or used beyond GD)

---

## 2. Security Hardening

### 2.1. Default Passwords

*   Change default passwords for all administrative accounts (e.g., database users, server SSH users, application admin accounts).
*   Ensure strong, unique passwords are used.

### 2.2. Stripe Webhook Secrets

*   Verify that the Stripe webhook secrets are correctly configured in the NGN environment (`.env` file or equivalent).
*   Ensure the webhook endpoint in the Stripe dashboard matches the configured URL and that the webhook secret is securely stored and used for signature verification.

### 2.3. Folder Permissions

*   Set appropriate file and directory permissions to ensure security and prevent unauthorized access or modification.
*   **Crucially, set `storage/` and its subdirectories (like `logs/`, `cache/`, `uploads/`) to `775`** to allow the web server user to write to them, while preventing general access from others.
    ```bash
    chmod -R 775 storage/
    ```
*   Ensure PHP scripts themselves are not world-writable.

---

## 3. Data Migration

### 3.1. Final SQL Patch

*   Apply the last known SQL migration script to ensure the database schema is up-to-date.
*   **Specifically, ensure `migrations/sql/schema/21_service_orders.sql` is applied.**
*   It is recommended to run all pending migrations in sequence to guarantee data integrity.

---

## 4. Smoke Tests (Manual Verification)

Perform manual verification of the core user loops to ensure critical functionality is working as expected.

### 4.1. The Investor Loop

1.  **Simulate Investment:** Navigate to the investment section and complete a simulated investment process.
2.  **Check APY Calculation:** Verify that the investment is marked as 'active' and that the system is scheduled to apply the 8% APY (check cron job logs for related tasks if applicable).
3.  **Verify Rank Boost:** Confirm that the user's NGN score or relevant ranking reflects the boost associated with being an investor.

### 4.2. The Fan Loop

1.  **Simulate Subscription:** As a test user, subscribe to a fan tier (e.g., 'Pro' or 'Elite').
2.  **Verify Feed Unlock:** Check if exclusive content or features (e.g., premium feed access) become available after subscription.
3.  **Simulate Tipping:** Attempt to send a tip to an artist or entity.

### 4.3. The Artist Loop (Service Purchase)

1.  **Simulate Service Purchase:** As an artist user, navigate to the services page and purchase a service (e.g., 'Professional Mastering' for $50).
2.  **Check Admin Order Reception:** Log in to the admin panel (`/admin/orders.php`) and verify that the new order appears with a 'pending' status.
3.  **Admin Action:** Use the admin panel to 'Mark Complete' the service order and verify the status update.

---

**Deployment Checklist Complete.**

This checklist covers the critical steps for a successful deployment of NGN 2.0. Ensure all items are verified before considering the project officially live.