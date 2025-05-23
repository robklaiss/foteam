<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/photographer_functions.php';

// Redirect to login if not logged in or not a photographer
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = '/fotografos/upload.php';
    set_flash_message('Debe iniciar sesión como fotógrafo para acceder a esta sección', 'warning');
    redirect('../login.php');
}

// Check if user is a photographer
if (!is_photographer()) {
    set_flash_message('No tiene permisos para acceder a esta sección', 'danger');
    redirect('../index.php');
}

// Get photographer's marathons
$photographer_marathons = get_photographer_marathons($_SESSION['user_id']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token
    check_csrf_token();
    
    $marathon_id = $_POST['marathon_id'] ?? '';
    
    // Validate marathon ID
    if (empty($marathon_id)) {
        set_flash_message('Debe seleccionar un maratón', 'danger');
    } else {
        // Check if marathon is assigned to this photographer
        $marathon_assigned = false;
        foreach ($photographer_marathons as $marathon) {
            if ($marathon['id'] == $marathon_id) {
                $marathon_assigned = true;
                break;
            }
        }
        
        if (!$marathon_assigned) {
            set_flash_message('No tiene permiso para subir fotos a este maratón', 'danger');
        } else if (empty($_FILES['photos']['name'][0])) {
            set_flash_message('Debe seleccionar al menos una foto', 'danger');
        } else {
            // Process each uploaded file
            $success_count = 0;
            $error_count = 0;
            
            foreach ($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['photos']['name'][$key],
                        'type' => $_FILES['photos']['type'][$key],
                        'tmp_name' => $tmp_name,
                        'error' => $_FILES['photos']['error'][$key],
                        'size' => $_FILES['photos']['size'][$key]
                    ];
                    
                    $result = upload_image_with_watermark($file, $marathon_id, $_SESSION['user_id']);
                    
                    if ($result['success']) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                } else {
                    $error_count++;
                }
            }
            
            if ($success_count > 0) {
                set_flash_message("Se subieron $success_count fotos correctamente" . ($error_count > 0 ? " ($error_count con errores)" : ""), $error_count > 0 ? 'warning' : 'success');
                redirect('marathon_photos.php?id=' . $marathon_id);
            } else {
                set_flash_message('Error al subir las fotos. Por favor, inténtelo de nuevo.', 'danger');
            }
        }
    }
}

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Subir Fotos</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($photographer_marathons)): ?>
                        <div class="alert alert-info">
                            No tiene maratones asignados. Contacte al administrador para que le asigne maratones.
                        </div>
                        <a href="/fotografos/" class="btn btn-primary">Volver al Área de Fotógrafos</a>
                    <?php else: ?>
                        <form action="upload.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="mb-3">
                                <label for="marathon_id" class="form-label">Seleccionar Maratón</label>
                                <select class="form-select" id="marathon_id" name="marathon_id" required>
                                    <option value="">Seleccione un maratón...</option>
                                    <?php foreach ($photographer_marathons as $marathon): ?>
                                        <option value="<?php echo $marathon['id']; ?>" <?php echo (isset($_GET['marathon_id']) && $_GET['marathon_id'] == $marathon['id']) ? 'selected' : ''; ?>>
                                            <?php echo h($marathon['name']); ?> (<?php echo date('d/m/Y', strtotime($marathon['event_date'])); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="photos" class="form-label">Seleccionar Fotos</label>
                                <input class="form-control" type="file" id="photos" name="photos[]" multiple accept="image/*" required>
                                <div class="form-text">
                                    Puede seleccionar múltiples fotos. Formatos permitidos: JPG, PNG, GIF.
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <strong>Nota:</strong> Las fotos se procesarán automáticamente para detectar números de corredores y se les aplicará una marca de agua con el logo de FoTeam.
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Subir Fotos</button>
                                <a href="/fotografos/" class="btn btn-outline-secondary">Cancelar</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
