# aapanel Cron Job Setup Guide for NGN 2.0.1

**For**: Hosting/DevOps team
**When**: After DAY 5 production deployment
**Purpose**: Configure recurring governance maintenance tasks
**Time Required**: 10-15 minutes

---

## Overview

Two cron jobs need to be configured in aapanel:

| Job | Purpose | Schedule | Command |
|-----|---------|----------|---------|
| SIR Reminders | Send reminders to directors for overdue SIRs (>14 days) | Daily at 9:00 AM UTC | `php /path/to/jobs/governance/send_sir_reminders.php` |
| Governance Report | Generate quarterly audit reports | First day of quarter at 6:00 AM UTC | `php /path/to/jobs/governance/generate_governance_report.php` |

---

## Prerequisites

- ✅ aapanel dashboard access
- ✅ Hosting account with PHP CLI available
- ✅ Project deployed to production directory
- ✅ PHP path verified (usually `/usr/bin/php` or `/usr/local/php/*/bin/php`)

---

## Step 1: Verify Your Project Path and PHP Version

Before setting up cron jobs, you need to know:

1. **Your project path**: `/path/to/ngn2.0` (replace with your actual path)
   - Example: `/home/username/public_html/ngn2.0`
   - Example: `/www/wwwroot/ngn.local`

2. **Your PHP CLI path**: Run this command via SSH to find it:
   ```bash
   which php
   ```
   - Result might be: `/usr/bin/php` or `/usr/local/php/7.4/bin/php`

3. **Verify PHP works**:
   ```bash
   php -v
   ```
   - Should show: `PHP X.X.X (cli)`

---

## Step 2: Access aapanel Cron Job Manager

### Via Web Interface:

1. **Log in** to your aapanel dashboard
   - URL: Usually `http://your-server-ip:8888` or `https://your-domain-aapanel.com`
   - Enter your username/password

2. **Navigate to Cron Jobs**:
   - Left sidebar → **Scheduled Tasks** or **Cron**
   - Button should say "Add Scheduled Task" or similar

3. You should see a form with fields like:
   - Task name
   - Script language (select: PHP)
   - Execution cycle (select: Custom time)
   - Command to execute
   - Notification email (optional)

---

## Step 3: Create First Cron Job - SIR Reminders

### Job 1: Daily SIR Reminder Notification

**Purpose**: Send daily reminders to directors with overdue SIRs (open for >14 days)

1. **Click "Add Scheduled Task"** button

2. **Fill in the form**:

   | Field | Value |
   |-------|-------|
   | **Task Name** | `NGN SIR Reminders - Daily` |
   | **Script Language** | `PHP` (dropdown) |
   | **Execution Cycle** | `Custom time` (if available) or `Daily` |
   | **Execution Time** | `9:00 AM` (UTC) |
   | **Command** | `php /path/to/ngn2.0/jobs/governance/send_sir_reminders.php` |
   | **Log Output** | `Enable` (checkbox) |
   | **Email on Error** | `your-email@example.com` (optional) |

3. **Important**: Replace `/path/to/ngn2.0` with your actual project path
   - ✅ Correct: `php /home/username/public_html/ngn2.0/jobs/governance/send_sir_reminders.php`
   - ✅ Correct: `php /www/wwwroot/ngn.local/jobs/governance/send_sir_reminders.php`
   - ❌ Wrong: `php /path/to/ngn2.0/jobs/governance/send_sir_reminders.php`

4. **Click "Add"** to save

5. **Verify** in the cron list:
   - Should see: "NGN SIR Reminders - Daily" with status "Enabled"
   - Next execution time should show ~9:00 AM UTC

---

## Step 4: Create Second Cron Job - Quarterly Report

### Job 2: Quarterly Governance Audit Report

**Purpose**: Generate quarterly audit reports on governance metrics

1. **Click "Add Scheduled Task"** button again

