<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/admin_functions.php';

// Redirect to login if not logged in or not admin
if (!is_logged_in() || !is_admin()) {
    $_SESSION['redirect_after_login'] = '/admin/create_marathon.php';
    set_flash_message('Debe iniciar sesión como administrador para acceder a esta sección', 'warning');
    redirect('../login.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token
    check_csrf_token();
    
    $name = $_POST['name'] ?? '';
    $event_date = $_POST['event_date'] ?? '';
    $location = $_POST['location'] ?? '';
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    
    // Validate input
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'El nombre del maratón es obligatorio';
    }
    
    if (empty($event_date)) {
        $errors[] = 'La fecha del maratón es obligatoria';
    } else if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
        $errors[] = 'El formato de fecha debe ser YYYY-MM-DD';
    }
    
    if (empty($errors)) {
        $result = create_marathon($name, $event_date, $is_public, $_SESSION['user_id'], $location);
        
        if ($result['success']) {
            // Log activity
            log_activity($_SESSION['user_id'], 'create_marathon', "Created marathon: $name");
            
            set_flash_message('Maratón creado correctamente', 'success');
            redirect('manage_marathons.php');
        } else {
            set_flash_message($result['message'], 'danger');
        }
    } else {
        // Display all errors
        foreach ($errors as $error) {
            set_flash_message($error, 'danger');
        }
    }
}

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Crear Nuevo Maratón</h4>
                </div>
                <div class="card-body">
                    <form action="create_marathon.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre del Maratón</label>
                            <input type="text" class="form-control" id="name" name="name" required value="<?php echo isset($_POST['name']) ? h($_POST['name']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="event_date" class="form-label">Fecha del Evento</label>
                            <input type="date" class="form-control" id="event_date" name="event_date" required value="<?php echo isset($_POST['event_date']) ? h($_POST['event_date']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="location" class="form-label">Ubicación</label>
                            <input type="text" class="form-control" id="location" name="location" value="<?php echo isset($_POST['location']) ? h($_POST['location']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_public" name="is_public" <?php echo isset($_POST['is_public']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_public">Maratón Público</label>
                            <div class="form-text">Si está marcado, el maratón será visible para todos los usuarios.</div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Crear Maratón</button>
                            <a href="manage_marathons.php" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
