<?php
/**
 * Bancard Configuration
 * This file checks for Bancard payment gateway configuration.
 * Instead of hardcoding credentials, it logs a warning if they're not set.
 */

// Load environment variables if not already loaded
require_once __DIR__ . '/env_loader.php';

// Check if environment variables are set
$missing_vars = [];
$required_vars = [
    'BANCARD_PUBLIC_KEY',
    'BANCARD_PRIVATE_KEY',
    'BANCARD_ENVIRONMENT',
    'BANCARD_API_URL_STAGING',
    'BANCARD_API_URL_PRODUCTION',
    'BANCARD_RETURN_URL',
    'BANCARD_CANCEL_URL'
];

foreach ($required_vars as $var) {
    if (empty(getenv($var))) {
        $missing_vars[] = $var;
    }
}

// Log missing variables
if (!empty($missing_vars)) {
    error_log('WARNING: Missing Bancard environment variables: ' . implode(', ', $missing_vars));
    error_log('Please set these variables in your .env file for secure operation');
    
    // For testing purposes only - set base URL variables if missing
    if (in_array('BANCARD_RETURN_URL', $missing_vars) || in_array('BANCARD_CANCEL_URL', $missing_vars)) {
        $base_url = isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : 'http://localhost';
        
        if (empty(getenv('BANCARD_RETURN_URL'))) {
            putenv('BANCARD_RETURN_URL=' . $base_url . '/payment_success.php');
            error_log('Temporarily set BANCARD_RETURN_URL for testing purposes only');
        }
        
        if (empty(getenv('BANCARD_CANCEL_URL'))) {
            putenv('BANCARD_CANCEL_URL=' . $base_url . '/payment_cancel.php');
            error_log('Temporarily set BANCARD_CANCEL_URL for testing purposes only');
        }
    }

    // If critical credentials are missing, add a prominent warning
    if (in_array('BANCARD_PUBLIC_KEY', $missing_vars) || in_array('BANCARD_PRIVATE_KEY', $missing_vars)) {
        error_log('CRITICAL: Bancard payment processing will not work without proper credentials!');
    }
}
?>
