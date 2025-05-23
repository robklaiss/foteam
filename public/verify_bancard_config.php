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

// Output page header
echo "<!DOCTYPE html>";
echo "<html lang='es'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Verificación de Bancard</title>";
echo "<link href='assets/css/bootstrap.min.css' rel='stylesheet'>";
echo "</head>";
echo "<body class='bg-light'>";
echo "<div class='container py-5'>";

// Output results
echo "<h2>Bancard Configuration Verification</h2>";
echo "<p>This script checks if the Bancard environment variables are properly loaded.</p>";
echo "<hr>";

$all_good = true;
echo "<div class='card mb-4'>";
echo "<div class='card-body'>";
echo "<h5 class='mb-3'>Environment Variables</h5>";
echo "<ul class='list-group'>";
foreach ($vars as $var) {
    $value = getenv($var);
    $masked_value = $var === 'BANCARD_PUBLIC_KEY' || $var === 'BANCARD_PRIVATE_KEY' 
        ? substr($value, 0, 4) . '...' . substr($value, -4) 
        : $value;
    
    if (empty($value)) {
        echo "<li class='list-group-item list-group-item-danger d-flex justify-content-between align-items-center'>";        
        echo "<strong>$var:</strong> <span>NOT SET</span>";
        echo "</li>";
        $all_good = false;
    } else {
        $validation_class = 'list-group-item-success';
        $validation_text = '';
        
        // Validate specific URLs
        if ($var === 'BANCARD_RETURN_URL') {
            // Check if the URL has the correct structure
            if (strpos($value, '/foteam/') !== false) {
                $validation_class = 'list-group-item-warning';
                $validation_text = ' <span class="text-warning">(Contains /foteam/ which may be incorrect)</span>';
            }
            if (strpos($value, 'https://bebe.com.py/payment/success.php') === false) {
                $validation_class = 'list-group-item-warning';
                $validation_text = ' <span class="text-warning">(Should be https://bebe.com.py/payment/success.php)</span>';
            }
        }
        if ($var === 'BANCARD_CANCEL_URL') {
            // Check if the URL has the correct structure
            if (strpos($value, '/foteam/') !== false) {
                $validation_class = 'list-group-item-warning';
                $validation_text = ' <span class="text-warning">(Contains /foteam/ which may be incorrect)</span>';
            }
            if (strpos($value, 'https://bebe.com.py/payment/cancel.php') === false) {
                $validation_class = 'list-group-item-warning';
                $validation_text = ' <span class="text-warning">(Should be https://bebe.com.py/payment/cancel.php)</span>';
            }
        }
        
        echo "<li class='list-group-item {$validation_class} d-flex justify-content-between align-items-center'>";
        echo "<strong>$var:</strong> <span>$masked_value$validation_text</span>";
        echo "</li>";
    }
}
echo "</ul>";
echo "</div>";
echo "</div>";

// Session debug information
echo "<div class='card mb-4'>";
echo "<div class='card-header'>";
echo "<h5 class='mb-0'>Session Information</h5>";
echo "</div>";
echo "<div class='card-body'>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Cart Status:</strong> " . (isset($_SESSION['cart']) ? 'Present' : 'Not set') . "</p>";
if (isset($_SESSION['cart'])) {
    echo "<p><strong>Cart Items Count:</strong> " . count($_SESSION['cart']) . "</p>";
    if (!empty($_SESSION['cart'])) {
        echo "<pre>" . json_encode($_SESSION['cart'], JSON_PRETTY_PRINT) . "</pre>";
    } else {
        echo "<p class='text-warning'>Cart array exists but is empty</p>";
    }
}
echo "</div>";
echo "</div>";

// Show overall status
if ($all_good) {
    echo "<div class='alert alert-success'>";
    echo "<h4 class='alert-heading'>SUCCESS!</h4>";
    echo "<p>All Bancard environment variables are properly loaded.</p>";
    echo "<p>The Bancard payment option should now be visible on the checkout page.</p>";
    echo "</div>";
} else {
    echo "<div class='alert alert-danger'>";
    echo "<h4 class='alert-heading'>ERROR</h4>";
    echo "<p>Some Bancard environment variables are missing.</p>";
    echo "</div>";
}

// Navigation buttons
echo "<div class='mt-4'>";
echo "<a href='checkout.php' class='btn btn-primary me-2'>Go to Checkout</a>";
echo "<a href='cart.php' class='btn btn-outline-secondary me-2'>View Cart</a>";
echo "</div>";

echo "</div>";
echo "<script src='assets/js/bootstrap.bundle.min.js'></script>";
echo "</body>";
echo "</html>";
?>
