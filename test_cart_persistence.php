<?php
// Start output buffering
ob_start();

// Include config to initialize session
require_once 'includes/config.php';

// Set page title
$page_title = 'Test Cart Persistence';

// Function to log debug info
function log_debug($message) {
    $log_file = __DIR__ . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// Log session and request data
log_debug('=== TEST CART PERSISTENCE ===');
log_debug('Request URI: ' . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
log_debug('Session ID: ' . session_id());
log_debug('Session Name: ' . session_name());
log_debug('Session Cookie: ' . ($_COOKIE[session_name()] ?? 'Not set'));
log_debug('Session Data: ' . print_r($_SESSION, true));

// Handle cart actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'add') {
        // Add a test item to cart
        $test_item = [
            'id' => 'test_' . time(),
            'name' => 'Test Product ' . rand(1000, 9999),
            'price' => rand(1000, 10000),
            'qty' => 1
        ];
        
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        $_SESSION['cart'][] = $test_item;
        log_debug('Added item to cart: ' . print_r($test_item, true));
        
        // Redirect to prevent form resubmission
        header('Location: test_cart_persistence.php');
        exit;
    } 
    elseif ($action === 'clear') {
        // Clear the cart
        $_SESSION['cart'] = [];
        log_debug('Cart cleared');
        
        // Redirect to prevent form resubmission
        header('Location: test_cart_persistence.php');
        exit;
    }
}

// Get current URL for form actions
$current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .debug-panel {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }
        .cart-item {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>Test Cart Persistence</h1>
        
        <div class="row mt-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3>Cart Contents</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($_SESSION['cart'])): ?>
                            <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                                <div class="cart-item">
                                    <h5><?php echo htmlspecialchars($item['name']); ?></h5>
                                    <p>Price: Gs. <?php echo number_format($item['price'], 0, ',', '.'); ?></p>
                                    <p>ID: <?php echo htmlspecialchars($item['id']); ?></p>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="mt-3">
                                <a href="test_cart_persistence.php?action=clear" class="btn btn-danger">Clear Cart</a>
                            </div>
                        <?php else: ?>
                            <p>Your cart is empty</p>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <a href="test_cart_persistence.php?action=add" class="btn btn-primary">Add Test Item</a>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h3>Navigation</h3>
                    </div>
                    <div class="card-body">
                        <a href="cart.php" class="btn btn-info">Go to Cart</a>
                        <a href="checkout.php" class="btn btn-success">Go to Checkout</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3>Debug Information</h3>
                    </div>
                    <div class="card-body">
                        <h5>Session ID:</h5>
                        <div class="debug-panel"><?php echo session_id(); ?></div>
                        
                        <h5>Session Cookie:</h5>
                        <div class="debug-panel">
                            <?php 
                            if (isset($_COOKIE[session_name()])) {
                                echo 'Name: ' . session_name() . '\n';
                                echo 'Value: ' . $_COOKIE[session_name()] . '\n';
                                echo 'Params: ' . print_r(session_get_cookie_params(), true);
                            } else {
                                echo 'No session cookie found';
                            }
                            ?>
                        </div>
                        
                        <h5>Session Data:</h5>
                        <div class="debug-panel"><?php print_r($_SESSION); ?></div>
                        
                        <h5>Server Variables:</h5>
                        <div class="debug-panel">
                            <?php 
                            $server_vars = [
                                'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
                                'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
                                'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
                                'HTTP_REFERER' => $_SERVER['HTTP_REFERER'] ?? 'N/A',
                                'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'N/A',
                                'HTTP_COOKIE' => $_SERVER['HTTP_COOKIE'] ?? 'N/A',
                                'HTTPS' => $_SERVER['HTTPS'] ?? 'Off',
                                'SERVER_PROTOCOL' => $_SERVER['SERVER_PROTOCOL'] ?? 'N/A',
                                'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'N/A',
                                'SERVER_PORT' => $_SERVER['SERVER_PORT'] ?? 'N/A'
                            ];
                            print_r($server_vars);
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
