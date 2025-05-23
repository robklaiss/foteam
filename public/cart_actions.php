<?php
// Include necessary files
require_once '../includes/bootstrap.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/global_session.php';

// Set content type to JSON
header('Content-Type: application/json');

// Verify it's an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid security token'
    ]);
    exit;
}

// Handle different actions
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Debug info
$debug = [
    'action' => $action,
    'post' => $_POST,
    'session' => isset($_SESSION) ? array_keys($_SESSION) : 'No session',
    'user_logged_in' => function_exists('is_logged_in') ? (is_logged_in() ? 'yes' : 'no') : 'function not found'
];

switch ($action) {
    case 'get_count':
        // Return the current cart count
        echo json_encode([
            'success' => true,
            'cart_count' => isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0
        ]);
        break;
        
    case 'add':
        // Debug output
        file_put_contents('../logs/cart_debug.log', date('Y-m-d H:i:s') . ' - ' . json_encode($debug) . "\n", FILE_APPEND);
        
        // Allow anyone to add to cart for testing
        /* 
        if (!is_logged_in()) {
            echo json_encode([
                'success' => false,
                'message' => 'Debes iniciar sesión para añadir fotos al carrito'
            ]);
            exit;
        }
        */
        
        // Get image ID
        $image_id = isset($_POST['image_id']) ? (int)$_POST['image_id'] : 0;
        
        if ($image_id <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de imagen inválido'
            ]);
            exit;
        }
        
        // Check if image exists
        $db = db_connect();
        $stmt = $db->prepare("SELECT * FROM images WHERE id = :image_id");
        $stmt->bindValue(':image_id', $image_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $image = $result->fetchArray(SQLITE3_ASSOC);
        $result->finalize();
        $db->close();
        
        if (!$image) {
            echo json_encode([
                'success' => false,
                'message' => 'Imagen no encontrada'
            ]);
            exit;
        }
        
        // Initialize cart if not exists
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        // Check if image is already in cart
        $found = false;
        foreach ($_SESSION['cart'] as $item) {
            if ($item['id'] == $image_id) {
                $found = true;
                break;
            }
        }
        
        if ($found) {
            echo json_encode([
                'success' => false,
                'message' => 'Esta foto ya está en tu carrito',
                'cart_count' => count($_SESSION['cart'])
            ]);
            exit;
        }
        
        // Add to cart
        $_SESSION['cart'][] = [
            'id' => $image_id,
            'price' => PHOTO_PRICE,
            'added_at' => time()
        ];
            
        echo json_encode([
            'success' => true,
            'message' => 'Foto añadida al carrito',
            'cart_count' => count($_SESSION['cart'])
        ]);
        break;
        
    case 'remove':
        // Get index
        $index = isset($_POST['index']) ? (int)$_POST['index'] : -1;
        
        if ($index < 0 || !isset($_SESSION['cart']) || !isset($_SESSION['cart'][$index])) {
            echo json_encode([
                'success' => false,
                'message' => 'Ítem no encontrado en el carrito'
            ]);
            exit;
        }
        
        // Calculate total before removing the item
        $total = 0;
        foreach ($_SESSION['cart'] as $item) {
            $total += $item['price'];
        }
        
        // Remove item
        unset($_SESSION['cart'][$index]);
        $_SESSION['cart'] = array_values($_SESSION['cart']); // Reindex array
        
        // Calculate new total
        $new_total = 0;
        foreach ($_SESSION['cart'] as $item) {
            $new_total += $item['price'];
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Foto eliminada del carrito',
            'cart_count' => count($_SESSION['cart']),
            'total' => $new_total,
            'item_price' => $total - $new_total // Price of the removed item
        ]);
        break;
        
    case 'clear':
        // Clear cart
        $_SESSION['cart'] = [];
        
        echo json_encode([
            'success' => true,
            'message' => 'Carrito vaciado',
            'cart_count' => 0
        ]);
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Acción no válida'
        ]);
}
