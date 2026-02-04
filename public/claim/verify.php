<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

use NGN\Lib\Config;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// --- Page Setup ---
$pageTitle = 'Verify Your Claim';

// --- Dependencies ---
try {
    if (!isset($pdo) || !($pdo instanceof \PDO)) {
        if (class_exists('NGN\Lib\Database\ConnectionFactory')) {
            $pdo = NGN\Lib\Database\ConnectionFactory::read(new Config());
        } else {
            throw new \RuntimeException("PDO connection not available and ConnectionFactory not found.");
        }
    }
    if (!isset($logger) || !($logger instanceof Logger)) {
        $logger = new Logger('claims');
        $logFilePath = __DIR__ . '/../../storage/logs/claims.log';
        $logger->pushHandler(new StreamHandler($logFilePath, Logger::DEBUG));
    }
    if (!isset($config) || !($config instanceof Config)) {
         $config = new Config();
    }
} catch (\Throwable $e) {
    error_log("Email Verification Page Setup Error: " . $e->getMessage());
    http_response_code(500);
    echo "<h1>Internal Server Error</h1><p>Could not initialize critical services.</p>";
    exit;
}

// Get verification code from URL
$verificationCode = $_GET['code'] ?? null;

$claim = null;
$errorMessage = null;
$successMessage = null;
$isVerified = false;

if (!$verificationCode) {
    $errorMessage = 'No verification code provided.';
} else {
    try {
        // Find claim by verification code
        $stmt = $pdo->prepare("
            SELECT * FROM `ngn_2025`.`pending_claims`
            WHERE verification_code = ? AND expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$verificationCode]);
        $claim = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$claim) {
            $errorMessage = 'Invalid or expired verification code.';
        } else if ($claim['email_verified'] === 1) {
            $successMessage = 'This email has already been verified. Your claim is pending admin review.';
            $isVerified = true;
        }
    } catch (\Throwable $e) {
        $errorMessage = 'An error occurred while verifying your email.';
        $logger->error("Email verification error: " . $e->getMessage());
    }
}

// Handle email verification (manual entry of code)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isVerified) {
    $manualCode = trim($_POST['verification_code'] ?? '');

    if (empty($manualCode)) {
        $errorMessage = 'Please enter the verification code.';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM `ngn_2025`.`pending_claims`
                WHERE verification_code = ? AND expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([$manualCode]);
            $claim = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$claim) {
                $errorMessage = 'Invalid or expired verification code.';
            } else if ($claim['email_verified'] === 0) {
                // Mark email as verified
                $stmt = $pdo->prepare("
                    UPDATE `ngn_2025`.`pending_claims`
                    SET email_verified = 1, email_verified_at = NOW(), updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$claim['id']]);

                $logger->info("Email verified for claim ID {$claim['id']}");

                $successMessage = 'Email verified successfully! Your claim is now pending admin review.';
                $isVerified = true;
            }
        } catch (\Throwable $e) {
            $errorMessage = 'An error occurred while verifying your email.';
            $logger->error("Email verification error: " . $e->getMessage());
        }
    }
}

