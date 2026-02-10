#!/bin/bash
# MIGRATE_AUDIO.sh - Moves legacy MP3s and updates DB links

PASS='Starr!1'
LEGACY_PATH="/www/wwwroot/nextgennoise/lib/uploads"
NEW_STORAGE="/www/wwwroot/beta.nextgennoise.com/storage/uploads/releases"

echo "Creating new storage directory..."
mkdir -p "$NEW_STORAGE"

echo "Copying release directories and MP3s..."
# Copy all directories from legacy uploads to new releases storage
cp -rv "$LEGACY_PATH"/* "$NEW_STORAGE/"

echo "Updating track links in ngn_2025..."
# Dynamic update based on release slug and song filename
mysql -h 127.0.0.1 -u root -p$PASS -e "
UPDATE ngn_2025.tracks t
JOIN ngn_2025.releases r ON t.release_id = r.id
JOIN nextgennoise.songs s ON t.id = s.id
SET t.mp3_url = CONCAT('/storage/uploads/releases/', r.slug, '/', s.mp3)
WHERE s.mp3 IS NOT NULL AND s.mp3 != '';
"

echo "Audio migration complete. Files moved and database links updated."
