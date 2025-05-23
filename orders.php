<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Redirect to login if not logged in
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = 'orders.php';
    set_flash_message('Debe iniciar sesión para ver sus pedidos', 'warning');
    redirect('login.php');
}

// Get user orders
$orders = get_user_orders($_SESSION['user_id']);

// Include header
include 'includes/header.php';
?>

<div class="container">
    <h1>Mis Pedidos</h1>
    
    <?php if (!empty($orders)): ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Pedido #</th>
                        <th>Fecha</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Fotos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?php echo $order['id']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($order['order_date'])); ?></td>
                        <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                        <td>
                            <?php if ($order['status'] === 'completed'): ?>
                                <span class="badge bg-success">Completado</span>
                            <?php elseif ($order['status'] === 'cancelled'): ?>
                                <span class="badge bg-danger">Cancelado</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Pendiente</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $order['item_count']; ?></td>
                        <td>
                            <a href="order_confirmation.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-eye"></i> Ver Detalles
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            No tiene pedidos realizados.
        </div>
        <a href="gallery.php" class="btn btn-primary">Explorar Galería</a>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
