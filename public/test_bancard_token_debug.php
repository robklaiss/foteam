<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load required files
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/bancard_config.php';
require_once __DIR__ . '/../includes/bancard.php';

// Test token generation with debug output
$shop_process_id = 'TEST' . time();
$amount = 10000; // 10,000 PYG

// Get environment variables
$private_key = getenv('BANCARD_PRIVATE_KEY');
$public_key = getenv('BANCARD_PUBLIC_KEY');
$environment = getenv('BANCARD_ENVIRONMENT');

// Debug output
echo "<h2>Bancard Token Debug</h2>";
echo "<pre>";

echo "Environment: " . ($environment ?: 'Not set') . "\n";
echo "Public Key: " . ($public_key ? 'Set' : 'Not Set') . "\n";
echo "Private Key: " . ($private_key ? 'Set' : 'Not Set') . "\n\n";

// Format amount with exactly two decimal places and ensure it's a string
$amount_formatted = number_format((float)$amount, 2, '.', '');

// Manual token generation for comparison
$token_raw_manual = $private_key . $shop_process_id . $amount_formatted . 'PYG';
$token_manual = md5($token_raw_manual);

// Function token generation
$token_function = generate_bancard_token($shop_process_id, $amount);

// Output comparison
echo "=== Manual Token Generation ===\n";
echo "Private Key: $private_key\n";
echo "Shop Process ID: $shop_process_id\n";
echo "Amount: $amount_formatted\n";
echo "Currency: PYG\n";
echo "Raw String: $token_raw_manual\n";
echo "MD5 Hash: $token_manual\n\n";

echo "=== Function Token Generation ===\n";
echo "Token from function: $token_function\n\n";

// Check if they match
if ($token_manual === $token_function) {
    echo "✅ Tokens match!\n";
} else {
    echo "❌ Tokens do not match!\n";
}

echo "</pre>";
?>
