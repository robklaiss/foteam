<?php
// Enhanced login with debugging for production server issues

// Include bootstrap first to handle session and configuration
require_once 'includes/bootstrap.php';

// Include required files
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Start output buffering to prevent headers already sent errors
ob_start();

// Enable error logging to file
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Debug information will be saved to a log file
$debug_file = __DIR__ . '/login_debug.log';
file_put_contents($debug_file, "=== Login Debug Started at " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);
file_put_contents($debug_file, "Session ID: " . session_id() . "\n", FILE_APPEND);
file_put_contents($debug_file, "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'NOT ACTIVE') . "\n", FILE_APPEND);
file_put_contents($debug_file, "SESSION Data before login attempt: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

// Check if file is directly accessible
$is_accessible = true;

// Ensure we have a CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    file_put_contents($debug_file, "Generated new CSRF token\n", FILE_APPEND);
}

// Redirect if already logged in
if (is_logged_in()) {
    file_put_contents($debug_file, "User already logged in, would redirect to index.php\n", FILE_APPEND);
    if (!$is_accessible) {
        redirect('index.php');
    } else {
        echo "<div class='alert alert-info'>You are already logged in. <a href='index.php'>Go to homepage</a></div>";
    }
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents($debug_file, "POST request received\n", FILE_APPEND);
    file_put_contents($debug_file, "POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);
    
    // Add explicit session data to confirm we're in POST
    $_SESSION['login_attempt'] = time();
    
    // Skip CSRF check for debugging
    // check_csrf_token();
    
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        file_put_contents($debug_file, "Email or password empty\n", FILE_APPEND);
        set_flash_message('Por favor complete todos los campos', 'danger');
    } else {
        file_put_contents($debug_file, "Login attempt for email: {$email}\n", FILE_APPEND);
        
        // Check if user exists before attempting login
        $user_exists = check_email_exists($email);
        file_put_contents($debug_file, "User exists check: " . ($user_exists ? 'YES' : 'NO') . "\n", FILE_APPEND);
        
        $result = login_user($email, $password);
        file_put_contents($debug_file, "Login result: " . print_r($result, true) . "\n", FILE_APPEND);
        
        // Check session after login attempt
        file_put_contents($debug_file, "SESSION after login attempt: " . print_r($_SESSION, true) . "\n", FILE_APPEND);
        file_put_contents($debug_file, "is_logged_in() after login attempt: " . (is_logged_in() ? 'TRUE' : 'FALSE') . "\n", FILE_APPEND);
        
        if ($result['success']) {
            // Explicitly check that session variables were set
            if (!isset($_SESSION['user_id'])) {
                file_put_contents($debug_file, "ERROR: login_user() reported success but session variables were not set\n", FILE_APPEND);
                set_flash_message('Error del sistema. Por favor intente de nuevo.', 'danger');
            } else {
                // Success! Prepare redirect
                $redirect_to = $_SESSION['redirect_after_login'] ?? 'index.php';
                unset($_SESSION['redirect_after_login']);
                
                // Force session write before redirect
                session_write_close();
                session_start();
                
                file_put_contents($debug_file, "Login successful for user ID: {$_SESSION['user_id']}\n", FILE_APPEND);
                file_put_contents($debug_file, "Would redirect to: {$redirect_to}\n", FILE_APPEND);
                
                if (!$is_accessible) {
                    redirect($redirect_to);
                } else {
                    echo "<div class='alert alert-success'>Login successful! <a href='{$redirect_to}'>Continue</a></div>";
                }
            }
        } else {
            $error_message = $result['message'] ?? 'Error de autenticación desconocido';
            set_flash_message($error_message, 'danger');
            file_put_contents($debug_file, "Login failed: {$error_message}\n", FILE_APPEND);
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

// Additional function to check if email exists
function check_email_exists($email) {
    $db = db_connect();
    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    $result->finalize();
    $db->close();
    return ($user !== false);
}

// Below is the regular login form if this page is accessed directly
if ($is_accessible):
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Login - FoTeam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Debug Login</h4>
                    </div>
                    <div class="card-body">
                        <h5>Session Information</h5>
                        <pre><?php
                            echo "Session ID: " . session_id() . "\n";
                            echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'NOT ACTIVE') . "\n";
                            echo "is_logged_in(): " . (is_logged_in() ? 'TRUE' : 'FALSE') . "\n";
                            echo "Session Contents:\n";
                            print_r($_SESSION);
                        ?></pre>
                        
                        <?php $flash = get_flash_message(); ?>
                        <?php if ($flash): ?>
                        <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                            <?php echo $flash['message']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="login_debug.php">
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
                    <div class="card-footer">
                        <div class="d-flex justify-content-between">
                            <a href="server_debug.php" class="btn btn-info">Server Info</a>
                            <a href="index.php" class="btn btn-secondary">Back to Home</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
endif;
?>
