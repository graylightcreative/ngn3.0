#!/bin/bash

# ==============================================================================
#
# NGN 2.0.1 - aapanel Cron Job Setup Script
#
# ==============================================================================
#
# This script provides the necessary crontab entries for the application.
# It is designed to be a reference. To activate these cron jobs, you
# should use the aapanel web interface or run 'crontab -e' and paste
# the relevant lines into your crontab file.
#
# For detailed instructions, refer to AAPANEL_CRON_SETUP_GUIDE.md
#
# ==============================================================================

# --- Configuration ---
# !!! IMPORTANT !!!
# Verify these paths before using the commands.
# The NGN_ROOT should be the absolute path to your project's root directory on the server.
#
PHP_BIN=/usr/bin/php
NGN_ROOT=/www/wwwroot/beta.nextgennoise.com # <-- VERIFY THIS PATH
LOG_DIR_LEGACY=$NGN_ROOT/storage/logs
LOG_DIR_CRON=$NGN_ROOT/storage/cron_logs

# --- Verification ---
# You can run the following commands via SSH to help verify your paths:
# which php
# find / -name "send_sir_reminders.php" 2>/dev/null
# ==============================================================================

# --- Crontab Entries ---

# ------------------------------------------------------------------------------
# Section 1: Core Application Cron Jobs
# ------------------------------------------------------------------------------

# 1. Recompute Rankings: Every hour at minute 0
#    Recomputes rankings for artists, tracks, etc.
0 * * * * "$PHP_BIN" "$NGN_ROOT/jobs/rankings/recompute.php" >> "$LOG_DIR_LEGACY/cron-rankings-recompute.log" 2>&1

# 2. Compute Weekly Charts: Every Tuesday at 00:01
#    Runs the weekly chart computation process.
1 0 * * 2 "$PHP_BIN" "$NGN_ROOT/jobs/charts/run_week.php" >> "$LOG_DIR_LEGACY/cron-charts-run-week.log" 2>&1

# 3. Ingest SMR Data: Every 15 minutes
#    Fetches and ingests SMR (Sound Music Report) data.
*/15 * * * * "$PHP_BIN" "$NGN_ROOT/jobs/ingestion/ingest_smr.php" >> "$LOG_DIR_LEGACY/cron-ingest-smr.log" 2>&1

# 4. Generate Sitemap: Every Sunday at 02:00
#    Updates sitemap.xml for SEO optimization.
0 2 * * 0 "$PHP_BIN" "$NGN_ROOT/bin/generate_sitemap.php" >> "$LOG_DIR_LEGACY/cron-sitemap.log" 2>&1

# 5. Forge Auto-Sync: Every day at 03:00
#    Automatically pulls the latest changes from Git and updates the project.
0 3 * * * /bin/bash "$NGN_ROOT/bin/sync_forge.sh" >> "$LOG_DIR_CRON/forge_sync.log" 2>&1

# ------------------------------------------------------------------------------
# Section 2: Governance Cron Jobs (from aapanel guide)
# ------------------------------------------------------------------------------

# 6. NGN SIR Reminders: Daily at 9:00 AM UTC
#    Sends reminders to directors for overdue SIRs (>14 days).
0 9 * * * "$PHP_BIN" "$NGN_ROOT/jobs/governance/send_sir_reminders.php" >> "$LOG_DIR_CRON/sir_reminders.log" 2>&1

# 5. NGN Governance Quarterly Report: First day of each quarter at 6:00 AM UTC
#    Generates quarterly audit reports on governance metrics.
0 6 1 1,4,7,10 * "$PHP_BIN" "$NGN_ROOT/jobs/governance/generate_governance_report.php" >> "$LOG_DIR_CRON/governance_reports.log" 2>&1

# --- Instructions for Setup ---
#
# How to use this file:
#
# 1. SSH into your server.
#
# 2. Manually verify the PHP and project paths.
#    - Run `which php` to confirm your PHP binary path.
#    - `cd` into what you think is the project root and run `ls -la`.
#      You should see files like `composer.json` and directories like `jobs`.
#
# 3. Test a job command manually (highly recommended):
#    "$PHP_BIN" "$NGN_ROOT/jobs/governance/send_sir_reminders.php"
#    You should see output indicating it ran successfully.
#
# 4. Add the jobs to crontab.
#    - Open the crontab editor:
#      crontab -e
#    - Copy the lines from the "Crontab Entries" section above.
#    - Paste them into the editor.
#    - Save and close the file.
#
# 5. Verify the cron jobs are listed:
#    crontab -l
#
# Your cron jobs are now active. Monitor the log files in
# storage/logs/ and storage/cron_logs/ to ensure they run correctly.
#
# ==============================================================================
# End of Script
# ==============================================================================
