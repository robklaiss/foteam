<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Redirect to login if not logged in
if (!is_logged_in()) {
    set_flash_message('Debe iniciar sesión para reiniciar su galería', 'warning');
    redirect('login.php');
}

// Check if POST request and CSRF token is valid
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('gallery.php');
}

check_csrf_token();

// Get user ID
$user_id = $_SESSION['user_id'];

// Connect to database
$conn = db_connect();

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Get all images for this user
    $images_stmt = $conn->prepare("SELECT original_path, thumbnail_path FROM images WHERE user_id = ?");
    $images_stmt->bind_param("i", $user_id);
    $images_stmt->execute();
    $result = $images_stmt->get_result();
    
    // Delete physical files
    while ($row = $result->fetch_assoc()) {
        if (file_exists($row['original_path'])) {
            unlink($row['original_path']);
        }
        
        if (file_exists($row['thumbnail_path'])) {
            unlink($row['thumbnail_path']);
        }
    }
    
    $images_stmt->close();
    
    // Delete from cart_items
    $cart_stmt = $conn->prepare("
        DELETE ci FROM cart_items ci
        JOIN images i ON ci.image_id = i.id
        WHERE i.user_id = ?
    ");
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_stmt->close();
    
    // Delete from order_items
    $order_items_stmt = $conn->prepare("
        DELETE oi FROM order_items oi
        JOIN images i ON oi.image_id = i.id
        WHERE i.user_id = ?
    ");
    $order_items_stmt->bind_param("i", $user_id);
    $order_items_stmt->execute();
    $order_items_stmt->close();
    
    // Delete from images
    $delete_stmt = $conn->prepare("DELETE FROM images WHERE user_id = ?");
    $delete_stmt->bind_param("i", $user_id);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    set_flash_message('Galería reiniciada correctamente. Todas sus fotos han sido eliminadas.', 'success');
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    set_flash_message('Error al reiniciar la galería: ' . $e->getMessage(), 'danger');
}

$conn->close();
redirect('gallery.php');
?>
