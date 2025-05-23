<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/bancard.php';

// Test data
$test_order_id = time(); // Use timestamp as test order ID
$test_amount = 15000; // Same as PHOTO_PRICE

echo "=== Testing Bancard Payment Integration ===\n\n";

echo "1. Testing environment variables...\n";
$required_vars = [
    'BANCARD_PUBLIC_KEY',
    'BANCARD_PRIVATE_KEY',
    'BANCARD_ENVIRONMENT',
    'BANCARD_API_URL_PRODUCTION',
    'BANCARD_API_URL_STAGING',
    'BANCARD_RETURN_URL',
    'BANCARD_CANCEL_URL'
];

$missing_vars = [];
foreach ($required_vars as $var) {
    if (empty(getenv($var))) {
        $missing_vars[] = $var;
    }
}

if (!empty($missing_vars)) {
    die("Error: Missing required environment variables:\n- " . implode("\n- ", $missing_vars) . "\n");
}

echo "Environment variables OK\n\n";

echo "2. Testing token generation...\n";
try {
    $token = generate_bancard_token($test_order_id, $test_amount);
    echo "Token generated successfully: " . substr($token, 0, 8) . "...\n\n";
} catch (Exception $e) {
    die("Error generating token: " . $e->getMessage() . "\n");
}

echo "3. Testing payment initialization...\n";
try {
    $payment_response = init_bancard_payment($test_order_id, $test_amount);
    echo "Payment initialized successfully!\n";
    echo "Process ID: " . $payment_response['process_id'] . "\n";
    echo "Payment URL: " . $payment_response['process_url'] . "\n\n";
} catch (Exception $e) {
    die("Error initializing payment: " . $e->getMessage() . "\n");
}

echo "4. Testing payment verification...\n";
try {
    $verification = verify_bancard_payment($test_order_id);
    echo "Payment verification response:\n";
    print_r($verification);
    echo "\n";
} catch (Exception $e) {
    echo "Payment verification error (expected for new payment): " . $e->getMessage() . "\n";
}

echo "\nTest complete. To test a full payment flow:\n";
echo "1. Copy the Payment URL above\n";
echo "2. Open it in your browser\n";
echo "3. Use Bancard test card: 4111 1111 1111 1111\n";
echo "4. Any future expiry date and CVV\n";
?>
