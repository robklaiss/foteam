<?php
// Load configuration
require_once 'includes/bootstrap.php';
require_once 'includes/config.php';
require_once 'includes/app_config.php';

// Test data from the failed request
$private_key = 'qiC()IV5GIM6M,KnolQ,J2mXTea+z$E)XcenzBz';
$shop_process_id = '12';
$amount = 55000.00;
$currency = 'PYG';

// Format amount with exactly two decimal places
$amount_formatted = number_format($amount, 2, '.', '');

// Generate token the same way as in the original function
$token_raw = $private_key . $shop_process_id . $amount_formatted . $currency;
$generated_token = md5($token_raw);

// Expected token from the error message
$expected_token = '2b984316bdaeb384056423d842a1de75';

// Output the results
echo "<h2>Bancard Token Generation Test</h2>";
echo "<pre>";
echo "Private Key: " . $private_key . "\n";
echo "Shop Process ID: " . $shop_process_id . "\n";
echo "Amount: " . $amount_formatted . "\n";
echo "Currency: " . $currency . "\n";
echo "Raw String: " . $token_raw . "\n";
echo "Generated Token: " . $generated_token . "\n";
echo "Expected Token: " . $expected_token . "\n";
echo "Tokens Match: " . ($generated_token === $expected_token ? 'YES' : 'NO') . "\n";

// Try with different formats
$test_cases = [
    'with_commas' => number_format($amount, 2, ',', '.'), // 55.000,00
    'no_decimals' => number_format($amount, 0, '', ''),   // 55000
    'us_format' => number_format($amount, 2, '.', ',')    // 55,000.00
];

echo "\nTesting different number formats:\n";
foreach ($test_cases as $name => $formatted_amount) {
    $test_token_raw = $private_key . $shop_process_id . $formatted_amount . $currency;
    $test_token = md5($test_token_raw);
    echo "\nFormat: $name\n";
    echo "Amount: $formatted_amount\n";
    echo "Token: $test_token\n";
    echo "Matches expected: " . ($test_token === $expected_token ? 'YES' : 'NO') . "\n";
}
?>
