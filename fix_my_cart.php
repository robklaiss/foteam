<?php
// Start session and include config
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/env_loader.php';

// Debug information
error_log('=== FIX_MY_CART.PHP DEBUG ===');
error_log('Session ID: ' . session_id());
error_log('Session before reset: ' . print_r($_SESSION, true));

// Make sure cart is an array
$_SESSION['cart'] = [];

// Connect to database
$db = db_connect();

// Get a valid image from the database
$result = $db->query('SELECT * FROM images LIMIT 1');
$image = $result->fetchArray(SQLITE3_ASSOC);

if ($image) {
    $valid_image_id = $image['id'];
    $price = PHOTO_PRICE; // Use the constant from config
    
    // Add item to cart with all required fields
    $_SESSION['cart'][] = [
        'id' => (int)$valid_image_id, // Make sure ID is an integer
        'name' => 'Test Image #' . $valid_image_id,
        'price' => (int)$price, // Make sure price is an integer (Guaraníes)
        'qty' => 1,
        'image' => $image['thumbnail_path'] ?? 'default.jpg'
    ];
    
    // Debug the created cart
    error_log('Created cart item: ' . print_r($_SESSION['cart'][0], true));
} else {
    // If no images found, create a fallback test item
    error_log('No images found in database. Creating fallback test item.');
    $_SESSION['cart'][] = [
        'id' => 1, 
        'name' => 'Test Image (Fallback)',
        'price' => 10000,
        'qty' => 1,
        'image' => 'default.jpg'
    ];
}

// Close DB
$db->close();

// Force session write - don't restart it immediately to avoid header issues
session_write_close();

// Verify session was saved by reading session file directly
error_log('Session after fixing cart: Cart created with ' . count($_SESSION['cart']) . ' items');

// Show success message
set_flash_message('Test item added to cart successfully', 'success');

// Redirect to verify_cart.php to check if it worked
header('Location: verify_cart.php');
exit;
?>
