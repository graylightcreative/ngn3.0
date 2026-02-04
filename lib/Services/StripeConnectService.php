<?php

declare(strict_types=1);

namespace NGN\Lib\Services;

use NGN\Lib\Config;
use PDO;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Stripe;
use Monolog\Logger;
use Exception;

class StripeConnectService
{
    private PDO $pdo;
    private Config $config;
    private Logger $logger;

    public function __construct(PDO $pdo, Config $config, Logger $logger)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->logger = $logger;

        $stripeSecretKey = $this->config->get('stripe.secret_key');
        if (!$stripeSecretKey) {
            throw new Exception('Stripe secret key is not configured.');
        }
        Stripe::setApiKey($stripeSecretKey);
        Stripe::setApiVersion('2023-10-16'); // Use a specific API version
    }

    /**
     * Creates a Stripe Connect account for the given user, or retrieves an existing one.
     * Generates an account link for onboarding.
     *
     * @param int $userId The internal NGN user ID.
     * @param string $userEmail The user's email address.
     * @param string $refreshUrl URL to redirect to if the link expires.
     * @param string $returnUrl URL to redirect to after onboarding is complete.
     * @return array Contains 'url' for the account link, 'account_id', and 'error' if any.
     * @throws Exception
     */
    public function createOnboardingLink(int $userId, string $userEmail, string $refreshUrl, string $returnUrl): array
    {
        try {
            // 1. Check if user already has a Stripe Connect account ID
            $stmt = $this->pdo->prepare("SELECT stripe_account_id FROM ngn_2025.users WHERE Id = ?");
            $stmt->execute([$userId]);
            $stripeAccountId = $stmt->fetchColumn();

            if (!$stripeAccountId) {
                // No existing account, create a new Express account
                $account = Account::create([
                    'type' => 'express',
                    'country' => 'US', // Default to US, can be dynamic based on user
                    'email' => $userEmail,
                    'capabilities' => [
                        'card_payments' => ['requested' => true],
                        'transfers' => ['requested' => true],
                    ],
                    'settings' => [
                        'payouts' => [
                            'schedule' => ['interval' => 'manual'], // Manual payouts for now
                        ],
                    ],
                    'business_type' => 'individual', // Default, can be dynamic
                    'metadata' => [
                        'ngn_user_id' => $userId,
                    ],
                ]);
                $stripeAccountId = $account->id;

                // Save the new Stripe account ID to the user in the database
                $updateStmt = $this->pdo->prepare("UPDATE ngn_2025.users SET stripe_account_id = ? WHERE Id = ?");
                $updateStmt->execute([$stripeAccountId, $userId]);
                $this->logger->info("Created new Stripe Connect account for user {$userId}: {$stripeAccountId}");
            } else {
                // Account already exists, retrieve it to ensure it's valid
                $account = Account::retrieve($stripeAccountId);
                $this->logger->info("Retrieved existing Stripe Connect account for user {$userId}: {$stripeAccountId}");
            }

            // 2. Create an Account Link for onboarding
            $accountLink = AccountLink::create([
                'account' => $stripeAccountId,
                'refresh_url' => $refreshUrl,
                'return_url' => $returnUrl,
                'type' => 'account_onboarding',
            ]);

            return [
                'success' => true,
                'url' => $accountLink->url,
                'account_id' => $stripeAccountId,
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->logger->error("Stripe Connect API Error for user {$userId}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw new Exception("Stripe API Error: " . $e->getMessage());
        } catch (Exception $e) {
            $this->logger->error("Stripe Connect Service Error for user {$userId}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * Retrieves the status of a user's Stripe Connect account.
     *
     * @param int $userId The internal NGN user ID.
     * @return array Contains account details or null if not found/error.
     */
    public function getAccountStatus(int $userId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT stripe_account_id FROM ngn_2025.users WHERE Id = ?");
            $stmt->execute([$userId]);
            $stripeAccountId = $stmt->fetchColumn();

            if (!$stripeAccountId) {
                return null; // User does not have a connected Stripe account
            }

            $account = Account::retrieve($stripeAccountId);

            return [
                'id' => $account->id,
                'charges_enabled' => $account->charges_enabled,
                'payouts_enabled' => $account->payouts_enabled,
                'details_submitted' => $account->details_submitted,
                'requirements' => $account->requirements->toArray(),
                // Add any other relevant fields
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->logger->error("Stripe Connect API Error retrieving account status for user {$userId}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return null;
        } catch (Exception $e) {
            $this->logger->error("Stripe Connect Service Error retrieving account status for user {$userId}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return null;
        }
    }

    /**
     * Updates the user's Stripe Connect account status in the database.
     * This would typically be called by a webhook handler.
     *
     * @param string $stripeAccountId The Stripe Connect account ID.
     * @param bool $detailsSubmitted Whether all required details have been submitted.
     * @param bool $chargesEnabled Whether charges are enabled.
     * @param bool $payoutsEnabled Whether payouts are enabled.
     * @return bool True on success, false on failure.
     */
    public function updateAccountStatusInDb(string $stripeAccountId, bool $detailsSubmitted, bool $chargesEnabled, bool $payoutsEnabled): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE ngn_2025.users
                SET
                    stripe_details_submitted = ?,
                    stripe_charges_enabled = ?,
                    stripe_payouts_enabled = ?,
                    updated_at = NOW()
                WHERE stripe_account_id = ?
            ");
            $result = $stmt->execute([
                $detailsSubmitted,
                $chargesEnabled,
                $payoutsEnabled,
                $stripeAccountId
            ]);
            if ($result) {
                $this->logger->info("Updated Stripe Connect status for account {$stripeAccountId} in DB.");
            } else {
                $this->logger->warning("Failed to update Stripe Connect status for account {$stripeAccountId} in DB.");
            }
            return $result;
        } catch (Exception $e) {
            $this->logger->error("Database update error for Stripe Connect account {$stripeAccountId}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return false;
        }
    }
}
