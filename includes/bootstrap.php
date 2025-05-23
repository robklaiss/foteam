<?php
/**
 * Bootstrap File - Application Configuration
 * 
 * This file handles application-wide configuration and settings.
 * It should be included before any output is sent to the browser.
 * 
 * @version 2.1.0
 * 
 * NOTE: This file must be the first include in any PHP file
 */

// Prevent multiple includes
if (defined('BOOTSTRAP_LOADED')) {
    return;
}

define('BOOTSTRAP_LOADED', true);

// Set error reporting based on environment
if (getenv('APP_ENV') === 'production') {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', dirname(__DIR__) . '/php_errors.log');
} else {
    // Development environment - show all errors
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', dirname(__DIR__) . '/php_errors.log');
}

// Set default timezone
date_default_timezone_set('America/Asuncion');

// Set default character encoding
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Set upload/memory limits 
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '50M');
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 600);

// Set default character encoding
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Enable output buffering
if (!ob_get_level()) {
    ob_start();
}

// Only initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Set a custom session name
    $session_name = 'foteam_session';
    
    // Determine HTTPS status
    $is_https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') || 
                (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    // Set domain for cookies
    $domain = '';
    if (isset($_SERVER['HTTP_HOST'])) {
        $host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']);
        if (strpos($host, 'bebe.com.py') !== false) {
            $domain = '.bebe.com.py';
            $is_https = true;
        } elseif (!in_array($host, ['localhost', '127.0.0.1', '::1'])) {
            $domain = $host;
        }
    }

    // Ensure we're not modifying session settings after headers are sent
    if (!headers_sent()) {
        // Set session settings
        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.use_trans_sid', '0');
        @ini_set('session.cookie_httponly', '1');
        @ini_set('session.cookie_secure', $is_https ? '1' : '0');
        @ini_set('session.cookie_samesite', 'Lax');
        @ini_set('session.gc_maxlifetime', '1800');
        @ini_set('session.gc_probability', '1');
        @ini_set('session.gc_divisor', '100');

        // Set session name
        @session_name($session_name);
        
        // Set session cookie parameters
        @session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => $domain,
            'secure' => $is_https,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        // Setup session directory
        $session_dir = __DIR__ . '/../tmp/sessions';
        if (!file_exists($session_dir)) {
            @mkdir($session_dir, 0777, true);
        }
        @session_save_path($session_dir);
        
        // Start the session with error suppression
        @session_start();
        
        // Initialize session variables if needed
        if (!isset($_SESSION['initiated'])) {
            $_SESSION['initiated'] = true;
            $_SESSION['last_activity'] = time();
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
        } else {
            $_SESSION['last_activity'] = time();
        }
    } else {
        // If headers already sent, just start the session with default settings
        @session_start();
    }
}

// Only define constants after session is started
define('SESSION_STARTED', true);

// Include application configuration
require_once __DIR__ . '/app_config.php';
