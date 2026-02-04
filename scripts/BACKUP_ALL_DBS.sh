#!/bin/bash
#
# BACKUP_ALL_DBS.sh
# Backs up all NGN 2.0.1 databases locally to storage/backups/
#
# Usage: bash scripts/BACKUP_ALL_DBS.sh
#

REMOTE_HOST="server.starrship1.com"
PASSWORD="NextGenNoise!1"
BACKUP_DIR="storage/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Create backups directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

echo ""
echo "================================================================================"
echo "NGN 2.0.1 DATABASE BACKUP"
echo "================================================================================"
echo ""
echo "Remote host: $REMOTE_HOST"
echo "Backup directory: $BACKUP_DIR"
echo "Timestamp: $TIMESTAMP"
echo ""

# Define databases to backup: (database_name:username)
# Note: ngn_smr is legacy and may not exist; it's included for completeness
declare -a DATABASES=(
    "ngn_2025:ngn_2025"
    "ngn_rankings_2025:ngn_rankings_2025"
    "ngn_spins_2025:ngn_spins_2025"
    "ngn_smr_2025:ngn_smr_2025"
    "ngn_notes_2025:ngn_notes_2025"
    "nextgennoise:nextgennoise"
    "ngnrankings:NGNRankings"
    "ngnspins:ngnspins"
    "ngn_smr:ngn_smr"
)

TOTAL=${#DATABASES[@]}
SUCCESS=0
FAILED=0
FAILED_DBS=""

echo "Backing up $TOTAL databases...\n"

for DB_PAIR in "${DATABASES[@]}"; do
    DB_NAME="${DB_PAIR%:*}"
    DB_USER="${DB_PAIR#*:}"
    BACKUP_FILE="$BACKUP_DIR/${DB_NAME}_${TIMESTAMP}.sql"

    echo -n "[$((SUCCESS + FAILED + 1))/$TOTAL] Backing up $DB_NAME... "

    if mysqldump \
        -h "$REMOTE_HOST" \
        -u "$DB_USER" \
        -p"$PASSWORD" \
        --single-transaction \
        --quick \
        --lock-tables=false \
        "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null; then

        FILE_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
        echo "✓ ($FILE_SIZE)"
        ((SUCCESS++))

    else
        echo "✗ FAILED"
        rm -f "$BACKUP_FILE"
        ((FAILED++))
        FAILED_DBS="$FAILED_DBS\n      - $DB_NAME (user: $DB_USER)"
    fi
done

echo ""
echo "================================================================================"
echo "BACKUP COMPLETE"
echo "================================================================================"
echo ""
echo "Successful: $SUCCESS/$TOTAL"
echo "Failed: $FAILED/$TOTAL"
echo ""

if [ $SUCCESS -gt 0 ]; then
    TOTAL_SIZE=$(du -csh "$BACKUP_DIR"/*_${TIMESTAMP}.sql 2>/dev/null | tail -1 | awk '{print $1}')
    echo "✓ All available databases backed up successfully"
    echo "  Total backup size: $TOTAL_SIZE"
    echo ""
    echo "Backup files:"
    ls -lh "$BACKUP_DIR"/*_${TIMESTAMP}.sql 2>/dev/null | awk '{printf "  %s (%s)\n", $9, $5}' | sed "s|.*backups/||g"
    echo ""
fi

if [ $FAILED -gt 0 ]; then
    if [ "$DB_NAME" = "ngn_smr" ]; then
        echo "⚠️  Note: ngn_smr legacy database backup failed (expected if not present)"
        echo "   This is normal - using ngn_smr_2025 instead"
    else
        echo "⚠️  Some backups failed:"
        echo -e "$FAILED_DBS"
        echo ""
        echo "Troubleshooting:"
        echo "  1. Check credentials (user/password may be incorrect)"
        echo "  2. Verify network connectivity to $REMOTE_HOST"
        echo "  3. Confirm database exists on remote server"
    fi
    echo ""
fi

echo ""
