<?php
/**
 * Application Configuration File
 * 
 * This file contains application configuration settings.
 * 
 * @version 2.1.0
 */

// Verify bootstrap was loaded first
if (!defined('BOOTSTRAP_LOADED')) {
    die('Error: bootstrap.php must be included before config.php');
}

// Prevent multiple inclusion
if (defined('CONFIG_LOADED')) {
    return;
}

define('CONFIG_LOADED', true);

// Check if session is active (should be started by bootstrap.php)
if (session_status() === PHP_SESSION_NONE) {
    // Silently try to start the session if it's not already started
    @session_start();
}

// Basic application settings
define('APP_VERSION', '2.0.0');
define('APP_NAME', 'FoTeam');

// Base URL configuration
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$script_name = dirname($_SERVER['SCRIPT_NAME']);

// Remove any trailing slashes from the script path
$script_path = rtrim($script_name, '/');

// If we're in a subdirectory, use that as the base path
if ($script_path && $script_path !== '/') {
    define('BASE_URL', $protocol . $host . $script_path);
} else {
    define('BASE_URL', $protocol . $host);
}

// Admin URL
if (!defined('ADMIN_URL')) {
    define('ADMIN_URL', rtrim(BASE_URL, '/') . '/admin');
}

// Site URL without trailing slash
if (!defined('SITE_URL')) {
    define('SITE_URL', rtrim(BASE_URL, '/'));
}

// Define BASE_URL
if (!defined('BASE_URL')) {
    if (getenv('APP_ENV') === 'production') {
        define('BASE_URL', 'https://bebe.com.py'); // Production URL
    } else {
        // Dynamically detect the protocol and host
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $base = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $base = str_replace('/public', '', $base); // Remove /public from the path if present
        
        // Remove any index.php from the base path
        $base = str_replace('/index.php', '', $base);
        
        define('BASE_URL', "$protocol://$host$base");
    }
}

// Database configuration for SQLite
define('DB_PATH', __DIR__ . '/../database/foteam.db');
define('DB_DIR', __DIR__ . '/../database/');

// E-commerce settings are now in app_config.php

// Application paths
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('THUMBNAIL_DIR', __DIR__ . '/../uploads/thumbnails/');

// Create necessary directories
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
if (!file_exists(THUMBNAIL_DIR)) {
    mkdir(THUMBNAIL_DIR, 0755, true);
}
if (!file_exists(DB_DIR)) {
    mkdir(DB_DIR, 0755, true);
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1); // Error reporting (disable in production)
// Upload limits moved to bootstrap.php


// Connect to SQLite database
function db_connect() {
    try {
        // Check if SQLite extension is loaded
        if (!extension_loaded('sqlite3')) {
            throw new Exception('SQLite3 extension is not loaded');
        }
        
        // Create/open the database
        $db = new SQLite3(DB_PATH);
        $db->enableExceptions(true);
        
        // Set pragmas for better performance and security
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('PRAGMA journal_mode = WAL');
        
        // Set character encoding to UTF-8
        $db->exec('PRAGMA encoding = "UTF-8"');
        
        return $db;
    } catch (Exception $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Initialize database if it doesn't exist
function initialize_database() {
    if (!file_exists(DB_PATH)) {
        $db = db_connect();
        
        // Read SQL schema from file
        $schema = file_get_contents(__DIR__ . '/database.sql');
        
        // Execute schema
        $db->exec($schema);
        
        $db->close();
    }
}

// Call initialize_database to ensure database exists
initialize_database();

// Session management is handled by bootstrap.php
// All session configurations must be set before session_start() is called

// CSRF Protection
function generate_csrf_token() {
    // Create a token that persists for the session
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Check CSRF token
function check_csrf_token() {
    // Skip CSRF check for large file uploads (over 10MB)
    if (isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 10485760) {
        // For large uploads, we'll rely on session validation instead
        if (!isset($_SESSION['user_id'])) {
            http_response_code(403);
            die('Authentication required');
        }
        return true;
    }
    
    // Normal CSRF validation for smaller requests
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
    
    return true;
}
?>
