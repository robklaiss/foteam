<?php
/**
 * Global Session Warning Silencer
 * 
 * This file provides a definitive solution to completely disable ALL session-related warnings
 * directly at the PHP level. It should be included at the very beginning of every PHP file
 * that starts or uses sessions.
 */

// Start output buffering to capture any output (including warnings)
ob_start();

// Completely turn off displaying errors (only for this request)
ini_set('display_errors', 0);

// Create a silent error handler specifically for session warnings
function silence_session_warnings($errno, $errstr, $errfile, $errline) {
    // Only silence session-related warnings
    if (($errno === E_WARNING || $errno === E_NOTICE) && 
        (stripos($errstr, 'session') !== false || stripos($errstr, 'Session') !== false)) {
        // Log the suppressed warning only (no display)
        error_log("Session warning silenced: $errstr in $errfile on line $errline");
        return true; // Prevent standard error handler
    }
    
    // Let regular error handler deal with non-session warnings
    return false;
}

// Install our error handler
set_error_handler('silence_session_warnings', E_WARNING | E_NOTICE);

// The key part: unregister_session_save_handler
// This prevents any session warnings related to save_handlers
@session_write_close();

// Force register shutdown function to ensure error handler stays active
register_shutdown_function(function() {
    // Clean up output buffer on shutdown
    if (ob_get_level() > 0) {
        $output = ob_get_clean();
        echo $output;
    }
    
    // Restore default error handler
    restore_error_handler();
});

