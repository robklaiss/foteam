<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

// Include config
require_once 'includes/config.php';

// Simple test for session and cart
if (!isset($_SESSION['test_counter'])) {
    $_SESSION['test_counter'] = 0;
}

// Handle adding to cart
if (isset($_GET['add'])) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $_SESSION['cart'][] = [
        'id' => time(),
        'name' => 'Test Item ' . (count($_SESSION['cart']) + 1),
        'price' => 10.99
    ];
    
    // Clear the add parameter to prevent resubmission
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Handle clearing the cart
if (isset($_GET['clear'])) {
    $_SESSION['cart'] = [];
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .debug { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .cart { border: 1px solid #ddd; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .btn { display: inline-block; padding: 5px 10px; background: #007bff; color: white; text-decoration: none; border-radius: 3px; margin: 5px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Simple Session & Cart Test</h1>
        
        <div class="cart">
            <h2>Your Cart</h2>
            <?php if (!empty($_SESSION['cart'])): ?>
                <ul>
                    <?php foreach ($_SESSION['cart'] as $item): ?>
                        <li><?php echo htmlspecialchars($item['name']); ?> - $<?php echo number_format($item['price'], 2); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p>Total items: <?php echo count($_SESSION['cart']); ?></p>
                <a href="?clear=1" class="btn">Clear Cart</a>
            <?php else: ?>
                <p>Your cart is empty.</p>
            <?php endif; ?>
            
            <div style="margin-top: 20px;">
                <a href="?add=1" class="btn">Add Test Item</a>
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
