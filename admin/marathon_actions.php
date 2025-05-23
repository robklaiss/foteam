<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/admin_functions.php';

// Redirect to login if not logged in or not admin
if (!is_logged_in() || !is_admin()) {
    $_SESSION['redirect_after_login'] = '/admin/manage_marathons.php';
    set_flash_message('Debe iniciar sesión como administrador para acceder a esta sección', 'warning');
    redirect('../login.php');
}

// Check CSRF token
check_csrf_token();

// Get action
$action = $_POST['action'] ?? '';

// Process based on action
switch ($action) {
    case 'toggle_status':
        // Toggle marathon public/private status
        $marathon_id = $_POST['marathon_id'] ?? '';
        $is_public = $_POST['is_public'] ?? '';
        
        // Validate input
        if (empty($marathon_id) || !in_array($is_public, ['0', '1'])) {
            set_flash_message('Parámetros inválidos', 'danger');
            redirect('manage_marathons.php');
            exit;
        }
        
        // Update marathon status
        $db = db_connect();
        
        // Get marathon name for logging
        $marathon_name = '';
        $stmt = $db->prepare("SELECT name FROM marathons WHERE id = :id");
        $stmt->bindValue(':id', $marathon_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $marathon_name = $row['name'];
        }
        $result->finalize();
        
        // Update status
        $stmt = $db->prepare("
            UPDATE marathons 
            SET is_public = :is_public 
            WHERE id = :marathon_id
        ");
        $stmt->bindValue(':is_public', $is_public, SQLITE3_INTEGER);
        $stmt->bindValue(':marathon_id', $marathon_id, SQLITE3_INTEGER);
        
        try {
            $stmt->execute();
            
            // Log activity
            $status_text = $is_public == '1' ? 'public' : 'private';
            log_activity($_SESSION['user_id'], 'toggle_marathon_status', "Changed marathon '$marathon_name' to $status_text");
            
            set_flash_message('Estado del maratón actualizado correctamente', 'success');
        } catch (Exception $e) {
            set_flash_message('Error al actualizar el estado del maratón: ' . $e->getMessage(), 'danger');
        }
        
        $db->close();
        redirect('manage_marathons.php');
        break;
        
    case 'update_marathon':
        // Update marathon details
        $marathon_id = $_POST['marathon_id'] ?? '';
        $name = $_POST['name'] ?? '';
        $event_date = $_POST['event_date'] ?? '';
        $location = $_POST['location'] ?? '';
        $is_public = isset($_POST['is_public']) ? 1 : 0;
        
        // Validate input
        $errors = [];
        
        if (empty($marathon_id)) {
            $errors[] = 'Maratón no válido';
        }
        
        if (empty($name)) {
            $errors[] = 'El nombre del maratón es obligatorio';
        }
        
        if (empty($event_date)) {
            $errors[] = 'La fecha del maratón es obligatoria';
        } else if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
            $errors[] = 'El formato de fecha debe ser YYYY-MM-DD';
        }
        
        if (empty($errors)) {
            // Update marathon
            $db = db_connect();
            $stmt = $db->prepare("
                UPDATE marathons 
                SET name = :name, event_date = :event_date, location = :location, is_public = :is_public 
                WHERE id = :marathon_id
            ");
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':event_date', $event_date, SQLITE3_TEXT);
            $stmt->bindValue(':location', $location, SQLITE3_TEXT);
            $stmt->bindValue(':is_public', $is_public, SQLITE3_INTEGER);
            $stmt->bindValue(':marathon_id', $marathon_id, SQLITE3_INTEGER);
            
            try {
                $stmt->execute();
                
                // Log activity
                log_activity($_SESSION['user_id'], 'update_marathon', "Updated marathon: $name");
                
                set_flash_message('Maratón actualizado correctamente', 'success');
                redirect('manage_marathons.php');
            } catch (Exception $e) {
                set_flash_message('Error al actualizar el maratón: ' . $e->getMessage(), 'danger');
                redirect('edit_marathon.php?id=' . $marathon_id);
            }
            
            $db->close();
        } else {
            // Display all errors
            foreach ($errors as $error) {
                set_flash_message($error, 'danger');
            }
            redirect('edit_marathon.php?id=' . $marathon_id);
        }
        break;
        
    case 'delete_marathon':
        // Delete marathon
        $marathon_id = $_POST['marathon_id'] ?? '';
        
        // Validate input
        if (empty($marathon_id)) {
            set_flash_message('Maratón no válido', 'danger');
            redirect('manage_marathons.php');
            exit;
        }
        
        $db = db_connect();
        
        // Get marathon name for logging
        $marathon_name = '';
        $stmt = $db->prepare("SELECT name FROM marathons WHERE id = :id");
        $stmt->bindValue(':id', $marathon_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $marathon_name = $row['name'];
        }
        $result->finalize();
        
        // Check if marathon has images
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM images WHERE marathon_id = :marathon_id");
        $stmt->bindValue(':marathon_id', $marathon_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $image_count = $result->fetchArray(SQLITE3_ASSOC)['count'];
        $result->finalize();
        
        if ($image_count > 0) {
            set_flash_message("No se puede eliminar el maratón porque tiene $image_count fotos asociadas", 'danger');
            redirect('manage_marathons.php');
            exit;
        }
        
        // Delete marathon assignments
        $stmt = $db->prepare("DELETE FROM photographer_marathons WHERE marathon_id = :marathon_id");
        $stmt->bindValue(':marathon_id', $marathon_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Delete marathon
        $stmt = $db->prepare("DELETE FROM marathons WHERE id = :marathon_id");
        $stmt->bindValue(':marathon_id', $marathon_id, SQLITE3_INTEGER);
        
        try {
            $stmt->execute();
            
            // Log activity
            log_activity($_SESSION['user_id'], 'delete_marathon', "Deleted marathon: $marathon_name");
            
            set_flash_message('Maratón eliminado correctamente', 'success');
        } catch (Exception $e) {
            set_flash_message('Error al eliminar el maratón: ' . $e->getMessage(), 'danger');
        }
        
        $db->close();
        redirect('manage_marathons.php');
        break;
        
    default:
        set_flash_message('Acción no válida', 'danger');
        redirect('manage_marathons.php');
}
?>
