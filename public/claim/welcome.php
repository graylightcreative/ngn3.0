<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

use NGN\Lib\Config;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// --- Page Setup ---
$pageTitle = 'Welcome to Your Profile';

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
    error_log("Welcome Page Setup Error: " . $e->getMessage());
    http_response_code(500);
    echo "<h1>Internal Server Error</h1><p>Could not initialize critical services.</p>";
    exit;
}

// Check if user is logged in
$currentUser = $_SESSION['User'] ?? null;
if (!$currentUser) {
    header('Location: /login');
    exit;
}

// Get entity type from URL or session
$entityType = $_GET['entity_type'] ?? $_SESSION['claimed_entity_type'] ?? null;
$entityId = (int)($_GET['entity_id'] ?? $_SESSION['claimed_entity_id'] ?? 0);

if (!$entityType || $entityId <= 0) {
    header('Location: /dashboard/artist');
    exit;
}

// Fetch entity details
$validTypes = ['artist', 'label', 'venue', 'station'];
if (!in_array($entityType, $validTypes)) {
    header('Location: /dashboard/artist');
    exit;
}

$tableMap = ['artist' => 'artists', 'label' => 'labels', 'venue' => 'venues', 'station' => 'stations'];
$table = $tableMap[$entityType];

$stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`$table` WHERE id = ? AND user_id = ?");
$stmt->execute([$entityId, $currentUser['Id']]);
$entity = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entity) {
    header('Location: /dashboard/artist');
    exit;
}

// Get current step from URL or session
$step = (int)($_GET['step'] ?? $_SESSION['welcome_step'] ?? 1);
if ($step < 1 || $step > 4) $step = 1;

