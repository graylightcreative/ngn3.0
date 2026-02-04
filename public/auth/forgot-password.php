<?php

// Include necessary NGN bootstrap and layout files
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

use App\Lib\Email\Mailer;
use NGN\Lib\Config;
use NGN\Lib\Http\{Request, Response, Router, JsonResponse};
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// --- Page Title ---
$pageTitle = 'Forgot Password';

// --- Dependencies ---
// Assuming $pdo, $logger, and $config are available from bootstrap.php
// If not, they need to be instantiated here.
try {
    if (!isset($pdo) || !($pdo instanceof \PDO)) {
        // Fallback instantiation if not globally available
        if (class_exists('NGN\Lib\Database\ConnectionFactory')) {
            $pdo = NGN\Lib\Database\ConnectionFactory::read(new Config());
        } else {
            throw new \RuntimeException("PDO connection not available and ConnectionFactory not found.");
        }
    }
    if (!isset($logger) || !($logger instanceof Logger)) {
        // Fallback instantiation if not globally available
        $logger = new Logger('auth');
        $logFilePath = __DIR__ . '/../../../storage/logs/auth.log';
        $logger->pushHandler(new StreamHandler($logFilePath, Logger::DEBUG));
    }
    // Ensure Config is available
    if (!isset($config) || !($config instanceof Config)) {
         $config = new Config();
    }

} catch (\Throwable $e) {
    error_log("Forgot Password Page Setup Error: " . $e->getMessage());
    // Display a critical error page or message if dependencies fail to load
    http_response_code(500);
    echo "<h1>Internal Server Error</h1><p>Could not initialize critical services for password reset.</p>";
    exit;
}

$mailer = new Mailer($pdo, $logger, $config);

// --- POST Request Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Invalid email address provided.';
    } else {
        try {
            // Check if user exists and generate reset token
            $stmt = $pdo->prepare("SELECT id, display_name, email FROM `ngn_2025`.`users` WHERE email = :email LIMIT 1");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Generate a unique reset token
                $resetToken = bin2hex(random_bytes(32));
                // Set expiry time (e.g., 1 hour from now)
                $resetExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Update user record with token and expiry
                $updateStmt = $pdo->prepare("UPDATE `ngn_2025`.`users` SET reset_token = :token, reset_expires = :expires WHERE id = :user_id");
                $updateSuccess = $updateStmt->execute([
                    ':token' => $resetToken,
                    ':expires' => $resetExpires,
                    ':user_id' => $user['Id']
                ]);

                if ($updateSuccess) {
                    // Construct the reset link
                    $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . "/reset-password.php?token=$resetToken";
                    
                    // Prepare email content
                    $subject = "NGN Password Reset Request";
                    $body = "<html><body>
                            <h2>Password Reset</h2>
                            <p>Hello " . htmlspecialchars($user['display_name'] ?? 'User') . ",</p>
                            <p>We received a request to reset your NGN password.</p>
                            <p>Please click the link below to reset your password:</p>
                            <p><a href=\"" . htmlspecialchars($resetLink) . "\">Reset Your Password</a></p>
                            <p>This link will expire in 1 hour.</p>
                            <p>If you did not request this, please ignore this email.</p>
                            <br>
                            <p>Sincerely,<br>The NGN Team</p>
                           </body></html>";

                    // Send the email
                    if ($mailer->send($email, $subject, $body, true)) {
                        $successMessage = 'Password reset instructions have been sent to your email address. Please check your inbox.';
                    } else {
                        $errorMessage = 'Failed to send password reset email. Please try again later.';
                        $logger->error("Failed to send password reset email for user ID: {$user['id']}");
                    }
                } else {
                    $errorMessage = 'Failed to update user record for password reset. Please contact support.';
                    $logger->error("Failed to update reset token/expiry for user ID: {$user['id']}");
                }
            } else {
                // User not found, but we don't want to reveal that.
                // Still send a success message to avoid email enumeration.
                $successMessage = 'If an account with that email address exists, a password reset email has been sent.';
            }

        } catch (\Throwable $e) {
            $errorMessage = 'An error occurred during the password reset process. Please try again.';
            $logger->error("Password reset error for email {$email}: " . $e->getMessage());
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
            <h1 class="text-4xl font-extrabold sk-text-gradient-primary mb-2">Forgot Password</h1>
            <p class="text-gray-600 dark:text-gray-300">Enter your email address and we'll send you instructions to reset your password.</p>
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
        <?php endif; ?>

        <form class="mt-8 space-y-6" action="forgot-password.php" method="POST">
            <div>
                <label for="email" class="sr-only">Email Address</label>
                <input id="email" name="email" type="email" required class="sk-input w-full" placeholder="Email address">
            </div>

            <div>
                <button type="submit" class="sk-btn sk-btn-primary sk-btn-glow w-full">
                    Send Reset Link
                </button>
            </div>
        </form>

        <div class="text-center text-sm">
            <p class="text-gray-600 dark:text-gray-300">Remember your password? <a href="/login.php" class="font-medium sk-text-gradient-primary hover:underline">Login here</a></p>
        </div>
    </div>
</div>

</body>
</html>
