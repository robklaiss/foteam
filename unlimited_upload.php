<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Redirect to login if not logged in
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = 'unlimited_upload.php';
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

// Include header
include 'includes/header.php';
?>

<div class="container">
    <h1>Subida Ilimitada de Fotos</h1>
    
    <?php if (empty($marathons)): ?>
    <div class="alert alert-warning">
        <p>No hay maratones disponibles. Debe crear un maratón antes de subir fotos.</p>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createMarathonModal">
            <i class="bi bi-plus-circle"></i> Crear Nuevo Maratón
        </button>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Subir Fotos (Sin Límite de Tamaño)</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <h6><i class="bi bi-info-circle"></i> Información Importante:</h6>
                <ul>
                    <li>Este método permite subir archivos de cualquier tamaño.</li>
                    <li>Puede subir fotos individuales o archivos ZIP con múltiples fotos.</li>
                    <li>Los archivos se suben en fragmentos pequeños para evitar problemas de límite de tamaño.</li>
                    <li>No cierre esta ventana hasta que la subida haya finalizado.</li>
                </ul>
            </div>
            
            <form id="upload-form">
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
                    <label for="files" class="form-label">Seleccione Archivos</label>
                    <input type="file" class="form-control" id="files" name="files" multiple accept=".jpg,.jpeg,.png,.gif,.zip,.ZIP" required>
                    <div class="form-text">Puede seleccionar fotos individuales (JPG, PNG, GIF) o archivos ZIP con múltiples fotos.</div>
                </div>
                
                <div id="upload-progress" class="mb-3" style="display: none;">
                    <h6>Progreso de la Subida:</h6>
                    <div class="progress mb-2">
                        <div id="total-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                    </div>
                    <div id="current-file" class="text-muted small"></div>
                    <div id="upload-stats" class="d-flex justify-content-between text-muted small">
                        <span id="upload-percentage">0%</span>
                        <span id="upload-speed">0 KB/s</span>
                        <span id="upload-remaining">--:--</span>
                    </div>
                </div>
                
                <div id="upload-results" class="mb-3" style="display: none;">
                    <h6>Resultados:</h6>
                    <div class="alert alert-success" id="success-message" style="display: none;"></div>
                    <div class="alert alert-danger" id="error-message" style="display: none;"></div>
                    <ul id="file-results" class="list-group"></ul>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary" id="upload-button">
                        <i class="bi bi-cloud-upload"></i> Subir Archivos
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="mt-3">
        <a href="upload.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver al Método de Subida Estándar
        </a>
    </div>
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
                <input type="hidden" name="redirect_after_create" value="unlimited_upload.php">
                
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
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_public" name="is_public" value="1">
                        <label class="form-check-label" for="is_public">Maratón Público</label>
                        <div class="form-text">Si marca esta opción, otros usuarios podrán ver y subir fotos a este maratón.</div>
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

