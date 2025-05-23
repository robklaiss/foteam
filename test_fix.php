<?php
/**
 * Simple test script to verify our session warning fixes
 */

// Clear output buffer
ob_start();

echo "<h1>Session Warning Fix Test</h1>";

// Include bootstrap properly
require_once __DIR__ . '/includes/bootstrap.php';

// Then include other files in the correct order
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Check session status
echo "<p>Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'NOT ACTIVE') . "</p>";
echo "<p>Session ID: " . session_id() . "</p>";

// Show cart contents if available
if (isset($_SESSION['cart'])) {
    echo "<h2>Cart Contents</h2>";
    echo "<pre>" . print_r($_SESSION['cart'], true) . "</pre>";
    
    // Check types of important fields
    if (!empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $index => $item) {
            echo "<p>Item #$index:</p>";
            echo "<ul>";
            if (isset($item['id'])) {
                echo "<li>id: " . $item['id'] . " (" . gettype($item['id']) . ")</li>";
            }
            if (isset($item['price'])) {
                echo "<li>price: " . $item['price'] . " (" . gettype($item['price']) . ")</li>";
            }
            if (isset($item['name'])) {
                echo "<li>name: " . $item['name'] . " (" . gettype($item['name']) . ")</li>";
            }
            echo "</ul>";
        }
    }
}

// Add a link to cart.php
echo "<p><a href='cart.php' class='btn btn-primary'>Go to Cart</a></p>";

// Output buffer
$output = ob_get_clean();
echo $output;
?>
