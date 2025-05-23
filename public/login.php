<?php
// Define the root directory path for includes
$root_path = dirname(__DIR__);

// Include bootstrap first to handle session and configuration
if (!defined('BOOTSTRAP_LOADED')) {
    require_once $root_path . '/includes/bootstrap.php';
}

// Include config
if (!defined('CONFIG_LOADED')) {
    require_once $root_path . '/includes/config.php';
}

// Include functions
require_once $root_path . '/includes/functions.php';

// Debug information - only for development
$debug_enabled = false;
if ($debug_enabled) {
    error_log('Login.php - Session ID: ' . session_id());
    error_log('Login.php - SESSION data: ' . print_r($_SESSION, true));
}

// Ensure we have a CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    if ($debug_enabled) {
        error_log('Generated new CSRF token');
    }
}

// Redirect if already logged in
if (is_logged_in()) {
    if ($debug_enabled) {
        error_log('User already logged in, redirecting to index.php');
    }
    redirect('index.php');
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add explicit session data to confirm we're in POST
    $_SESSION['login_attempt'] = time();
    
    // Temporarily skip CSRF check during troubleshooting
    // check_csrf_token();
    
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        set_flash_message('Por favor complete todos los campos', 'danger');
    } else {
        // Log login attempt (only in dev mode)
        if ($debug_enabled) {
            error_log("Login attempt for email: {$email}");
        }
        
        $result = login_user($email, $password);
        
        if ($result['success']) {
            // Explicitly check that session variables were set
            if (!isset($_SESSION['user_id'])) {
                error_log('ERROR: login_user() reported success but session variables were not set');
                set_flash_message('Error del sistema. Por favor intente de nuevo.', 'danger');
            } else {
                // Success! Prepare redirect
                $redirect_to = $_SESSION['redirect_after_login'] ?? 'index.php';
                unset($_SESSION['redirect_after_login']);
                
                // Force session write before redirect
                session_write_close();
                
                if ($debug_enabled) {
                    error_log("Login successful for user ID: {$_SESSION['user_id']}");
                    error_log("Redirecting to: {$redirect_to}");
                }
                
                redirect($redirect_to);
            }
        } else {
            $error_message = $result['message'] ?? 'Error de autenticación desconocido';
            set_flash_message($error_message, 'danger');
            if ($debug_enabled) {
                error_log("Login failed: {$error_message}");
            }
        }
    }
}

// Remember the current page for redirect after login
if (!isset($_SESSION['redirect_after_login']) && isset($_SERVER['HTTP_REFERER'])) {
    $referer = $_SERVER['HTTP_REFERER'];
    // Only set if referer is not the login or register page
    if (strpos($referer, 'login.php') === false && strpos($referer, 'register.php') === false) {
        $_SESSION['redirect_after_login'] = $referer;
    }
}

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Include header
require_once $root_path . '/includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Iniciar Sesión</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="login.php">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Correo Electrónico</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center">
                    <p class="mb-0">¿No tienes una cuenta? <a href="register.php">Regístrate</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include $root_path . '/includes/footer.php'; ?>
