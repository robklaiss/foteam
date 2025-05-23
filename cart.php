<?php
// Include bootstrap first to configure session settings
require_once __DIR__ . '/includes/bootstrap.php';

// Then include config
require_once __DIR__ . '/includes/config.php';

// Then include global session manager
require_once __DIR__ . '/includes/global_session.php';

// Finally include functions
require_once __DIR__ . '/includes/functions.php';

// Debug session and cart data
error_log('=== CART.PHP DEBUG ===');
error_log('Session ID: ' . session_id());
error_log('Session data: ' . print_r($_SESSION, true));

// Debug the session cookie
if (isset($_COOKIE[session_name()])) {
    error_log('Session cookie exists: ' . $_COOKIE[session_name()]);
} else {
    error_log('No session cookie found');
}

// Initialize cart if not exists or not an array
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    error_log('Initializing new cart in session');
    $_SESSION['cart'] = [];
}

// Debug cart contents
error_log('Cart has ' . count($_SESSION['cart']) . ' items');
foreach ($_SESSION['cart'] as $index => $item) {
    error_log(sprintf("Cart item %d: %s (ID: %s, Price: %s)", 
        $index, 
        $item['name'] ?? 'No name', 
        $item['id'] ?? 'No ID',
        $item['price'] ?? 'No price'
    ));
}

// Handle cart actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'add' && isset($_GET['image_id'])) {
        $image_id = (int)$_GET['image_id'];
        
        // Check if image exists
        $db = db_connect();
        $stmt = $db->prepare("SELECT * FROM images WHERE id = :image_id");
        $stmt->bindValue(':image_id', $image_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $image = $result->fetchArray(SQLITE3_ASSOC);
        $result->finalize();
        $db->close();
        
        if ($image) {
            // Add to cart if not already in cart
            $found = false;
            foreach ($_SESSION['cart'] as $item) {
                if ($item['id'] == $image_id) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $_SESSION['cart'][] = [
                    'id' => $image_id,
                    'price' => PHOTO_PRICE
                ];
                set_flash_message('Foto añadida al carrito', 'success');
            } else {
                set_flash_message('Esta foto ya está en tu carrito', 'info');
            }
        } else {
            set_flash_message('Foto no encontrada', 'danger');
        }
        
        // Redirect back to referring page or search results
        if (isset($_SERVER['HTTP_REFERER'])) {
            redirect($_SERVER['HTTP_REFERER']);
        } else {
            redirect('index.php');
        }
    } else if ($action === 'remove') {
        // Handle removing items from cart
        if (isset($_GET['index'])) { // Remove from session cart
            $index = (int)$_GET['index'];
            
            if (isset($_SESSION['cart'][$index])) {
                unset($_SESSION['cart'][$index]);
                $_SESSION['cart'] = array_values($_SESSION['cart']); // Reindex array
                set_flash_message('Foto eliminada del carrito', 'success');
            }
        } else if (isset($_GET['db_id']) && is_logged_in()) { // Remove from database cart
            $cart_id = (int)$_GET['db_id'];
            $user_id = $_SESSION['user_id'];
            
            $db = db_connect();
            $stmt = $db->prepare("DELETE FROM cart_items WHERE id = :cart_id AND user_id = :user_id");
            $stmt->bindValue(':cart_id', $cart_id, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $stmt->execute();
            $db->close();
            
            set_flash_message('Foto eliminada del carrito', 'success');
        }
        
        redirect('cart.php');
    } else if ($action === 'clear') {
        $_SESSION['cart'] = [];
        set_flash_message('Carrito vaciado', 'success');
        redirect('cart.php');
    }
}

// Get cart items details
$cart_items = [];
$total = 0;

// Get cart items from both session and database
$db = db_connect();

