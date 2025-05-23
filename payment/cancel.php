<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/bancard.php';

// Get order ID from session
$order_id = $_SESSION['order_id'] ?? null;

if ($order_id) {
    try {
        $db = db_connect();
        
        // Update order status to cancelled
        $stmt = $db->prepare('UPDATE orders SET status = :status WHERE id = :id');
        $stmt->bindValue(':status', 'cancelled', SQLITE3_TEXT);
        $stmt->bindValue(':id', $order_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Clear payment session data
        unset($_SESSION['bancard_process_id']);
        unset($_SESSION['order_id']);
        
        set_flash_message('El pago ha sido cancelado. Puedes intentar nuevamente cuando lo desees.', 'warning');
    } catch (Exception $e) {
        set_flash_message('Error al procesar la cancelación: ' . $e->getMessage(), 'danger');
    }
}

// Redirect back to checkout
redirect('/checkout.php');
?>
