<?php

namespace NGN\Lib\Security;

use Exception;

/**
 * Encryption Service for Secure API Key Storage
 *
 * Uses AES-256-GCM encryption for API keys, secrets, and sensitive data
 * Critical for: Stripe keys, webhook secrets, OAuth tokens
 *
 * Requirements:
 * - ENCRYPTION_KEY in .env (32 bytes, base64 encoded)
 * - OpenSSL PHP extension
 */
class EncryptionService
{
    private string $key;
    private string $cipher = 'aes-256-gcm';

    public function __construct(string $encryptionKey = null)
    {
        // Get encryption key from .env or parameter
        $key = $encryptionKey ?? $_ENV['ENCRYPTION_KEY'] ?? getenv('ENCRYPTION_KEY');

        if (empty($key)) {
            throw new Exception('ENCRYPTION_KEY not configured in .env');
        }

        // Decode base64 key
        $this->key = base64_decode($key);

        if (strlen($this->key) !== 32) {
            throw new Exception('ENCRYPTION_KEY must be 32 bytes (256 bits) when decoded');
        }

        // Verify OpenSSL is available
        if (!function_exists('openssl_encrypt')) {
            throw new Exception('OpenSSL extension not available');
        }
    }

    /**
     * Encrypt plaintext data
     *
     * @param string $plaintext Data to encrypt
     * @return string Encrypted data (base64 encoded: iv:tag:ciphertext)
     * @throws Exception
     */
    public function encrypt(string $plaintext): string
    {
        if (empty($plaintext)) {
            throw new Exception('Cannot encrypt empty string');
        }

        // Generate random IV (initialization vector)
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);

        // Encrypt
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16 // Tag length for GCM
        );

        if ($ciphertext === false) {
            throw new Exception('Encryption failed: ' . openssl_error_string());
        }

        // Combine iv:tag:ciphertext and encode
        $encrypted = base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($ciphertext);

        return $encrypted;
    }

    /**
     * Decrypt encrypted data
     *
     * @param string $encrypted Encrypted data (base64 encoded: iv:tag:ciphertext)
     * @return string Decrypted plaintext
     * @throws Exception
     */
    public function decrypt(string $encrypted): string
    {
        if (empty($encrypted)) {
            throw new Exception('Cannot decrypt empty string');
        }

        // Split iv:tag:ciphertext
        $parts = explode(':', $encrypted);
        if (count($parts) !== 3) {
            throw new Exception('Invalid encrypted data format');
        }

        [$ivEncoded, $tagEncoded, $ciphertextEncoded] = $parts;

        // Decode components
        $iv = base64_decode($ivEncoded);
        $tag = base64_decode($tagEncoded);
        $ciphertext = base64_decode($ciphertextEncoded);

        // Decrypt
        $plaintext = openssl_decrypt(
            $ciphertext,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new Exception('Decryption failed: ' . openssl_error_string());
        }

        return $plaintext;
    }

    /**
     * Generate a secure random encryption key
     *
     * Use this once to generate ENCRYPTION_KEY for .env
     *
     * @return string Base64 encoded 32-byte key
     */
    public static function generateKey(): string
    {
        $key = openssl_random_pseudo_bytes(32);
        return base64_encode($key);
    }

    /**
     * Hash sensitive data for comparison (one-way)
     *
     * Use for: Password verification, API key fingerprinting
     *
     * @param string $data Data to hash
     * @return string Hashed data
     */
    public function hash(string $data): string
    {
        return hash('sha256', $data);
    }

    /**
     * Mask sensitive string for display
     *
     * Shows first 4 and last 4 characters: "pk_t••••••••2345"
     *
     * @param string $value Sensitive string
     * @param int $visibleStart Visible characters at start
     * @param int $visibleEnd Visible characters at end
     * @return string Masked string
     */
    public static function mask(string $value, int $visibleStart = 4, int $visibleEnd = 4): string
    {
        if (empty($value)) {
            return '';
        }

        $length = strlen($value);

        if ($length <= ($visibleStart + $visibleEnd)) {
            // String too short to mask meaningfully
            return str_repeat('•', $length);
        }

        $start = substr($value, 0, $visibleStart);
        $end = substr($value, -$visibleEnd);
        $masked = str_repeat('•', $length - $visibleStart - $visibleEnd);

        return $start . $masked . $end;
    }

    /**
     * Validate Stripe key format
     *
     * @param string $key Key to validate
     * @param string $type 'publishable', 'secret', or 'webhook'
     * @param string $environment 'live' or 'sandbox'
     * @return bool True if valid format
     */
    public static function validateStripeKeyFormat(string $key, string $type, string $environment): bool
    {
        if (empty($key)) {
            return false;
        }

        $prefix = '';
        switch ($type) {
            case 'publishable':
                $prefix = $environment === 'live' ? 'pk_live_' : 'pk_test_';
                break;
            case 'secret':
                $prefix = $environment === 'live' ? 'sk_live_' : 'sk_test_';
                break;
            case 'webhook':
                $prefix = 'whsec_';
                break;
            default:
                return false;
        }

        return str_starts_with($key, $prefix);
    }
}
