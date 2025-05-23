<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

// Include config to start session
require_once 'includes/config.php';

// Handle form submission to add items to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    $item_id = 'item_' . time();
    $_SESSION['cart'][] = [
        'id' => $item_id,
        'name' => 'Test Item ' . (count($_SESSION['cart']) + 1),
        'price' => 10.99
    ];
    
    // Redirect to avoid form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle clearing the cart
if (isset($_GET['clear_cart'])) {
    $_SESSION['cart'] = [];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Persistence Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .debug { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .cart { border: 1px solid #ddd; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Session Persistence Test</h1>
        
        <div class="cart">
            <h2>Your Cart</h2>
            <?php if (!empty($_SESSION['cart'])): ?>
                <ul>
                    <?php foreach ($_SESSION['cart'] as $item): ?>
                        <li><?php echo htmlspecialchars($item['name']); ?> - $<?php echo number_format($item['price'], 2); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p>Total items: <?php echo count($_SESSION['cart']); ?></p>
                <p><a href="?clear_cart=1">Clear Cart</a> | <a href="checkout.php" target="_blank">Go to Checkout</a></p>
            <?php else: ?>
                <p>Your cart is empty.</p>
            <?php endif; ?>
            
            <form method="post" style="margin-top: 20px;">
                <button type="submit" name="add_item">Add Test Item to Cart</button>
            </form>
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
session.cookie_domain: <?php echo ini_get('session.cookie_domain'); ?>
session.cookie_path: <?php echo ini_get('session.cookie_path'); ?>
session.cookie_secure: <?php echo ini_get('session.cookie_secure') ? 'true' : 'false'; ?>
session.cookie_httponly: <?php echo ini_get('session.cookie_httponly') ? 'true' : 'false'; ?>
session.use_only_cookies: <?php echo ini_get('session.use_only_cookies') ? 'true' : 'false'; ?></pre>
        </div>
        
        <div style="margin-top: 20px;">
            <h3>Test Links</h3>
            <ul>
                <li><a href="cart.php" target="_blank">View Cart</a></li>
                <li><a href="checkout.php" target="_blank">Go to Checkout</a></li>
            </ul>
        </div>
    </div>
</body>
</html>
