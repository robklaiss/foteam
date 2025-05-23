<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/bancard.php';

// Check if environment variables are properly loaded
$vars = [
    'BANCARD_PUBLIC_KEY',
    'BANCARD_PRIVATE_KEY',
    'BANCARD_ENVIRONMENT',
    'BANCARD_API_URL_PRODUCTION',
    'BANCARD_API_URL_STAGING',
    'BANCARD_RETURN_URL',
    'BANCARD_CANCEL_URL'
];

// Output results
echo "<h2>Bancard Configuration Verification</h2>";
echo "<p>This script checks if the Bancard environment variables are properly loaded.</p>";
echo "<hr>";

$all_good = true;
echo "<ul>";
foreach ($vars as $var) {
    $value = getenv($var);
    $masked_value = $var === 'BANCARD_PUBLIC_KEY' || $var === 'BANCARD_PRIVATE_KEY' 
        ? substr($value, 0, 4) . '...' . substr($value, -4) 
        : $value;
    
    if (empty($value)) {
        echo "<li style='color:red'><strong>$var:</strong> NOT SET</li>";
        $all_good = false;
    } else {
        echo "<li style='color:green'><strong>$var:</strong> $masked_value</li>";
    }
}
echo "</ul>";

if ($all_good) {
    echo "<p style='color:green; font-weight:bold;'>SUCCESS! All environment variables are properly loaded.</p>";
    echo "<p>The Bancard payment option should now be visible on the checkout page.</p>";
} else {
    echo "<p style='color:red; font-weight:bold;'>ERROR: Some environment variables are still missing.</p>";
}

echo "<p>Go to <a href='checkout.php'>Checkout Page</a> to verify the Bancard payment option is showing.</p>";
?>
