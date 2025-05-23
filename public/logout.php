<?php
// Define the root directory path for includes
$root_path = dirname(__DIR__);

// Include bootstrap first to configure environment and settings
if (!defined('BOOTSTRAP_LOADED')) {
    require_once $root_path . '/includes/bootstrap.php';
}

// Include config
if (!defined('CONFIG_LOADED')) {
    require_once $root_path . '/includes/config.php';
}

// Include functions
require_once $root_path . '/includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Logout user
logout_user();

// Set success message
$_SESSION['flash_message'] = 'Ha cerrado sesión correctamente';
$_SESSION['flash_type'] = 'success';

// Ensure no output before header
if (ob_get_level()) {
    ob_end_clean();
}

// Debug output - only in development
$debug = false;
if ($debug) {
    error_log('Logout completed, redirecting to index.php');
    echo 'Logout completed, redirecting...';
    exit;
}

// Redirect to home page
header('Location: ' . BASE_URL . '/index.php');
exit();
