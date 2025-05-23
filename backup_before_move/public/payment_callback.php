<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/bancard.php';

// Get the JSON payload
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    exit('Invalid JSON payload');
}

// Verify the payment
try {
    $shop_process_id = $data['operation']['shop_process_id'] ?? null;
    if (!$shop_process_id) {
        throw new Exception('Missing shop_process_id');
    }
    
    $db = db_connect();
    
    // Get order details
    $stmt = $db->prepare('SELECT id, status FROM orders WHERE id = :id');
    $stmt->bindValue(':id', $shop_process_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $order = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    if ($order['status'] !== 'pending') {
        // Order already processed
        http_response_code(200);
        exit('OK');
    }
    
    // Verify payment status
    $confirmation = verify_bancard_payment($shop_process_id);
    
    if ($confirmation['status'] === 'success' && $confirmation['confirmation']['response'] === 'success') {
        // Update order status
        $stmt = $db->prepare('UPDATE orders SET status = :status, payment_id = :payment_id WHERE id = :id');
        $stmt->bindValue(':status', 'completed', SQLITE3_TEXT);
        $stmt->bindValue(':payment_id', $confirmation['confirmation']['authorization_number'], SQLITE3_TEXT);
        $stmt->bindValue(':id', $shop_process_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Send confirmation email
        // TODO: Implement email sending
    } else {
        // Mark order as failed
        $stmt = $db->prepare('UPDATE orders SET status = :status WHERE id = :id');
        $stmt->bindValue(':status', 'failed', SQLITE3_TEXT);
        $stmt->bindValue(':id', $shop_process_id, SQLITE3_INTEGER);
        $stmt->execute();
    }
    
    http_response_code(200);
    exit('OK');
    
} catch (Exception $e) {
    error_log('Payment callback error: ' . $e->getMessage());
    http_response_code(500);
    exit('Error processing payment confirmation');
}
?>
