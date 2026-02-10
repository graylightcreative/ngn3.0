<?php
// admin/erik-smr/login.php
session_start();

require_once __DIR__ . '/../../lib/bootstrap.php'; // Bootstrap application

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Simple hardcoded check for skeleton. In real app, authenticate against DB.
    if ($username === 'erik.baker' && $password === 'smrdata') { // Placeholder credentials
        $_SESSION['erik_smr_logged_in'] = true;
        $_SESSION['erik_smr_username'] = $username;
        header('Location: index.php');
        exit();
    } else {
        $error = 'Invalid credentials. Please try again.';
    }
}

// Check if already logged in
if (isset($_SESSION['erik_smr_logged_in']) && $_SESSION['erik_smr_logged_in'] === true) {
    header('Location: index.php');
    exit();
}

// Include the admin header (simplified for login)
// Note: This might need adjustment based on the actual admin header/layout.
// For now, we'll keep it minimal.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erik Baker SMR Login</title>
    <link href="/frontend/public/build/tailwind.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f3f4f6; /* Tailwind gray-100 */
        }
        .login-container {
            background-color: #ffffff; /* White */
            padding: 2.5rem; /* p-10 */
            border-radius: 0.5rem; /* rounded-lg */
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); /* shadow-xl */
            width: 100%;
            max-width: 28rem; /* max-w-md */
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="text-2xl font-bold text-gray-800 text-center mb-6">Erik Baker SMR Portal Login</h2>
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <div class="mb-4">
                <label for="username" class="block text-gray-700 text-sm font-bold mb-2">
                    Username:
                </label>
                <input type="text" id="username" name="username" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" value="erik.baker" required>
            </div>
            <div class="mb-6">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">
                    Password:
                </label>
                <input type="password" id="password" name="password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline" value="smrdata" required>
            </div>
            <div class="flex items-center justify-between">
                <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Sign In
                </button>
            </div>
        </form>
    </div>
</body>
</html>
