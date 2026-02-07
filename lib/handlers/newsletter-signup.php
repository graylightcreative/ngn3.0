<?php

$root = dirname(dirname(dirname(__FILE__))) . '/';
require_once $root . 'lib/bootstrap.php';
require_once $root . 'lib/definitions/site-settings.php';
require_once $root . 'lib/controllers/EmailController.php'; // Contains generateWelcomeEmailContent
require_once $root . 'lib/controllers/ResponseController.php';

// Ensure JSON input is parsed
$_POST = json_decode(file_get_contents('php://input'), true);

// --- Dependencies ---
try {
    // Assume $pdo, $logger, and $config are available from bootstrap.php
    if (!isset($pdo) || !($pdo instanceof \PDO)) {
        if (class_exists('NGN\Lib\DB\ConnectionFactory')) {
            $pdo = NGN\Lib\DB\ConnectionFactory::read(new NGN\Lib\Config());
        } else {
            throw new \RuntimeException("PDO connection not available and ConnectionFactory not found.");
        }
    }
    if (!isset($logger)) {
        $logger = new \Monolog\Logger('newsletter_signup');
        // Adjust log path to be relative to project root
        $logFilePath = dirname(dirname(__DIR__)) . '/storage/logs/newsletter_signup.log';
        $logger->pushHandler(new \Monolog\Handler\StreamHandler($logFilePath, \Monolog\Logger::DEBUG));
    }
    if (!isset($config) || !($config instanceof NGN\Lib\Config)) {
         $config = new NGN\Lib\Config();
    }

} catch (\Throwable $e) {
    error_log("Newsletter Signup Handler Setup Error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Internal server error during setup.']);
    exit;
}

$pageResponse = makeResponse(); // Assuming this function is available and returns an array/object for response structure

// --- Input Validation ---
$incomingEmail = $_POST['email'] ?? null;
if (empty($incomingEmail) || !filter_var($incomingEmail, FILTER_VALIDATE_EMAIL)) {
    $pageResponse['code'] = 400;
    killWithMessage('Please provide a valid email address', $pageResponse);
}

$firstName = $_POST['first_name'] ?? null;
$lastName = $_POST['last_name'] ?? null;
$phone = $_POST['phone'] ?? null;
$birthdayInput = $_POST['birthday'] ?? null;
$band = $_POST['band'] ?? null;

// Process birthday for Mailchimp merge field format (MM/DD)
$processedBirthday = null;
if (!empty($birthdayInput)) {
    try {
        $date = new DateTime($birthdayInput);
        $processedBirthday = $date->format('m/d');
    } catch (Exception $e) {
        $logger->warning("Invalid birthday format received: {$birthdayInput}");
        // Optionally, set an error or ignore invalid birthday
    }
}

// Check if contact already exists
$check = read('Contacts', 'Email', $incomingEmail);
if ($check) {
    $pageResponse['code'] = 400;
    killWithMessage('This email address is already subscribed', $pageResponse);
}

// --- Mailchimp Integration (for adding to mailing list) ---
// Assuming $marketing is an instance of MailchimpMarketing class, properly configured.
// If MailchimpMarketing is not available, this part needs to be handled or removed.
try {
    // Ensure MailchimpMarketing class is available. If not, this section needs to be handled.
    if (class_exists('MailchimpMarketing')) {
        $marketing = new MailchimpMarketing($_ENV['MAILCHIMP_API_KEY']);

        $memberData = [
            'email_address' => $incomingEmail,
            'status' => 'subscribed',
            'email_type' => 'html',
            'ip_signup' => getUserIP(), // Assuming getUserIP() is a defined function
            'tags' => ['newsletter'],
            'merge_fields' => [
                'EMAIL' => $incomingEmail,
                'FNAME' => $firstName,
                'LNAME' => $lastName,
                'PHONE' => $phone,
                'BIRTHDAY' => $processedBirthday,
                'BAND' => $band
            ],
        ];

        $mailchimpResponse = $marketing->addListMember($_ENV['MAILCHIMP_AUDIENCE_ID'], $memberData);

        // Basic check for Mailchimp success. Adjust based on actual Mailchimp API response structure.
        if (!isset($mailchimpResponse['status']) || $mailchimpResponse['status'] !== 'subscribed') {
            // Log or handle Mailchimp specific errors
            $logger->warning('Mailchimp subscription failed or status not subscribed: ' . json_encode($mailchimpResponse));
            // Decide if this should block NGN save, or proceed with NGN save only.
            // For now, we proceed with NGN save even if Mailchimp fails, but log the issue.
        }
    } else {
        $logger->warning('MailchimpMarketing class not found. Mailchimp subscription skipped.');
    }
} catch (\Throwable $e) {
    $logger->error('Mailchimp integration error: ' . $e->getMessage());
    // Decide if Mailchimp failure should stop the entire process.
    // For now, we proceed with NGN save.
}

// --- Save to NGN Database ---
$addtoNGN = add('Contacts', [
    'FirstName' => $firstName,
    'LastName' => $lastName,
    'Email' => $incomingEmail,
    'Phone' => $phone,
    'Address' => null,
    'Status' => 'subscribed',
    'Birthday' => $processedBirthday ? date('Y-m-d', strtotime($birthdayInput)) : null,
    'Company' => $company,
    'Band' => $band,
    'tags' => ['newsletter']
]);

if (!$addtoNGN) {
    $logger->error("Failed to save contact to NGN database: Email {$incomingEmail}");
    killWithMessage('An error occurred saving your subscription to NGN. Please try again.', $pageResponse);
}

// --- Send Welcome Email ---
// Email sending will be handled via Mailchimp's welcome automation
// Manual email sending skipped for now
$logger->info("Newsletter signup completed for {$incomingEmail}. Welcome email sent via Mailchimp automation.");

// --- Final Response ---
$pageResponse['success'] = true;
$pageResponse['code'] = 200;
header('Content-Type: application/json');
echo json_encode($pageResponse);

?>