// 1. Check session cart first
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $index => $item) {
        $stmt = $db->prepare("
            SELECT i.*, m.name as marathon_name, u.username as photographer_name
            FROM images i
            JOIN marathons m ON i.marathon_id = m.id
            JOIN users u ON i.user_id = u.id
            WHERE i.id = :image_id
        ");
        $stmt->bindValue(':image_id', $item['id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        $image = $result->fetchArray(SQLITE3_ASSOC);
        $result->finalize();
        
        if ($image) {
            // Convert paths to URLs - Fixed for root deployment
            $image['url'] = '/uploads/' . basename($image['original_path']);
            $image['thumbnail_url'] = '/uploads/thumbnails/' . basename($image['thumbnail_path']);
            
            // Add cart index for removal
            $image['cart_index'] = $index;
            $image['source'] = 'session'; // Mark as from session
            
            // Add price
            $image['price'] = $item['price'];
            $total += $item['price'];
            
            $cart_items[] = $image;
        }
    }
}

// 2. Check database cart for logged in users
if (is_logged_in()) {
    $user_id = $_SESSION['user_id'];
    
    $stmt = $db->prepare("
        SELECT i.*, c.id as cart_id, m.name as marathon_name, u.username as photographer_name
        FROM cart_items c
        JOIN images i ON c.image_id = i.id
        JOIN marathons m ON i.marathon_id = m.id
        JOIN users u ON i.user_id = u.id
        WHERE c.user_id = :user_id
    ");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Convert paths to URLs - Fixed for root deployment
        $row['url'] = '/uploads/' . basename($row['original_path']);
        $row['thumbnail_url'] = '/uploads/thumbnails/' . basename($row['thumbnail_path']);
        
        // Add database cart ID for removal
        $row['cart_db_id'] = $row['cart_id'];
        $row['source'] = 'database'; // Mark as from database
        
        // Add price (assuming PHOTO_PRICE is defined)
        $row['price'] = PHOTO_PRICE;
        $total += $row['price'];
        
        $cart_items[] = $row;
    }
    
    $result->finalize();
}

$db->close();

// Include header
include 'includes/header.php';
?>

<div class="container">
    <div class="jumbotron bg-light p-5 rounded">
        <h1 class="display-4">Tu Carrito</h1>
        <p class="lead">Revisa las fotos que has seleccionado para comprar</p>
        <hr class="my-4">
        
        <?php if (empty($cart_items)): ?>
            <div class="alert alert-info">
                <p>Tu carrito está vacío. <a href="index.php">Buscar fotos</a></p>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Fotos Seleccionadas (<?php echo count($cart_items); ?>)</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="row mb-4 align-items-center">
                                    <div class="col-md-3">
                                        <img src="<?php echo $item['thumbnail_url']; ?>" class="img-fluid rounded" alt="Foto de maratón">
                                    </div>
                                    <div class="col-md-7">
                                        <h5><?php echo h($item['filename']); ?></h5>
                                        <p class="mb-1">
                                            <strong>Maratón:</strong> <?php echo h($item['marathon_name']); ?>
                                        </p>
                                        <p class="mb-1">
                                            <strong>Fotógrafo:</strong> <?php echo h($item['photographer_name']); ?>
                                        </p>
                                        <?php if (!empty($item['detected_numbers'])): ?>
                                            <p class="mb-1">
                                                <strong>Números detectados:</strong> <?php echo h($item['detected_numbers']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <p class="mb-0">
                                            <strong>Precio:</strong> <?php echo format_money($item['price']); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-2 text-end">
                                        <?php if ($item['source'] === 'session'): ?>
                                        <a href="cart.php?action=remove&index=<?php echo $item['cart_index']; ?>" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i> Eliminar
                                        </a>
                                        <?php else: ?>
                                        <a href="cart.php?action=remove&db_id=<?php echo $item['cart_db_id']; ?>" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i> Eliminar
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <hr>
                            <?php endforeach; ?>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="cart.php?action=clear" class="btn btn-outline-secondary" onclick="return confirm('¿Estás seguro de que deseas vaciar el carrito?')">
                                    Vaciar Carrito
                                </a>
                                <a href="index.php" class="btn btn-primary">
                                    Seguir Comprando
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Resumen de Compra</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-3">
                                <span>Subtotal:</span>
                                <span>$<?php echo number_format($total, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span>Impuestos:</span>
                                <span>$<?php echo number_format($total * TAX_RATE, 2); ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3">
                                <strong>Total:</strong>
                                <strong>$<?php echo number_format($total * (1 + TAX_RATE), 2); ?></strong>
                            </div>
                            <div class="d-grid gap-2">
                                <a href="checkout.php" class="btn btn-success">
                                    Proceder al Pago
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
