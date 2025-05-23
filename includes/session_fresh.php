<?php
/**
 * COMPLETELY NEW SESSION HANDLING FILE
 * Created at 21:38 to avoid any confusion with existing files
 */

// Mark this file as loaded
define('FRESH_SESSION_LOADED', true);

// Start session with no params
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set session variables if needed
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Add a debug marker to confirm this file was loaded
error_log("FRESH SESSION FILE LOADED SUCCESSFULLY");
