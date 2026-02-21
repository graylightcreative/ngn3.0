<?php

// Include necessary NGN bootstrap and layout files
require_once __DIR__ . '/../lib/bootstrap.php'; // Adjust path if register.php is not in root

use App\Lib\Email\Mailer;
use NGN\Lib\Config;
use NGN\Lib\Http\{Request, Response, Router, JsonResponse};
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// --- Page Title ---
$pageTitle = 'Register for NGN';

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
        $logger = new Logger('auth');
        $logFilePath = __DIR__ . '/../storage/logs/auth.log'; // Adjust path
        $logger->pushHandler(new StreamHandler($logFilePath, Logger::DEBUG));
    }
    if (!isset($config) || !($config instanceof Config)) {
         $config = new Config();
    }

} catch (\Throwable $e) {
    error_log("Register Page Setup Error: " . $e->getMessage());
    http_response_code(500);
    echo "<h1>Internal Server Error</h1><p>Could not initialize critical services for registration.</p>";
    exit;
}

// --- POST Request Handling ---
$errorMessage = null;
$successMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $userType = $_POST['user_type'] ?? 'fan'; // Default to fan

    // Basic Validation
    if (empty($name) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($password) || $password !== $confirmPassword || !in_array($userType, ['fan', 'industry_pro'])) {
        $errorMessage = 'Please fill in all fields correctly. Ensure passwords match and select a valid user type.';
    } else {
        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT Id FROM users WHERE LOWER(Email) = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errorMessage = 'An account with this email already exists.';
            } else {
                // Determine Role (3 = Fan, 8 = Writer/Pro)
                $roleId = ($userType === 'fan') ? 3 : 8;
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("INSERT INTO users (Name, Email, PasswordHash, RoleId, CreatedAt) VALUES (?, ?, ?, ?, NOW())");
                if ($stmt->execute([$name, $email, $passwordHash, $roleId])) {
                    $successMessage = "Account created successfully. You can now <a href='/login.php' class='text-brand font-bold'>Log in</a>.";
                } else {
                    $errorMessage = "Failed to create account. Please try again.";
                }
            }
        } catch (\Throwable $e) {
            $errorMessage = 'An unexpected error occurred during registration. Please contact support.';
            $logger->error("Unexpected error during registration for email {$email}: " . $e->getMessage());
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
    <div class="w-full max-w-2xl p-8 space-y-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg">
        <div class="text-center">
            <h1 class="text-4xl font-extrabold sk-text-gradient-primary mb-2">Join NextGenNoise</h1>
            <p class="text-gray-600 dark:text-gray-300">Start your journey with us today.</p>
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

        <!-- Path Selection Section -->
        <div id="pathSection" class="mb-8">
            <div class="text-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-2">How would you like to join?</h2>
                <p class="text-gray-600 dark:text-gray-300 text-sm">Choose the option that fits you best</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <!-- Option 1: Claim Existing Profile -->
                <button type="button" class="path-option-btn p-6 border-2 border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition" data-path="claim">
                    <div class="text-center">
                        <div class="text-3xl mb-2">🎵</div>
                        <h3 class="font-bold text-lg text-gray-800 dark:text-gray-100 mb-2">Claim Existing Profile</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Already on NextGenNoise? Claim your artist, label, venue, or station profile</p>
                    </div>
                </button>

                <!-- Option 2: Create New Profile -->
                <button type="button" class="path-option-btn p-6 border-2 border-gray-300 rounded-lg hover:border-purple-500 hover:bg-purple-50 dark:hover:bg-purple-900/20 transition" data-path="create">
                    <div class="text-center">
                        <div class="text-3xl mb-2">✨</div>
                        <h3 class="font-bold text-lg text-gray-800 dark:text-gray-100 mb-2">Create New Profile</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Don't see your profile? Create a new artist, label, venue, or station profile</p>
                    </div>
                </button>

                <!-- Option 3: Just a Fan -->
                <button type="button" class="path-option-btn p-6 border-2 border-gray-300 rounded-lg hover:border-green-500 hover:bg-green-50 dark:hover:bg-green-900/20 transition" data-path="fan">
                    <div class="text-center">
                        <div class="text-3xl mb-2">❤️</div>
                        <h3 class="font-bold text-lg text-gray-800 dark:text-gray-100 mb-2">I'm a Fan</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Discover and follow artists, discover new music, and engage with the community</p>
                    </div>
                </button>

                <!-- Option 4: Industry Pro -->
                <button type="button" class="path-option-btn p-6 border-2 border-gray-300 rounded-lg hover:border-orange-500 hover:bg-orange-50 dark:hover:bg-orange-900/20 transition" data-path="pro">
                    <div class="text-center">
                        <div class="text-3xl mb-2">💼</div>
                        <h3 class="font-bold text-lg text-gray-800 dark:text-gray-100 mb-2">Industry Pro</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Manager, label rep, or promoter looking to manage multiple profiles</p>
                    </div>
                </button>
            </div>
        </div>

        <form class="mt-8 space-y-6" action="register.php" method="POST">
            <div>
                <label for="name" class="sr-only">Full Name</label>
                <input id="name" name="name" type="text" required class="sk-input w-full" placeholder="Full Name">
            </div>
            <div>
                <label for="email" class="sr-only">Email Address</label>
                <input id="email" name="email" type="email" required class="sk-input w-full" placeholder="Email address">
            </div>
            <div>
                <label for="password" class="sr-only">Password</label>
                <input id="password" name="password" type="password" required class="sk-input w-full" placeholder="Password">
            </div>
            <div>
                <label for="confirm_password" class="sr-only">Confirm Password</label>
                <input id="confirm_password" name="confirm_password" type="password" required class="sk-input w-full" placeholder="Confirm password">
            </div>

            <?php // User Type Selection ?>
            <fieldset>
              <legend class="sr-only">User Type</legend>
              <div class="space-y-2">
                <div class="flex items-center">
                  <input id="user_type_fan" name="user_type" type="radio" value="fan" checked class="sk-radio" data-role-trigger="fan-specific-fields">
                  <label for="user_type_fan" class="ml-2 text-sm text-gray-700 dark:text-gray-200 cursor-pointer">I am a Fan</label>
                </div>
                <div class="flex items-center">
                  <input id="user_type_pro" name="user_type" type="radio" value="industry_pro" class="sk-radio" data-role-trigger="pro-specific-fields">
                  <label for="user_type_pro" class="ml-2 text-sm text-gray-700 dark:text-gray-200 cursor-pointer">I am an Industry Pro</label>
                </div>
              </div>
            </fieldset>

            <?php // Placeholder for Industry Pro specific fields, hidden by default if Fan is selected ?>
            <?php // e.g., Business Name, Role, etc. ?>
            <div id="pro-specific-fields" class="hidden space-y-4">
              <div>
                <label for="business_name" class="sr-only">Business/Company Name</label>
                <input id="business_name" name="business_name" type="text" class="sk-input w-full" placeholder="Business/Company Name (Optional)">
              </div>
              <div>
                <label for="industry_role" class="sr-only">Your Role</label>
                <input id="industry_role" name="industry_role" type="text" class="sk-input w-full" placeholder="Your Role (e.g., Manager, Label Rep)">
              </div>
            </div>

            <div>
                <button type="submit" class="sk-btn sk-btn-primary sk-btn-glow w-full">
                    Register
                </button>
            </div>
        </form>

        <div class="text-center text-sm">
            <p class="text-gray-600 dark:text-gray-300">Already have an account? <a href="/login.php" class="font-medium sk-text-gradient-primary hover:underline">Login here</a></p>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const pathOptionBtns = document.querySelectorAll('.path-option-btn');
        const pathSection = document.getElementById('pathSection');
        const registrationForm = document.querySelector('form');
        const userTypeRadios = document.querySelectorAll('input[name="user_type"]');
        const proFields = document.getElementById('pro-specific-fields');

        // Handle path selection
        pathOptionBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const path = this.getAttribute('data-path');

                if (path === 'claim') {
                    // Redirect to claim profile page
                    window.location.href = '/claim-profile';
                } else if (path === 'create') {
                    // Redirect to create entity page (future feature)
                    window.location.href = '/create-entity';
                } else if (path === 'fan') {
                    // Continue with fan registration
                    document.getElementById('user_type_fan').checked = true;
                    pathSection.style.display = 'none';
                    registrationForm.style.display = 'block';
                    toggleProFields();
                } else if (path === 'pro') {
                    // Continue with pro registration
                    document.getElementById('user_type_pro').checked = true;
                    pathSection.style.display = 'none';
                    registrationForm.style.display = 'block';
                    toggleProFields();
                }
            });
        });

        const toggleProFields = () => {
            if (document.getElementById('user_type_pro').checked) {
                proFields.classList.remove('hidden');
            } else {
                proFields.classList.add('hidden');
            }
        };

        userTypeRadios.forEach(radio => {
            radio.addEventListener('change', toggleProFields);
        });

        // Initially hide the form (show path selection)
        registrationForm.style.display = 'block';
        pathSection.style.display = 'block';

        // Initial check on page load
        toggleProFields();
    });
</script>

<?php require dirname(__DIR__) . "/lib/partials/global-footer.php"; ?>

</body>
</html>
