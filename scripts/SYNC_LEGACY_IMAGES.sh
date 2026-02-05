#!/bin/bash
# SYNC_LEGACY_IMAGES.sh - Copies images from legacy project to 2.0 storage

LEGACY_UPLOADS="/www/wwwroot/nextgennoise/uploads"
NEW_UPLOADS="/www/wwwroot/beta.nextgennoise.com/storage/uploads"

echo "Syncing users images..."
mkdir -p "$NEW_UPLOADS/users"
cp -rv "$LEGACY_UPLOADS/users"/* "$NEW_UPLOADS/users/" 2>/dev/null

echo "Syncing label images..."
mkdir -p "$NEW_UPLOADS/labels"
cp -rv "$LEGACY_UPLOADS/labels"/* "$NEW_UPLOADS/labels/" 2>/dev/null

echo "Syncing post images..."
mkdir -p "$NEW_UPLOADS/posts"
cp -rv "$LEGACY_UPLOADS/posts"/* "$NEW_UPLOADS/posts/" 2>/dev/null

# Fix permissions
chown -R www:www "$NEW_UPLOADS"
chmod -R 755 "$NEW_UPLOADS"

echo "Image sync complete."
