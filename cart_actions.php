<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Ensure the request is AJAX
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Check if user is logged in
if (!is_logged_in()) {
    if ($is_ajax) {
        echo json_encode(['success' => false, 'message' => 'Debe iniciar sesión para usar el carrito']);
        exit;
    } else {
        set_flash_message('Debe iniciar sesión para usar el carrito', 'danger');
        redirect('login.php');
    }
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    if ($is_ajax) {
        echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
        exit;
    } else {
        set_flash_message('Token de seguridad inválido', 'danger');
        redirect('gallery.php');
    }
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

switch ($action) {
    case 'add':
        $image_id = isset($_POST['image_id']) ? (int)$_POST['image_id'] : 0;
        
        if ($image_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de imagen inválido']);
            exit;
        }
        
        $result = add_to_cart($user_id, $image_id);
        
        echo json_encode([
            'success' => $result['success'],
            'message' => $result['message']
        ]);
        break;
        
    case 'remove':
        $image_id = isset($_POST['image_id']) ? (int)$_POST['image_id'] : 0;
        
        if ($image_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de imagen inválido']);
            exit;
        }
        
        $success = remove_from_cart($user_id, $image_id);
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Eliminado del carrito' : 'Error al eliminar del carrito',
            'image_id' => $image_id
        ]);
        break;
        
    case 'clear':
        // Clear all items from cart
        $db = db_connect();
        $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = :user_id");
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        $changes = $db->changes();
        $db->close();
        
        // Also clear session cart to be safe
        $_SESSION['cart'] = [];
        
        echo json_encode([
            'success' => true,
            'message' => 'Carrito vaciado',
            'items_removed' => $changes
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}
?>
