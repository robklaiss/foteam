<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Redirect if not logged in
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = '/public/order_detail.php';
    set_flash_message('Debe iniciar sesión para ver los detalles de su pedido', 'warning');
    redirect('../login.php');
}

// Get order ID from query string
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id <= 0) {
    set_flash_message('Pedido no válido', 'danger');
    redirect('my_account.php#orders');
}

// Get order details
$db = db_connect();
$stmt = $db->prepare("
    SELECT * FROM orders
    WHERE id = :order_id AND user_id = :user_id
");
$stmt->bindValue(':order_id', $order_id, SQLITE3_INTEGER);
$stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
$result = $stmt->execute();

$order = $result->fetchArray(SQLITE3_ASSOC);
$result->finalize();

if (!$order) {
    $db->close();
    set_flash_message('Pedido no encontrado o no tiene permiso para verlo', 'danger');
    redirect('my_account.php#orders');
}

// Get order items
$stmt = $db->prepare("
    SELECT oi.*, i.filename, i.original_path, i.thumbnail_path, i.detected_numbers,
           m.name as marathon_name, u.username as photographer_name
    FROM order_items oi
    JOIN images i ON oi.image_id = i.id
    JOIN marathons m ON i.marathon_id = m.id
    JOIN users u ON i.user_id = u.id
    WHERE oi.order_id = :order_id
");
$stmt->bindValue(':order_id', $order_id, SQLITE3_INTEGER);
$result = $stmt->execute();

$order_items = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    // Convert paths to URLs - Fixed for root deployment
    $row['url'] = '/uploads/' . basename($row['original_path']);
    $row['thumbnail_url'] = '/uploads/thumbnails/' . basename($row['thumbnail_path']);
    
    $order_items[] = $row;
}

$result->finalize();
$db->close();

// Include header
include 'includes/header.php';
?>

<div class="container">
    <div class="jumbotron bg-light p-5 rounded">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="display-5">Detalles del Pedido #<?php echo $order_id; ?></h1>
            <a href="my_account.php#orders" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Volver a Mis Pedidos
            </a>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Información del Pedido</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Fecha del Pedido:</strong> <?php echo date('d/m/Y H:i', strtotime($order['order_date'])); ?></p>
                                <p><strong>Estado:</strong> 
                                    <span class="badge bg-<?php echo get_order_status_color($order['status']); ?>">
                                        <?php echo get_order_status_text($order['status']); ?>
                                    </span>
                                </p>
                                <p><strong>Método de Pago:</strong> <?php echo get_payment_method_text($order['payment_method']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Nombre:</strong> <?php echo h($order['name']); ?></p>
                                <p><strong>Email:</strong> <?php echo h($order['email']); ?></p>
                                <p><strong>Teléfono:</strong> <?php echo h($order['phone']); ?></p>
                                <?php if (!empty($order['address'])): ?>
                                    <p><strong>Dirección:</strong> <?php echo h($order['address']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Fotos Compradas</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($order_items)): ?>
                            <div class="alert alert-info">
                                No hay fotos en este pedido.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($order_items as $item): ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="card h-100">
                                            <img src="<?php echo $item['thumbnail_url']; ?>" class="card-img-top" alt="Foto">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo h($item['filename']); ?></h5>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        Maratón: <?php echo h($item['marathon_name']); ?><br>
                                                        Fotógrafo: <?php echo h($item['photographer_name']); ?>
                                                    </small>
                                                </p>
                                            </div>
                                            <div class="card-footer bg-transparent">
                                                <?php if ($order['status'] === 'completed'): ?>
                                                    <a href="<?php echo $item['url']; ?>" class="btn btn-success btn-sm w-100" download>
                                                        <i class="bi bi-download"></i> Descargar
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-secondary btn-sm w-100" disabled>
                                                        <i class="bi bi-lock"></i> Pendiente de Pago
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Resumen del Pedido</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span>Subtotal:</span>
                            <span>$<?php echo number_format($order['subtotal'], 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Impuestos (<?php echo TAX_RATE * 100; ?>%):</span>
                            <span>$<?php echo number_format($order['tax'], 2); ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total:</strong>
                            <strong>$<?php echo number_format($order['total_amount'], 2); ?></strong>
                        </div>
                    </div>
                </div>
                
                <?php if ($order['status'] === 'pending'): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Instrucciones de Pago</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($order['payment_method'] === 'credit_card'): ?>
                                <p>Complete el pago con su tarjeta de crédito para desbloquear la descarga de sus fotos.</p>
                                <div class="d-grid gap-2">
                                    <a href="#" class="btn btn-primary">Completar Pago</a>
                                </div>
                            <?php elseif ($order['payment_method'] === 'paypal'): ?>
                                <p>Complete el pago a través de PayPal para desbloquear la descarga de sus fotos.</p>
                                <div class="d-grid gap-2">
                                    <a href="#" class="btn btn-primary">Pagar con PayPal</a>
                                </div>
                            <?php elseif ($order['payment_method'] === 'bank_transfer'): ?>
                                <p>Por favor, realice una transferencia bancaria con los siguientes datos:</p>
                                <div class="alert alert-info">
                                    <p><strong>Banco:</strong> Banco Nacional</p>
                                    <p><strong>Titular:</strong> FoTeam Photography</p>
                                    <p><strong>Cuenta:</strong> 1234-5678-90-1234567890</p>
                                    <p><strong>Concepto:</strong> Pedido #<?php echo $order_id; ?></p>
                                    <p><strong>Importe:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p>
                                </div>
                                <p>Una vez que recibamos su pago, procesaremos su pedido y podrá descargar sus fotos.</p>
                            <?php elseif ($order['payment_method'] === 'bancard'): ?>
                                <div class="alert alert-info">
                                    <h5>Pago con Bancard</h5>
                                    <?php if ($order['status'] === 'completed'): ?>
                                        <p>El pago ha sido procesado exitosamente.</p>
                                        <?php if (!empty($order['payment_id'])): ?>
                                            <p><strong>Número de autorización:</strong> <?php echo h($order['payment_id']); ?></p>
                                        <?php endif; ?>
                                    <?php elseif ($order['status'] === 'pending'): ?>
                                        <p>El pago está siendo procesado. Por favor espera un momento...</p>
                                    <?php else: ?>
                                        <p>Hubo un problema con el pago. Por favor intenta nuevamente.</p>
                                    <?php endif; ?>
                                </div>
                                <p><strong>Concepto:</strong> Pedido #<?php echo $order_id; ?></p>
                                <p><strong>Importe:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Soporte</h5>
                    </div>
                    <div class="card-body">
                        <p>Si tiene alguna pregunta sobre su pedido, por favor contáctenos:</p>
                        <p><i class="bi bi-envelope"></i> <a href="mailto:info@foteam.com">info@foteam.com</a></p>
                        <p><i class="bi bi-telephone"></i> +1 (123) 456-7890</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
