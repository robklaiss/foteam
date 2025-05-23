<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Get order ID from query string
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id <= 0) {
    set_flash_message('Pedido no válido', 'danger');
    redirect('index.php');
}

// Get order details
$db = db_connect();
$stmt = $db->prepare("
    SELECT * FROM orders
    WHERE id = :order_id
");
$stmt->bindValue(':order_id', $order_id, SQLITE3_INTEGER);
$result = $stmt->execute();

$order = $result->fetchArray(SQLITE3_ASSOC);
$result->finalize();

if (!$order) {
    $db->close();
    set_flash_message('Pedido no encontrado', 'danger');
    redirect('index.php');
}

// Check if order belongs to current user if logged in
if (is_logged_in() && $order['user_id'] && $order['user_id'] != $_SESSION['user_id']) {
    $db->close();
    set_flash_message('No tienes permiso para ver este pedido', 'danger');
    redirect('index.php');
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
    // Convert paths to URLs
    $row['url'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $row['original_path']);
    $row['thumbnail_url'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $row['thumbnail_path']);
    
    $order_items[] = $row;
}

$result->finalize();
$db->close();

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="jumbotron bg-light p-5 rounded">
        <div class="text-center mb-4">
            <h1 class="display-4">¡Gracias por tu compra!</h1>
            <p class="lead">Tu pedido ha sido recibido y está siendo procesado</p>
            <div class="alert alert-success d-inline-block">
                Número de Pedido: <strong><?php echo $order_id; ?></strong>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Detalles del Pedido</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Nombre:</strong> <?php echo h($order['name']); ?></p>
                                <p><strong>Email:</strong> <?php echo h($order['email']); ?></p>
                                <p><strong>Teléfono:</strong> <?php echo h($order['phone']); ?></p>
                                <?php if (!empty($order['address'])): ?>
                                    <p><strong>Dirección:</strong> <?php echo h($order['address']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Fecha del Pedido:</strong> <?php echo date('d/m/Y H:i', strtotime($order['order_date'])); ?></p>
                                <p><strong>Estado:</strong> 
                                    <span class="badge bg-<?php echo get_order_status_color($order['status']); ?>">
                                        <?php echo get_order_status_text($order['status']); ?>
                                    </span>
                                </p>
                                <p><strong>Método de Pago:</strong> <?php echo get_payment_method_text($order['payment_method']); ?></p>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h5 class="mb-3">Fotos Compradas</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Foto</th>
                                        <th>Detalles</th>
                                        <th class="text-end">Precio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order_items as $item): ?>
                                        <tr>
                                            <td style="width: 100px;">
                                                <img src="<?php echo $item['thumbnail_url']; ?>" class="img-thumbnail" alt="Foto">
                                            </td>
                                            <td>
                                                <p class="mb-1"><?php echo h($item['filename']); ?></p>
                                                <small class="text-muted">
                                                    Maratón: <?php echo h($item['marathon_name']); ?><br>
                                                    Fotógrafo: <?php echo h($item['photographer_name']); ?>
                                                </small>
                                            </td>
                                            <td class="text-end"><?php echo format_money($item['price']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="2" class="text-end"><strong>Subtotal:</strong></td>
                                        <td class="text-end">$<?php echo number_format($order['subtotal'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="text-end"><strong>Impuestos (<?php echo TAX_RATE * 100; ?>%):</strong></td>
                                        <td class="text-end">$<?php echo number_format($order['tax'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="text-end"><strong>Total:</strong></td>
                                        <td class="text-end"><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Instrucciones de Pago</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($order['payment_method'] === 'credit_card'): ?>
                            <p>Se te redirigirá a nuestra pasarela de pago segura para completar la transacción con tu tarjeta de crédito.</p>
                            <p>Una vez que el pago sea procesado, recibirás un correo electrónico con los enlaces para descargar tus fotos en alta resolución.</p>
                            <div class="d-grid gap-2">
                                <a href="#" class="btn btn-primary">Proceder al Pago</a>
                            </div>
                        <?php elseif ($order['payment_method'] === 'paypal'): ?>
                            <p>Serás redirigido a PayPal para completar tu pago de forma segura.</p>
                            <p>Una vez que el pago sea confirmado, recibirás un correo electrónico con los enlaces para descargar tus fotos en alta resolución.</p>
                            <div class="d-grid gap-2">
                                <a href="#" class="btn btn-primary">Pagar con PayPal</a>
                            </div>
                        <?php elseif ($order['payment_method'] === 'bank_transfer'): ?>
                            <p>Por favor, realiza una transferencia bancaria con los siguientes datos:</p>
                            <div class="alert alert-info">
                                <p><strong>Banco:</strong> Banco Nacional</p>
                                <p><strong>Titular:</strong> FoTeam Photography</p>
                                <p><strong>Cuenta:</strong> 1234-5678-90-1234567890</p>
                                <p><strong>Concepto:</strong> Pedido #<?php echo $order_id; ?></p>
                                <p><strong>Importe:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p>
                            </div>
                            <p>Una vez que recibamos tu pago, procesaremos tu pedido y recibirás un correo electrónico con los enlaces para descargar tus fotos en alta resolución.</p>
                        <?php elseif ($order['payment_method'] === 'bancard'): ?>
                            <div class="alert alert-info">
                                <h5>Pago con Bancard</h5>
                                <?php if ($order['status'] === 'completed'): ?>
                                    <p>Tu pago ha sido procesado exitosamente.</p>
                                    <?php if (!empty($order['payment_id'])): ?>
                                        <p><strong>Número de autorización:</strong> <?php echo h($order['payment_id']); ?></p>
                                    <?php endif; ?>
                                <?php elseif ($order['status'] === 'pending'): ?>
                                    <p>Tu pago está siendo procesado. Por favor espera un momento...</p>
                                <?php else: ?>
                                    <p>Hubo un problema con el pago. Por favor intenta nuevamente.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-center mb-4">
                    <p>Se ha enviado un correo electrónico con los detalles de tu pedido a <strong><?php echo h($order['email']); ?></strong></p>
                    <p>Si tienes alguna pregunta sobre tu pedido, por favor contáctanos a <a href="mailto:info@foteam.com">info@foteam.com</a></p>
                    <a href="/public/" class="btn btn-primary">Volver a la Página Principal</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
