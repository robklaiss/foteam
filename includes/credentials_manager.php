<?php
/**
 * Credentials Manager
 * 
 * This file provides functions to securely access and manage API credentials
 * using environment variables instead of hardcoded values.
 */

require_once __DIR__ . '/env_loader.php';

/**
 * Get Google Cloud Vision credentials as a JSON object
 * 
 * @return array|null The credentials as an array or null if not configured
 */
function get_google_vision_credentials() {
    // Check if required environment variables are set
    if (empty(env('GOOGLE_CLOUD_VISION_PROJECT_ID')) || 
        empty(env('GOOGLE_CLOUD_VISION_CLIENT_EMAIL'))) {
        return null;
    }
    
    // Create a credentials array with environment variables
    // Note: We're not including the actual private key in the .env file for security
    $credentials = [
        'type' => 'service_account',
        'project_id' => env('GOOGLE_CLOUD_VISION_PROJECT_ID'),
        'private_key_id' => env('GOOGLE_CLOUD_VISION_PRIVATE_KEY_ID'),
        'client_email' => env('GOOGLE_CLOUD_VISION_CLIENT_EMAIL'),
        'client_id' => env('GOOGLE_CLOUD_VISION_CLIENT_ID'),
        'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
        'token_uri' => 'https://oauth2.googleapis.com/token',
        'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
        'client_x509_cert_url' => 'https://www.googleapis.com/robot/v1/metadata/x509/' . 
            urlencode(env('GOOGLE_CLOUD_VISION_CLIENT_EMAIL')),
        'universe_domain' => 'googleapis.com'
    ];
    
    return $credentials;
}

/**
 * Get Firebase Admin SDK credentials as a JSON object
 * 
 * @return array|null The credentials as an array or null if not configured
 */
function get_firebase_credentials() {
    // Check if required environment variables are set
    if (empty(env('FIREBASE_PROJECT_ID')) || 
        empty(env('FIREBASE_CLIENT_EMAIL'))) {
        return null;
    }
    
    // Create a credentials array with environment variables
    // Note: We're not including the actual private key in the .env file for security
    $credentials = [
        'type' => 'service_account',
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'private_key_id' => env('FIREBASE_PRIVATE_KEY_ID'),
        'client_email' => env('FIREBASE_CLIENT_EMAIL'),
        'client_id' => env('FIREBASE_CLIENT_ID'),
        'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
        'token_uri' => 'https://oauth2.googleapis.com/token',
        'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
        'client_x509_cert_url' => 'https://www.googleapis.com/robot/v1/metadata/x509/' . 
            urlencode(env('FIREBASE_CLIENT_EMAIL')),
        'universe_domain' => 'googleapis.com'
    ];
    
    return $credentials;
}

/**
 * Save credentials to a temporary JSON file for use with APIs
 * 
 * @param array $credentials The credentials array
 * @param string $type The type of credentials ('vision' or 'firebase')
 * @return string|false The path to the temporary file or false on failure
 */
function save_temp_credentials($credentials, $type) {
    if (empty($credentials)) {
        return false;
    }
    
    // Create a temporary file in the system temp directory
    $temp_dir = sys_get_temp_dir();
    $filename = $temp_dir . '/foteam_' . $type . '_' . md5(uniqid()) . '.json';
    
    // Write credentials to the file
    $result = file_put_contents($filename, json_encode($credentials, JSON_PRETTY_PRINT));
    
    if ($result === false) {
        return false;
    }
    
    // Set proper permissions (readable only by the current user)
    chmod($filename, 0600);
    
    // Register a shutdown function to delete the file when the script ends
    register_shutdown_function(function() use ($filename) {
        if (file_exists($filename)) {
            unlink($filename);
        }
    });
    
    return $filename;
}
?>
