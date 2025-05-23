<?php
// Include bootstrap first to configure session settings
require_once __DIR__ . '/includes/bootstrap.php';

// Then include config
require_once __DIR__ . '/includes/config.php';

// Then include global session manager
require_once __DIR__ . '/includes/global_session.php';

// Finally include functions
require_once __DIR__ . '/includes/functions.php';

// Function to log debug info
function log_debug($message) {
    echo "<pre>$message</pre>";
    error_log($message);
}

// Check cart contents
log_debug("=== CART VERIFICATION ===");
log_debug("Session ID: " . session_id());
log_debug("Cart contents: " . print_r($_SESSION['cart'] ?? 'No cart in session', true));

// Check if cart exists and has items
if (empty($_SESSION['cart'])) {
    log_debug("ERROR: Cart is empty");
    echo "<p>Your cart is empty. <a href='fix_my_cart.php'>Click here to add a test item</a></p>";
} else {
    // Check each item in cart
    foreach ($_SESSION['cart'] as $index => $item) {
        log_debug("\nItem #$index:");
        
        // Check required fields
        $required = ['id', 'price'];
        foreach ($required as $field) {
            if (!isset($item[$field])) {
                log_debug("ERROR: Missing required field '$field'");
            } else {
                log_debug("$field: " . $item[$field] . " (" . gettype($item[$field]) . ")");
            }
        }
        
        // Check if ID is numeric
        if (isset($item['id']) && !is_numeric($item['id'])) {
            log_debug("WARNING: Item ID is not numeric: " . $item['id']);
        }
    }
    
    echo "<p>Cart verification complete. <a href='checkout.php'>Proceed to Checkout</a></p>";
}
?>

<h2>Cart Verification</h2>
<p><a href="fix_my_cart.php" class="btn btn-primary">Add Test Item to Cart</a></p>
<p><a href="cart.php" class="btn btn-secondary">View Cart</a></p>
