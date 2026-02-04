<?php

$root = '../';
require $root.'definitions/site-settings.php';
require $root.'controllers/ResponseController.php';
$data = json_decode(file_get_contents('php://input'), true);

$response = makeResponse();

!isset($data['lineItems']) ? killWithMessage('No line items', $response) : $lineItems = $data['lineItems'];
!isset($data['success_url']) ? killWithMessage('No success url', $response) : $successUrl = $data['success_url'];
!isset($data['cancel_url']) ? killWithMessage('No cancel url', $response) : $cancelUrl = $data['cancel_url'];

$stripe = new Stripe();
$session = $stripe->createCheckoutSession($lineItems, $successUrl, $cancelUrl);

header('Content-Type: application/json');

echo json_encode(['session'=>$session]);