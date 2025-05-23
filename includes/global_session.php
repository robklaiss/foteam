<?php
/**
 * Global Session Manager
 * 
 * This file provides session-related helper functions and initialization.
 * It should be included after bootstrap.php and config.php
 * 
 * @version 2.1.0
 */

// Already loaded check
if (defined('GLOBAL_SESSION_LOADED')) {
    return;
}

define('GLOBAL_SESSION_LOADED', true);

// Verify required files were loaded
if (!defined('BOOTSTRAP_LOADED') || !defined('CONFIG_LOADED')) {
    trigger_error('Error: bootstrap.php and config.php must be included before global_session.php', E_USER_ERROR);
    return;
}

// Initialize cart if needed
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

/**
 * Get a session variable safely
 * 
 * @param string $key The session key
 * @param mixed $default Default value if key doesn't exist
 * @return mixed The session value or default
 */
function get_session_var($key, $default = null) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return $default;
    }
    return $_SESSION[$key] ?? $default;
}

/**
 * Set a session variable safely
 * 
 * @param string $key The session key
 * @param mixed $value The value to set
 * @return bool True if successful, false otherwise
 */
function set_session_var($key, $value) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION[$key] = $value;
        return true;
    }
    return false;
}

/**
 * Get current user ID
 * 
 * @return int|null User ID or null if not logged in
 */
function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Regenerate session ID
 * 
 * @param bool $delete_old_session Whether to delete the old session
 * @return bool True on success, false on failure
 */
function regenerate_session($delete_old_session = false) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return session_regenerate_id($delete_old_session);
    }
    return false;
}
?>
