<?php
// Include bootstrap first to configure environment and settings
if (!defined('BOOTSTRAP_LOADED')) {
    require_once __DIR__ . '/../includes/bootstrap.php';
}

// Include config
if (!defined('CONFIG_LOADED')) {
    require_once __DIR__ . '/../includes/config.php';
}

require_once '../includes/functions.php';
require_once '../includes/session_debug.php';

// Log session information at page load
log_session_debug('cart.php', 'page_load');

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
    log_session_debug('cart.php', 'cart_initialized');
}

// Cart actions are now handled by cart_actions.php via AJAX

// Get cart items details
$cart_items = [];
$total = 0;

if (!empty($_SESSION['cart'])) {
    $db = db_connect();
    
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
            // Convert paths to URLs
            $image['url'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $image['original_path']);
            $image['thumbnail_url'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $image['thumbnail_path']);
            
            // Add cart index for removal
            $image['cart_index'] = $index;
            
            // Add price
            $image['price'] = $item['price'];
            $total += $item['price'];
            
            $cart_items[] = $image;
        }
    }
    
    $db->close();
}

// Include header
include '../includes/header.php';
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
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-from-cart" data-index="<?php echo $item['cart_index']; ?>">
                                            <i class="bi bi-trash"></i> Eliminar
                                        </button>
                                    </div>
                                </div>
                                <hr class="item-divider">
                            <?php endforeach; ?>
                            <style>
                                .item-divider:last-child {
                                    display: none;
                                }
                            </style>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-outline-secondary" id="clear-cart">
                                    Vaciar Carrito
                                </button>
                                <a href="index.php" class="btn btn-primary">
                                    Seguir Comprando
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Resumen del Pedido</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal (<?php echo count($cart_items); ?> <?php echo count($cart_items) === 1 ? 'foto' : 'fotos'; ?>):</span>
                                <span><?php echo format_money($total); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span>IVA (21%):</span>
                                <span><?php echo format_money($total * 0.21); ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fw-bold mb-4">
                                <span>Total:</span>
                                <span><?php echo format_money($total * 1.21); ?></span>
                            </div>
                            <a href="checkout.php" class="btn btn-primary w-100 py-2">
                                Proceder al Pago
                            </a>
                            <p class="text-muted small mt-2 text-center">
                                Pago seguro con tarjeta de crédito o transferencia bancaria
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Remove item from cart
    document.querySelectorAll('.remove-from-cart').forEach(button => {
        button.addEventListener('click', function() {
            const index = this.getAttribute('data-index');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const row = this.closest('.row.mb-4');
            
            fetch('cart_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=remove&index=${index}&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the item row
                    const hr = row.nextElementSibling;
                    if (hr && hr.tagName === 'HR') {
                        hr.remove();
                    }
                    row.remove();
                    
                    // Update cart count in navbar
                    const cartBadge = document.querySelector('.cart-badge');
                    if (cartBadge) {
                        if (data.cart_count > 0) {
                            cartBadge.textContent = data.cart_count;
                        } else {
                            cartBadge.remove();
                            // Redirect to empty cart view
                            window.location.href = 'cart.php';
                        }
                    }
                    
                    // Update order summary
                    updateOrderSummary(data.cart_count, data.total || 0);
                    
                    // Show success message
                    alert(data.message);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al eliminar el ítem del carrito');
            });
        });
    });
    
    // Clear cart
    const clearCartBtn = document.getElementById('clear-cart');
    if (clearCartBtn) {
        clearCartBtn.addEventListener('click', function() {
            if (confirm('¿Estás seguro de que deseas vaciar el carrito?')) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                
                fetch('cart_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=clear&csrf_token=${csrfToken}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update cart count in navbar
                        const cartBadge = document.querySelector('.cart-badge');
                        if (cartBadge) {
                            cartBadge.remove();
                        }
                        
                        // Redirect to empty cart view
                        window.location.href = 'cart.php';
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al vaciar el carrito');
                });
            }
        });
    }
    
    // Function to update order summary
    function updateOrderSummary(itemCount, total) {
        const subtotalElement = document.querySelector('.order-subtotal');
        const taxElement = document.querySelector('.order-tax');
        const totalElement = document.querySelector('.order-total');
        const itemCountElement = document.querySelector('.item-count');
        
        if (itemCountElement) {
            itemCountElement.textContent = itemCount + ' ' + (itemCount === 1 ? 'foto' : 'fotos');
        }
        
        if (subtotalElement && totalElement) {
            const subtotal = total;
            const tax = subtotal * 0.21;
            const totalWithTax = subtotal + tax;
            
            subtotalElement.textContent = '€' + subtotal.toFixed(2).replace('.', ',');
            taxElement.textContent = '€' + tax.toFixed(2).replace('.', ',');
            totalElement.textContent = '€' + totalWithTax.toFixed(2).replace('.', ',');
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
