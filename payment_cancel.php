<?php
// Start output buffering
ob_start();

// Include required files
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Get process ID and shop process ID from GET parameters
$process_id = $_GET['process_id'] ?? '';
$shop_process_id = $_GET['shop_process_id'] ?? '';

// Log payment cancellation
error_log('=== PAYMENT CANCELLATION CALLBACK ===');
error_log('Process ID: ' . $process_id);
error_log('Shop Process ID: ' . $shop_process_id);
error_log('GET params: ' . print_r($_GET, true));

// Update order status in database if shop_process_id is valid
if (!empty($shop_process_id)) {
    $db = db_connect();
    $order_id = (int)$shop_process_id;
    
    $stmt = $db->prepare("UPDATE orders SET status = :status WHERE id = :order_id");
    $stmt->bindValue(':status', 'cancelled', SQLITE3_TEXT);
    $stmt->bindValue(':order_id', $order_id, SQLITE3_INTEGER);
    $stmt->execute();
    $db->close();
}

// Set message
set_flash_message('El proceso de pago ha sido cancelado', 'warning');

// Redirect to cart
redirect('cart.php');
?>
