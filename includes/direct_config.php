<?php
/**
 * Direct Configuration Fallback
 * 
 * This file provides fallback configuration values if environment variables 
 * aren't already set through the .env file. It no longer forcibly overrides
 * existing environment variables.
 */

// Only set API keys if not already present
if (empty(getenv('GOOGLE_CLOUD_API_KEY'))) {
    $api_key = 'a46ade53287b4bbf8be673e9e813a6b811d961ad'; // Updated to the correct API key
    $_ENV['GOOGLE_CLOUD_API_KEY'] = $api_key;
    $_SERVER['GOOGLE_CLOUD_API_KEY'] = $api_key;
    putenv("GOOGLE_CLOUD_API_KEY=$api_key");
    error_log("Setting fallback GOOGLE_CLOUD_API_KEY: " . substr($api_key, 0, 5) . '...' . substr($api_key, -5));
}

// Only set Google Cloud Vision project info if not already present
if (empty(getenv('GOOGLE_CLOUD_VISION_PROJECT_ID'))) {
    $_ENV['GOOGLE_CLOUD_VISION_PROJECT_ID'] = 'foteam-cloud-vision';
    $_SERVER['GOOGLE_CLOUD_VISION_PROJECT_ID'] = 'foteam-cloud-vision';
    putenv('GOOGLE_CLOUD_VISION_PROJECT_ID=foteam-cloud-vision');
    error_log("Setting fallback GOOGLE_CLOUD_VISION_PROJECT_ID");
}

if (empty(getenv('GOOGLE_CLOUD_VISION_CLIENT_EMAIL'))) {
    $_ENV['GOOGLE_CLOUD_VISION_CLIENT_EMAIL'] = 'foteam-cloud-vision@foteam-cloud-vision.iam.gserviceaccount.com';
    $_SERVER['GOOGLE_CLOUD_VISION_CLIENT_EMAIL'] = 'foteam-cloud-vision@foteam-cloud-vision.iam.gserviceaccount.com';
    putenv('GOOGLE_CLOUD_VISION_CLIENT_EMAIL=foteam-cloud-vision@foteam-cloud-vision.iam.gserviceaccount.com');
    error_log("Setting fallback GOOGLE_CLOUD_VISION_CLIENT_EMAIL");
}

// Log current configuration
error_log("Current API Key configuration: " . substr(getenv('GOOGLE_CLOUD_API_KEY'), 0, 5) . '...' . substr(getenv('GOOGLE_CLOUD_API_KEY'), -5));
?>
