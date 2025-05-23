<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/zip_handler.php'; // Add ZIP handler

// Redirect to login if not logged in
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = 'upload.php';
    set_flash_message('Debe iniciar sesión para subir fotos', 'warning');
    redirect('login.php');
}

// Get all available marathons for dropdown (both user's and public ones)
$user_marathons = get_user_marathons($_SESSION['user_id']);
$public_marathons = get_public_marathons();

// Combine and remove duplicates
$marathons = $user_marathons;
$user_marathon_ids = array_column($user_marathons, 'id');

foreach ($public_marathons as $marathon) {
    if (!in_array($marathon['id'], $user_marathon_ids)) {
        $marathons[] = $marathon;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token
    check_csrf_token();
    
    $marathon_id = isset($_POST['marathon_id']) ? (int)$_POST['marathon_id'] : 0;
    $upload_type = isset($_POST['upload_type']) ? $_POST['upload_type'] : 'photos';
    
    // Validate marathon
    if ($marathon_id <= 0) {
        set_flash_message('Por favor seleccione un maratón', 'danger');
    } elseif ($upload_type === 'photos' && empty($_FILES['photos']['name'][0])) {
        set_flash_message('Por favor seleccione al menos una foto', 'danger');
    } elseif ($upload_type === 'zip' && empty($_FILES['zip_file']['name'])) {
        set_flash_message('Por favor seleccione un archivo ZIP', 'danger');
    } else {
        // Check if marathon exists and is either owned by user or is public
        $marathon = get_marathon($marathon_id);
        if (!$marathon) {
            set_flash_message('El maratón seleccionado no existe', 'danger');
            redirect('upload.php');
        }
        
        // Allow upload if marathon is public or owned by user
        if ($marathon['user_id'] != $_SESSION['user_id'] && $marathon['is_public'] != 1) {
            set_flash_message('No tiene permiso para subir fotos a este maratón', 'danger');
            redirect('upload.php');
        }
        
        $success_count = 0;
        $error_count = 0;
        
        // Process based on upload type
        if ($upload_type === 'photos') {
            // Process each uploaded photo
            foreach ($_FILES['photos']['name'] as $key => $name) {
                if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                    $temp_file = $_FILES['photos']['tmp_name'][$key];
                    $file_info = [
                        'name' => $name,
                        'type' => $_FILES['photos']['type'][$key],
                        'tmp_name' => $temp_file,
                        'error' => $_FILES['photos']['error'][$key],
                        'size' => $_FILES['photos']['size'][$key]
                    ];
                    
                    $result = upload_image($file_info, $marathon_id, $_SESSION['user_id']);
                    
                    if ($result['success']) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                } else {
                    $error_count++;
                }
            }
        } else if ($upload_type === 'zip') {
            // Process ZIP file
            if ($_FILES['zip_file']['error'] === UPLOAD_ERR_OK) {
                $result = process_zip_file($_FILES['zip_file'], $marathon_id, $_SESSION['user_id']);
                
                if ($result['success']) {
                    $success_count = $result['success_count'];
                    $error_count = $result['error_count'];
                } else {
                    set_flash_message('Error al procesar el archivo ZIP: ' . $result['message'], 'danger');
                    redirect('upload.php');
                }
            } else {
                set_flash_message('Error al subir el archivo ZIP. Código: ' . $_FILES['zip_file']['error'], 'danger');
                redirect('upload.php');
            }
        }
        
        if ($success_count > 0) {
            set_flash_message("Se subieron $success_count fotos correctamente" . 
                             ($error_count > 0 ? " ($error_count con errores)" : ""), 
                             'success');
            redirect('gallery.php?marathon_id=' . $marathon_id);
        } else {
            set_flash_message('Error al subir las fotos. Por favor intente nuevamente.', 'danger');
        }
    }
}

