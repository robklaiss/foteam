<?php
// This file provides a clean test flow for Bancard payments
// It handles all session configuration properly

// First, destroy any existing session
if (session_status() == PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Remove the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// Clear session data
$_SESSION = array();
session_destroy();

// Now configure and start a clean session
ini_set('session.use_strict_mode', 1);
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', 86400); // 24 hours
ini_set('session.cookie_lifetime', 0); // Until browser closes
ini_set('session.cookie_path', '/');

// Set a custom session name
session_name('foteam_bancard_test');

// Set session cookie parameters
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
          (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

session_set_cookie_params([
    'lifetime' => 0, // Until browser closes
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Now start a fresh session
session_start();

// Now we can safely include config and functions
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/bancard.php';

// Ensure Bancard environment is configured
if (empty(getenv('BANCARD_PUBLIC_KEY'))) {
    // Set environment variables directly
    putenv('BANCARD_PUBLIC_KEY=vBvkW7xkVoCxsogzVAgcIgXeW4DveMYH');
    putenv('BANCARD_PRIVATE_KEY=ToQSzGYQKLiopicQKtZtKpQ.m4iPE8XElg6W706e');
    
    // Set to staging for testing
    putenv('BANCARD_ENVIRONMENT=staging');
    putenv('BANCARD_API_URL_STAGING=https://vpos.infonet.com.py:8888');
    putenv('BANCARD_API_URL_PRODUCTION=https://vpos.infonet.com.py');
    
    // Local testing URLs
    $base_url = isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : 'http://localhost:8000';
    putenv('BANCARD_RETURN_URL=' . $base_url . '/payment_success.php');
    putenv('BANCARD_CANCEL_URL=' . $base_url . '/payment_cancel.php');
}

// Debug information
error_log('=== BANCARD_TEST_FLOW.PHP ===');
error_log('Clean session started with ID: ' . session_id());

// Test mode - create a sample cart
$_SESSION['cart'] = [];

// Create a test item with valid structure
$_SESSION['cart'][] = [
    'id' => 1, 
    'name' => 'Bancard Test Image',
    'price' => 10000, // 10,000 Guaraníes
    'qty' => 1
];

// For user info
$test_user = [
    'name' => 'Test User',
    'email' => 'test@example.com',
    'phone' => '0981123456'
];

// Handle form submission
$payment_url = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['process_payment'])) {
        try {
            // Create a test order in the database
            $db = db_connect();
            
            // Start transaction
            $db->exec('BEGIN TRANSACTION');
            
            // Create order
            $stmt = $db->prepare("
                INSERT INTO orders (
                    user_id, name, email, phone, address, 
                    payment_method, subtotal, tax, total_amount, status
                ) VALUES (
                    :user_id, :name, :email, :phone, :address, 
                    :payment_method, :subtotal, :tax, :total_amount, 'pending'
                )
            ");
            
            $user_id = null;
            $name = $_POST['name'] ?? $test_user['name'];
            $email = $_POST['email'] ?? $test_user['email'];
            $phone = $_POST['phone'] ?? $test_user['phone'];
            $address = $_POST['address'] ?? '';
            
            $subtotal = 10000; // Fixed for test
            $tax = $subtotal * TAX_RATE;
            $total = $subtotal + $tax;
            
            $stmt->bindValue(':user_id', $user_id, SQLITE3_NULL);
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
            $stmt->bindValue(':address', $address, SQLITE3_TEXT);
            $stmt->bindValue(':payment_method', 'bancard', SQLITE3_TEXT);
            $stmt->bindValue(':subtotal', $subtotal, SQLITE3_FLOAT);
            $stmt->bindValue(':tax', $tax, SQLITE3_FLOAT);
            $stmt->bindValue(':total_amount', $total, SQLITE3_FLOAT);
            
            $stmt->execute();
            $order_id = $db->lastInsertRowID();
            
            // Add order items
            foreach ($_SESSION['cart'] as $item) {
                $stmt = $db->prepare("
                    INSERT INTO order_items (order_id, image_id, price)
                    VALUES (:order_id, :image_id, :price)
                ");
                
                $stmt->bindValue(':order_id', $order_id, SQLITE3_INTEGER);
                $stmt->bindValue(':image_id', $item['id'], SQLITE3_INTEGER);
                $stmt->bindValue(':price', $item['price'], SQLITE3_FLOAT);
                $stmt->execute();
            }
            
            // Commit transaction
            $db->exec('COMMIT');
            
            // Initialize Bancard payment
            $process_id = init_bancard_payment($order_id, $total);
            
            // Store process ID in session for later verification
            $_SESSION['bancard_payment'] = [
                'order_id' => $order_id,
                'process_id' => $process_id
            ];
            
            // Get the payment URL
            $api_url = getenv('BANCARD_ENVIRONMENT') === 'production'
                ? getenv('BANCARD_API_URL_PRODUCTION')
                : getenv('BANCARD_API_URL_STAGING');
            
            $payment_url = $api_url . '/vpos/API/0.3/payment/single';
            
            // Success message
            $success_message = "Payment initialized! Order #" . $order_id;
            
        } catch (Exception $e) {
            $errors[] = 'Error: ' . $e->getMessage();
            
            // Rollback transaction on error
            if (isset($db) && $db) {
                $db->exec('ROLLBACK');
            }
        }
    }
}

// Page title
$page_title = 'Bancard Payment Test';

// Include header (optional based on your site structure)
if (file_exists('includes/header.php')) {
    include 'includes/header.php';
} else {
    // Minimal header
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>' . $page_title . '</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>';
}
?>

<div class="container">
    <div class="jumbotron bg-light p-5 rounded my-4">
        <h1 class="display-4">Bancard Payment Test</h1>
        <p class="lead">Use this page to test the Bancard payment gateway integration</p>
        <hr class="my-4">
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Cart Contents</h5>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Price (Gs)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['cart'] as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo number_format($item['price'], 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>Total:</th>
                                    <th><?php echo number_format(10000, 0, ',', '.'); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Bancard Configuration</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Public Key:</strong> <?php echo htmlspecialchars(getenv('BANCARD_PUBLIC_KEY')); ?></p>
                        <p><strong>Environment:</strong> <?php echo htmlspecialchars(getenv('BANCARD_ENVIRONMENT')); ?></p>
                        <p><strong>Return URL:</strong> <?php echo htmlspecialchars(getenv('BANCARD_RETURN_URL')); ?></p>
                        <p><strong>Cancel URL:</strong> <?php echo htmlspecialchars(getenv('BANCARD_CANCEL_URL')); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <?php if ($payment_url): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Payment Ready</h5>
                        </div>
                        <div class="card-body">
                            <p>Your payment has been initialized. Click the button below to proceed to Bancard's payment page.</p>
                            
                            <div class="d-grid gap-2">
                                <a href="<?php echo htmlspecialchars($payment_url); ?>" class="btn btn-primary btn-lg">
                                    Proceed to Payment
                                </a>
                            </div>
                            
                            <div class="mt-3">
                                <p class="small text-muted">Order ID: <?php echo htmlspecialchars($_SESSION['bancard_payment']['order_id']); ?></p>
                                <p class="small text-muted">Process ID: <?php echo htmlspecialchars($_SESSION['bancard_payment']['process_id']); ?></p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Customer Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Name</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($test_user['name']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($test_user['email']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($test_user['phone']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address (Optional)</label>
                                    <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" name="process_payment" class="btn btn-primary btn-lg">Process Payment with Bancard</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer (optional based on your site structure)
if (file_exists('includes/footer.php')) {
    include 'includes/footer.php';
} else {
    // Minimal footer
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>';
}
?>
