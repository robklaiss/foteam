<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Redirect to login if not logged in
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = 'manage_marathons.php';
    set_flash_message('Debe iniciar sesión para administrar maratones', 'warning');
    redirect('login.php');
}

// Get user's marathons
$marathons = get_user_marathons($_SESSION['user_id']);

// Process marathon creation form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    // Check CSRF token
    check_csrf_token();
    
    $name = $_POST['name'] ?? '';
    $event_date = $_POST['event_date'] ?? '';
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    $location = $_POST['location'] ?? '';
    
    if (empty($name) || empty($event_date)) {
        set_flash_message('Por favor complete todos los campos obligatorios', 'danger');
    } else {
        $result = create_marathon($name, $event_date, $is_public, $_SESSION['user_id'], $location);
        
        if ($result['success']) {
            set_flash_message('Maratón creado correctamente', 'success');
            
            // Check if there's a redirect after create parameter
            if (isset($_POST['redirect_after_create']) && !empty($_POST['redirect_after_create'])) {
                redirect($_POST['redirect_after_create']);
            } else {
                redirect('manage_marathons.php');
            }
        } else {
            set_flash_message($result['message'], 'danger');
        }
    }
}

// Process marathon update form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    // Check CSRF token
    check_csrf_token();
    
    $marathon_id = $_POST['marathon_id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $event_date = $_POST['event_date'] ?? '';
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    $location = $_POST['location'] ?? '';
    
    if (empty($name) || empty($event_date) || $marathon_id <= 0) {
        set_flash_message('Por favor complete todos los campos obligatorios', 'danger');
    } else {
        // Verify ownership
        $marathon = get_marathon($marathon_id);
        if (!$marathon || $marathon['user_id'] != $_SESSION['user_id']) {
            set_flash_message('No tiene permiso para editar este maratón', 'danger');
        } else {
            $success = update_marathon($marathon_id, $name, $event_date, $is_public, $location);
            
            if ($success) {
                set_flash_message('Maratón actualizado correctamente', 'success');
                redirect('manage_marathons.php');
            } else {
                set_flash_message('Error al actualizar el maratón', 'danger');
            }
        }
    }
}

// Include header
include 'includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Administrar Maratones</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createMarathonModal">
            <i class="bi bi-plus-circle"></i> Nuevo Maratón
        </button>
    </div>
    
    <?php if (empty($marathons)): ?>
    <div class="alert alert-info">
        <p>No tiene maratones creados. Utilice el botón "Nuevo Maratón" para crear uno.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Nombre</th>
                    <th>Fecha</th>
                    <th>Ubicación</th>
                    <th>Público</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($marathons as $marathon): ?>
                <tr>
                    <td><?php echo h($marathon['name']); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($marathon['event_date'])); ?></td>
                    <td><?php echo h($marathon['location'] ?? ''); ?></td>
                    <td>
                        <?php if ($marathon['is_public']): ?>
                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Sí</span>
                        <?php else: ?>
                        <span class="badge bg-secondary"><i class="bi bi-x-circle"></i> No</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="gallery.php?marathon_id=<?php echo $marathon['id']; ?>" class="btn btn-info">
                                <i class="bi bi-images"></i> Ver Fotos
                            </a>
                            <button type="button" class="btn btn-primary edit-marathon" 
                                    data-id="<?php echo $marathon['id']; ?>"
                                    data-name="<?php echo h($marathon['name']); ?>"
                                    data-date="<?php echo date('Y-m-d', strtotime($marathon['event_date'])); ?>"
                                    data-location="<?php echo h($marathon['location'] ?? ''); ?>"
                                    data-public="<?php echo $marathon['is_public'] ? '1' : '0'; ?>">
                                <i class="bi bi-pencil"></i> Editar
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Create Marathon Modal -->
<div class="modal fade" id="createMarathonModal" tabindex="-1" aria-labelledby="createMarathonModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="createMarathonModalLabel">Crear Nuevo Maratón</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="manage_marathons.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="create">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre del Maratón *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="event_date" class="form-label">Fecha del Evento *</label>
                        <input type="date" class="form-control" id="event_date" name="event_date" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="location" class="form-label">Ubicación</label>
                        <input type="text" class="form-control" id="location" name="location">
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="is_public" name="is_public">
                        <label class="form-check-label" for="is_public">
                            Maratón Público (visible para todos los usuarios)
                        </label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Maratón</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Marathon Modal -->
<div class="modal fade" id="editMarathonModal" tabindex="-1" aria-labelledby="editMarathonModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editMarathonModalLabel">Editar Maratón</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="manage_marathons.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="marathon_id" id="edit_marathon_id">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Nombre del Maratón *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_event_date" class="form-label">Fecha del Evento *</label>
                        <input type="date" class="form-control" id="edit_event_date" name="event_date" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_location" class="form-label">Ubicación</label>
                        <input type="text" class="form-control" id="edit_location" name="location">
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="edit_is_public" name="is_public">
                        <label class="form-check-label" for="edit_is_public">
                            Maratón Público (visible para todos los usuarios)
                        </label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set min date for event_date to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('event_date').min = today;
    
    // Handle edit marathon button clicks
    const editButtons = document.querySelectorAll('.edit-marathon');
    const editModal = new bootstrap.Modal(document.getElementById('editMarathonModal'));
    
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const date = this.getAttribute('data-date');
            const location = this.getAttribute('data-location');
            const isPublic = this.getAttribute('data-public') === '1';
            
            document.getElementById('edit_marathon_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_event_date').value = date;
            document.getElementById('edit_location').value = location;
            document.getElementById('edit_is_public').checked = isPublic;
            
            editModal.show();
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
