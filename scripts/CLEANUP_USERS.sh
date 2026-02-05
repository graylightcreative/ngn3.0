#!/bin/bash
# CLEANUP_USERS.sh - Removes placeholder legacy users and marks entities as unclaimed

PASS='Starr!1'
DB="ngn_2025"

echo "Clearing placeholder user accounts..."
# We keep IDs 1-7 (Brock + Writers/Test accounts)
mysql -h 127.0.0.1 -u root -p$PASS -e "
SET FOREIGN_KEY_CHECKS=0;

-- 1. Unlink all entities from placeholder users
UPDATE $DB.artists SET user_id = NULL, claimed = 0 WHERE user_id > 7 OR (user_id IS NOT NULL AND user_id NOT IN (1,2,3,4,5,6,7));
UPDATE $DB.labels SET user_id = NULL, claimed = 0 WHERE user_id > 7 OR (user_id IS NOT NULL AND user_id NOT IN (1,2,3,4,5,6,7));
UPDATE $DB.stations SET user_id = NULL WHERE user_id > 7 OR (user_id IS NOT NULL AND user_id NOT IN (1,2,3,4,5,6,7));
UPDATE $DB.venues SET user_id = NULL WHERE user_id > 7 OR (user_id IS NOT NULL AND user_id NOT IN (1,2,3,4,5,6,7));

-- 2. Delete the placeholder users
DELETE FROM $DB.users WHERE id > 7;

-- 3. Cleanup related personal data if any
DELETE FROM $DB.user_sparks_ledger WHERE user_id > 7;
DELETE FROM $DB.user_fan_subscriptions WHERE user_id > 7;

SET FOREIGN_KEY_CHECKS=1;
"

echo "Cleanup complete. Only Admin and Test accounts remain. All profiles are now open for claiming."
