<?php

// MAILCHIMP MERGE TAGS
// EMAIL
// FNAME
// LNAME
// ADDRESS
// PHONE
// BIRTHDAY
// COMPANY
// BAND
// NGNCONTENT
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
class Email {

    private $code;
    private $title;
    public $emailAddress;
    public $subject;
    public $content;

    public function __construct($code=null, $title=null, $emailAddress=null, $subject=null, $content=null)
    {
        $this->code = $code;
        $this->title = $title;
        $this->emailAddress = $this->validateEmail($emailAddress) ? $emailAddress : null;
        $this->subject = $subject;
        $this->content = $content;
    }

    private function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public function createMailButton($url, $text) {
        return '<a href="' . htmlspecialchars($url) . '" style="width:100%;display:inline-block;text-align:center;text-decoration: none;background-color:#46980a;color: White;padding-top:20px;padding-bottom:20px;">' . htmlspecialchars($text) . '</a>';
    }

    public function addContactToAudience($client, $data) {
        try {
            return $client->lists->addListMember($_ENV['MAILCHIMP_AUDIENCE_ID'], $data);
        } catch (Exception $e) {
            die('Error: ' . $e->getMessage());
        }
    }

    public function generateWelcomeEmail() {
        $subject = '🤘 Welcome to NextGen Noise! 🤘';
        $message = '
            <h1>' . htmlspecialchars($subject) . '</h1>
            <p>Hey there, music enthusiast!</p>
            <p>We\'re stoked to have you join the NextGen Noise community. Get ready to discover the freshest sounds and the rising stars of the rock and metal scene.</p>
            <p>Here\'s what you can expect on the NGN:</p>
            <ul>
                <li><strong>Cutting-Edge Charts:</strong> Our unique charts, powered by a blend of industry data and fan engagement, showcase the bands and labels that are shaping the future.</li>
                <li><strong>Exclusive Content:</strong> Dive into in-depth interviews, behind-the-scenes features, and thought-provoking editorials.</li>
                <li><strong>A Vibrant Community:</strong> Connect with fellow music lovers, share your passion, and discover your next favorite band.</li>
            </ul>
            <p>So crank up the volume and let the NGN engine roar! We\'re excited to have you on board for this sonic adventure.</p>
            <p><strong>Rock on,</strong><br>
            The NextGen Noise Team</p>
            <p>' . $this->createMailButton($GLOBALS['Default']['Baseurl'], 'Explore the NGN (Coming Soon)') . '</p> 
        ';
        return ['subject' => htmlspecialchars($subject), 'content' => $message];
    }







	public function generateWelcomeEmailWithVerification() {
		$subject = '🤘 Welcome to NextGen Noise! 🤘';
		$message = "<h1>Greetings, ".$this->title."!</h1>

  <p>Get ready to amplify your signal and connect with a passionate community of rock and metal fans on NextGen Noise! We're thrilled to have you join the revolution.</p>

  <p>Here's how NextGen Noise can help you rock the airwaves:</p>

  <ul>
    <li><strong>Discover the hottest new tracks:</strong> Stay ahead of the curve with our cutting-edge charts that blend industry data and fan engagement. Find your next big hit!</li>
    <li><strong>Connect with your audience:</strong> Engage with listeners, share exclusive content, and build a loyal following on the NGN platform.</li>
    <li><strong>Amplify your reach:</strong> Get discovered by new listeners and industry influencers, expanding your station's impact.</li>
  </ul>

  <p>To complete your station profile and unlock the full power of NextGen Noise, please verify your email address:</p>".
            $this->createMailButton($GLOBALS['Default']['Baseurl'].'verify-email.php/?e=' . urlencode($this->emailAddress) . '&c=' . $this->code, 'Verify Your Email Now!')
            ."<p>Once verified, you can:</p>
  <ul>
    <li>Customize your station profile</li>
    <li>Share your playlists and upcoming shows</li>
    <li>Connect with artists and labels</li>
    <li>And much more!</li>
  </ul>

  <p>Welcome to the future of radio! We're excited to have you on board.</p>

  <p>Rock on,<br>The NextGen Noise Team</p>";

		return ['subject'=>$subject,'content'=>$message];
	}
	public function wrapEmail($subject,$content){
		return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>'.$subject.'</title>
    <style>
        /* Basic Styling */
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif; /* Choose your desired font */
        }

        .header, .footer {
            background-color: black;
            padding: 40px;
            text-align: center;
        }

        .logo a {
            display: inline-block;
            width:50%;
            max-width:200px;
        }

        .logo img {
            max-width: 100%;
            height: auto;
        }

        .main {
            /* Add your custom content styles here */
            padding:30px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <a href="https://nextgennoise.com" title="Visit NextGenNoise.com">
                <img src="https://nextgennoise.com/lib/images/site/web-light-1.png" alt="NextGen Noise Logo">
            </a>
        </div>
    </div>

    <div class="main">
        '.$content.'
        </div>

    <div class="footer">
        <div class="logo">
            <a href="https://nextgennoise.com" title="Visit NextGenNoise.com">
                <img src="https://nextgennoise.com/lib/images/site/web-light-1.png" alt="NextGen Noise Logo">
                
            </a>
            <br>
               	<a href="https://nextgennoise.com" style="color:White;margin-top:20px;">nextgennoise.com</a>
        </div>
    </div>
</body>
</html>';

	}
	public function sendTransactionalEmail($client,$message){
		try {
			return $client->messages->send($message); // Use 'send' instead of 'sendTemplate'


		} catch (Error $e) {
			return 'ERROR: '.$e->getMessage();
		}
	}

    public function sendNGNEmail()
    {
        // Create an instance; passing `true` enables exceptions
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = 'send.smtp.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'nextgennoise';
            $mail->Password = 'NextGen@2';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 587;

            // Recipients
            $mail->setFrom('messenger@nextgennoise.com', 'NGN Messenger');
            $mail->addReplyTo('messenger@nextgennoise.com', 'NGN Messenger');
            $mail->addEmbeddedImage('/www/wwwroot/nextgennoise/lib/images/site/web-dark-1.png', 'ngn_avatar', 'web-dark-1.png');
            $mail->addEmbeddedImage('/www/wwwroot/nextgennoise/lib/images/site/web-light-1.png', 'ngn_avatar', 'web-light-1.png');

            if (is_string($this->emailAddress) && !empty($this->emailAddress)) $mail->addAddress($this->emailAddress);

            // Add headers to enhance credibility
            $mail->addCustomHeader('X-Mailer', 'PHPMailer');
            $mail->addCustomHeader('Return-Path', 'messenger@nextgennoise.com');
            $mail->addCustomHeader('List-Unsubscribe', '<mailto:unsubscribe@nextgennoise.com>, <https://nextgennoise.com/unsubscribe>');

            // Content
            $mail->isHTML(true);
            $mail->Subject = $this->subject;
            $mail->Body = $this->content;
            $mail->AltBody = strip_tags($this->content);
            $mail->CharSet = 'UTF-8';
            $mail->WordWrap = 50;

            return $mail->send();
        } catch (Exception $e) {
            throw new Exception("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }

    }



}