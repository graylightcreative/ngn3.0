<?php
/**
 * Assistant Login Portal
 *
 * Dedicated login page for Erik Baker's assistant to access the SMR upload portal.
 * Provides role-based authentication separate from main admin login.
 *
 * Setup Instructions:
 * 1. Create assistant user in database with role 'assistant'
 * 2. Set strong password using password_hash()
 * 3. Assistant can only access assistant-upload.php, not full admin
 */

session_start();

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config\Config;

// If already logged in as assistant, redirect to upload page
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'assistant') {
    header('Location: assistant-upload.php');
    exit;
}

$loginError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (!$username || !$password) {
            throw new \Exception("Please enter both username and password.");
        }

        $config = new Config();
        $pdo = ConnectionFactory::read($config);

        // Look up user in database
        $stmt = $pdo->prepare("
            SELECT id, username, password_hash, role, name
            FROM ngn_2025.admin_users
            WHERE username = ? AND role = 'assistant' AND active = 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new \Exception("Invalid username or password.");
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            throw new \Exception("Invalid username or password.");
        }

        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();

        // Log successful login
        $stmt = $pdo->prepare("
            INSERT INTO ngn_2025.admin_login_log (user_id, username, ip_address, user_agent, success, login_at)
            VALUES (?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([
            $user['id'],
            $user['username'],
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);

        // Redirect to upload portal
        header('Location: assistant-upload.php');
        exit;

    } catch (\Throwable $e) {
        $loginError = $e->getMessage();

        // Log failed login attempt
        try {
            $stmt = $pdo->prepare("
                INSERT INTO ngn_2025.admin_login_log (username, ip_address, user_agent, success, login_at)
                VALUES (?, ?, ?, 0, NOW())
            ");
            $stmt->execute([
                $username ?? 'unknown',
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (\Throwable $logError) {
            error_log("Failed to log login attempt: " . $logError->getMessage());
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assistant Login - NextGenNoise</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="min-h-screen flex items-center justify-center p-6">
        <div class="w-full max-w-md">
            <!-- Logo/Header -->
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold text-white mb-2">NextGenNoise</h1>
                <p class="text-gray-400">SMR Upload Portal</p>
            </div>

            <!-- Login Card -->
            <div class="bg-gray-800 border border-gray-700 rounded-lg p-8 shadow-xl">
                <h2 class="text-2xl font-bold text-white mb-6">Assistant Login</h2>

                <?php if ($loginError): ?>
                    <div class="mb-6 p-4 bg-red-900/30 border border-red-500 rounded-lg">
                        <div class="flex items-center space-x-2">
                            <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-red-400 text-sm"><?= htmlspecialchars($loginError) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-300 mb-2">
                            Username
                        </label>
                        <input type="text" id="username" name="username" required autofocus
                               class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/50 transition"
                               placeholder="Enter your username">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-300 mb-2">
                            Password
                        </label>
                        <input type="password" id="password" name="password" required
                               class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/50 transition"
                               placeholder="Enter your password">
                    </div>

                    <button type="submit" class="w-full px-6 py-3 bg-blue-600 hover:bg-blue-500 text-white font-medium rounded-lg transition flex items-center justify-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                        </svg>
                        <span>Sign In</span>
                    </button>
                </form>

                <div class="mt-6 pt-6 border-t border-gray-700">
                    <p class="text-sm text-gray-400 text-center">
                        For technical support or password reset,<br>
                        contact the NextGenNoise admin team.
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-8 text-center text-sm text-gray-500">
                <p>&copy; <?= date('Y') ?> NextGenNoise. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
