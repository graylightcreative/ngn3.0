<?php

declare(strict_types=1);

namespace NGN\Lib\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use NGN\Lib\Config;
use PDO;
use Psr\Log\LoggerInterface;
use chillerlan\QRCode\{QRCode,QROptions};
use chillerlan\QRCode\Data\QRCodeDataException;
use chillerlan\QRCode\Output\QRCodeOutputException;

class EmailService
{
    private PDO $db;
    private LoggerInterface $logger;
    private Config $config;

    /**
     * Constructor.
     *
     * @param PDO $db Database connection instance.
     * @param LoggerInterface $logger Logger instance.
     * @param Config $config Configuration instance.
     */
    public function __construct(PDO $db, LoggerInterface $logger, Config $config)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Sends an email using PHPMailer.
     *
     * @param string $to      The recipient's email address.
     * @param string $subject The email subject.
     * @param string $body    The email body (HTML or plain text).
     * @param bool $isHtml  Whether the body is HTML (default: true).
     * @return bool True on success, false on failure.
     */
    public function send(string $to, string $subject, string $body, bool $isHtml = true): bool
    {
        $mail = new PHPMailer(true);

        try {
            // --- Server settings ---
            // Load SMTP settings from config
            $smtpHost = $this->config->get('smtp.host');
            $smtpPort = $this->config->get('smtp.port', 587); // Default to 587
            $smtpUser = $this->config->get('smtp.username');
            $smtpPass = $this->config->get('smtp.password');
            $smtpSecure = $this->config->get('smtp.encryption', PHPMailer::ENCRYPTION_STARTTLS);
            $smtpAuth = $this->config->get('smtp.auth', true);
            $smtpFromEmail = $this->config->get('smtp.from_email', 'noreply@example.com'); // Default From Email
            $smtpFromName = $this->config->get('smtp.from_name', 'NGN System'); // Default From Name

            // Use SMTP authentication
            $mail->isSMTP();
            $mail->Host       = $smtpHost;
            $mail->SMTPAuth   = $smtpAuth;
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            $mail->SMTPSecure = $smtpSecure;
            $mail->Port       = $smtpPort;

            // --- Recipients ---
            $mail->setFrom($smtpFromEmail, $smtpFromName);
            $mail->addAddress($to);

            // --- Content ---
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            // Plain text body for non-HTML clients
            if ($isHtml) {
                // Generate plain text alternative body if possible, or leave empty.
                // For now, assuming it's not critical to auto-generate.
                // $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';
            }

            $mail->send();
            $this->logger->info("Email sent successfully to {$to} with subject '{$subject}'.");
            return true;

        } catch (Exception $e) {
            $this->logger->error("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
            return false;
        } catch (\Throwable $e) {
             $this->logger->error("An unexpected error occurred while sending email to {$to}: " . $e->getMessage());
             return false;
        }
    }

    /**
     * Generates a QR code as a base64 encoded PNG image.
     *
     * @param string $data The data to encode in the QR code.
     * @return string|null Base64 encoded PNG image data, or null on failure.
     */
    public function generateQrCodeAsBase64(string $data): ?string
    {
        try {
            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                'imageBase64' => true,
                'eccLevel' => QRCode::ECC_L,
                'scale' => 5, // Scale factor for the image. 5 means 5px per module.
                'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                'imageTransparent' => false,
            ]);

            return (new QRCode($options))->render($data);
        } catch (QRCodeDataException $e) {
            $this->logger->error("QR Code Data Error: " . $e->getMessage());
            return null;
        } catch (QRCodeOutputException $e) {
            $this->logger->error("QR Code Output Error: " . $e->getMessage());
            return null;
        } catch (\Throwable $e) {
            $this->logger->error("An unexpected error occurred during QR code generation: " . $e->getMessage());
            return null;
        }
    }
}
