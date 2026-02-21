<?php

declare(strict_types=1);

namespace NGN\Lib\Commerce;

use PDO;
use Psr\Log\LoggerInterface;

class ServiceOrderManager
{
    private PDO $db;
    private LoggerInterface $logger;

    // Assuming this service will be injected with a PDO connection and a logger
    public function __construct(PDO $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Creates a new service order.
     *
     * @param int $userId The ID of the user placing the order.
     * @param string $serviceType The type of service being ordered.
     * @param float $price The price of the service.
     * @return array An array containing success status and optionally a Stripe session ID.
     */
    public function createOrder(int $userId, string $serviceType, float $price): array
    {
        // Validate input (basic check)
        if ($userId <= 0 || empty($serviceType) || $price < 0) {
            $this->logger->warning('Invalid input for createOrder: userId=' . $userId . ', serviceType=' . $serviceType . ', price=' . $price);
            return ['success' => false, 'message' => 'Invalid order details.'];
        }

        try {
            // Insert order into service_orders table
            $stmt = $this->db->prepare(
                "INSERT INTO service_orders (user_id, service_type, price, status, created_at, updated_at) 
                 VALUES (:user_id, :service_type, :price, :status, NOW(), NOW())"
            );
            
            $status = 'pending'; // Default status
            $success = $stmt->execute([
                ':user_id' => $userId,
                ':service_type' => $serviceType,
                ':price' => $price,
                ':status' => $status
            ]);

            if ($success) {
                $orderId = $this->db->lastInsertId();
                $this->logger->info("Service order created: ID {$orderId} for User {$userId}, Service: {$serviceType}, Price: {$price}.");

                // Optional: Integrate with Stripe Checkout
                // If StripeCheckoutService is available and configured:
                /*
                try {
                    $stripeCheckoutService = new StripeCheckoutService($this->db, $this->logger, $config); // Assuming config is accessible
                    $session = $stripeCheckoutService->createCheckoutSessionForService(
                        $orderId, // Pass the order ID
                        $userId, // Pass user ID for context
                        $serviceType, // Service description
                        $price     // Price in cents for Stripe
                    );
                    if ($session && isset($session['id'])) {
                        return ['success' => true, 'sessionId' => $session['id'], 'message' => 'Order created successfully. Redirecting to payment.'];
                    }
                } catch (
App\Lib\Commerce\Stripe\ApiException $e) {
                    $this->logger->error("Stripe API error for order {$orderId}: " . $e->getMessage());
                    // Handle Stripe API errors, maybe mark order as failed or require manual intervention
                    return ['success' => false, 'message' => 'Failed to initiate payment. Please contact support.'];
                } catch (
Throwable $e) {
                    $this->logger->error("Unexpected error during Stripe integration for order {$orderId}: " . $e->getMessage());
                    return ['success' => false, 'message' => 'An unexpected error occurred during payment setup.'];
                }
                */

                // If not using Stripe or if Stripe integration fails, return success for manual invoicing/processing
                return ['success' => true, 'message' => 'Order created successfully. Please check your dashboard for payment details.'];
            } else {
                $this->logger->error("Failed to insert service order into database for User {$userId}.");
                return ['success' => false, 'message' => 'Failed to create order. Please try again later.'];
            }

        } catch (
PDOException $e) {
            $this->logger->error("Database error creating service order for User {$userId}: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error. Please contact support.'];
        } catch (
Throwable $e) {
            $this->logger->error("An unexpected error occurred in ServiceOrderManager: " . $e->getMessage());
            return ['success' => false, 'message' => 'An unexpected error occurred.'];
        }
    }
}
