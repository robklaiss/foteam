<?php
// Load environment variables first
require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/app_config.php';

// Database configuration for SQLite
define('DB_PATH', __DIR__ . '/../database/foteam.db'); // SQLite database file path
define('DB_DIR', __DIR__ . '/../database/'); // SQLite database directory

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS

// Application paths
// Use domain root for production or localhost for development
if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'bebe.com.py') {
    define('BASE_URL', 'https://bebe.com.py'); // Production URL
} else {
    define('BASE_URL', 'http://localhost:8000'); // Local development URL
}
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('THUMBNAIL_DIR', __DIR__ . '/../uploads/thumbnails/');

// Create necessary directories if they don't exist
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
ini_set('display_errors', 1);

// Upload limits
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '50M');
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 600); // 10 minutes

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

// Start session
session_start();

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
