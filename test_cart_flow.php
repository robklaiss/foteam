<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

// Include config to start session
require_once 'includes/config.php';

// Handle adding items to cart
if (isset($_GET['add_to_cart'])) {
    $item_id = (int)$_GET['add_to_cart'];
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Add item to cart if not already there
    $item_exists = false;
    foreach ($_SESSION['cart'] as $item) {
        if ($item['id'] === $item_id) {
            $item_exists = true;
            break;
        }
    }
    
    if (!$item_exists) {
        $_SESSION['cart'][] = [
            'id' => $item_id,
            'name' => 'Test Item ' . $item_id,
            'price' => 10.99 * $item_id
        ];
    }
    
    // Build redirect URL without the add_to_cart parameter
    $url_parts = parse_url($_SERVER['REQUEST_URI']);
    $query = [];
    if (isset($url_parts['query'])) {
        parse_str($url_parts['query'], $query);
        unset($query['add_to_cart']);
    }
    $new_query = !empty($query) ? '?' . http_build_query($query) : '';
    $redirect_url = $url_parts['path'] . $new_query;
    
    // Redirect to avoid form resubmission
    header('Location: ' . $redirect_url);
    exit;
}

// Handle clearing the cart
if (isset($_GET['clear_cart'])) {
    $_SESSION['cart'] = [];
    
    // Build redirect URL without the clear_cart parameter
    $url_parts = parse_url($_SERVER['REQUEST_URI']);
    $query = [];
    if (isset($url_parts['query'])) {
        parse_str($url_parts['query'], $query);
        unset($query['clear_cart']);
    }
    $new_query = !empty($query) ? '?' . http_build_query($query) : '';
    $redirect_url = $url_parts['path'] . $new_query;
    
    header('Location: ' . $redirect_url);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Cart Flow</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .debug { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .cart { border: 1px solid #ddd; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .links { margin: 20px 0; }
        .links a { display: inline-block; margin-right: 10px; padding: 5px 10px; background: #007bff; color: white; text-decoration: none; border-radius: 3px; }
        .links a:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test Cart Flow</h1>
        
        <div class="links">
            <a href="test_cart_flow.php">This Page</a>
            <a href="cart.php" target="_blank">Cart Page</a>
            <a href="checkout.php" target="_blank">Checkout Page</a>
            <a href="test_cart_flow.php?clear_cart=1">Clear Cart</a>
        </div>
        
        <div class="cart">
            <h2>Your Cart</h2>
            <?php if (!empty($_SESSION['cart'])): ?>
                <ul>
                    <?php foreach ($_SESSION['cart'] as $item): ?>
                        <li>
                            <?php echo htmlspecialchars($item['name']); ?> - 
                            $<?php echo number_format($item['price'], 2); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p>Total items: <?php echo count($_SESSION['cart']); ?></p>
                <p>Total price: $<?php 
                    $total = array_sum(array_column($_SESSION['cart'], 'price'));
                    echo number_format($total, 2);
                ?></p>
            <?php else: ?>
                <p>Your cart is empty.</p>
            <?php endif; ?>
            
            <div style="margin-top: 20px;">
                <h3>Add Test Items:</h3>
                <a href="?add_to_cart=1" style="display: inline-block; margin: 5px; padding: 5px 10px; background: #28a745; color: white; text-decoration: none; border-radius: 3px;">Add Item 1</a>
                <a href="?add_to_cart=2" style="display: inline-block; margin: 5px; padding: 5px 10px; background: #28a745; color: white; text-decoration: none; border-radius: 3px;">Add Item 2</a>
                <a href="?add_to_cart=3" style="display: inline-block; margin: 5px; padding: 5px 10px; background: #28a745; color: white; text-decoration: none; border-radius: 3px;">Add Item 3</a>
            </div>
        </div>
        
        <div class="debug">
            <h3>Debug Information</h3>
            <p><strong>Session ID:</strong> <?php echo session_id(); ?></p>
            <p><strong>Session Status:</strong> <?php echo session_status(); ?> (2 = PHP_SESSION_ACTIVE)</p>
            
            <h4>Session Data:</h4>
            <pre><?php print_r($_SESSION); ?></pre>
            
            <h4>Cookies:</h4>
            <pre><?php print_r($_COOKIE); ?></pre>
            
            <h4>Session Configuration:</h4>
            <pre>session.save_path: <?php echo ini_get('session.save_path'); ?>
session.name: <?php echo ini_get('session.name'); ?>
session.cookie_domain: <?php echo ini_get('session.cookie_domain'); ?>
session.cookie_path: <?php echo ini_get('session.cookie_path'); ?>
session.cookie_secure: <?php echo ini_get('session.cookie_secure') ? 'true' : 'false'; ?>
session.cookie_httponly: <?php echo ini_get('session.cookie_httponly') ? 'true' : 'false'; ?>
session.use_only_cookies: <?php echo ini_get('session.use_only_cookies') ? 'true' : 'false'; ?>
session.use_strict_mode: <?php echo ini_get('session.use_strict_mode') ? 'true' : 'false'; ?></pre>
        </div>
    </div>
</body>
</html>