// Get entity details if claim exists
$entity = null;
if ($claim) {
    $tableMap = [
        'artist' => 'artists',
        'label' => 'labels',
        'venue' => 'venues',
        'station' => 'stations'
    ];
    $table = $tableMap[$claim['entity_type']];
    $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`$table` WHERE id = ?");
    $stmt->execute([$claim['entity_id']]);
    $entity = $stmt->fetch(PDO::FETCH_ASSOC);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - NextGenNoise</title>

    <link rel="stylesheet" href="/frontend/src/spotify-killer-theme.css">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">

    <style>
        .verify-container {
            max-width: 500px;
            margin: 3rem auto;
            padding: 2rem;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .verify-header {
            margin-bottom: 2rem;
        }

        .verify-header h1 {
            margin: 0 0 1rem 0;
            font-size: 1.875rem;
        }

        .verify-header p {
            margin: 0;
            color: #6b7280;
            font-size: 1rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
        }

        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        .alert.success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #22c55e;
        }

        .alert.info {
            background: #eff6ff;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
            text-align: left;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-family: monospace;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            text-align: center;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .code-info {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .claim-details {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .claim-details h3 {
            margin: 0 0 0.75rem 0;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            text-transform: uppercase;
        }

        .claim-details p {
            margin: 0;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1rem;
            width: 100%;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
            text-decoration: none;
            text-align: center;
            display: inline-block;
            margin-top: 1rem;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .next-steps {
            background: #eff6ff;
            padding: 1.5rem;
            border-radius: 0.375rem;
            margin: 1.5rem 0;
            text-align: left;
        }

        .next-steps h3 {
            margin-top: 0;
            color: #1e40af;
        }

        .next-steps ol {
            margin: 1rem 0;
            padding-left: 1.5rem;
            color: #374151;
        }

        .next-steps li {
            margin-bottom: 0.5rem;
        }

        .back-link {
            display: inline-block;
            margin-top: 1rem;
            color: #667eea;
            text-decoration: none;
            font-size: 0.875rem;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .verify-container {
                margin: 1rem;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>

<div class="flex min-h-screen flex-col bg-gray-100 dark:bg-gray-900">

    <?php // Header/Navigation ?>
    <nav class="bg-white dark:bg-gray-800 shadow">
        <div class="max-w-7xl mx-auto px-4 py-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center">
                <a href="/" class="text-2xl font-bold text-blue-600">NextGenNoise</a>
                <div>
                    <a href="/login" class="mr-4 text-gray-600 hover:text-gray-900">Login</a>
                    <a href="/register" class="text-blue-600 font-semibold hover:text-blue-700">Register</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="verify-container">
        <div class="verify-header">
            <h1>Verify Your Email</h1>
            <p>Confirm your email address to complete your claim</p>
        </div>

        <?php if ($errorMessage): ?>
            <div class="alert error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="alert success">
                <strong>Success!</strong> <?php echo htmlspecialchars($successMessage); ?>
            </div>

            <?php if ($claim && $entity): ?>
                <div class="claim-details">
                    <h3>Claim Information</h3>
                    <p><strong>Profile:</strong> <?php echo htmlspecialchars($entity['name']); ?></p>
                    <p><strong>Type:</strong> <?php echo ucfirst($claim['entity_type']); ?></p>
                    <p><strong>Claimant Email:</strong> <?php echo htmlspecialchars($claim['claimant_email']); ?></p>
                    <p><strong>Status:</strong> Pending Admin Review</p>
                </div>

                <div class="next-steps">
                    <h3>What Happens Next?</h3>
                    <ol>
                        <li>Our team will review your claim request</li>
                        <li>We may reach out for additional verification if needed</li>
                        <li>Once approved, you'll gain full access to manage your profile</li>
                        <li>You'll receive an email notification when your claim is approved</li>
                    </ol>
                </div>

                <a href="/claim-profile" class="back-link">← Search for Another Profile</a>
            <?php endif; ?>

        <?php elseif (!$isVerified && $claim): ?>
            <form method="POST" class="verify-form">
                <div class="alert info">
                    We've already found your claim. Please verify your email by submitting the code we sent.
                </div>

                <div class="code-info">
                    📧 A verification code has been sent to:<br>
                    <strong><?php echo htmlspecialchars($claim['claimant_email']); ?></strong>
                </div>

                <div class="form-group">
                    <label for="verification_code">Enter Verification Code</label>
                    <input type="text" id="verification_code" name="verification_code"
                           placeholder="ENTER CODE HERE"
                           autocomplete="off"
                           maxlength="64"
                           required>
                </div>

                <button type="submit" class="btn btn-primary">Verify Email</button>

                <div style="margin-top: 1.5rem; font-size: 0.875rem; color: #6b7280;">
                    <p>Didn't receive the code? Check your spam folder or
                        <a href="/claim-profile" style="color: #667eea;">start a new claim request</a>
                    </p>
                </div>
            </form>

        <?php elseif (!$claim && !$errorMessage): ?>
            <div class="alert info">
                Looking for your verification code. Please wait...
            </div>
        <?php endif; ?>

    </div>

</div>

</body>
</html>