<script src="https://cdn.jsdelivr.net/npm/spark-md5@3.0.2/spark-md5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('upload-form');
    const filesInput = document.getElementById('files');
    const uploadButton = document.getElementById('upload-button');
    const marathonSelect = document.getElementById('marathon_id');
    const progressContainer = document.getElementById('upload-progress');
    const totalProgressBar = document.getElementById('total-progress-bar');
    const currentFileElement = document.getElementById('current-file');
    const uploadPercentage = document.getElementById('upload-percentage');
    const uploadSpeed = document.getElementById('upload-speed');
    const uploadRemaining = document.getElementById('upload-remaining');
    const resultsContainer = document.getElementById('upload-results');
    const successMessage = document.getElementById('success-message');
    const errorMessage = document.getElementById('error-message');
    const fileResultsList = document.getElementById('file-results');
    
    // Set min date for event_date to today
    const today = new Date().toISOString().split('T')[0];
    if (document.getElementById('event_date')) {
        document.getElementById('event_date').min = today;
    }
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const marathonId = marathonSelect.value;
        const files = filesInput.files;
        
        if (!marathonId) {
            alert('Por favor seleccione un maratón');
            return;
        }
        
        if (files.length === 0) {
            alert('Por favor seleccione al menos un archivo');
            return;
        }
        
        // Prepare for upload
        uploadButton.disabled = true;
        uploadButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Preparando...';
        progressContainer.style.display = 'block';
        resultsContainer.style.display = 'none';
        successMessage.style.display = 'none';
        errorMessage.style.display = 'none';
        fileResultsList.innerHTML = '';
        
        // Generate a unique upload ID
        const uploadId = generateUUID();
        
        // Process each file
        const totalFiles = files.length;
        let completedFiles = 0;
        let successfulFiles = 0;
        let failedFiles = 0;
        
        // Start uploading files one by one
        processNextFile(0);
        
        function processNextFile(index) {
            if (index >= totalFiles) {
                // All files processed
                uploadButton.disabled = false;
                uploadButton.innerHTML = '<i class="bi bi-cloud-upload"></i> Subir Archivos';
                
                // Show final results
                resultsContainer.style.display = 'block';
                
                if (successfulFiles > 0) {
                    successMessage.style.display = 'block';
                    successMessage.textContent = `Se subieron ${successfulFiles} archivos correctamente` + 
                                               (failedFiles > 0 ? ` (${failedFiles} con errores)` : '');
                }
                
                if (failedFiles > 0 && successfulFiles === 0) {
                    errorMessage.style.display = 'block';
                    errorMessage.textContent = 'Error al subir los archivos. Por favor intente nuevamente.';
                }
                
                return;
            }
            
            const file = files[index];
            const fileName = file.name;
            const fileSize = file.size;
            const fileType = file.type;
            
            // Update progress display
            currentFileElement.textContent = `Procesando: ${fileName} (${formatFileSize(fileSize)})`;
            totalProgressBar.style.width = '0%';
            uploadPercentage.textContent = '0%';
            uploadSpeed.textContent = '0 KB/s';
            uploadRemaining.textContent = '--:--';
            
            // Check if it's an image or ZIP file
            const fileExt = fileName.split('.').pop().toLowerCase();
            const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExt);
            const isZip = ['zip'].includes(fileExt);
            
            if (!isImage && !isZip) {
                // Unsupported file type
                const listItem = document.createElement('li');
                listItem.className = 'list-group-item list-group-item-danger';
                listItem.innerHTML = `<strong>${fileName}</strong>: Tipo de archivo no soportado`;
                fileResultsList.appendChild(listItem);
                
                failedFiles++;
                completedFiles++;
                
                // Process next file
                processNextFile(index + 1);
                return;
            }
            
            // Upload in chunks
            const chunkSize = 2 * 1024 * 1024; // 2MB chunks
            const chunks = Math.ceil(fileSize / chunkSize);
            let currentChunk = 0;
            let startTime = Date.now();
            let bytesUploaded = 0;
            
            uploadNextChunk();
            
            function uploadNextChunk() {
                const start = currentChunk * chunkSize;
                const end = Math.min(fileSize, start + chunkSize);
                const chunk = file.slice(start, end);
                
                const formData = new FormData();
                formData.append('file', chunk);
                formData.append('name', fileName);
                formData.append('upload_id', uploadId);
                formData.append('chunk', currentChunk);
                formData.append('chunks', chunks);
                formData.append('marathon_id', marathonId);
                
                const xhr = new XMLHttpRequest();
                
                xhr.open('POST', 'chunk_upload.php', true);
                
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const chunkProgress = e.loaded / e.total;
                        const totalProgress = (currentChunk + chunkProgress) / chunks;
                        const progressPercent = Math.round(totalProgress * 100);
                        
                        totalProgressBar.style.width = progressPercent + '%';
                        uploadPercentage.textContent = progressPercent + '%';
                        
                        // Calculate speed
                        const currentTime = Date.now();
                        const elapsedTime = (currentTime - startTime) / 1000; // seconds
                        const currentBytesUploaded = bytesUploaded + e.loaded;
                        const speed = currentBytesUploaded / elapsedTime; // bytes per second
                        
                        uploadSpeed.textContent = formatFileSize(speed) + '/s';
                        
                        // Calculate time remaining
                        const remainingBytes = fileSize - currentBytesUploaded;
                        if (speed > 0) {
                            const remainingTime = remainingBytes / speed; // seconds
                            uploadRemaining.textContent = formatTime(remainingTime);
                        }
                    }
                });
                
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            // Update bytes uploaded
                            bytesUploaded += chunk.size;
                            
                            try {
                                const response = JSON.parse(xhr.responseText);
                                
                                if (currentChunk < chunks - 1) {
                                    // More chunks to upload
                                    currentChunk++;
                                    uploadNextChunk();
                                } else {
                                    // All chunks uploaded
                                    const listItem = document.createElement('li');
                                    
                                    if (response.success) {
                                        listItem.className = 'list-group-item list-group-item-success';
                                        listItem.innerHTML = `<strong>${fileName}</strong>: Subido correctamente`;
                                        successfulFiles++;
                                    } else {
                                        listItem.className = 'list-group-item list-group-item-danger';
                                        listItem.innerHTML = `<strong>${fileName}</strong>: ${response.message}`;
                                        failedFiles++;
                                    }
                                    
                                    fileResultsList.appendChild(listItem);
                                    completedFiles++;
                                    
                                    // Process next file
                                    processNextFile(index + 1);
                                }
                            } catch (e) {
                                console.error('Error parsing response:', e);
                                
                                const listItem = document.createElement('li');
                                listItem.className = 'list-group-item list-group-item-danger';
                                listItem.innerHTML = `<strong>${fileName}</strong>: Error al procesar la respuesta del servidor`;
                                fileResultsList.appendChild(listItem);
                                
                                failedFiles++;
                                completedFiles++;
                                
                                // Process next file
                                processNextFile(index + 1);
                            }
                        } else {
                            // HTTP error
                            const listItem = document.createElement('li');
                            listItem.className = 'list-group-item list-group-item-danger';
                            listItem.innerHTML = `<strong>${fileName}</strong>: Error de red (${xhr.status})`;
                            fileResultsList.appendChild(listItem);
                            
                            failedFiles++;
                            completedFiles++;
                            
                            // Process next file
                            processNextFile(index + 1);
                        }
                    }
                };
                
                xhr.send(formData);
            }
        }
    });
    
    // Helper functions
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    function formatTime(seconds) {
        if (!isFinite(seconds) || seconds < 0) {
            return '--:--';
        }
        
        const minutes = Math.floor(seconds / 60);
        seconds = Math.floor(seconds % 60);
        
        return `${minutes}:${seconds.toString().padStart(2, '0')}`;
    }
});
</script>

<?php include 'includes/footer.php'; ?>
