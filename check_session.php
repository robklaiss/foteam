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
echo "=== Session Debug ===\n\n";

// Check if session is active
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "Session is active\n";
    echo "Session ID: " . session_id() . "\n\n";
    
    // Dump session data
    echo "Session data:\n";
    print_r($_SESSION);
    
    // Check cart in session
    if (isset($_SESSION['cart'])) {
        echo "\nCart has " . count($_SESSION['cart']) . " items\n";
        echo "Cart contents:\n";
        print_r($_SESSION['cart']);
    } else {
        echo "\nNo cart found in session\n";
    }
    
    // Check session cookie
    $cookies = $_COOKIE;
    echo "\nCookies:\n";
    print_r($cookies);
    
    // Check session save path
    $save_path = session_save_path();
    echo "\nSession save path: " . ($save_path ?: 'default') . "\n";
    
    // Check if save path is writable
    if ($save_path && is_dir($save_path)) {
        echo "Save path is " . (is_writable($save_path) ? 'writable' : 'NOT writable') . "\n";
    }
    
} else {
    echo "Session is not active. Status: " . session_status() . "\n";
    echo "Headers sent: " . (headers_sent() ? 'Yes' : 'No') . "\n";
    
    // Try to start session
    if (session_start()) {
        echo "Session started successfully. ID: " . session_id() . "\n";
    } else {
        echo "Failed to start session\n";
    }
}

// Output any errors
$errors = ob_get_clean();
echo $errors;