// Include header
include 'includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>Subir Fotos</h1>
        <a href="unlimited_upload.php" class="btn btn-success">
            <i class="bi bi-lightning-charge"></i> Modo Subida Ilimitada
        </a>
    </div>
    
    <div class="alert alert-info mb-4">
        <p><strong>¿Subiendo archivos grandes?</strong> Si necesita subir archivos muy grandes o muchas fotos, use el <a href="unlimited_upload.php" class="alert-link">Modo de Subida Ilimitada</a> que permite archivos de cualquier tamaño.</p>
    </div>
    
    <?php if (empty($marathons)): ?>
    <div class="alert alert-warning">
        <p>No hay maratones disponibles. Debe crear un maratón antes de subir fotos.</p>
        <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#createMarathonModal">
            <i class="bi bi-plus-circle"></i> Crear Nuevo Maratón
        </button>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Subir Nuevas Fotos</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="upload.php" enctype="multipart/form-data" id="upload-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <label for="marathon_id" class="form-label">Seleccione Maratón</label>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createMarathonModal">
                            <i class="bi bi-plus-circle"></i> Crear Nuevo
                        </button>
                    </div>
                    <select class="form-select" id="marathon_id" name="marathon_id" required>
                        <option value="">-- Seleccione un Maratón --</option>
                        
                        <!-- User's marathons -->
                        <?php if (!empty($user_marathons)): ?>
                        <optgroup label="Mis Maratones">
                            <?php foreach ($user_marathons as $marathon): ?>
                            <option value="<?php echo $marathon['id']; ?>">
                                <?php echo h($marathon['name']); ?> (<?php echo date('d/m/Y', strtotime($marathon['event_date'])); ?>)
                                <?php if (!empty($marathon['location'])): ?> - <?php echo h($marathon['location']); ?><?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endif; ?>
                        
                        <!-- Public marathons (not created by user) -->
                        <?php 
                        $other_marathons = array_filter($marathons, function($m) use ($user_marathon_ids) {
                            return !in_array($m['id'], $user_marathon_ids);
                        });
                        
                        if (!empty($other_marathons)): 
                        ?>
                        <optgroup label="Maratones Públicos">
                            <?php foreach ($other_marathons as $marathon): ?>
                            <option value="<?php echo $marathon['id']; ?>">
                                <?php echo h($marathon['name']); ?> (<?php echo date('d/m/Y', strtotime($marathon['event_date'])); ?>)
                                <?php if (!empty($marathon['location'])): ?> - <?php echo h($marathon['location']); ?><?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Tipo de Subida</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="upload_type" id="upload_type_photos" value="photos" checked>
                        <label class="form-check-label" for="upload_type_photos">
                            Fotos Individuales
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="upload_type" id="upload_type_zip" value="zip">
                        <label class="form-check-label" for="upload_type_zip">
                            Archivo ZIP con Fotos
                        </label>
                    </div>
                </div>
                
                <div id="photos-upload-section" class="mb-3">
                    <label for="photos" class="form-label">Seleccione Fotos</label>
                    <input class="form-control" type="file" id="photos" name="photos[]" multiple accept="image/*">
                    <div class="form-text">Puede seleccionar múltiples archivos. Formatos permitidos: JPG, PNG, GIF.</div>
                </div>
                
                <div id="zip-upload-section" class="mb-3" style="display: none;">
                    <label for="zip_file" class="form-label">Seleccione Archivo ZIP</label>
                    <input class="form-control" type="file" id="zip_file" name="zip_file" accept=".zip,.ZIP">
                    <div class="form-text">Suba un archivo ZIP que contenga imágenes. Formatos permitidos dentro del ZIP: JPG, PNG, GIF.</div>
                </div>
                
                <div id="preview-container" class="row g-2 mb-3" style="display: none;">
                    <h6>Vista Previa:</h6>
                    <div id="loading-preview" style="display: none;">
                        <div class="d-flex justify-content-center mb-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                        </div>
                        <p class="text-center text-muted">Cargando vista previa...</p>
                    </div>
                    <div id="image-preview" class="d-flex flex-wrap"></div>
                </div>
                
                <div class="alert alert-info">
                    <h6><i class="bi bi-info-circle"></i> Información Importante:</h6>
                    <ul>
                        <li>Las fotos se asociarán al maratón seleccionado.</li>
                        <li>Se generarán miniaturas automáticamente.</li>
                        <li>Se intentará detectar números de corredor en las imágenes.</li>
                        <li>Tamaño máximo por archivo: 10MB.</li>
                    </ul>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary" id="upload-button">
                        <i class="bi bi-cloud-upload"></i> Subir Fotos
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const photoInput = document.getElementById('photos');
    const zipInput = document.getElementById('zip_file');
    const previewContainer = document.getElementById('preview-container');
    const imagePreview = document.getElementById('image-preview');
    const loadingPreview = document.getElementById('loading-preview');
    const uploadForm = document.getElementById('upload-form');
    const uploadButton = document.getElementById('upload-button');
    const uploadTypePhotos = document.getElementById('upload_type_photos');
    const uploadTypeZip = document.getElementById('upload_type_zip');
    const photosUploadSection = document.getElementById('photos-upload-section');
    const zipUploadSection = document.getElementById('zip-upload-section');
    
    // Handle upload type selection
    uploadTypePhotos.addEventListener('change', function() {
        if (this.checked) {
            photosUploadSection.style.display = 'block';
            zipUploadSection.style.display = 'none';
            zipInput.value = ''; // Clear ZIP input
            
            // Update required attributes
            photoInput.setAttribute('required', '');
            zipInput.removeAttribute('required');
        }
    });
    
    uploadTypeZip.addEventListener('change', function() {
        if (this.checked) {
            photosUploadSection.style.display = 'none';
            zipUploadSection.style.display = 'block';
            photoInput.value = ''; // Clear photos input
            imagePreview.innerHTML = ''; // Clear preview
            previewContainer.style.display = 'none';
            
            // Update required attributes
            zipInput.setAttribute('required', '');
            photoInput.removeAttribute('required');
        }
    });
    
    photoInput.addEventListener('change', function() {
        // Clear previous previews
        imagePreview.innerHTML = '';
        
        if (this.files.length > 0) {
            previewContainer.style.display = 'block';
            loadingPreview.style.display = 'block'; // Show loading indicator
            imagePreview.style.display = 'none';
            
            // Use setTimeout to allow the UI to update before processing images
            setTimeout(() => {
                // Create preview for each selected file
                for (let i = 0; i < this.files.length; i++) {
                    if (i >= 12) break; // Limit preview to 12 images
                    
                    const file = this.files[i];
                    
                    if (file.type.match('image.*')) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            const previewDiv = document.createElement('div');
                            previewDiv.className = 'col-md-2 mb-2';
                            
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.className = 'img-thumbnail';
                            img.style.height = '100px';
                            img.style.objectFit = 'cover';
                            
                            previewDiv.appendChild(img);
                            imagePreview.appendChild(previewDiv);
                        };
                        
                        reader.readAsDataURL(file);
                    }
                }
                
                if (this.files.length > 12) {
                    const moreText = document.createElement('div');
                    moreText.className = 'col-12 mt-2';
                    moreText.innerHTML = `<em>Y ${this.files.length - 12} archivos más...</em>`;
                    imagePreview.appendChild(moreText);
                }
                
                // Hide loading indicator after all previews are generated
                loadingPreview.style.display = 'none';
                imagePreview.style.display = 'flex';
            }, 50);
        } else {
            previewContainer.style.display = 'none';
        }
    });
    
    uploadForm.addEventListener('submit', function(e) {
        const marathonId = document.getElementById('marathon_id').value;
        const uploadType = document.querySelector('input[name="upload_type"]:checked').value;
        
        if (marathonId === '') {
            e.preventDefault();
            alert('Por favor seleccione un maratón');
            return false;
        }
        
        if (uploadType === 'photos' && photoInput.files.length === 0) {
            e.preventDefault();
            alert('Por favor seleccione al menos una foto');
            return false;
        }
        
        if (uploadType === 'zip' && zipInput.files.length === 0) {
            e.preventDefault();
            alert('Por favor seleccione un archivo ZIP');
            return false;
        }
        
        // Disable button and show loading state
        uploadButton.disabled = true;
        uploadButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Subiendo...';
    });
    
    // Set min date for event_date to today
    const today = new Date().toISOString().split('T')[0];
    if (document.getElementById('event_date')) {
        document.getElementById('event_date').min = today;
    }
});
</script>

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
                <input type="hidden" name="redirect_after_create" value="upload.php">
                
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

<?php include 'includes/footer.php'; ?>
