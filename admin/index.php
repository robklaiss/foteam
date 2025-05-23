<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Redirect to login if not logged in or not admin
if (!is_logged_in() || !is_admin()) {
    $_SESSION['redirect_after_login'] = '/admin/';
    set_flash_message('Debe iniciar sesión como administrador para acceder a esta sección', 'warning');
    redirect('../login.php');
}

// Get statistics
$db = db_connect();

// Total users
$stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
$result = $stmt->execute();
$total_users = $result->fetchArray(SQLITE3_ASSOC)['count'];
$result->finalize();

// Total photographers (using username pattern matching for now)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE username LIKE '%fotografo%' OR username LIKE '%photographer%'");
$result = $stmt->execute();
$total_photographers = $result->fetchArray(SQLITE3_ASSOC)['count'];
$result->finalize();

// Total marathons
$stmt = $db->prepare("SELECT COUNT(*) as count FROM marathons");
$result = $stmt->execute();
$total_marathons = $result->fetchArray(SQLITE3_ASSOC)['count'];
$result->finalize();

// Total images
$stmt = $db->prepare("SELECT COUNT(*) as count FROM images");
$result = $stmt->execute();
$total_images = $result->fetchArray(SQLITE3_ASSOC)['count'];
$result->finalize();

// Total orders
$stmt = $db->prepare("SELECT COUNT(*) as count FROM orders");
$result = $stmt->execute();
$total_orders = $result->fetchArray(SQLITE3_ASSOC)['count'];
$result->finalize();

// Total revenue
$stmt = $db->prepare("SELECT SUM(total_amount) as total FROM orders WHERE status = 'completed'");
$result = $stmt->execute();
$revenue = $result->fetchArray(SQLITE3_ASSOC)['total'] ?: 0;
$result->finalize();

$db->close();

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="jumbotron bg-light p-5 rounded">
        <h1 class="display-4">Panel de Administración</h1>
        <p class="lead">Gestiona maratones, fotógrafos y usuarios</p>
        <hr class="my-4">
        
        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-md-4">
                <a href="create_marathon.php" class="btn btn-primary w-100 p-3">
                    <i class="bi bi-plus-circle fs-4"></i><br>
                    Crear Nuevo Maratón
                </a>
            </div>
            <div class="col-md-4">
                <a href="manage_photographers.php" class="btn btn-primary w-100 p-3">
                    <i class="bi bi-camera fs-4"></i><br>
                    Gestionar Fotógrafos
                </a>
            </div>
            <div class="col-md-4">
                <a href="view_orders.php" class="btn btn-primary w-100 p-3">
                    <i class="bi bi-cart-check fs-4"></i><br>
                    Ver Pedidos
                </a>
            </div>
        </div>
        
        <!-- Statistics -->
        <h2 class="mb-4">Estadísticas del Sitio</h2>
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <h3 class="card-title"><?php echo $total_users; ?></h3>
                        <p class="card-text">Usuarios Registrados</p>
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href="manage_users.php" class="btn btn-sm btn-outline-primary">Gestionar Usuarios</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <h3 class="card-title"><?php echo $total_photographers; ?></h3>
                        <p class="card-text">Fotógrafos</p>
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href="manage_photographers.php" class="btn btn-sm btn-outline-primary">Gestionar Fotógrafos</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <h3 class="card-title"><?php echo $total_marathons; ?></h3>
                        <p class="card-text">Maratones</p>
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href="manage_marathons.php" class="btn btn-sm btn-outline-primary">Gestionar Maratones</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <h3 class="card-title"><?php echo $total_images; ?></h3>
                        <p class="card-text">Fotos Subidas</p>
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href="manage_images.php" class="btn btn-sm btn-outline-primary">Gestionar Fotos</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <h3 class="card-title"><?php echo $total_orders; ?></h3>
                        <p class="card-text">Pedidos</p>
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href="view_orders.php" class="btn btn-sm btn-outline-primary">Ver Pedidos</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <h3 class="card-title">$<?php echo number_format($revenue, 2); ?></h3>
                        <p class="card-text">Ingresos Totales</p>
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href="revenue_report.php" class="btn btn-sm btn-outline-primary">Ver Informe</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <h2 class="mb-4 mt-3">Actividad Reciente</h2>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Usuario</th>
                                <th>Acción</th>
                                <th>Detalles</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // In a real implementation, you would fetch recent activity from a log table
                            // This is just a placeholder
                            ?>
                            <tr>
                                <td colspan="4" class="text-center">No hay actividad reciente para mostrar</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
