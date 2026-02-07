<?php
// admin/testing/lib/stripe_test.php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../lib/bootstrap.php';

$response = ['success' => false, 'message' => 'An unexpected error occurred.'];

try {
    // Stripe API key is now set globally in lib/bootstrap.php based on APP_ENV
    // Ensure the Stripe library is loaded by Composer
    if (!class_exists('Stripe\Stripe')) {
        throw new Exception("Stripe library not loaded. Check Composer dependencies.");
    }


    // Attempt a simple API call, e.g., list customers
    $customers = \Stripe\Customer::all(['limit' => 1]);

    $products = \Stripe\Product::all(['limit' => 1]);

    if ($customers && $products) {
        $response['success'] = true;
        $response['message'] = 'Successfully connected to Stripe API, retrieved customer data, and product data.';
    } elseif ($customers) {
        $response['success'] = false;
        $response['message'] = 'Successfully connected to Stripe API and retrieved customer data, but failed to retrieve product data.';
    } elseif ($products) {
        $response['success'] = false;
        $response['message'] = 'Successfully connected to Stripe API and retrieved product data, but failed to retrieve customer data.';
    } else {
        $response['message'] = 'Could not retrieve customer or product data from Stripe. Check API key and permissions.';
    }

} catch (\Stripe\Exception\ApiErrorException $e) {
    $response['message'] = 'Stripe API Error: ' . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>