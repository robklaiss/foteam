<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

// Include config to start session
require_once 'includes/config.php';

// Debug output
header('Content-Type: text/plain');
echo "=== Cart Test ===\n\n";

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    echo "Starting session...\n";
    session_start();
}

echo "Session ID: " . session_id() . "\n";
echo "Session status: " . session_status() . " (2 = PHP_SESSION_ACTIVE)\n";

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    echo "Initializing empty cart...\n";
    $_SESSION['cart'] = [];
}

// Add test item to cart
$test_item = [
    'id' => 'test_' . time(),
    'name' => 'Test Item',
    'price' => 9.99
];

$_SESSION['cart'][] = $test_item;
echo "\nAdded test item to cart:\n";
print_r($test_item);

// Save session
session_write_close();

// Start new request
session_start();

// Check if cart is preserved
echo "\n\n=== After session restart ===\n";
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    echo "Cart contains " . count($_SESSION['cart']) . " items\n";
    echo "Cart contents:\n";
    print_r($_SESSION['cart']);
    
    // Clean up test item
    foreach ($_SESSION['cart'] as $key => $item) {
        if (isset($item['id']) && strpos($item['id'], 'test_') === 0) {
            unset($_SESSION['cart'][$key]);
        }
    }
    $_SESSION['cart'] = array_values($_SESSION['cart']); // Reindex
} else {
    echo "Cart is empty or not set in session\n";
}

// Debug session info
echo "\n=== Session Info ===\n";
echo "Session save path: " . ini_get('session.save_path') . "\n";
echo "Session cookie parameters:\n";
print_r(session_get_cookie_params());

// Output any errors
$errors = ob_get_clean();
echo $errors;
