<?php
require __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$pdo = ConnectionFactory::write($config);

echo "Creating test accounts...\n\n";

$testUsers = [
    [
        'email' => 'admin@ngn.local',
        'password' => 'password123',
        'username' => 'admin_test',
        'display_name' => 'Admin Test',
        'role_id' => 1,
    ],
    [
        'email' => 'station_test@ngn.local',
        'password' => 'password123',
        'username' => 'station_test',
        'display_name' => 'Station Test',
        'role_id' => 4,
    ],
    [
        'email' => 'artist_test@ngn.local',
        'password' => 'password123',
        'username' => 'artist_test',
        'display_name' => 'Artist Test',
        'role_id' => 3,
    ],
    [
        'email' => 'venue_test@ngn.local',
        'password' => 'password123',
        'username' => 'venue_test',
        'display_name' => 'Venue Test',
        'role_id' => 5,
    ],
    [
        'email' => 'label_test@ngn.local',
        'password' => 'password123',
        'username' => 'label_test',
        'display_name' => 'Label Test',
        'role_id' => 7,
    ],
];

foreach ($testUsers as $user) {
    // Hash password
    $passwordHash = password_hash($user['password'], PASSWORD_BCRYPT);

    // Delete existing user with this email
    $deleteStmt = $pdo->prepare("DELETE FROM `ngn_2025`.`users` WHERE email = ?");
    $deleteStmt->execute([$user['email']]);

    // Insert user
    $insertStmt = $pdo->prepare("
        INSERT INTO `ngn_2025`.`users` (email, password_hash, username, display_name, role_id)
        VALUES (?, ?, ?, ?, ?)
    ");

    try {
        $insertStmt->execute([
            $user['email'],
            $passwordHash,
            $user['username'],
            $user['display_name'],
            $user['role_id']
        ]);
        echo "✓ Created: {$user['email']}\n";
    } catch (Throwable $e) {
        echo "✗ Failed: {$user['email']} - " . $e->getMessage() . "\n";
    }
}

echo "\n✓ Test accounts created successfully\n";