2. **Fill in the form**:

   | Field | Value |
   |-------|-------|
   | **Task Name** | `NGN Governance Quarterly Report` |
   | **Script Language** | `PHP` |
   | **Execution Cycle** | `Custom time` (if available) or `Monthly` |
   | **Execution Time** | `6:00 AM UTC` on day `1` of month |
   | **Execution Months** | `January, April, July, October` (quarters only) |
   | **Command** | `php /path/to/ngn2.0/jobs/governance/generate_governance_report.php` |
   | **Log Output** | `Enable` |
   | **Email on Error** | `your-email@example.com` (optional) |

3. **Replace `/path/to/ngn2.0`** with your actual project path

4. **Click "Add"** to save

5. **Verify** in the cron list:
   - Should see: "NGN Governance Quarterly Report" with status "Enabled"
   - Next execution should show: `2026-04-01 06:00:00` (first quarter after deployment)

---

## Step 5: Alternative - If aapanel Uses Different Interface

If your aapanel version uses a different layout, follow these general steps:

### For "SSH Terminal" style input:

1. **SSH into your server**:
   ```bash
   ssh user@your-server-ip
   ```

2. **Edit crontab directly**:
   ```bash
   crontab -e
   ```

3. **Add these two lines** (adjust paths):
   ```cron
   # NGN SIR Reminders - Daily at 9:00 AM UTC
   0 9 * * * php /path/to/ngn2.0/jobs/governance/send_sir_reminders.php >> /path/to/ngn2.0/storage/cron_logs/sir_reminders.log 2>&1

   # NGN Governance Quarterly Report - 6:00 AM UTC on quarters
   0 6 1 1,4,7,10 * php /path/to/ngn2.0/jobs/governance/generate_governance_report.php >> /path/to/ngn2.0/storage/cron_logs/governance_reports.log 2>&1
   ```

4. **Save** (Ctrl+X, then Y, then Enter if using nano)

5. **Verify**:
   ```bash
   crontab -l
   ```

---

## Step 6: Test the Cron Jobs

### Manual Test Before First Execution:

1. **SSH into your server** and test each job manually:

   ```bash
   # Test SIR Reminders
   php /path/to/ngn2.0/jobs/governance/send_sir_reminders.php
   ```

   Expected output:
   ```
   Processing overdue SIRs...
   Sent reminder for SIR-2026-005 to Brandon Lamb
   Processed 3 overdue SIRs
   ```

   ```bash
   # Test Governance Report
   php /path/to/ngn2.0/jobs/governance/generate_governance_report.php
   ```

   Expected output:
   ```
   === Quarterly Governance Audit ===
   Total SIRs: 47
   Completion Rate: 45%
   Overdue SIRs: 3
   Report generated successfully
   ```

2. **Check log files**:
   ```bash
   cat /path/to/ngn2.0/storage/cron_logs/sir_reminders.log
   cat /path/to/ngn2.0/storage/cron_logs/governance_reports.log
   ```

   Both should have successful execution messages.

---

## Step 7: Monitor Cron Job Execution

### In aapanel Dashboard:

1. **After scheduling**, click on each job to view:
   - **Last execution time**
   - **Last execution status** (Success/Error)
   - **Log output** (if enabled)

2. **Expected behavior**:
   - First SIR Reminder executes: Tomorrow at 9:00 AM UTC
   - First Quarterly Report executes: Next quarter's first day at 6:00 AM UTC

### Check Log Files:

```bash
# SSH into server
tail -20 /path/to/ngn2.0/storage/cron_logs/sir_reminders.log
tail -20 /path/to/ngn2.0/storage/cron_logs/governance_reports.log
```

---

## Troubleshooting

### Problem 1: "Command not found" error

**Solution**: Verify PHP path
```bash
# Find correct PHP path
which php
# or
find /usr -name "php" -type f 2>/dev/null

# Use full path in cron command
# Example: /usr/bin/php or /usr/local/php/7.4/bin/php
```

### Problem 2: "Permission denied" error

**Solution**: Check file permissions
```bash
ls -la /path/to/ngn2.0/jobs/governance/send_sir_reminders.php

# Should show: -rw-r--r-- (at minimum)
# Make executable if needed:
chmod +x /path/to/ngn2.0/jobs/governance/send_sir_reminders.php
chmod +x /path/to/ngn2.0/jobs/governance/generate_governance_report.php
```

