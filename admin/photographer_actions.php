<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/admin_functions.php';

// Redirect to login if not logged in or not admin
if (!is_logged_in() || !is_admin()) {
    $_SESSION['redirect_after_login'] = '/admin/manage_photographers.php';
    set_flash_message('Debe iniciar sesión como administrador para acceder a esta sección', 'warning');
    redirect('../login.php');
}

// Check CSRF token
check_csrf_token();

// Get action
$action = $_POST['action'] ?? '';

// Process based on action
switch ($action) {
    case 'create':
        // Create a new photographer
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate input
        $errors = [];
        
        if (empty($username)) {
            $errors[] = 'El nombre de usuario es obligatorio';
        }
        
        if (empty($email)) {
            $errors[] = 'El email es obligatorio';
        } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El email no es válido';
        }
        
        if (empty($password)) {
            $errors[] = 'La contraseña es obligatoria';
        } else if (strlen($password) < 6) {
            $errors[] = 'La contraseña debe tener al menos 6 caracteres';
        }
        
        if ($password !== $confirm_password) {
            $errors[] = 'Las contraseñas no coinciden';
        }
        
        if (empty($errors)) {
            $result = create_photographer($username, $email, $password);
            
            if ($result['success']) {
                // Log activity
                log_activity($_SESSION['user_id'], 'create_photographer', "Created photographer: $username");
                
                set_flash_message('Fotógrafo creado correctamente', 'success');
            } else {
                set_flash_message($result['message'], 'danger');
            }
        } else {
            // Display all errors
            foreach ($errors as $error) {
                set_flash_message($error, 'danger');
            }
        }
        
        redirect('manage_photographers.php');
        break;
        
    case 'assign_marathon':
        // Assign a marathon to a photographer
        $photographer_id = $_POST['photographer_id'] ?? '';
        $marathon_id = $_POST['marathon_id'] ?? '';
        
        // Validate input
        if (empty($photographer_id) || empty($marathon_id)) {
            set_flash_message('Fotógrafo y maratón son obligatorios', 'danger');
            redirect('manage_photographers.php');
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
            set_flash_message('Este maratón ya está asignado a este fotógrafo', 'warning');
            redirect('manage_photographers.php');
            exit;
        }
        
        // Assign marathon to photographer
        $stmt = $db->prepare("
            INSERT INTO photographer_marathons (user_id, marathon_id)
            VALUES (:user_id, :marathon_id)
        ");
        $stmt->bindValue(':user_id', $photographer_id, SQLITE3_INTEGER);
        $stmt->bindValue(':marathon_id', $marathon_id, SQLITE3_INTEGER);
        
        try {
            $stmt->execute();
            
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
            
            // Log activity
            log_activity($_SESSION['user_id'], 'assign_marathon', "Assigned marathon '$marathon_name' to photographer '$photographer_name'");
            
            set_flash_message('Maratón asignado correctamente al fotógrafo', 'success');
        } catch (Exception $e) {
            set_flash_message('Error al asignar maratón: ' . $e->getMessage(), 'danger');
        }
        
        $db->close();
        redirect('manage_photographers.php');
        break;
        
    case 'reset_password':
        // Reset photographer password
        $photographer_id = $_POST['photographer_id'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_new_password = $_POST['confirm_new_password'] ?? '';
        
        // Validate input
        $errors = [];
        
        if (empty($photographer_id)) {
            $errors[] = 'Fotógrafo no válido';
        }
        
        if (empty($new_password)) {
            $errors[] = 'La nueva contraseña es obligatoria';
        } else if (strlen($new_password) < 6) {
            $errors[] = 'La contraseña debe tener al menos 6 caracteres';
        }
        
        if ($new_password !== $confirm_new_password) {
            $errors[] = 'Las contraseñas no coinciden';
        }
        
        if (empty($errors)) {
            // Hash password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            $db = db_connect();
            $stmt = $db->prepare("
                UPDATE users 
                SET password = :password 
                WHERE id = :user_id
            ");
            $stmt->bindValue(':password', $hashed_password, SQLITE3_TEXT);
            $stmt->bindValue(':user_id', $photographer_id, SQLITE3_INTEGER);
            
            try {
                $stmt->execute();
                
                // Get photographer name for logging
                $photographer_name = '';
                $stmt = $db->prepare("SELECT username FROM users WHERE id = :id");
                $stmt->bindValue(':id', $photographer_id, SQLITE3_INTEGER);
                $result = $stmt->execute();
                if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $photographer_name = $row['username'];
                }
                
                // Log activity
                log_activity($_SESSION['user_id'], 'reset_password', "Reset password for photographer '$photographer_name'");
                
                set_flash_message('Contraseña reseteada correctamente', 'success');
            } catch (Exception $e) {
                set_flash_message('Error al resetear contraseña: ' . $e->getMessage(), 'danger');
            }
            
            $db->close();
        } else {
            // Display all errors
            foreach ($errors as $error) {
                set_flash_message($error, 'danger');
            }
        }
        
        redirect('manage_photographers.php');
        break;
        
    default:
        set_flash_message('Acción no válida', 'danger');
        redirect('manage_photographers.php');
}
?>
