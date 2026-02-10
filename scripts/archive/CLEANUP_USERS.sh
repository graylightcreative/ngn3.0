#!/bin/bash
# CLEANUP_USERS.sh - Removes placeholder legacy users and marks entities as unclaimed

PASS='Starr!1'
DB="ngn_2025"

echo "Clearing placeholder user accounts..."
# We keep Brock (ID 1), Writers (who might have specific IDs), and the .local test accounts
mysql -h 127.0.0.1 -u root -p$PASS -e "
SET FOREIGN_KEY_CHECKS=0;

-- 1. Unlink all entities from placeholder users (keep Brock, Writers, and .local)
-- We identify .local users by email pattern
UPDATE $DB.artists SET user_id = NULL, claimed = 0 
WHERE user_id NOT IN (1) 
  AND user_id NOT IN (SELECT id FROM $DB.users WHERE email LIKE '%@ngn.local' OR email LIKE '%@nextgennoise.com');

UPDATE $DB.labels SET user_id = NULL, claimed = 0 
WHERE user_id NOT IN (1) 
  AND user_id NOT IN (SELECT id FROM $DB.users WHERE email LIKE '%@ngn.local' OR email LIKE '%@nextgennoise.com');

UPDATE $DB.stations SET user_id = NULL 
WHERE user_id NOT IN (1) 
  AND user_id NOT IN (SELECT id FROM $DB.users WHERE email LIKE '%@ngn.local' OR email LIKE '%@nextgennoise.com');

UPDATE $DB.venues SET user_id = NULL 
WHERE user_id NOT IN (1) 
  AND user_id NOT IN (SELECT id FROM $DB.users WHERE email LIKE '%@ngn.local' OR email LIKE '%@nextgennoise.com');

-- 2. Delete the placeholder users
DELETE FROM $DB.users 
WHERE id > 1 
  AND email NOT LIKE '%@ngn.local' 
  AND email NOT LIKE '%@nextgennoise.com'
  AND email != 'brock@graylightcreative.com';

SET FOREIGN_KEY_CHECKS=1;
"

echo "Cleanup complete. Only Admin and Test accounts remain. All profiles are now open for claiming."
