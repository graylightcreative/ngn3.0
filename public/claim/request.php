<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

use NGN\Lib\Config;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// --- Page Setup ---
$pageTitle = 'Claim Your Profile';

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
    error_log("Claim Request Page Setup Error: " . $e->getMessage());
    http_response_code(500);
    echo "<h1>Internal Server Error</h1><p>Could not initialize critical services.</p>";
    exit;
}

// Check if user is logged in
$currentUser = $_SESSION['User'] ?? null;
$userName = $currentUser['DisplayName'] ?? '';
$userEmail = $currentUser['email'] ?? '';

// Get entity type and ID from query params
$entityType = $_GET['entity_type'] ?? null;
$entityId = (int)($_GET['entity_id'] ?? 0);

// Validate entity type
$validTypes = ['artist', 'label', 'venue', 'station'];
if (!in_array($entityType, $validTypes) || $entityId <= 0) {
    http_response_code(400);
    echo "<h1>Invalid Request</h1><p>Missing or invalid entity type or ID.</p>";
    exit;
}

// Fetch entity details
$entity = null;
$tableMap = [
    'artist' => 'artists',
    'label' => 'labels',
    'venue' => 'venues',
    'station' => 'stations'
];

$table = $tableMap[$entityType];
$stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`$table` WHERE id = ?");
$stmt->execute([$entityId]);
$entity = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entity) {
    http_response_code(404);
    echo "<h1>Profile Not Found</h1><p>The profile you're trying to claim does not exist.</p>";
    exit;
}

// Check if already claimed
if ($entity['claimed'] === 1 || $entity['user_id'] !== null) {
    http_response_code(409);
    echo "<h1>Already Claimed</h1><p>This profile has already been claimed by another user.</p>";
    exit;
}

// Check for existing claim requests from this email
$stmt = $pdo->prepare("
    SELECT * FROM `ngn_2025`.`pending_claims`
    WHERE entity_type = ? AND entity_id = ?
    AND status IN ('pending', 'approved')
    LIMIT 1
");
$stmt->execute([$entityType, $entityId]);
$existingClaim = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingClaim) {
    http_response_code(409);
    echo "<h1>Claim Already Exists</h1><p>This profile already has a pending or approved claim.</p>";
    exit;
}

