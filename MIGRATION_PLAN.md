# Migration Plan: Deploying NGN 2.0 to beta.nextgennoise.com

This document provides a step-by-step guide for migrating the NGN 2.0 application to a new server environment. It is synthesized from the project's existing documentation (`DEPLOYMENT_CHECKLIST.md`, `GO_LIVE_CHECKLIST.md`, and `AAPANEL_CRON_SETUP_GUIDE.md`).

---

## Phase 1: Server Preparation

This phase ensures the target server (`beta.nextgennoise.com`) is ready to host the application.

### Step 1.1: Provision Server
- Provision a new server instance (e.g., VPS or dedicated server).
- Install a standard LAMP/LEMP stack (Linux, Apache/Nginx, MySQL, PHP).

### Step 1.2: Install PHP Extensions
- Verify that all required PHP extensions are installed and enabled. You can check this by running `php -m`.
- Required extensions (from `docs/GO_LIVE_CHECKLIST.md`):
  - `PDO` (with MySQL driver)
  - `mbstring`
  - `xml`
  - `json`
  - `openssl`
  - `gd`
  - `curl`
  - `zip`
  - `intl`
  - `imagick`

### Step 1.3: Install Composer
- Install Composer globally. This is required to install PHP dependencies.
  ```bash
  php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
  php composer-setup.php --install-dir=/usr/local/bin --filename=composer
  php -r "unlink('composer-setup.php');"
  ```

---

## Phase 2: Code Deployment

This phase covers getting the application code onto the new server.

### Step 2.1: Clone the Repository
- SSH into your server.
- Navigate to the directory where you want to store the project (e.g., `/www/wwwroot`).
- Clone the Git repository:
  ```bash
  git clone <your-repository-url> beta.nextgennoise.com
  cd beta.nextgennoise.com
  ```

### Step 2.2: Install Dependencies
- Run Composer to install all the PHP libraries defined in `composer.json`.
  ```bash
  composer install --no-dev --optimize-autoloader
  ```

### Step 2.3: Set Up `.env` File
- Copy the reference environment file `.env-reference` to a new `.env` file.
  ```bash
  cp .env-reference .env
  ```
