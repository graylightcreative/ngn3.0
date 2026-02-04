<?php


function createMailButton($url,$text){
	return '<a href="' . htmlspecialchars($url) . '" style="width:100%;display:inline-block;text-align:center;text-decoration: none;background-color:#46980a;color: White;padding-top:20px;padding-bottom:20px;">' . htmlspecialchars($text) . '</a>';
}

function generateWelcomeEmailContent() {
	$subject = '🤘 Welcome to NextGen Noise! 🤘';
	$message = "
        <h1>" . htmlspecialchars($subject) . "</h1>
        <p>Hey there, music enthusiast!</p>

        <p>We're stoked to have you join the NextGen Noise community. Get ready to discover the freshest sounds and the rising stars of the rock and metal scene.</p>

        <p>Here's what you can expect on the NGN (pronounced \"Engine\"):</p>

        <ul>
            <li><strong>Cutting-Edge Charts:</strong> Our unique charts, powered by a blend of industry data and fan engagement, showcase the bands and labels that are shaping the future.</li>
            <li><strong>Exclusive Content:</strong> Dive into in-depth interviews, behind-the-scenes features, and thought-provoking editorials.</li>
            <li><strong>A Vibrant Community:</strong> Connect with fellow music lovers, share your passion, and discover your next favorite band.</li>
        </ul>

        <p>So crank up the volume and let the NGN roar! We're excited to have you on board for this sonic adventure.</p>

        <p><strong>Rock on,</strong><br>
        The NextGen Noise Team</p>

        <p>" . createMailButton($GLOBALS['Default']['Baseurl'], 'Explore the NGN (Coming Soon)') . " 
    ";
	$array = [];
	$array['subject'] = $subject;
	$array['body'] = $message; // Renamed from 'message' to 'body' for clarity with Mailer
	return $array;
}

// This function is no longer needed as Mailer class handles content wrapping
// function wrapEmail($subject,$content){
// 	return "<!DOCTYPE html>
// <html lang=\"en\">
// <head>
//     <meta charset=\"UTF-8\">
//     <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
//     <title>" . htmlspecialchars($subject) . "</title>
//     <style>
//         /* Basic Styling */
//         body {
//             margin: 0;
//             padding: 0;
//             font-family: Arial, sans-serif; /* Choose your desired font */
//         }

//         .header, .footer {
//             background-color: black;
//             padding: 40px;
//             text-align: center;
//         }

//         .logo a {
//             display: inline-block;
//             width:50%;
//             max-width:200px;
//         }

//         .logo img {
//             max-width: 100%;
//             height: auto;
//         }

//         .main {
//             /* Add your custom content styles here */
//             padding:30px;
//         }
//     </style>
// </head>
// <body>
//     <div class="header">
//         <div class="logo">
//             <a href=\"https://nextgennoise.com\" title=\"Visit NextGenNoise.com\">
//                 <img src=\"https://nextgennoise.com/lib/images/site/web-light-1.png\" alt=\"NextGen Noise Logo">
//             </a>
//         </div>
//     </div>

//     <div class="main">
//         " . $content . "
//         </div>

//     <div class="footer">
//         <div class="logo">
//             <a href=\"https://nextgennoise.com\" title=\"Visit NextGenNoise.com\">
//                 <img src=\"https://nextgennoise.com/lib/images/site/web-light-1.png\" alt=\"NextGen Noise Logo">
//             </a>
//         </div>
//     </div>
// </body>
// </html>";
// }

// --- Mailer Class for sending emails ---
// Note: Ensure Mailer class is properly namespaced and instantiated with necessary dependencies.
// For example: use App\Lib\Email\Mailer; 
// Assume $mailer instance is available or instantiated properly in bootstrap.

// Function to send welcome email (now uses Mailer)
function sendWelcomeEmail($email, $title) {
    $emailContent = generateWelcomeEmailContent();
    $subject = $emailContent['subject'];
    $body = $emailContent['body'];

    // Ensure the Mailer instance is available
    global $mailer;
    if (!$mailer) {
        // Attempt to instantiate Mailer if not globally available
        // This requires access to $pdo, $logger, $config. If they are not global, this will fail.
        try {
            // Re-instantiate dependencies if necessary (assuming they are not global)
            $pdo = null; // Needs actual PDO instance
            $logger = null; // Needs actual Logger instance
            $config = new Config(); // Assuming Config is accessible
            
            if (class_exists('NGN\Lib\Database\ConnectionFactory')) {
                $pdo = NGN\Lib\Database\ConnectionFactory::read($config);
            }
            if (!$pdo) throw new \RuntimeException('PDO not available.');

            $logger = new Logger('email_controller');
            $logFilePath = __DIR__ . '/../../../storage/logs/email_controller.log';
            $logger->pushHandler(new StreamHandler($logFilePath, Logger::INFO));
            if (!$logger) throw new \RuntimeException('Logger not available.');

            $mailer = new Mailer($pdo, $logger, $config);
        } catch (\Throwable $e) {
            error_log("Mailer instantiation failed in sendWelcomeEmail: " . $e->getMessage());
            return false;
        }
    }

    // Send the email using the Mailer class
    if ($mailer->send($email, $subject, $body, true)) {
        error_log("Welcome email sent to {$email} for user {$title}.");
        return true;
    } else {
        error_log("Failed to send welcome email to {$email} for user {$title}.");
        return false;
    }
}

// The MailchimpTransactional class and sendMailchimpTransactional function are now deprecated and should be removed or refactored.
// If these functions are used elsewhere and are critical, they should be addressed during the Mandrill replacement process.


// Example of how another controller might use the Mailer:
// function sendPasswordResetEmail($email, $resetLink) {
//     global $mailer;
//     if (!$mailer) { /* handle initialization */ }
//     $subject = "Password Reset Request";
//     $body = "<p>Please reset your password using this link: <a href=\"" . htmlspecialchars($resetLink) . "\">Reset Password</a></p>";
//     return $mailer->send($email, $subject, $body, true);
// }

// Note: This file is primarily for demonstrating email functions. Actual email sending logic might be
// within services or specific controllers that instantiate the Mailer class.
?>
