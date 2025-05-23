<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load required files
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/bancard_config.php';

// Test environment variables
echo "<h2>Bancard Configuration Test</h2>";
echo "<pre>";

echo "Environment: " . getenv('BANCARD_ENVIRONMENT') . "\n";
echo "Public Key: " . (getenv('BANCARD_PUBLIC_KEY') ? 'Set' : 'Not Set') . "\n";
echo "Private Key: " . (getenv('BANCARD_PRIVATE_KEY') ? 'Set' : 'Not Set') . "\n";

$api_url = getenv('BANCARD_ENVIRONMENT') === 'production' 
    ? getenv('BANCARD_API_URL_PRODUCTION') 
    : getenv('BANCARD_API_URL_STAGING');

echo "API URL: " . $api_url . "\n";

// Test token generation
function test_token_generation() {
    $private_key = getenv('BANCARD_PRIVATE_KEY');
    $shop_process_id = 'TEST' . time();
    $amount = 10000; // 10,000 PYG
    $currency = 'PYG';
    
    $token_raw = $private_key . $shop_process_id . $amount . $currency;
    $token = md5($token_raw);
    
    return [
        'shop_process_id' => $shop_process_id,
        'amount' => $amount,
        'token' => $token,
        'token_raw' => $token_raw
    ];
}

$token_test = test_token_generation();
echo "\nToken Generation Test:\n";
echo "Shop Process ID: " . $token_test['shop_process_id'] . "\n";
echo "Amount: " . $token_test['amount'] . "\n";
echo "Token: " . $token_test['token'] . "\n";

// Test API connection
function test_api_connection() {
    $public_key = getenv('BANCARD_PUBLIC_KEY');
    $private_key = getenv('BANCARD_PRIVATE_KEY');
    $shop_process_id = 'TEST' . time();
    $amount = 10000; // 10,000 PYG
    
    // Generate token
    $token_raw = $private_key . $shop_process_id . $amount . 'PYG';
    $token = md5($token_raw);
    
    $api_url = getenv('BANCARD_ENVIRONMENT') === 'production' 
        ? getenv('BANCARD_API_URL_PRODUCTION') 
        : getenv('BANCARD_API_URL_STAGING');
    
    $data = [
        'public_key' => $public_key,
        'operation' => [
            'token' => $token,
            'shop_process_id' => $shop_process_id,
            'currency' => 'PYG',
            'amount' => $amount,
            'description' => 'Test Connection',
            'return_url' => getenv('BANCARD_RETURN_URL'),
            'cancel_url' => getenv('BANCARD_CANCEL_URL')
        ]
    ];
    
    $ch = curl_init($api_url . '/vpos/api/0.3/single_buy');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing only
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // For testing only
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'http_code' => $http_code,
        'response' => $response,
        'error' => $error,
        'request_data' => $data
    ];
}

// Only run the API test if explicitly requested
if (isset($_GET['test_api'])) {
    echo "\nTesting API Connection...\n";
    $api_test = test_api_connection();
    
    echo "HTTP Status: " . $api_test['http_code'] . "\n";
    echo "Response: " . $api_test['response'] . "\n";
    if ($api_test['error']) {
        echo "Error: " . $api_test['error'] . "\n";
    }
    
    echo "\nRequest Data:\n";
    print_r($api_test['request_data']);
}

echo "</pre>";

echo "<p><a href='?test_api=1' class='btn btn-primary'>Test API Connection</a></p>";
?>
