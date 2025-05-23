<?php
// Start output buffering
ob_start();

// Include config to initialize session
require_once 'includes/config.php';

// Output session and cart information
function debug_session() {
    echo "<pre>";
    echo "=== SESSION DEBUG ===\n";
    echo "Session ID: " . session_id() . "\n";
    echo "Session Status: " . session_status() . "\n";
    echo "Session Data: \n";
    print_r($_SESSION);
    
    // Check if session is active
    echo "\nIs session active? " . (session_status() === PHP_SESSION_ACTIVE ? 'Yes' : 'No') . "\n";
    
    // Check session cookie
    echo "\nSession Cookie: \n";
    print_r($_COOKIE[session_name()] ?? 'No session cookie');
    
    // Check headers
    echo "\nHeaders: \n";
    foreach (headers_list() as $header) {
        echo $header . "\n";
    }
    
    echo "</pre>
";
}

// Output HTML
?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Session Debug Information</h1>
        <div class="card mt-4">
            <div class="card-header">
                <h3>Session Data</h3>
            </div>
            <div class="card-body">
                <?php debug_session(); ?>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3>Actions</h3>
            </div>
            <div class="card-body">
                <a href="cart.php" class="btn btn-primary">Go to Cart</a>
                <a href="checkout.php" class="btn btn-success">Go to Checkout</a>
                <a href="session_debug.php?clear=1" class="btn btn-danger">Clear Session</a>
            </div>
        </div>
    </div>
</body>
</html>
<?php
// Clear session if requested
if (isset($_GET['clear'])) {
    session_unset();
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
    header('Location: session_debug.php');
    exit;
}
?>
