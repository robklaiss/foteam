<?php
/**
 * Final Session Handling Test
 * This file tests our global session manager solution
 */

// Suppress output buffering
ob_start();

// Clear any previous session warnings
echo "<style>body { font-family: Arial, sans-serif; line-height: 1.6; max-width: 800px; margin: 0 auto; padding: 20px; }</style>";
echo "<h1>FoTeam Session Fix Test</h1>";

// Include bootstrap first to configure session settings
require_once __DIR__ . '/includes/bootstrap.php';

// Then include config
require_once __DIR__ . '/includes/config.php';

// Then include global session manager
require_once __DIR__ . '/includes/global_session.php';

// Finally include functions
require_once __DIR__ . '/includes/functions.php';

// Display session info
echo "<h2>Session Information</h2>";
echo "<p><strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'NOT ACTIVE') . "</p>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";

// Database test
echo "<h2>Database Connection Test</h2>";
try {
    $db = db_connect();
    echo "<p style='color: green;'>✅ Database connection successful</p>";
    
    // Test a simple query
    $result = $db->query("SELECT COUNT(*) as count FROM sqlite_master");
    $row = $result->fetchArray();
    echo "<p>Database has " . $row['count'] . " tables.</p>";
    
    $db->close();
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

// Cart test
echo "<h2>Cart Test</h2>";
if (isset($_SESSION['cart'])) {
    if (count($_SESSION['cart']) > 0) {
        echo "<p>Cart has " . count($_SESSION['cart']) . " items</p>";
        echo "<pre>" . print_r($_SESSION['cart'], true) . "</pre>";
    } else {
        echo "<p>Cart is empty</p>";
        
        // Option to add a test item
        echo "<p><a href='?add_test_item=1' class='btn btn-primary'>Add Test Item to Cart</a></p>";
    }
} else {
    echo "<p style='color: red;'>Cart is not initialized in session</p>";
}

// Add a test item to cart if requested
if (isset($_GET['add_test_item'])) {
    $_SESSION['cart'][] = [
        'id' => 1,
        'name' => 'Valid Test Image',
        'price' => 10000,
        'qty' => 1
    ];
    
    echo "<p style='color: green;'>Test item added to cart. <a href='final_test.php'>Refresh</a> to see it.</p>";
}

// Navigation
echo "<h2>Navigation</h2>";
echo "<p><a href='index.php'>Home</a> | <a href='cart.php'>Cart</a> | <a href='verify_cart.php'>Verify Cart</a></p>";

// End output buffering
$output = ob_get_clean();
echo $output;
?>