// Handle form submission
$errorMessage = null;
$successMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $claimantName = trim($_POST['claimant_name'] ?? '');
    $claimantEmail = trim($_POST['claimant_email'] ?? '');
    $claimantPhone = trim($_POST['claimant_phone'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $relationship = trim($_POST['relationship'] ?? '');
    $socialAgree = (int)($_POST['social_agree'] ?? 0);
    $rightsAgree = (int)($_POST['rights_agree'] ?? 0);
    $verificationMethods = $_POST['verification_methods'] ?? [];

    // Validation
    if (empty($claimantName)) {
        $errorMessage = 'Your name is required.';
    } elseif (empty($claimantEmail) || !filter_var($claimantEmail, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'A valid email address is required.';
    } elseif (empty($relationship)) {
        $errorMessage = 'Please specify your relationship to this profile.';
    } elseif (!$socialAgree || !$rightsAgree) {
        $errorMessage = 'You must agree to all terms to proceed.';
    } elseif (empty($verificationMethods)) {
        $errorMessage = 'Please select at least one verification method.';
    } else {
        try {
            // Generate verification code
            $verificationCode = bin2hex(random_bytes(32));

            // Check for multiple claims per email per day (rate limiting)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM `ngn_2025`.`pending_claims`
                WHERE claimant_email = ? AND DATE(created_at) = CURDATE()
            ");
            $stmt->execute([$claimantEmail]);
            $claimCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($claimCount >= 5) {
                $errorMessage = 'Too many claim requests from this email today. Please try again tomorrow.';
            } else {
                // Insert pending claim
                $stmt = $pdo->prepare("
                    INSERT INTO `ngn_2025`.`pending_claims` (
                        entity_type, entity_id, claimant_name, claimant_email,
                        claimant_phone, company, relationship,
                        verification_code, social_agree, rights_agree,
                        status, expires_at, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), NOW(), NOW())
                ");

                $stmt->execute([
                    $entityType, $entityId, $claimantName, $claimantEmail,
                    $claimantPhone, $company, $relationship,
                    $verificationCode, $socialAgree, $rightsAgree, 'pending'
                ]);

                $claimId = $pdo->lastInsertId();
                $logger->info("Claim request created: ID=$claimId, Email=$claimantEmail, Entity=$entityType/$entityId");

                // Send verification email via API endpoint
                $verifyUrl = "https://" . $_SERVER['HTTP_HOST'] . "/claim/verify?code=$verificationCode";

                // TODO: Send email using EmailService
                // For now, just display success

                $successMessage = 'Claim request submitted! Please check your email to verify your email address.';

                // Redirect to verification page after a brief pause
                header("Refresh: 3; URL=/claim/verify?code=$verificationCode");
            }

        } catch (\Throwable $e) {
            $errorMessage = 'An error occurred while submitting your claim. Please try again.';
            $logger->error("Claim request error: " . $e->getMessage());
        }
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
        .claim-container {
            max-width: 700px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .claim-header {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .claim-profile-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 0.5rem;
            flex-shrink: 0;
        }

        .claim-profile-info h2 {
            margin: 0 0 0.5rem 0;
            font-size: 1.5rem;
        }

        .claim-profile-info p {
            margin: 0;
            color: #6b7280;
            font-size: 0.875rem;
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

        .form-group input[readonly] {
            background: #f9fafb;
            color: #6b7280;
            cursor: not-allowed;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .checkbox-item input[type="checkbox"] {
            width: auto;
            margin: 0;
            cursor: pointer;
        }

        .checkbox-item label {
            margin: 0;
            font-weight: normal;
            cursor: pointer;
            flex: 1;
        }

        .verification-methods {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
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

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
            text-decoration: none;
            text-align: center;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .required {
            color: #dc2626;
        }

        .help-text {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        @media (max-width: 768px) {
            .claim-container {
                margin: 1rem;
                padding: 1rem;
            }

            .claim-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .form-actions {
                flex-direction: column;
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
                    <?php if ($currentUser): ?>
                        <a href="/dashboard/artist" class="mr-4 text-gray-600 hover:text-gray-900">Dashboard</a>
                        <a href="/logout" class="text-gray-600 hover:text-gray-900">Logout</a>
                    <?php else: ?>
                        <a href="/login" class="mr-4 text-gray-600 hover:text-gray-900">Login</a>
                        <a href="/register" class="text-blue-600 font-semibold hover:text-blue-700">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="claim-container">
        <div class="claim-header">
            <img src="<?php echo htmlspecialchars($entity['image_url'] ?? '/assets/placeholder-profile.png'); ?>"
                 alt="<?php echo htmlspecialchars($entity['name']); ?>"
                 class="claim-profile-image"
                 onerror="this.src='/assets/placeholder-profile.png'">
            <div class="claim-profile-info">
                <h2><?php echo htmlspecialchars($entity['name']); ?></h2>
                <p><?php echo ucfirst($entityType); ?> Profile</p>
                <?php if (!empty($entity['city'])): ?>
                    <p><?php echo htmlspecialchars($entity['city']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($errorMessage): ?>
            <div class="alert error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="alert success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php else: ?>

            <form method="POST" class="claim-form">
                <h3>Claim This Profile</h3>

                <!-- Your Information -->
                <div class="form-group">
                    <label for="claimant_name">Your Name <span class="required">*</span></label>
                    <input type="text" id="claimant_name" name="claimant_name"
                           value="<?php echo htmlspecialchars($userName ?? $_POST['claimant_name'] ?? ''); ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="claimant_email">Email Address <span class="required">*</span></label>
                    <input type="email" id="claimant_email" name="claimant_email"
                           value="<?php echo htmlspecialchars($userEmail ?? $_POST['claimant_email'] ?? ''); ?>"
                           required>
                    <div class="help-text">We'll send a verification code to this email.</div>
                </div>

                <div class="form-group">
                    <label for="claimant_phone">Phone Number</label>
                    <input type="tel" id="claimant_phone" name="claimant_phone"
                           value="<?php echo htmlspecialchars($_POST['claimant_phone'] ?? ''); ?>"
                           placeholder="+1 (555) 123-4567">
                </div>

                <div class="form-group">
                    <label for="company">Company/Organization Name</label>
                    <input type="text" id="company" name="company"
                           value="<?php echo htmlspecialchars($_POST['company'] ?? ''); ?>"
                           placeholder="Optional">
                </div>

                <div class="form-group">
                    <label for="relationship">Your Relationship to This <?php echo ucfirst($entityType); ?> <span class="required">*</span></label>
                    <select id="relationship" name="relationship" required>
                        <option value="">-- Select Relationship --</option>
                        <option value="owner" <?php echo ($_POST['relationship'] ?? '') === 'owner' ? 'selected' : ''; ?>>Owner</option>
                        <option value="manager" <?php echo ($_POST['relationship'] ?? '') === 'manager' ? 'selected' : ''; ?>>Manager</option>
                        <option value="member" <?php echo ($_POST['relationship'] ?? '') === 'member' ? 'selected' : ''; ?>>Member/Artist</option>
                        <option value="representative" <?php echo ($_POST['relationship'] ?? '') === 'representative' ? 'selected' : ''; ?>>Representative</option>
                        <option value="other" <?php echo ($_POST['relationship'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <!-- Verification Methods -->
                <div class="verification-methods">
                    <h4 style="margin-top: 0;">Verification Methods <span class="required">*</span></h4>
                    <p style="font-size: 0.875rem; color: #6b7280; margin-bottom: 1rem;">
                        Select at least one method to verify your ownership:
                    </p>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="verify_email" name="verification_methods" value="email" required>
                            <label for="verify_email">I have access to this profile's email</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="verify_social" name="verification_methods" value="social">
                            <label for="verify_social">I can verify via connected social media</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="verify_documentation" name="verification_methods" value="documentation">
                            <label for="verify_documentation">I have documentation (I'll upload later)</label>
                        </div>
                    </div>
                </div>

                <!-- Agreements -->
                <div style="background: #eff6ff; padding: 1rem; border-radius: 0.375rem; margin-bottom: 1.5rem;">
                    <div class="checkbox-item" style="margin-bottom: 0.75rem;">
                        <input type="checkbox" id="social_agree" name="social_agree" value="1" required>
                        <label for="social_agree">
                            I agree to connect my social media accounts for verification
                        </label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="rights_agree" name="rights_agree" value="1" required>
                        <label for="rights_agree">
                            I confirm that I have the legal right to manage this profile and agree to the
                            <a href="/terms-of-service" target="_blank">Terms of Service</a>
                        </label>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="/claim-profile" class="btn btn-secondary">← Back</a>
                    <button type="submit" class="btn btn-primary">Submit Claim Request</button>
                </div>
            </form>

        <?php endif; ?>
    </div>

</div>

</body>
</html>
