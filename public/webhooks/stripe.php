<?php
/**
 * Stripe Webhook Handler
 *
 * Handles incoming webhooks from Stripe for various events like
 * successful payments, subscription updates, etc.
 */

require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Setup logger
$log = new Logger('stripe_webhook');
$log->pushHandler(new StreamHandler(dirname(__DIR__, 2) . '/storage/logs/stripe_webhooks.log', Logger::INFO));

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? null;
$event = null;

try {
    $config = new Config();
    $webhook_secret = $_ENV['STRIPE_WEBHOOK_SECRET'];

    if (!$webhook_secret) {
        throw new \Exception('Stripe webhook secret is not configured.');
    }
    if (!$sig_header) {
        throw new \Exception('Stripe signature header is missing.');
    }

    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, $webhook_secret
    );

} catch(\UnexpectedValueException $e) {
    // Invalid payload
    $log->error('Invalid payload', ['exception' => $e->getMessage()]);
    http_response_code(400);
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    // Invalid signature
    $log->error('Invalid signature', ['exception' => $e->getMessage()]);
    http_response_code(400);
    exit();
} catch (\Throwable $e) {
    $log->error('Webhook construction failed', ['exception' => $e->getMessage()]);
    http_response_code(500);
    exit();
}

$log->info('Received event', ['type' => $event->type, 'id' => $event->id]);