### Problem 3: "Fatal error: Uncaught Exception" in logs

**Solution**: Check database connection
```bash
# SSH and test database
mysql -u user -p -e "USE ngn_2025; SHOW TABLES LIKE 'directorate%';"

# Verify .env configuration
grep -i "DATABASE\|DB_" /path/to/ngn2.0/.env
```

### Problem 4: Cron job not executing

**Solution**: Check aapanel cron daemon
```bash
# SSH and verify cron is running
ps aux | grep cron

# If not running, restart
sudo systemctl restart cron
# or
sudo service crond restart
```

---

## Verification Checklist

After setting up both cron jobs, verify:

- [ ] Both cron jobs appear in aapanel Scheduled Tasks list
- [ ] Both jobs show status: "Enabled"
- [ ] Manual test of both jobs runs successfully
- [ ] Log files are created in `storage/cron_logs/`
- [ ] Log files contain successful execution messages
- [ ] Email notifications configured (optional but recommended)
- [ ] First execution times are correct (tomorrow 9 AM for daily, next quarter for quarterly)

---

## Schedule Reference

### SIR Reminders (Daily)
- **Time**: 9:00 AM UTC every day
- **What it does**:
  - Finds all SIRs open for >14 days
  - Sends push notification to assigned director
  - Logs reminder in `sir_reminders.log`
- **Expected frequency**: Daily if overdue SIRs exist, otherwise 0 notifications

### Governance Report (Quarterly)
- **Time**: 6:00 AM UTC on first day of: January, April, July, October
- **What it does**:
  - Calculates SIR statistics for the quarter
  - Generates audit report
  - Stores in reports directory
- **Expected frequency**: 4 times per year (once per quarter)

---

## aapanel Interface Examples

### If You See "Scheduled Tasks" Section:

```
┌─────────────────────────────────────────┐
│ Scheduled Tasks                    [Add] │
├─────────────────────────────────────────┤
│ Task Name          | Next Run | Status  │
├─────────────────────────────────────────┤
│ NGN SIR Reminders  | 09:00    | Enabled │
│ NGN Gov Quarterly  | 06:00    | Enabled │
└─────────────────────────────────────────┘
```

### If You See "Cron Jobs" Section:

```
┌──────────────────────────────────────────────┐
│ Cron Jobs                               [Add] │
├──────────────────────────────────────────────┤
│ Name          | Command       | Cycle        │
├──────────────────────────────────────────────┤
│ SIR Reminders | php .../...   | Daily 9 AM   │
│ Gov Reports   | php .../...   | Monthly 6 AM │
└──────────────────────────────────────────────┘
```

---

## Quick Reference - Copy/Paste Commands

**Replace `/path/to/ngn2.0` with your actual project path!**

### Command 1 - SIR Reminders:
```
php /path/to/ngn2.0/jobs/governance/send_sir_reminders.php
```

### Command 2 - Governance Reports:
```
php /path/to/ngn2.0/jobs/governance/generate_governance_report.php
```

### Find Your Path:
```bash
find /home -name "ngn2.0" -type d 2>/dev/null
# or
find /www -name "ngn2.0" -type d 2>/dev/null
```

### Find Your PHP:
```bash
which php
```

---

## Support & Troubleshooting

If cron jobs don't execute:

1. **Check aapanel logs**: Dashboard → Logs → Scheduled Tasks
2. **Check system logs**: SSH and run `tail -50 /var/log/syslog`
3. **Verify permissions**: `ls -la /path/to/jobs/governance/`
4. **Test manually**: `php /path/to/jobs/governance/send_sir_reminders.php`
5. **Check database**: `mysql -u user -p -e "SELECT COUNT(*) FROM directorate_sirs;"`

Contact DevOps if issues persist beyond troubleshooting above.

---

**Last Updated**: 2026-01-25
**Next Step**: Execute DAY5_LAUNCH_RUNBOOK.md for production deployment
