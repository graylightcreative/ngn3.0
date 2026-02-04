<?php
/**
 * Newsletter Form Signup Handler
 * Handles form POST submissions (not JSON API)
 * Supports redirect parameter for success/error handling
 */
$root = dirname(__DIR__) . '/';
require_once $root . 'definitions/site-settings.php';
require_once $root . 'controllers/EmailController.php';

// Get form data
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$firstName = isset($_POST['firstName']) ? trim($_POST['firstName']) : '';
$lastName = isset($_POST['lastName']) ? trim($_POST['lastName']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$birthday = isset($_POST['birthday']) ? trim($_POST['birthday']) : '';
$band = isset($_POST['band']) ? trim($_POST['band']) : '';
$redirect = isset($_POST['redirect']) ? $_POST['redirect'] : '/frontend/';

// Validate email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . $redirect . (strpos($redirect, '?') !== false ? '&' : '?') . 'error=' . urlencode('Please provide a valid email address'));
    exit;
}

// Check if already subscribed
$check = read('Contacts', 'Email', $email);
if ($check) {
    header('Location: ' . $redirect . (strpos($redirect, '?') !== false ? '&' : '?') . 'error=' . urlencode('This email is already subscribed'));
    exit;
}

// Format birthday if provided
$originalBirthday = $birthday;
$formattedBirthday = !empty($birthday) ? date('m/d', strtotime($birthday)) : null;

try {
    // Add to Mailchimp
    $marketing = new MailchimpMarketing($_ENV['MAILCHIMP_API_KEY']);
    
    $memberData = [
        'email_address' => $email,
        'status' => 'subscribed',
        'email_type' => 'html',
        'ip_signup' => getUserIP(),
        'tags' => ['newsletter', 'ngn2-waitlist'],
        'merge_fields' => [
            'EMAIL' => $email,
            'FNAME' => $firstName,
            'LNAME' => $lastName,
            'PHONE' => $phone,
            'BIRTHDAY' => $formattedBirthday,
            'BAND' => $band
        ],
    ];
    
    $response = $marketing->addListMember($_ENV['MAILCHIMP_AUDIENCE_ID'], $memberData);
    
    // Add to local Contacts table
    $addtoNGN = add('Contacts', [
        'FirstName' => $firstName,
        'LastName' => $lastName,
        'Email' => $email,
        'Phone' => $phone,
        'Address' => null,
        'Status' => 'subscribed',
        'Birthday' => !empty($originalBirthday) ? date('Y-m-d', strtotime($originalBirthday)) : null,
        'Company' => '',
        'Band' => $band,
        'tags' => 'newsletter,ngn2-waitlist'
    ]);
    
    // Send welcome email
    try {
        $ourEmail = new Email();
        $welcomeEmail = $ourEmail->generateWelcomeEmail();
        $wrappedMail = $ourEmail->wrapEmail($welcomeEmail['subject'], $welcomeEmail['content']);
        
        $smtp = new SMTP();
        $to = [
            'name' => $firstName . ' ' . $lastName,
            'address' => $email
        ];
        $from = [
            'name' => 'NGN Messenger',
            'address' => 'messenger@nextgennoise.com',
            'custom_headers' => null
        ];
        $smtp->send($to, $from, $welcomeEmail['subject'], $wrappedMail);
    } catch (\Throwable $e) {
        // Log but don't fail if email fails
        error_log('Newsletter welcome email failed: ' . $e->getMessage());
    }
    
    // Success redirect
    header('Location: ' . $redirect);
    exit;
    
} catch (\Throwable $e) {
    error_log('Newsletter signup error: ' . $e->getMessage());
    header('Location: ' . $redirect . (strpos($redirect, '?') !== false ? '&' : '?') . 'error=' . urlencode('An error occurred. Please try again.'));
    exit;
}

