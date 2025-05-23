<?php\n// Include bootstrap first to configure environment and settings\nrequire_once dirname(__DIR__) . '/includes/bootstrap.php';\n\n// Include config and functions\nrequire_once dirname(__DIR__) . '/includes/config.php';
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
$marathon_id = $_POST['marathon_id'] ?? '';

// Validate marathon ID
if (empty($marathon_id)) {
    set_flash_message('Maratón no válido', 'danger');
    redirect('manage_marathons.php');
    exit;
}

// Process based on action
switch ($action) {
    case 'add_photographer':
        // Add photographer to marathon
        $photographer_id = $_POST['photographer_id'] ?? '';
        
        // Validate input
        if (empty($photographer_id)) {
            set_flash_message('Fotógrafo no válido', 'danger');
            redirect('edit_marathon.php?id=' . $marathon_id);
            exit;
        }
        
        // Check if already assigned
        $db = db_connect();
        $stmt = $db->prepare("
            SELECT id FROM photographer_marathons 
            WHERE user_id = :user_id AND marathon_id = :marathon_id
        ");
        $stmt->bindValue(':user_id', $photographer_id, SQLITE3_INTEGER);
        $stmt->bindValue(':marathon_id', $marathon_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        if ($result->fetchArray(SQLITE3_ASSOC)) {
            $result->finalize();
            $db->close();
            set_flash_message('Este fotógrafo ya está asignado a este maratón', 'warning');
            redirect('edit_marathon.php?id=' . $marathon_id);
            exit;
        }
        
        // Get marathon and photographer names for logging
        $marathon_name = '';
        $photographer_name = '';
        
        $stmt = $db->prepare("SELECT name FROM marathons WHERE id = :id");
        $stmt->bindValue(':id', $marathon_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $marathon_name = $row['name'];
        }
        
        $stmt = $db->prepare("SELECT username FROM users WHERE id = :id");
        $stmt->bindValue(':id', $photographer_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $photographer_name = $row['username'];
        }
        
        // Assign photographer to marathon
        $stmt = $db->prepare("
            INSERT INTO photographer_marathons (user_id, marathon_id)
            VALUES (:user_id, :marathon_id)
        ");
        $stmt->bindValue(':user_id', $photographer_id, SQLITE3_INTEGER);
        $stmt->bindValue(':marathon_id', $marathon_id, SQLITE3_INTEGER);
        
        try {
            $stmt->execute();
            
            // Log activity
            log_activity($_SESSION['user_id'], 'assign_photographer', "Assigned photographer '$photographer_name' to marathon '$marathon_name'");
            
            set_flash_message('Fotógrafo asignado correctamente al maratón', 'success');
        } catch (Exception $e) {
            set_flash_message('Error al asignar fotógrafo: ' . $e->getMessage(), 'danger');
        }
        
        $db->close();
        redirect('edit_marathon.php?id=' . $marathon_id);
        break;
        
    case 'remove_photographer':
        // Remove photographer from marathon
        $photographer_id = $_POST['photographer_id'] ?? '';
        
        // Validate input
        if (empty($photographer_id)) {
            set_flash_message('Fotógrafo no válido', 'danger');
            redirect('edit_marathon.php?id=' . $marathon_id);
            exit;
        }
        
        $db = db_connect();
        
        // Get marathon and photographer names for logging
        $marathon_name = '';
        $photographer_name = '';
        
        $stmt = $db->prepare("SELECT name FROM marathons WHERE id = :id");
        $stmt->bindValue(':id', $marathon_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $marathon_name = $row['name'];
        }
        
        $stmt = $db->prepare("SELECT username FROM users WHERE id = :id");
        $stmt->bindValue(':id', $photographer_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $photographer_name = $row['username'];
        }
        
        // Check if photographer has images for this marathon
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM images 
            WHERE user_id = :user_id AND marathon_id = :marathon_id
        ");
        $stmt->bindValue(':user_id', $photographer_id, SQLITE3_INTEGER);
        $stmt->bindValue(':marathon_id', $marathon_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $image_count = $result->fetchArray(SQLITE3_ASSOC)['count'];
        
        if ($image_count > 0) {
            set_flash_message("No se puede eliminar al fotógrafo porque tiene $image_count fotos asociadas a este maratón", 'danger');
            redirect('edit_marathon.php?id=' . $marathon_id);
            exit;
        }
        
        // Remove photographer from marathon
        $stmt = $db->prepare("
            DELETE FROM photographer_marathons 
            WHERE user_id = :user_id AND marathon_id = :marathon_id
        ");
        $stmt->bindValue(':user_id', $photographer_id, SQLITE3_INTEGER);
        $stmt->bindValue(':marathon_id', $marathon_id, SQLITE3_INTEGER);
        
        try {
            $stmt->execute();
            
            // Log activity
            log_activity($_SESSION['user_id'], 'remove_photographer', "Removed photographer '$photographer_name' from marathon '$marathon_name'");
            
            set_flash_message('Fotógrafo eliminado correctamente del maratón', 'success');
        } catch (Exception $e) {
            set_flash_message('Error al eliminar fotógrafo: ' . $e->getMessage(), 'danger');
        }
        
        $db->close();
        redirect('edit_marathon.php?id=' . $marathon_id);
        break;
        
    default:
        set_flash_message('Acción no válida', 'danger');
        redirect('edit_marathon.php?id=' . $marathon_id);
}
?>
