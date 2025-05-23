<?php
// Start output buffering
ob_start();

// Include config to initialize session
require_once 'includes/config.php';

// Add test item to cart if requested
if (isset($_GET['add_to_cart'])) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Add a test item
    $_SESSION['cart'][] = [
        'id' => 'test_' . time(),
        'price' => 10000,
        'name' => 'Test Item',
        'image' => 'test.jpg'
    ];
    
    header('Location: test_session_flow.php');
    exit;
}

// Clear cart if requested
if (isset($_GET['clear_cart'])) {
    unset($_SESSION['cart']);
    header('Location: test_session_flow.php');
    exit;
}

// Get current URL for form actions
$current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Session Flow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .debug-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-family: monospace;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1>Test Session Flow</h1>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Cart Actions</h3>
                    </div>
                    <div class="card-body">
                        <a href="test_session_flow.php?add_to_cart=1" class="btn btn-primary">Add Test Item to Cart</a>
                        <a href="test_session_flow.php?clear_cart=1" class="btn btn-danger">Clear Cart</a>
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
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Debug Information</h3>
                    </div>
                    <div class="card-body">
                        <h5>Session ID:</h5>
                        <div class="debug-info"><?php echo session_id(); ?></div>
                        
                        <h5>Session Data:</h5>
                        <div class="debug-info"><?php print_r($_SESSION); ?></div>
                        
                        <h5>Cookie Data:</h5>
                        <div class="debug-info"><?php print_r($_COOKIE); ?></div>
                        
                        <h5>Headers:</h5>
                        <div class="debug-info"><?php print_r(headers_list()); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
