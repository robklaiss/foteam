<?php
/**
 * Bancard Configuration
 * This file contains direct configuration for Bancard payment gateway.
 * It's used as a fallback when environment variables are not available.
 */

// Check if environment variables are already set
if (empty(getenv('BANCARD_PUBLIC_KEY'))) {
    // Set environment variables directly
    putenv('BANCARD_PUBLIC_KEY=vBvkW7xkVoCxsogzVAgcIgXeW4DveMYH');
    putenv('BANCARD_PRIVATE_KEY=ToQSzGYQKLiopicQKtZtKpQ.m4iPE8XElg6W706e');
    putenv('BANCARD_ENVIRONMENT=production');
    putenv('BANCARD_API_URL_STAGING=https://vpos.infonet.com.py:8888');
    putenv('BANCARD_API_URL_PRODUCTION=https://vpos.infonet.com.py');
    putenv('BANCARD_RETURN_URL=https://bebe.com.py/foteam/payment/success.php');
    putenv('BANCARD_CANCEL_URL=https://bebe.com.py/foteam/payment/cancel.php');
    
    // Log that we're using direct configuration
    error_log('Using direct Bancard configuration (environment variables not found)');
}
?>
