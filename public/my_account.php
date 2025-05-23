<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Redirect if not logged in
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = '/public/my_account.php';
    set_flash_message('Debe iniciar sesión para acceder a su cuenta', 'warning');
    redirect('../login.php');
}

// Get user details
$db = db_connect();
$stmt = $db->prepare("SELECT * FROM users WHERE id = :user_id");
$stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);
$result->finalize();

if (!$user) {
    $db->close();
    set_flash_message('Usuario no encontrado', 'danger');
    redirect('../logout.php');
}

// Get user orders
$stmt = $db->prepare("
    SELECT o.*, COUNT(oi.id) as item_count
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = :user_id
    GROUP BY o.id
    ORDER BY o.order_date DESC
");
$stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
$result = $stmt->execute();

$orders = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $orders[] = $row;
}
$result->finalize();
$db->close();

// Handle form submission for profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Check CSRF token
    check_csrf_token();
    
    if ($action === 'update_profile') {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate input
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'El nombre es obligatorio';
        }
        
        if (empty($email)) {
            $errors[] = 'El email es obligatorio';
        } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El email no es válido';
        }
        
        // Check if email is already in use by another user
        if ($email !== $user['email']) {
            $db = db_connect();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :user_id");
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            if ($result->fetchArray(SQLITE3_ASSOC)) {
                $errors[] = 'Este email ya está en uso por otro usuario';
            }
            
            $result->finalize();
            $db->close();
        }
        
        // If changing password, validate
        $change_password = false;
        if (!empty($new_password) || !empty($confirm_password)) {
            $change_password = true;
            
            if (empty($current_password)) {
                $errors[] = 'Debe ingresar su contraseña actual para cambiarla';
            } else {
                // Verify current password
                $db = db_connect();
                $stmt = $db->prepare("SELECT password FROM users WHERE id = :user_id");
                $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                $result = $stmt->execute();
                $row = $result->fetchArray(SQLITE3_ASSOC);
                $result->finalize();
                $db->close();
                
                if (!password_verify($current_password, $row['password'])) {
                    $errors[] = 'La contraseña actual es incorrecta';
                }
            }
            
            if (empty($new_password)) {
                $errors[] = 'La nueva contraseña es obligatoria';
            } else if (strlen($new_password) < 6) {
                $errors[] = 'La nueva contraseña debe tener al menos 6 caracteres';
            }
            
            if ($new_password !== $confirm_password) {
                $errors[] = 'Las contraseñas no coinciden';
            }
        }
        
        if (empty($errors)) {
            $db = db_connect();
            
            // Update user profile
            if ($change_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("
                    UPDATE users 
                    SET name = :name, email = :email, phone = :phone, password = :password
                    WHERE id = :user_id
                ");
                $stmt->bindValue(':password', $hashed_password, SQLITE3_TEXT);
            } else {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET name = :name, email = :email, phone = :phone
                    WHERE id = :user_id
                ");
            }
            
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            
            try {
                $stmt->execute();
                
                // Log activity
                log_activity($_SESSION['user_id'], 'update_profile', 'Updated profile information');
                
                set_flash_message('Perfil actualizado correctamente', 'success');
                redirect('my_account.php');
            } catch (Exception $e) {
                set_flash_message('Error al actualizar el perfil: ' . $e->getMessage(), 'danger');
            }
            
            $db->close();
        } else {
            // Display all errors
            foreach ($errors as $error) {
                set_flash_message($error, 'danger');
            }
        }
    }
}

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="jumbotron bg-light p-5 rounded">
        <h1 class="display-4">Mi Cuenta</h1>
        <p class="lead">Gestiona tu perfil y revisa tus pedidos</p>
        <hr class="my-4">
        
        <div class="row">
            <div class="col-md-3">
                <div class="list-group mb-4">
                    <a href="#profile" class="list-group-item list-group-item-action active" data-bs-toggle="list">Mi Perfil</a>
                    <a href="#orders" class="list-group-item list-group-item-action" data-bs-toggle="list">Mis Pedidos</a>
                    <a href="../logout.php" class="list-group-item list-group-item-action text-danger">Cerrar Sesión</a>
                </div>
            </div>
            <div class="col-md-9">
                <div class="tab-content">
                    <!-- Profile Tab -->
                    <div class="tab-pane fade show active" id="profile">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Información Personal</h5>
                            </div>
                            <div class="card-body">
                                <form action="my_account.php" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Nombre Completo</label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo h($user['name'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo h($user['email']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Teléfono</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo h($user['phone'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Nombre de Usuario</label>
                                        <input type="text" class="form-control" id="username" value="<?php echo h($user['username']); ?>" disabled>
                                        <div class="form-text">El nombre de usuario no se puede cambiar</div>
                                    </div>
                                    
                                    <hr>
                                    <h5 class="mb-3">Cambiar Contraseña</h5>
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Contraseña Actual</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">Nueva Contraseña</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">Actualizar Perfil</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Orders Tab -->
                    <div class="tab-pane fade" id="orders">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Historial de Pedidos</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($orders)): ?>
                                    <div class="alert alert-info">
                                        No tienes pedidos realizados. <a href="/public/">Buscar fotos</a>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
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
                                                        <td><?php echo $order['id']; ?></td>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($order['order_date'])); ?></td>
                                                        <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo get_order_status_color($order['status']); ?>">
                                                                <?php echo get_order_status_text($order['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $order['item_count']; ?></td>
                                                        <td>
                                                            <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                Ver Detalles
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Activate tab based on hash in URL
    const hash = window.location.hash;
    if (hash) {
        const tab = document.querySelector(`a[href="${hash}"]`);
        if (tab) {
            tab.click();
        }
    }
    
    // Update URL hash when tab changes
    const tabLinks = document.querySelectorAll('.list-group-item');
    tabLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (this.getAttribute('href').startsWith('#')) {
                history.replaceState(null, null, this.getAttribute('href'));
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
