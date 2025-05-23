<?php
// Start output buffering
ob_start();

// Include required files
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/bancard.php';

// Get process ID and shop process ID from GET parameters
$process_id = $_GET['process_id'] ?? '';
$shop_process_id = $_GET['shop_process_id'] ?? '';

// Log payment response
error_log('=== PAYMENT SUCCESS CALLBACK ===');
error_log('Process ID: ' . $process_id);
error_log('Shop Process ID: ' . $shop_process_id);
error_log('GET params: ' . print_r($_GET, true));

// Validate parameters
if (empty($process_id) || empty($shop_process_id)) {
    set_flash_message('Información de pago incompleta', 'danger');
    redirect('cart.php');
    exit;
}

// Connect to database
$db = db_connect();

try {
    // Verify payment with Bancard
    $payment_info = verify_bancard_payment($shop_process_id);
    error_log('Payment verification response: ' . print_r($payment_info, true));
    
    // Update order status in database
    $order_id = (int)$shop_process_id;
    
    $stmt = $db->prepare("UPDATE orders SET status = :status, payment_id = :payment_id WHERE id = :order_id");
    $stmt->bindValue(':status', 'completed', SQLITE3_TEXT);
    $stmt->bindValue(':payment_id', $process_id, SQLITE3_TEXT);
    $stmt->bindValue(':order_id', $order_id, SQLITE3_INTEGER);
    $stmt->execute();
    
    // Clear cart after successful payment
    $_SESSION['cart'] = [];
    
    // Set success message
    set_flash_message('¡Pago completado con éxito! Tu número de orden es #' . $order_id, 'success');
    
    // Redirect to order confirmation page
    redirect('order_confirmation.php?order_id=' . $order_id);
} catch (Exception $e) {
    // Log error
    error_log('Payment verification error: ' . $e->getMessage());
    
    // Set error message
    set_flash_message('Error al verificar el pago: ' . $e->getMessage(), 'danger');
    
    // Redirect to cart
    redirect('cart.php');
}
?>
