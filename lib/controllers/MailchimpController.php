<?php

// Note: The MailchimpMarketing class might still be relevant for list management,
// but transactional email sending is now handled by App\Lib\Email\Mailer.

// Removed the sendTransactionalEmail function as it used MailchimpTransactional.
// If transactional emails are still needed, they should be sent using the Mailer class directly
// or via a dedicated service that utilizes the Mailer.

// This function handles adding/updating subscribers in Mailchimp lists.
// It relies on MailchimpMarketing and assumes the necessary API keys and audience IDs are configured.
function addToAudience($email, $firstName, $lastName, $phone = '', $birthday, $band) {
    // Ensure MailchimpMarketing class exists and is configured
    if (!class_exists('MailchimpMarketing')) {
        // Log a warning if the class is not available, as this functionality will fail.
        error_log('MailchimpMarketing class not found. Cannot add subscriber.');
        return false; // Indicate failure
    }

    // Ensure API key and Audience ID are set in environment variables.
    if (empty($_ENV['MAILCHIMP_API_KEY']) || empty($_ENV['MAILCHIMP_AUDIENCE_ID'])) {
        error_log('Mailchimp API Key or Audience ID not configured. Cannot add subscriber.');
        return false; // Indicate failure
    }

    $marketing = new MailchimpMarketing($_ENV['MAILCHIMP_API_KEY']);

    // Process birthday for Mailchimp merge field format (MM/DD)
    $processedBirthday = null;
    if (!empty($birthday)) {
        try {
            $date = new DateTime($birthday);
            $processedBirthday = $date->format('m/d');
        } catch (Exception $e) {
            // Log invalid birthday format, but proceed.
            error_log("Invalid birthday format for Mailchimp: {$birthday}");
        }
    }

    $memberData = [
        'email_address' => $email,
        'status' => 'subscribed',
        'email_type' => 'html',
        'ip_signup' => getUserIP(), // Assuming getUserIP() is a defined function
        'tags' => ['newsletter'],
        'merge_fields' => [
            'EMAIL' => $email,
            'FNAME' => $firstName,
            'LNAME' => $lastName,
            'PHONE' => $phone,
            'BIRTHDAY' => $processedBirthday,
            'BAND' => $band
        ],
    ];

    try {
        $response = $marketing->addListMember($_ENV['MAILCHIMP_AUDIENCE_ID'], $memberData);
        
        // Check Mailchimp response for success.
        // The exact structure might vary, but typically 'status' indicates success.
        if (isset($response['status']) && $response['status'] === 'subscribed') {
            return true;
        } else {
            // Log Mailchimp specific errors
            error_log('Mailchimp subscription failed: ' . json_encode($response));
            return false;
        }
    } catch (\Throwable $e) {
        error_log('Mailchimp integration error: ' . $e->getMessage());
        return false;
    }
}

// Todo: Create Send Email Blast functionality using Mailer class if needed.

?>