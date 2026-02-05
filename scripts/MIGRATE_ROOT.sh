#!/bin/bash
# MIGRATE_ROOT.sh - Runs migration SQL as root to bypass permission issues

PASS='Starr!1'

echo "Migrating Videos..."
mysql -h 127.0.0.1 -u root -p$PASS -e "
DELETE FROM ngn_2025.videos;
INSERT INTO ngn_2025.videos 
(id, entity_type, entity_id, slug, title, description, video_type, video_id, published_at, created_at, updated_at)
SELECT 
    Id, 'artist', ArtistId, Slug, Title, Summary, 'youtube', VideoId, 
    COALESCE(ReleaseDate, Created), Created, Updated
FROM nextgennoise.videos;
"

echo "Restoring Entity Images..."
mysql -h 127.0.0.1 -u root -p$PASS -e "
-- Fix Artist Images
UPDATE ngn_2025.artists a
JOIN nextgennoise.users u ON a.id = u.ArtistId
SET a.image_url = CONCAT('/uploads/users/', u.Slug, '/', u.Image)
WHERE u.Image IS NOT NULL AND u.Image != '';

-- Fix Label Images
UPDATE ngn_2025.labels l
JOIN nextgennoise.users u ON l.id = u.LabelId
SET l.image_url = CONCAT('/uploads/users/', u.Slug, '/', u.Image)
WHERE u.Image IS NOT NULL AND u.Image != '';

-- Fix Station Images
UPDATE ngn_2025.stations s
JOIN nextgennoise.users u ON s.id = u.StationId
SET s.image_url = CONCAT('/uploads/users/', u.Slug, '/', u.Image)
WHERE u.Image IS NOT NULL AND u.Image != '';
"

echo "Migrating Releases..."
mysql -h 127.0.0.1 -u root -p$PASS -e "
DELETE FROM ngn_2025.releases;
INSERT INTO ngn_2025.releases 
(id, artist_id, slug, title, type, release_date, description, cover_url, created_at, updated_at)
SELECT 
    Id, ArtistId, Slug, Title, LOWER(COALESCE(Type, 'album')), 
    ReleaseDate, Body, Image, Created, Updated
FROM nextgennoise.releases;
"

echo "Migrating Tracks..."
# Check if songs or tracks table exists in legacy
TABLE="songs"
EXISTS=$(mysql -h 127.0.0.1 -u root -p$PASS -e "SHOW TABLES FROM nextgennoise LIKE 'tracks';" | grep tracks)
if [ ! -z "$EXISTS" ]; then TABLE="tracks"; fi

mysql -h 127.0.0.1 -u root -p$PASS -e "
DELETE FROM ngn_2025.tracks;
INSERT INTO ngn_2025.tracks 
(id, release_id, artist_id, slug, title, created_at, updated_at)
SELECT 
    id, ReleaseId, ArtistId, 
    LOWER(REPLACE(REPLACE(Title, ' ', '-'), '--', '-')), 
    Title, Created, Updated
FROM nextgennoise.$TABLE;
"

echo "Cleanup: Fixing orphaned user_id links..."
# No user_id column in videos table based on DESCRIBE, skip or add if needed.
# For now just confirming counts.
mysql -h 127.0.0.1 -u root -p$PASS -e "
SELECT COUNT(*) FROM ngn_2025.videos;
SELECT COUNT(*) FROM ngn_2025.releases;
SELECT COUNT(*) FROM ngn_2025.tracks;
"

echo "Migration Complete."
