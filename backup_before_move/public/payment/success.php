<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/bancard.php';

// Verify the payment status
$process_id = $_SESSION['bancard_process_id'] ?? null;
$order_id = $_SESSION['order_id'] ?? null;

if (!$process_id || !$order_id) {
    set_flash_message('Sesión de pago inválida', 'danger');
    redirect('../checkout.php');
}

try {
    // Verify payment status
    $confirmation = verify_bancard_payment($order_id);
    
    if ($confirmation['status'] === 'success' && $confirmation['confirmation']['response'] === 'success') {
        // Clear cart and payment session data
        $_SESSION['cart'] = [];
        unset($_SESSION['bancard_process_id']);
        unset($_SESSION['order_id']);
        
        set_flash_message('¡Pago realizado con éxito! Tu pedido está siendo procesado.', 'success');
        redirect('../order_confirmation.php?id=' . $order_id);
    } else {
        set_flash_message('El pago no pudo ser confirmado. Por favor, intente nuevamente.', 'warning');
        redirect('../checkout.php');
    }
} catch (Exception $e) {
    set_flash_message('Error al verificar el pago: ' . $e->getMessage(), 'danger');
    redirect('../checkout.php');
}
?>
