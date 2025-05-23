<?php
// Start output buffering
ob_start();

// Include config to initialize session
require_once 'includes/config.php';

// Set page title
$page_title = 'Check Cart Structure';

// Function to log debug info
function log_debug($message) {
    $log_file = __DIR__ . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
    echo "<pre>$message</pre>";
}

// Log session and request data
echo "<h2>Cart Structure Check</h2>";
log_debug('=== CART STRUCTURE CHECK ===');
log_debug('Session ID: ' . session_id());
log_debug('Session Name: ' . session_name());
log_debug('Session Cookie: ' . ($_COOKIE[session_name()] ?? 'Not set'));

// Check if cart exists in session
if (!isset($_SESSION['cart'])) {
    log_debug('ERROR: No cart found in session');
    log_debug('Session data: ' . print_r($_SESSION, true));
    die('</body></html>');
}

// Check cart structure
log_debug('Cart contents: ' . print_r($_SESSION['cart'], true));
log_debug('Cart item count: ' . count($_SESSION['cart']));

// Check each item in cart
$has_issues = false;
foreach ($_SESSION['cart'] as $index => $item) {
    log_debug("\nChecking cart item #$index:");
    
    // Check required fields
    $required_fields = ['id', 'price'];
    foreach ($required_fields as $field) {
        if (!array_key_exists($field, $item)) {
            log_debug("ERROR: Missing required field '$field'");
            $has_issues = true;
        } else {
            log_debug("$field: " . $item[$field]);
        }
    }
    
    // Check if ID is numeric
    if (isset($item['id']) && !is_numeric($item['id'])) {
        log_debug("WARNING: Item ID is not numeric (" . gettype($item['id']) . "): " . $item['id']);
        $has_issues = true;
    }
    
    // Check if price is numeric
    if (isset($item['price']) && !is_numeric($item['price'])) {
        log_debug("WARNING: Item price is not numeric (" . gettype($item['price']) . "): " . $item['price']);
        $has_issues = true;
    }
}

// Check if images exist in database
$db = db_connect();
if ($db) {
    foreach ($_SESSION['cart'] as $index => $item) {
        if (isset($item['id'])) {
            $stmt = $db->prepare("SELECT id, filename FROM images WHERE id = :id");
            $stmt->bindValue(':id', $item['id'], SQLITE3_INTEGER);
            $result = $stmt->execute();
            $image = $result->fetchArray(SQLITE3_ASSOC);
            
            if ($image) {
                log_debug("Image ID {$item['id']} exists in database: " . $image['filename']);
            } else {
                log_debug("ERROR: Image ID {$item['id']} does not exist in database");
                $has_issues = true;
            }
        }
    }
    $db->close();
} else {
    log_debug("WARNING: Could not connect to database to verify image IDs");
}

// Summary
if ($has_issues) {
    log_debug("\nISSUES DETECTED: Some problems were found with the cart structure.");
} else {
    log_debug("\nCART STRUCTURE IS VALID: No issues detected.");
}

// Add a link to test checkout
echo '<div class="mt-4">';
echo '<h3>Test Checkout</h3>';
echo '<p>If the cart structure looks correct, you can try to proceed to checkout:</p>';
echo '<a href="checkout.php" class="btn btn-primary">Proceed to Checkout</a>';
echo '</div>';
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    pre { 
        background-color: #f5f5f5; 
        padding: 10px; 
        border: 1px solid #ddd; 
        border-radius: 4px; 
        overflow-x: auto; 
    }
    .error { color: #dc3545; font-weight: bold; }
    .warning { color: #ffc107; font-weight: bold; }
    .success { color: #28a745; font-weight: bold; }
</style>
