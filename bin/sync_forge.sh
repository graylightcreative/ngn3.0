#!/bin/bash

# ==============================================================================
# NGN Forge Auto-Sync Script
# Updates the production environment from the latest git main branch.
# ==============================================================================

# Configuration
NGN_ROOT="/www/wwwroot/nextgennoise"
GIT_BRANCH="main"
LOG_FILE="$NGN_ROOT/storage/logs/git-sync.log"

# Ensure log directory exists
mkdir -p "$(dirname "$LOG_FILE")"

echo "--- Sync Started: $(date) ---" >> "$LOG_FILE"

# Move to project root
cd "$NGN_ROOT" || { echo "Failed to enter $NGN_ROOT" >> "$LOG_FILE"; exit 1; }

# 1. Fetch and Pull latest changes
echo "[1/4] Pulling latest changes from git ($GIT_BRANCH)..." >> "$LOG_FILE"
git fetch origin "$GIT_BRANCH" >> "$LOG_FILE" 2>&1
git reset --hard "origin/$GIT_BRANCH" >> "$LOG_FILE" 2>&1

# 2. Update Composer dependencies
if [ -f "composer.json" ]; then
    echo "[2/4] Updating composer dependencies..." >> "$LOG_FILE"
    composer install --no-interaction --no-dev --optimize-autoloader >> "$LOG_FILE" 2>&1
fi

# 3. Build Frontend Assets (Vite)
if [ -f "package.json" ]; then
    echo "[3/4] Building frontend assets (Vite)..." >> "$LOG_FILE"
    npm install >> "$LOG_FILE" 2>&1
    npm run build >> "$LOG_FILE" 2>&1
fi

# 4. Clear Caches (if applicable)
echo "[4/4] Finalizing sync..." >> "$LOG_FILE"
# Add any framework-specific cache clearing here if needed.

echo "--- Sync Completed: $(date) ---" >> "$LOG_FILE"
echo "" >> "$LOG_FILE"