- **Crucially, edit the `.env` file** and fill in all the required values for the beta environment:
  - `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
  - `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`
  - Other API keys and application settings.

---

## Phase 3: Data Migration

This phase involves setting up the database schema and migrating any necessary data.

### Step 3.1: Create the Database
- Access your MySQL server.
- Create a new database and a user for the application.
  ```sql
  CREATE DATABASE ngn_beta;
  CREATE USER 'ngn_user'@'localhost' IDENTIFIED BY 'a-very-strong-password';
  GRANT ALL PRIVILEGES ON ngn_beta.* TO 'ngn_user'@'localhost';
  FLUSH PRIVILEGES;
  ```
- Make sure these details match what you put in the `.env` file.

### Step 3.2: Import Schema and Data
- Apply all SQL migration scripts in order, from the `migrations/` directory. The `GO_LIVE_CHECKLIST.md` specifically mentions `migrations/sql/schema/21_service_orders.sql` as a critical final step for the schema.
- If you have a baseline schema dump, import it first, then apply subsequent migrations.
  ```bash
  mysql -u ngn_user -p ngn_beta < path/to/baseline_schema.sql
  # Apply migrations sequentially
  mysql -u ngn_user -p ngn_beta < migrations/001_create_posts.sql
  mysql -u ngn_user -p ngn_beta < migrations/002_create_users.sql
  # ... and so on for all migrations.
  ```

---

## Phase 4: Server Configuration

This phase configures the web server and cron jobs.

### Step 4.1: Configure Web Server (aapanel or manual)
- The project is structured to have a public webroot located at `/public`. This is a critical security measure.
- **Using aapanel:**
  1. Add a new website in aapanel for `beta.nextgennoise.com`.
  2. In the website settings, set the "Website-Directory" (or "Document Root") to the `/public` folder of your project, e.g., `/www/wwwroot/beta.nextgennoise.com/public`.
  3. Ensure "URL rewrite" is enabled and set to the `nginx` or `apache2` rules for your framework (if applicable, often found in `.htaccess`).
- **Manual Apache Example (`httpd-vhosts.conf`):**
  ```apache
  <VirtualHost *:80>
      ServerName beta.nextgennoise.com
      DocumentRoot "/www/wwwroot/beta.nextgennoise.com/public"

      <Directory "/www/wwwroot/beta.nextgennoise.com/public">
          Options Indexes FollowSymLinks
          AllowOverride All
          Require all granted
      </Directory>

      # Block access to the project root
      <Directory "/www/wwwroot/beta.nextgennoise.com">
          Require all denied
      </Directory>

      ErrorLog /var/log/apache2/beta.nextgennoise.com-error.log
      CustomLog /var/log/apache2/beta.nextgennoise.com-access.log combined
  </VirtualHost>
  ```
- After configuring, restart the web server (Apache or Nginx).

### Step 4.2: Set Up Cron Jobs
- Use `aapanel`'s "Scheduled Tasks" or the command-line `crontab` to set up the necessary cron jobs.
- The `bin/setup_cron.sh` script contains a full list of required jobs and instructions.
- **Example from the script:**
  ```cron
  # NGN SIR Reminders: Daily at 9:00 AM UTC
  0 9 * * * /usr/bin/php /www/wwwroot/beta.nextgennoise.com/jobs/governance/send_sir_reminders.php >> /www/wwwroot/beta.nextgennoise.com/storage/cron_logs/sir_reminders.log 2>&1
  ```
- Add all jobs listed in `bin/setup_cron.sh` to your crontab.

### Step 4.3: Set File Permissions
- The web server user needs to be able to write to certain directories.
- Set the `storage` and `public/uploads` (if it exists) directories to be writable by the web server user. `775` is a common choice.
  ```bash
  chmod -R 775 storage/
  # chown -R www-data:www-data storage/ # Use chown to set ownership to your webserver user
  ```

---

## Phase 5: Testing and Verification

Before making the site public, perform thorough testing.

### Step 5.1: Basic Functionality Tests (from `DEPLOYMENT_CHECKLIST.md`)
- Test that main pages return a `200 OK` status.
  ```bash
  curl -I http://beta.nextgennoise.com/
  curl -I http://beta.nextgennoise.com/login.php
  curl -I http://beta.nextgennoise.com/artist/test-slug
  ```
### Step 5.2: Security Tests
- Verify that sensitive files are **not** accessible from the web. These should return a `403 Forbidden` or `404 Not Found` error.
  ```bash
  curl -I http://beta.nextgennoise.com/../.env
  curl -I http://beta.nextgennoise.com/../composer.json
  curl -I http://beta.nextgennoise.com/../migrations/001_create_posts.sql
  ```
### Step 5.3: Manual Smoke Tests (from `GO_LIVE_CHECKLIST.md`)
- **Investor Loop:** Simulate an investment and verify APY calculation and rank boosts.
- **Fan Loop:** Simulate a fan subscription and verify exclusive content is unlocked.
- **Artist Loop:** Simulate a service purchase and verify the order appears in the admin panel.

### Step 5.4: Monitor Logs
- While testing, keep an eye on the application and server logs for any errors.
  - Application logs: `storage/logs/`
  - Server logs: `/var/log/apache2/` or `/var/log/nginx/`

---

## Phase 6: Go-Live

Once all tests pass and you are confident the application is stable:

### Step 6.1: Update DNS
- Point the `beta.nextgennoise.com` DNS A record to the new server's IP address.

### Step 6.2: Final Manual Test
- Once DNS has propagated, perform one final check of the core site functionality by visiting `http://beta.nextgennoise.com` in your browser.

### Step 6.3: (Future) Cutover Strategy
- For the full production launch, refer to the `docs/CutoverRunbook.md`. It outlines a strategy for a zero-downtime cutover using feature flags, which can be implemented once the beta is stable.

This completes the migration. Continue to monitor logs for the first 24-48 hours to catch any post-launch issues.
