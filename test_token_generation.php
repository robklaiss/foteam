<?php
// Load required files
require_once 'includes/bootstrap.php';
require_once 'includes/config.php';
require_once 'includes/app_config.php';

// Test data from the request
$private_key = 'qiC()IV5GIM6M,KnolQ,J2mXTea+z$E)XcenzBz';
$shop_process_id = '12';
$amount = 55000.00;
$currency = 'PYG';

// Format amount with exactly two decimal places
$amount_formatted = number_format($amount, 2, '.', '');

// Generate token
$token_raw = $private_key . $shop_process_id . $amount_formatted . $currency;
$token = md5($token_raw);

// Output results
echo "<h2>Token Generation Test</h2>";
echo "<pre>";
echo "Private Key: " . $private_key . "\n";
echo "Shop Process ID: " . $shop_process_id . "\n";
echo "Amount: " . $amount_formatted . "\n";
echo "Currency: " . $currency . "\n";
echo "Raw String: " . $token_raw . "\n";
echo "Generated Token: " . $token . "\n";

echo "\nExpected Token: 2b984316bdaeb384056423d842a1de75\n";
echo "Tokens Match: " . ($token === '2b984316bdaeb384056423d842a1de75' ? 'YES' : 'NO') . "\n";
?>
