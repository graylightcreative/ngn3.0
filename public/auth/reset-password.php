<?php

// Include necessary NGN bootstrap and layout files
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

use App\Lib\Email\Mailer;
use NGN\Lib\Config;
use NGN\Lib\Http\{Request, Response, Router, JsonResponse};
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// --- Page Title ---
$pageTitle = 'Reset Password';

// --- Dependencies ---
// Assuming $pdo, $logger, and $config are available from bootstrap.php
try {
    if (!isset($pdo) || !($pdo instanceof \PDO)) {
        if (class_exists('NGN\Lib\Database\ConnectionFactory')) {
            $pdo = NGN\Lib\Database\ConnectionFactory::read(new Config());
        } else {
            throw new \RuntimeException("PDO connection not available and ConnectionFactory not found.");
        }
    }
    if (!isset($logger) || !($logger instanceof Logger)) {
        $logger = new Logger('auth');
        $logFilePath = __DIR__ . '/../../../storage/logs/auth.log';
        $logger->pushHandler(new StreamHandler($logFilePath, Logger::DEBUG));
    }
    if (!isset($config) || !($config instanceof Config)) {
         $config = new Config();
    }

} catch (\Throwable $e) {
    error_log("Reset Password Page Setup Error: " . $e->getMessage());
    http_response_code(500);
    echo "<h1>Internal Server Error</h1><p>Could not initialize critical services for password reset.</p>";
    exit;
}

$mailer = new Mailer($pdo, $logger, $config);

// --- POST Request Handling ---
$errorMessage = null;
$successMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? $_GET['token'] ?? ''; // Get token from POST or GET
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($token)) {
        $errorMessage = 'Invalid password reset token. Please try requesting a reset again.';
    } elseif (empty($password) || empty($confirmPassword)) {
        $errorMessage = 'Password and confirmation fields cannot be empty.';
    } elseif ($password !== $confirmPassword) {
        $errorMessage = 'Passwords do not match.';
    } else {
        try {
            // Validate token and check expiry
            $stmt = $pdo->prepare("SELECT id, email, display_name FROM `ngn_2025`.`users` WHERE reset_token = :token AND reset_expires > NOW() LIMIT 1");
            $stmt->bindParam(':token', $token);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Hash the new password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Update user record: new password, clear token and expiry
                $updateStmt = $pdo->prepare("UPDATE `ngn_2025`.`users` SET password_hash = :password_hash, reset_token = NULL, reset_expires = NULL WHERE id = :user_id");
                $updateSuccess = $updateStmt->execute([
                    ':password_hash' => $hashedPassword,
                    ':user_id' => $user['Id']
                ]);

                if ($updateSuccess) {
                    $successMessage = 'Your password has been successfully reset. You can now log in.';
                    $logger->info("Password reset successful for User ID: {$user['id']}");
                    
                    // Optional: Send a confirmation email to the user
                    $confirmationSubject = "NGN Password Reset Confirmation";
                    $confirmationBody = "<html><body>
                            <h2>Password Reset Confirmation</h2>
                            <p>Hello " . htmlspecialchars($user['display_name'] ?? 'User') . ",</p>
                            <p>This email confirms that your NGN password has been successfully reset.</p>
                            <p>If you did not perform this action, please contact NGN support immediately.</p>
                            <br>
                            <p>Sincerely,<br>The NGN Team</p>
                           </body></html>";
                    $mailer->send($user['email'], $confirmationSubject, $confirmationBody, true);

                    // Redirect to login page after a short delay
                    echo "<meta http-equiv=\"refresh\" content=\"5;url=/login.php\">";
                } else {
                    $errorMessage = 'Failed to update password. Please try again or contact support.';
                    $logger->error("Failed to update password hash for user ID: {$user['id']}");
                }
            } else {
                $errorMessage = 'Invalid or expired password reset token. Please request a new reset link.';
            }

        } catch (\Throwable $e) {
            $errorMessage = 'An error occurred during the password reset process. Please try again.';
            $logger->error("Password reset processing error for token {$token}: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - NGN</title>
    
    <?php // Link to core theme CSS - Loaded in head for critical rendering ?>
    <link rel="stylesheet" href="/frontend/src/spotify-killer-theme.css">
    <?php // Link to any page-specific CSS if needed ?>
    <link rel="stylesheet" href="/css/auth.css">

    <?php // Favicon and other head assets ?>
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>

<div class="flex min-h-screen items-center justify-center bg-gray-100 dark:bg-gray-900">
    <div class="w-full max-w-md p-8 space-y-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg">
        <div class="text-center">
            <h1 class="text-4xl font-extrabold sk-text-gradient-primary mb-2">Reset Your Password</h1>
            <p class="text-gray-600 dark:text-gray-300">Enter your new password and confirm it.</p>
        </div>

        <?php if (!empty($errorMessage)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Error!</strong> 
                <span class="block sm:inline"><?php echo $errorMessage; ?></span>
            </div>
        <?php elseif (!empty($successMessage)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Success!</strong> 
                <span class="block sm:inline"><?php echo $successMessage; ?></span>
            </div>
            <?php // Redirect happens via meta refresh, so no need for a "Done" button here ?>
        <?php endif; ?>

        <?php // Only show the form if password reset is not yet successful ?>
        <?php if (empty($successMessage)): ?>
        <form class="mt-8 space-y-6" action="reset-password.php" method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? $_POST['token'] ?? ''); ?>">
            
            <div>
                <label for="password" class="sr-only">New Password</label>
                <input id="password" name="password" type="password" required class="sk-input w-full" placeholder="New password">
            </div>
            
            <div>
                <label for="confirm_password" class="sr-only">Confirm New Password</label>
                <input id="confirm_password" name="confirm_password" type="password" required class="sk-input w-full" placeholder="Confirm new password">
            </div>

            <div>
                <button type="submit" class="sk-btn sk-btn-primary sk-btn-glow w-full">
                    Reset Password
                </button>
            </div>
        </form>
        <?php endif; ?>

        <div class="text-center text-sm">
            <p class="text-gray-600 dark:text-gray-300">Already have an account? <a href="/login.php" class="font-medium sk-text-gradient-primary hover:underline">Login here</a></p>
        </div>
    </div>
</div>

</body>
</html>