$successMessage = null;
$errorMessage = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($step) {
            case 1: // Password Setup
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';

                if (empty($newPassword) || empty($confirmPassword)) {
                    $errorMessage = 'Password fields are required.';
                } elseif ($newPassword !== $confirmPassword) {
                    $errorMessage = 'Passwords do not match.';
                } elseif (strlen($newPassword) < 8) {
                    $errorMessage = 'Password must be at least 8 characters.';
                } else {
                    // Update password
                    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $updateStmt = $pdo->prepare("UPDATE `nextgennoise`.`users` SET PasswordHash = ? WHERE Id = ?");
                    $updateStmt->execute([$passwordHash, $currentUser['Id']]);

                    $_SESSION['welcome_step'] = 2;
                    header('Location: /claim/welcome?entity_type=' . urlencode($entityType) . '&entity_id=' . $entityId . '&step=2');
                    exit;
                }
                break;

            case 2: // Profile Complete
                $bio = trim($_POST['bio'] ?? '');
                $email = trim($_POST['email'] ?? '');

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errorMessage = 'Valid email address is required.';
                } else {
                    // Update profile
                    $updateStmt = $pdo->prepare("
                        UPDATE `ngn_2025`.`$table`
                        SET bio = ?, email = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$bio, $email, $entityId]);

                    $_SESSION['welcome_step'] = 3;
                    header('Location: /claim/welcome?entity_type=' . urlencode($entityType) . '&entity_id=' . $entityId . '&step=3');
                    exit;
                }
                break;

            case 3: // Tier Selection (just log and move on)
                $selectedTier = trim($_POST['tier'] ?? 'free');
                $logger->info("User {$currentUser['Id']} selected tier: $selectedTier");

                $_SESSION['welcome_step'] = 4;
                header('Location: /claim/welcome?entity_type=' . urlencode($entityType) . '&entity_id=' . $entityId . '&step=4');
                exit;
                break;

            case 4: // Complete
                unset($_SESSION['welcome_step']);
                unset($_SESSION['claimed_entity_type']);
                unset($_SESSION['claimed_entity_id']);

                header('Location: /dashboard/' . $entityType);
                exit;
                break;
        }
    } catch (\Throwable $e) {
        $errorMessage = 'An error occurred. Please try again.';
        $logger->error("Welcome wizard error: " . $e->getMessage());
    }
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
        .welcome-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .welcome-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .welcome-header h1 {
            margin: 0 0 0.5rem 0;
            font-size: 1.875rem;
        }

        .welcome-header p {
            margin: 0;
            color: #6b7280;
        }

        .step-progress {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            justify-content: space-between;
        }

        .step-item {
            flex: 1;
            text-align: center;
        }

        .step-circle {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background: #e5e7eb;
            color: #6b7280;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .step-circle.active {
            background: #667eea;
            color: white;
        }

        .step-circle.completed {
            background: #10b981;
            color: white;
        }

        .step-name {
            font-size: 0.75rem;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-family: inherit;
            font-size: 1rem;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .tier-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .tier-card {
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .tier-card:hover {
            border-color: #667eea;
        }

        .tier-card.selected {
            border-color: #667eea;
            background: #f0f9ff;
        }

        .tier-card input[type="radio"] {
            display: none;
        }

        .tier-card h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.25rem;
        }

        .tier-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .tier-features {
            font-size: 0.875rem;
            color: #6b7280;
            text-align: left;
        }

        .tier-features li {
            margin-bottom: 0.25rem;
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

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            flex: 1;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1rem;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: #5568d3;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .help-text {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        .congratulations {
            text-align: center;
            padding: 2rem;
        }

        .confetti {
            font-size: 3rem;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        @media (max-width: 768px) {
            .welcome-container {
                margin: 1rem;
                padding: 1rem;
            }

            .tier-grid {
                grid-template-columns: 1fr;
            }

            .step-progress {
                gap: 0.5rem;
            }

            .step-circle {
                width: 2rem;
                height: 2rem;
                font-size: 0.75rem;
            }

            .step-name {
                font-size: 0.65rem;
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
                <div class="text-gray-600">Welcome, <?php echo htmlspecialchars($currentUser['DisplayName'] ?? 'User'); ?></div>
            </div>
        </div>
    </nav>

    <div class="welcome-container">
        <div class="welcome-header">
            <h1>Welcome to Your Profile!</h1>
            <p><?php echo htmlspecialchars($entity['name']); ?></p>
        </div>

        <!-- Step Progress -->
        <div class="step-progress">
            <div class="step-item">
                <div class="step-circle <?php echo $step >= 1 ? ($step === 1 ? 'active' : 'completed') : ''; ?>">
                    <?php echo $step > 1 ? '✓' : '1'; ?>
                </div>
                <div class="step-name">Password</div>
            </div>
            <div class="step-item">
                <div class="step-circle <?php echo $step >= 2 ? ($step === 2 ? 'active' : 'completed') : ''; ?>">
                    <?php echo $step > 2 ? '✓' : '2'; ?>
                </div>
                <div class="step-name">Profile</div>
            </div>
            <div class="step-item">
                <div class="step-circle <?php echo $step >= 3 ? ($step === 3 ? 'active' : 'completed') : ''; ?>">
                    <?php echo $step > 3 ? '✓' : '3'; ?>
                </div>
                <div class="step-name">Tier</div>
            </div>
            <div class="step-item">
                <div class="step-circle <?php echo $step >= 4 ? 'completed' : ''; ?>">
                    4
                </div>
                <div class="step-name">Done</div>
            </div>
        </div>

        <?php if ($errorMessage): ?>
            <div class="alert error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="alert success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <!-- Step 1: Password Setup -->
            <form method="POST">
                <h2>Step 1: Set Your Password</h2>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required placeholder="Minimum 8 characters">
                    <div class="help-text">Use a strong password with letters, numbers, and symbols.</div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Re-enter your password">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Continue to Next Step</button>
                </div>
            </form>

        <?php elseif ($step === 2): ?>
            <!-- Step 2: Profile Completion -->
            <form method="POST">
                <h2>Step 2: Complete Your Profile</h2>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo htmlspecialchars($entity['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="bio">Bio / Description</label>
                    <textarea id="bio" name="bio" placeholder="Tell your audience about you..."><?php echo htmlspecialchars($entity['bio'] ?? ''); ?></textarea>
                    <div class="help-text">Keep it concise and compelling (max 500 characters).</div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Continue to Tier Selection</button>
                </div>
            </form>

        <?php elseif ($step === 3): ?>
            <!-- Step 3: Tier Selection -->
            <form method="POST">
                <h2>Step 3: Choose Your Tier</h2>
                <p style="color: #6b7280; margin-bottom: 1.5rem;">You can always upgrade later!</p>

                <div class="tier-grid">
                    <label class="tier-card selected">
                        <input type="radio" name="tier" value="free" checked>
                        <h3>Free</h3>
                        <div class="tier-price">$0</div>
                        <ul class="tier-features">
                            <li>✓ Basic Profile</li>
                            <li>✓ Upload Content</li>
                            <li>✓ View Charts</li>
                        </ul>
                    </label>

                    <label class="tier-card">
                        <input type="radio" name="tier" value="pro">
                        <h3>Pro</h3>
                        <div class="tier-price">$9/mo</div>
                        <ul class="tier-features">
                            <li>✓ All Free features</li>
                            <li>✓ Analytics</li>
                            <li>✓ Priority Support</li>
                            <li>✓ Remove Ads</li>
                        </ul>
                    </label>

                    <label class="tier-card">
                        <input type="radio" name="tier" value="premium">
                        <h3>Premium</h3>
                        <div class="tier-price">$29/mo</div>
                        <ul class="tier-features">
                            <li>✓ All Pro features</li>
                            <li>✓ Custom Domain</li>
                            <li>✓ API Access</li>
                        </ul>
                    </label>

                    <label class="tier-card">
                        <input type="radio" name="tier" value="enterprise">
                        <h3>Enterprise</h3>
                        <div class="tier-price">Custom</div>
                        <ul class="tier-features">
                            <li>✓ All Premium features</li>
                            <li>✓ Advanced Features</li>
                            <li>✓ Dedicated Support</li>
                        </ul>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Complete Setup</button>
                </div>
            </form>

        <?php elseif ($step === 4): ?>
            <!-- Step 4: Congratulations -->
            <div class="congratulations">
                <div class="confetti">🎉</div>
                <h2>All Set!</h2>
                <p style="font-size: 1.125rem; color: #6b7280; margin-bottom: 2rem;">
                    Your profile is ready to go. Welcome to NextGenNoise!
                </p>

                <div style="background: #f0f9ff; padding: 1.5rem; border-radius: 0.5rem; margin-bottom: 2rem; text-align: left;">
                    <h3 style="margin-top: 0;">Next Steps:</h3>
                    <ul style="margin: 0; padding-left: 1.5rem;">
                        <li>Explore your dashboard</li>
                        <li>Upload your content</li>
                        <li>Connect your social media</li>
                        <li>Invite your collaborators</li>
                    </ul>
                </div>

                <a href="/dashboard/<?php echo htmlspecialchars($entityType); ?>" class="btn btn-primary" style="display: inline-block; text-decoration: none; margin-bottom: 1rem;">
                    Go to Dashboard
                </a>
            </div>

        <?php endif; ?>
    </div>

</div>

<script>
    // Handle tier card selection
    document.querySelectorAll('.tier-card').forEach(card => {
        card.addEventListener('click', function() {
            document.querySelectorAll('.tier-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            this.querySelector('input[type="radio"]').checked = true;
        });
    });
</script>

</body>
</html>
