<?php
// Start output buffering
ob_start();

// Important: Don't start the session here
// Load config first which handles session properly
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Debug information
error_log('=== RESET_CART_TEST.PHP DEBUG ===');
error_log('Session ID: ' . session_id());

// Connect to database
$db = db_connect();

// Get a valid image from the database
$result = $db->query('SELECT * FROM images LIMIT 1');
$image = $result->fetchArray(SQLITE3_ASSOC);

// Clear the cart
$_SESSION['cart'] = [];

if ($image) {
    $valid_image_id = $image['id'];
    $price = PHOTO_PRICE; // Use the constant from config
    
    // Add item to cart with all required fields
    $_SESSION['cart'][] = [
        'id' => (int)$valid_image_id, // Make sure ID is an integer
        'name' => 'Test Image #' . $valid_image_id,
        'price' => (int)$price, // Make sure price is an integer
        'qty' => 1,
        'image' => $image['thumbnail_path'] ?? 'default.jpg'
    ];
    
    error_log('Added image #' . $valid_image_id . ' to cart');
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

// Show success message
set_flash_message('Test item added to cart successfully', 'success');

// Debug cart contents
error_log('Cart now has ' . count($_SESSION['cart']) . ' items');
error_log('Cart contents: ' . print_r($_SESSION['cart'], true));

// Redirect to cart verification page
header('Location: verify_cart.php');
exit;
?>
