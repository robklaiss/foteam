<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/admin_functions.php';
require_once '../includes/helper_functions.php';

// Redirect to login if not logged in or not admin
if (!is_logged_in() || !is_admin()) {
    $_SESSION['redirect_after_login'] = '/admin/manage_photographers.php';
    set_flash_message('Debe iniciar sesión como administrador para acceder a esta sección', 'warning');
    redirect('../login.php');
}

// Process form submission for creating a new photographer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_photographer') {
    // Check CSRF token
    check_csrf_token();
    
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($email) || empty($password)) {
        set_flash_message('Todos los campos son obligatorios', 'danger');
    } else {
        $photographer_id = create_photographer($username, $email, $password);
        if ($photographer_id) {
            // Redirect to avoid form resubmission
            redirect('manage_photographers.php');
        }
    }
}

// Get all photographers
$photographers = get_all_photographers();

// Get all marathons for assignment
$marathons = get_public_marathons();

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="jumbotron bg-light p-5 rounded">
        <h1 class="display-4">Gestionar Fotógrafos</h1>
        <p class="lead">Administre los fotógrafos y asigne maratones</p>
        <hr class="my-4">
        
        <!-- Actions -->
        <div class="d-flex justify-content-between mb-4">
            <a href="/admin/" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Volver al Panel
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPhotographerModal">
                <i class="bi bi-person-plus"></i> Crear Nuevo Fotógrafo
            </button>
        </div>
        
        <!-- Photographers List -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($photographers)): ?>
                    <div class="alert alert-info">
                        No hay fotógrafos registrados. Cree un nuevo fotógrafo para comenzar.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuario</th>
                                    <th>Email</th>
                                    <th>Fecha de Registro</th>
                                    <th>Maratones Asignados</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($photographers as $photographer): ?>
                                    <tr>
                                        <td><?php echo $photographer['id']; ?></td>
                                        <td><?php echo h($photographer['username']); ?></td>
                                        <td><?php echo h($photographer['email']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($photographer['created_at'])); ?></td>
                                        <td>
                                            <?php
                                            // Get marathons assigned to this photographer
                                            // In a real application, this would be from a proper relationship table
                                            // For now, we'll just show a placeholder
                                            echo '<span class="text-muted">Asignar maratones</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#assignMarathonModal" data-photographer-id="<?php echo $photographer['id']; ?>" data-photographer-name="<?php echo h($photographer['username']); ?>">
                                                Asignar Maratón
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#resetPasswordModal" data-photographer-id="<?php echo $photographer['id']; ?>" data-photographer-name="<?php echo h($photographer['username']); ?>">
                                                Resetear Contraseña
                                            </button>
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

<!-- Create Photographer Modal -->
<div class="modal fade" id="createPhotographerModal" tabindex="-1" aria-labelledby="createPhotographerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createPhotographerModalLabel">Crear Nuevo Fotógrafo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="manage_photographers.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="create_photographer">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Nombre de Usuario</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmar Contraseña</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Fotógrafo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Marathon Modal -->
<div class="modal fade" id="assignMarathonModal" tabindex="-1" aria-labelledby="assignMarathonModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignMarathonModalLabel">Asignar Maratón a Fotógrafo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="photographer_actions.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="assign_marathon">
                    <input type="hidden" name="photographer_id" id="assign_photographer_id">
                    
                    <p>Asignar maratón a: <strong id="assign_photographer_name"></strong></p>
                    
                    <div class="mb-3">
                        <label for="marathon_id" class="form-label">Seleccionar Maratón</label>
                        <select class="form-select" id="marathon_id" name="marathon_id" required>
                            <option value="">Seleccione un maratón...</option>
                            <?php foreach ($marathons as $marathon): ?>
                                <option value="<?php echo $marathon['id']; ?>">
                                    <?php echo h($marathon['name']); ?> (<?php echo date('d/m/Y', strtotime($marathon['event_date'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Asignar Maratón</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetPasswordModalLabel">Resetear Contraseña</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="photographer_actions.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="photographer_id" id="reset_photographer_id">
                    
                    <p>Resetear contraseña para: <strong id="reset_photographer_name"></strong></p>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nueva Contraseña</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_new_password" class="form-label">Confirmar Nueva Contraseña</label>
                        <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Resetear Contraseña</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript for Modals -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Assign Marathon Modal
    const assignMarathonModal = document.getElementById('assignMarathonModal');
    if (assignMarathonModal) {
        assignMarathonModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const photographerId = button.getAttribute('data-photographer-id');
            const photographerName = button.getAttribute('data-photographer-name');
            
            document.getElementById('assign_photographer_id').value = photographerId;
            document.getElementById('assign_photographer_name').textContent = photographerName;
        });
    }
    
    // Reset Password Modal
    const resetPasswordModal = document.getElementById('resetPasswordModal');
    if (resetPasswordModal) {
        resetPasswordModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const photographerId = button.getAttribute('data-photographer-id');
            const photographerName = button.getAttribute('data-photographer-name');
            
            document.getElementById('reset_photographer_id').value = photographerId;
            document.getElementById('reset_photographer_name').textContent = photographerName;
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
