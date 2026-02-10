<?php
/**
 * Stripe Webhook Testing Interface
 *
 * This provides a way to test webhook signatures and manually trigger webhook events
 * for local development.
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use Stripe\Webhook;

// Verify admin access
session_start();
if (empty($_SESSION['user_id']) || empty($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(401);
    die('Unauthorized. Admin access required.');
}

$config = new Config();
$webhook_secret = $_ENV['STRIPE_WEBHOOK_SECRET'];
$webhook_url = $_ENV['APP_URL'] . '/webhooks/stripe.php';

$result = null;
$error = null;
$test_payload = null;

// Sample webhook payloads for testing
$sample_payloads = [
    'checkout.session.completed' => [
        'id' => 'evt_test_checkout_session_completed',
        'object' => 'event',
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_' . uniqid(),
                'object' => 'checkout.session',
                'payment_status' => 'paid',
                'subscription' => 'sub_test_' . uniqid(),
                'metadata' => [
                    'user_id' => '1',
                    'tier_id' => '6'
                ]
            ]
        ]
    ],
    'customer.subscription.updated' => [
        'id' => 'evt_test_subscription_updated',
        'object' => 'event',
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => 'sub_test_' . uniqid(),
                'status' => 'active',
                'current_period_start' => time(),
                'current_period_end' => time() + (30 * 86400),
                'items' => [
                    'object' => 'list',
                    'data' => [
                        [
                            'price' => [
                                'id' => 'price_test_monthly'
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ],
    'customer.subscription.deleted' => [
        'id' => 'evt_test_subscription_deleted',
        'object' => 'event',
        'type' => 'customer.subscription.deleted',
        'data' => [
            'object' => [
                'id' => 'sub_test_' . uniqid()
            ]
        ]
    ]
];

// Handle webhook signature test
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;

    if ($action === 'test_signature' && !empty($webhook_secret)) {
        // Test the webhook signature verification
        $payload = $_POST['payload'] ?? '{}';
        $signature = $_POST['signature'] ?? '';

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $webhook_secret
            );
            $result = ['success' => true, 'message' => 'Signature verified successfully!', 'event' => $event];
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            $error = 'Signature verification failed: ' . $e->getMessage();
        } catch (\Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } elseif ($action === 'generate_signature') {
        if (!$webhook_secret) {
            $error = 'STRIPE_WEBHOOK_SECRET not configured';
        } else {
            $payload = $_POST['payload'] ?? '{}';
            $timestamp = time();
            $signed_content = "{$timestamp}.{$payload}";
            $signature = 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $signed_content, $webhook_secret);

            $result = [
                'success' => true,
                'message' => 'Signature generated',
                'signature' => $signature,
                'payload' => $payload,
                'webhook_url' => $webhook_url
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stripe Webhook Testing - NGN Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin-top: 20px; }
        .card { margin-bottom: 20px; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto; max-height: 300px; }
        code { color: #d63384; }
        .webhook-url { background: #e7f3ff; padding: 10px; border-left: 4px solid #0066cc; margin: 15px 0; }
        .setup-step { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #ffc107; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Stripe Webhook Testing & Configuration</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($result && $result['success']): ?>
            <div class="alert alert-success">
                <strong>✓ <?= htmlspecialchars($result['message']) ?></strong>
                <?php if (!empty($result['signature'])): ?>
                    <div style="margin-top: 15px;">
                        <h6>Generated Signature:</h6>
                        <code style="word-break: break-all; color: inherit;"><?= htmlspecialchars($result['signature']) ?></code>
                        <p style="margin-top: 10px; font-size: 12px; color: #666;">
                            Use this signature header value: <code>Stripe-Signature: <?= htmlspecialchars($result['signature']) ?></code>
                        </p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($result['event'])): ?>
                    <div style="margin-top: 15px;">
                        <h6>Verified Event:</h6>
                        <pre><?= json_encode($result['event'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?></pre>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 style="margin: 0;">Webhook Configuration</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Webhook Endpoint URL:</strong></p>
                        <div class="webhook-url">
                            <code><?= htmlspecialchars($webhook_url) ?></code>
                        </div>

                        <?php if (!$webhook_secret): ?>
                            <div class="alert alert-warning">
                                ⚠️ <strong>STRIPE_WEBHOOK_SECRET not configured</strong>
                                <p style="margin-top: 10px; margin-bottom: 0;">
                                    To enable webhook verification, generate a signing secret from the <a href="https://dashboard.stripe.com/test/webhooks" target="_blank">Stripe Dashboard</a> and add it to your <code>.env</code> file:
                                </p>
                                <code style="display: block; margin-top: 10px; color: inherit;">STRIPE_WEBHOOK_SECRET=whsec_test_...</code>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">
                                ✓ Webhook secret is configured
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 style="margin: 0;">Local Webhook Testing Methods</h5>
                    </div>
                    <div class="card-body">
                        <div class="setup-step">
                            <h6>Method 1: Stripe CLI (Recommended)</h6>
                            <ol style="margin-top: 10px; margin-bottom: 0;">
                                <li>Install <a href="https://stripe.com/docs/stripe-cli" target="_blank">Stripe CLI</a></li>
                                <li>Run: <code>stripe login</code> (authenticate with your Stripe account)</li>
                                <li>Run: <code>stripe listen --forward-to <?= htmlspecialchars(str_replace('https://', '', $webhook_url)) ?></code></li>
                                <li>Copy the webhook signing secret and add to <code>.env</code></li>
                                <li>Stripe CLI will forward all events to your local webhook endpoint</li>
                            </ol>
                        </div>

                        <div class="setup-step">
                            <h6>Method 2: ngrok Tunnel</h6>
                            <ol style="margin-top: 10px; margin-bottom: 0;">
                                <li>Install <a href="https://ngrok.com/download" target="_blank">ngrok</a></li>
                                <li>Run: <code>ngrok http 8088</code> (assuming your app runs on port 8088)</li>
                                <li>You'll get a URL like: <code>https://abc123.ngrok.io</code></li>
                                <li>Add webhook endpoint in <a href="https://dashboard.stripe.com/test/webhooks" target="_blank">Stripe Dashboard</a>: <code>https://abc123.ngrok.io/webhooks/stripe.php</code></li>
                                <li>Select events: <code>checkout.session.completed</code>, <code>invoice.payment_succeeded</code>, <code>customer.subscription.updated</code>, <code>customer.subscription.deleted</code></li>
                            </ol>
                        </div>

                        <div class="setup-step">
                            <h6>Method 3: Manual Testing (Below)</h6>
                            <p style="margin-top: 10px; margin-bottom: 0;">
                                Use the test interface below to generate webhook signatures and verify signature verification logic.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 style="margin: 0;">Webhook Signature Testing</h5>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs" role="tablist" style="margin-bottom: 20px;">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#tab-generate">Generate Signature</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#tab-verify">Verify Signature</a>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <!-- Generate Signature Tab -->
                            <div id="tab-generate" class="tab-pane fade show active">
                                <form method="POST">
                                    <input type="hidden" name="action" value="generate_signature">

                                    <div class="mb-3">
                                        <label class="form-label">Sample Payload Type</label>
                                        <select class="form-select" id="payload-type" onchange="updatePayload()">
                                            <option value="">-- Select a sample --</option>
                                            <?php foreach (array_keys($sample_payloads) as $type): ?>
                                                <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Webhook Payload (JSON)</label>
                                        <textarea class="form-control" name="payload" id="payload-textarea" rows="10" placeholder='{"id":"evt_test_...","type":"...","data":{...}}'><?= htmlspecialchars(json_encode($sample_payloads['checkout.session.completed'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Generate Signature</button>
                                </form>
                            </div>

                            <!-- Verify Signature Tab -->
                            <div id="tab-verify" class="tab-pane fade">
                                <form method="POST">
                                    <input type="hidden" name="action" value="test_signature">

                                    <div class="mb-3">
                                        <label class="form-label">Webhook Payload (JSON)</label>
                                        <textarea class="form-control" name="payload" rows="8" placeholder='{"id":"evt_test_...","type":"...","data":{...}}' required></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Stripe-Signature Header</label>
                                        <input type="text" class="form-control" name="signature" placeholder="t=...,v1=..." required>
                                    </div>

                                    <button type="submit" class="btn btn-success">Verify Signature</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-light">
                        <h5 style="margin: 0;">Sample Payloads</h5>
                    </div>
                    <div class="card-body">
                        <p>Click on an event type to see its structure:</p>
                        <div class="accordion" id="payloads-accordion">
                            <?php foreach ($sample_payloads as $event_type => $payload): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#payload-<?= str_replace('.', '_', $event_type) ?>">
                                            <?= htmlspecialchars($event_type) ?>
                                        </button>
                                    </h2>
                                    <div id="payload-<?= str_replace('.', '_', $event_type) ?>" class="accordion-collapse collapse" data-bs-parent="#payloads-accordion">
                                        <div class="accordion-body">
                                            <pre><?= json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?></pre>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const samplePayloads = <?= json_encode($sample_payloads) ?>;

        function updatePayload() {
            const type = document.getElementById('payload-type').value;
            if (type && samplePayloads[type]) {
                document.getElementById('payload-textarea').value = JSON.stringify(samplePayloads[type], null, 2);
            }
        }
    </script>
</body>
</html>