// Handle the event
switch ($event->type) {
    case 'checkout.session.completed':
        $session = $event->data->object;
        $metadata = $session->metadata;
        $userId = $metadata->user_id ?? null;
        $tierId = $metadata->tier_id ?? null;
        $entityType = $metadata->entity_type ?? 'artist'; // Default to artist

        if ($session->payment_status === 'paid' && $userId && $tierId) {
            try {
                $pdo = ConnectionFactory::write($config);
                $subscriptionId = $session->subscription;

                // Check if a subscription already exists
                $stmt = $pdo->prepare("SELECT id FROM `ngn_2025`.`user_subscriptions` WHERE user_id = ? AND entity_type = ?");
                $stmt->execute([$userId, $entityType]);
                $existingSubscription = $stmt->fetch();

                if ($existingSubscription) {
                    // Update existing subscription
                    try {
                        $sub = \Stripe\Subscription::retrieve($subscriptionId);
                    } catch (\Stripe\Exception\ApiErrorException $e) {
                        $log->error('Stripe API error retrieving subscription for checkout.session.completed update', ['subscription_id' => $subscriptionId, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                        http_response_code(500); exit();
                    }
                    $stmt = $pdo->prepare("
                        UPDATE `ngn_2025`.`user_subscriptions` 
                        SET tier_id = ?, status = 'active', stripe_subscription_id = ?, updated_at = NOW(), current_period_start = FROM_UNIXTIME(?), current_period_end = FROM_UNIXTIME(?)
                        WHERE id = ?
                    ");
                    $stmt->execute([$tierId, $subscriptionId, $sub->current_period_start, $sub->current_period_end, $existingSubscription['id']]);
                    $log->info('Subscription updated', ['user_id' => $userId, 'tier_id' => $tierId, 'stripe_subscription_id' => $subscriptionId]);
                } else {
                    // Insert new subscription
                    try {
                        $sub = \Stripe\Subscription::retrieve($subscriptionId);
                    } catch (\Stripe\Exception\ApiErrorException $e) {
                        $log->error('Stripe API error retrieving subscription for checkout.session.completed insert', ['subscription_id' => $subscriptionId, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                        http_response_code(500); exit();
                    }
                    $stmt = $pdo->prepare("
                        INSERT INTO `ngn_2025`.`user_subscriptions` (user_id, tier_id, entity_type, status, stripe_subscription_id, created_at, updated_at, current_period_start, current_period_end)
                        VALUES (?, ?, ?, 'active', ?, NOW(), NOW(), FROM_UNIXTIME(?), FROM_UNIXTIME(?))
                    ");
                    $stmt->execute([$userId, $tierId, $entityType, $subscriptionId, $sub->current_period_start, $sub->current_period_end]);
                    $log->info('Subscription created', ['user_id' => $userId, 'tier_id' => $tierId, 'stripe_subscription_id' => $subscriptionId]);
                }

            } catch (\Throwable $e) {
                $log->error('Error handling checkout.session.completed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                http_code(500);
                exit();
            }
        }
        break;

    case 'invoice.payment_succeeded':
        $invoice = $event->data->object;
        if ($invoice->billing_reason === 'subscription_cycle') {
            $subscriptionId = $invoice->subscription;
            try {
                $pdo = ConnectionFactory::write($config);
                try {
                    $sub = \Stripe\Subscription::retrieve($subscriptionId);
                } catch (\Stripe\Exception\ApiErrorException $e) {
                    $log->error('Stripe API error retrieving subscription for invoice.payment_succeeded', ['subscription_id' => $subscriptionId, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                    http_response_code(500); exit();
                }

                $stmt = $pdo->prepare("
                    UPDATE `ngn_2025`.`user_subscriptions` 
                    SET status = 'active', updated_at = NOW(), current_period_start = FROM_UNIXTIME(?), current_period_end = FROM_UNIXTIME(?)
                    WHERE stripe_subscription_id = ?
                ");
                $stmt->execute([$sub->current_period_start, $sub->current_period_end, $subscriptionId]);

                $log->info('Subscription renewed', ['stripe_subscription_id' => $subscriptionId]);

            } catch (\Throwable $e) {
                $log->error('Error handling invoice.payment_succeeded', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                http_response_code(500);
                exit();
            }
        }
        break;
        
    case 'customer.subscription.updated':
        $subscription = $event->data->object;
        $subscriptionId = $subscription->id;
        $priceId = $subscription->items->data[0]->price->id;

        try {
            $pdo = ConnectionFactory::write($config);

            // Find tier by stripe price id
            $stmt = $pdo->prepare("SELECT id FROM `ngn_2025`.`subscription_tiers` WHERE stripe_price_id_monthly = ? OR stripe_price_id_annual = ?");
            $stmt->execute([$priceId, $priceId]);
            $tier = $stmt->fetch();

            if ($tier) {
                $stmt = $pdo->prepare("
                    UPDATE `ngn_2025`.`user_subscriptions` 
                    SET tier_id = ?, status = ?, updated_at = NOW(), current_period_start = FROM_UNIXTIME(?), current_period_end = FROM_UNIXTIME(?)
                    WHERE stripe_subscription_id = ?
                ");
                $stmt->execute([$tier['id'], $subscription->status, $subscription->current_period_start, $subscription->current_period_end, $subscriptionId]);
                $log->info('Subscription updated', ['stripe_subscription_id' => $subscriptionId, 'new_tier_id' => $tier['id']]);
            } else {
                $log->warning('Tier not found for price id', ['price_id' => $priceId]);
            }

        } catch (\Throwable $e) {
            $log->error('Error handling customer.subscription.updated', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            http_response_code(500);
            exit();
        }
        break;

    case 'customer.subscription.deleted':
        $subscription = $event->data->object;
        $subscriptionId = $subscription->id;

        try {
            $pdo = ConnectionFactory::write($config);
            $stmt = $pdo->prepare("
                UPDATE `ngn_2025`.`user_subscriptions` 
                SET status = 'canceled', canceled_at = NOW(), updated_at = NOW()
                WHERE stripe_subscription_id = ?
            ");
            $stmt->execute([$subscriptionId]);
            $log->info('Subscription canceled', ['stripe_subscription_id' => $subscriptionId]);

        } catch (\Throwable $e) {
            $log->error('Error handling customer.subscription.deleted', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            http_response_code(500);
            exit();
        }
        break;
        
    default:
        $log->info('Unhandled event type', ['type' => $event->type]);
}

http_response_code(200);