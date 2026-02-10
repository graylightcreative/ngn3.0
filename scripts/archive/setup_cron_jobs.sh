#!/bin/bash
###############################################################################
# Governance Cron Job Setup Script
# DAY 4: Execute this to configure all cron jobs
# Usage: bash scripts/setup_cron_jobs.sh
###############################################################################

set -e  # Exit on error

PROJECT_ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
CRON_LOG_DIR="/var/log/ngn"
BACKUP_CRONTAB="/tmp/crontab_backup_$(date +%s).txt"

echo "=========================================="
echo "NGN 2.0.1 Governance Cron Setup"
echo "=========================================="
echo ""

# Create log directory
echo "Creating log directory..."
mkdir -p "$CRON_LOG_DIR"
chmod 755 "$CRON_LOG_DIR"
echo "✅ Log directory: $CRON_LOG_DIR"
echo ""

# Backup current crontab
echo "Backing up current crontab..."
crontab -l > "$BACKUP_CRONTAB" 2>/dev/null || echo "# No existing crontab" > "$BACKUP_CRONTAB"
echo "✅ Backup saved to: $BACKUP_CRONTAB"
echo ""

# Create new crontab entries
echo "Creating cron jobs..."
cat > /tmp/cron_entries.txt << 'EOF'
# NGN 2.0.1 - Governance System Cron Jobs
# Last updated: 2026-01-24

# =========================================
# SIR Reminders - Daily at 9 AM UTC
# Sends reminders for overdue SIRs (>14 days)
# =========================================
0 9 * * * php /var/www/ngn2.0/jobs/governance/send_sir_reminders.php >> /var/log/ngn/cron_reminders.log 2>&1

# =========================================
# Governance Report - Quarterly at 6 AM UTC
# First day of each quarter (Jan 1, Apr 1, Jul 1, Oct 1)
# =========================================
0 6 1 1,4,7,10 * php /var/www/ngn2.0/jobs/governance/generate_governance_report.php >> /var/log/ngn/cron_reports.log 2>&1
EOF

echo "Cron entries to be added:"
echo "---"
cat /tmp/cron_entries.txt
echo "---"
echo ""

# Merge with existing crontab
echo "Merging with existing crontab..."
{
  crontab -l 2>/dev/null || true
  echo ""
  cat /tmp/cron_entries.txt
} | crontab -

echo "✅ Cron jobs installed"
echo ""

# Verify installation
echo "Verifying cron installation..."
echo "Current crontab:"
echo "---"
crontab -l
echo "---"
echo ""

# Test cron jobs manually
echo "Testing cron jobs (manual execution)..."
echo ""

echo "Test 1: SIR Reminders"
php "$PROJECT_ROOT/jobs/governance/send_sir_reminders.php"
if [ $? -eq 0 ]; then
    echo "✅ SIR Reminders job: OK"
else
    echo "⚠️  SIR Reminders job: Check output above"
fi
echo ""

echo "Test 2: Governance Report"
php "$PROJECT_ROOT/jobs/governance/generate_governance_report.php"
if [ $? -eq 0 ]; then
    echo "✅ Governance Report job: OK"
else
    echo "⚠️  Governance Report job: Check output above"
fi
echo ""

# Display log files
echo "Log files created:"
ls -lh "$CRON_LOG_DIR/"
echo ""

echo "=========================================="
echo "✅ CRON SETUP COMPLETE"
echo "=========================================="
echo ""
echo "What was configured:"
echo "1. SIR Reminders job"
echo "   Schedule: Daily at 9:00 AM UTC"
echo "   Purpose: Send reminders for overdue SIRs"
echo "   Log: $CRON_LOG_DIR/cron_reminders.log"
echo ""
echo "2. Quarterly Report job"
echo "   Schedule: 6:00 AM UTC on quarter start dates (1st of Jan, Apr, Jul, Oct)"
echo "   Purpose: Generate governance audit reports"
echo "   Log: $CRON_LOG_DIR/cron_reports.log"
echo ""
echo "Configuration location: /etc/cron.d/ (via crontab)"
echo "Backup location: $BACKUP_CRONTAB"
echo ""
echo "To revert changes:"
echo "  crontab $BACKUP_CRONTAB"
echo ""
echo "To manually test reminders:"
echo "  php $PROJECT_ROOT/jobs/governance/send_sir_reminders.php"
echo ""
echo "To monitor logs:"
echo "  tail -f $CRON_LOG_DIR/cron_reminders.log"
echo "  tail -f $CRON_LOG_DIR/cron_reports.log"
echo ""

# Clean up
rm -f /tmp/cron_entries.txt

echo "✅ Setup script complete. Ready for DAY 5 launch!"
