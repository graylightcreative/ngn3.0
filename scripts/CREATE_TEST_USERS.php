<?php
/**
 * CREATE_TEST_USERS.php
 * Creates the required .local test accounts for dashboard verification
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$db = ConnectionFactory::write($config);

$testUsers = [
    [
        'email' => 'admin@ngn.local',
        'username' => 'admin-test',
        'display_name' => 'Admin Test',
        'role_id' => 1,
        'password' => 'password123'
    ],
    [
        'email' => 'station_test@ngn.local',
        'username' => 'station-test',
        'display_name' => 'Station Test',
        'role_id' => 4,
        'password' => 'password123'
    ],
    [
        'email' => 'artist_test@ngn.local',
        'username' => 'artist-test',
        'display_name' => 'Artist Test',
        'role_id' => 3,
        'password' => 'password123'
    ],
    [
        'email' => 'venue_test@ngn.local',
        'username' => 'venue-test',
        'display_name' => 'Venue Test',
        'role_id' => 5,
        'password' => 'password123'
    ],
    [
        'email' => 'label_test@ngn.local',
        'username' => 'label-test',
        'display_name' => 'Label Test',
        'role_id' => 7,
        'password' => 'password123'
    ]
];

echo "
Creating Test Users...
";

foreach ($testUsers as $u) {
    $hash = password_hash($u['password'], PASSWORD_DEFAULT);
    
    try {
        $db->beginTransaction();
        $stmt = $db->prepare("INSERT INTO `ngn_2025`.`users` (email, username, display_name, password_hash, role_id, status, created_at) 
                             VALUES (?, ?, ?, ?, ?, 'active', NOW())
                             ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role_id = VALUES(role_id)");
        $stmt->execute([$u['email'], $u['username'], $u['display_name'], $hash, $u['role_id']]);
        
        $userIdStmt = $db->prepare("SELECT id FROM `ngn_2025`.`users` WHERE email = ?");
        $userIdStmt->execute([$u['email']]);
        $userId = $userIdStmt->fetchColumn();
        
        // Link to entity if applicable
        $slug = $u['username'];
        $name = $u['display_name'];
        if ($u['role_id'] == 3) { // Artist
            $db->exec("INSERT INTO `ngn_2025`.`artists` (slug, name, user_id, status) 
                       VALUES ('$slug', '$name', $userId, 'active')
                       ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)");
        } elseif ($u['role_id'] == 4) { // Station
            $db->exec("INSERT INTO `ngn_2025`.`stations` (slug, name, user_id) 
                       VALUES ('$slug', '$name', $userId)
                       ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)");
        } elseif ($u['role_id'] == 5) { // Venue
            $db->exec("INSERT INTO `ngn_2025`.`venues` (slug, name, user_id) 
                       VALUES ('$slug', '$name', $userId)
                       ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)");
        } elseif ($u['role_id'] == 7) { // Label
            $db->exec("INSERT INTO `ngn_2025`.`labels` (slug, name, user_id) 
                       VALUES ('$slug', '$name', $userId)
                       ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)");
        }
        $db->commit();

        echo "✓ Created/Updated: {$u['email']}\n";
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo "✗ Error creating {$u['email']}: " . $e->getMessage() . "\n";
    }
}

echo "
All test accounts ready.
";
