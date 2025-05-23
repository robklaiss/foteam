<?php
/**
 * Session Warning Suppression
 *
 * This file provides a custom error handler to suppress only session-related warnings
 * while allowing other warnings to be displayed normally.
 */

// Define a custom error handler to filter out session-related warnings
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Check if this is a warning and if it contains 'session' in the error message
    if ($errno === E_WARNING) {
        // Only suppress session-related warnings
        if (strpos($errstr, 'session') !== false || 
            strpos($errstr, 'Session') !== false) {
            // Silently ignore the warning
            return true;
        }
    }
    
    // For all other errors, use the default error handler
    return false;
}, E_WARNING);
?>
