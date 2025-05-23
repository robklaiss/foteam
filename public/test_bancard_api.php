<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load required files
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/bancard_config.php';
require_once __DIR__ . '/../includes/bancard.php';

// Test data
$microtime = microtime(true);
$shop_process_id = 'TEST' . str_replace([' ', '.'], '', $microtime);
$amount = 10000; // 10,000 PYG
$public_key = getenv('BANCARD_PUBLIC_KEY');
$private_key = getenv('BANCARD_PRIVATE_KEY');
$environment = getenv('BANCARD_ENVIRONMENT');
$api_url = $environment === 'production' 
    ? getenv('BANCARD_API_URL_PRODUCTION') 
    : getenv('BANCARD_API_URL_STAGING');

// Generate token
$token = generate_bancard_token($shop_process_id, $amount);

// Prepare request data
$data = [
    'public_key' => $public_key,
    'operation' => [
        'token' => $token,
        'shop_process_id' => $shop_process_id,
        'currency' => 'PYG',
        'amount' => number_format((float)$amount, 2, '.', ''),
        'additional_data' => '',
        'description' => 'Test Payment',
        'return_url' => 'http://localhost:8000/payment_success.php',
        'cancel_url' => 'http://localhost:8000/payment_cancel.php'
    ]
];

// Debug output
echo "<h2>Bancard API Test</h2>";
echo "<pre>";
echo "Environment: " . $environment . "\n";
echo "API URL: " . $api_url . "/vpos/api/0.3/single_buy\n";
echo "Public Key: " . $public_key . "\n";
echo "Shop Process ID: " . $shop_process_id . "\n";
echo "Amount: " . number_format((float)$amount, 2, '.', '') . ' ' . 'PYG' . "\n";
echo "Token: " . $token . "\n\n";

echo "Sending request to Bancard...\n";

// Initialize cURL
$ch = curl_init($api_url . '/vpos/api/0.3/single_buy');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Requested-With: XMLHttpRequest'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing only
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // For testing only

// Execute the request
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Output the result
echo "HTTP Status: " . $http_code . "\n";
if ($error) {
    echo "cURL Error: " . $error . "\n";
}
echo "Response: " . $response . "\n";

// Decode and pretty print JSON response
$json_response = json_decode($response, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "\nDecoded Response:\n";
    print_r($json_response);
}

echo "</pre>";
?>
