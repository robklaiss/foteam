<?php
// Include the direct configuration (overrides environment variables)
require_once __DIR__ . '/direct_config.php';

// Include the environment variable loader
require_once __DIR__ . '/env_loader.php';

// Google Cloud Vision API Configuration

// Your Google Cloud API Key
// Get this from the Google Cloud Console: https://console.cloud.google.com/apis/credentials
$api_key = env('GOOGLE_CLOUD_API_KEY', '');

// Force API key for testing if not already set
if (empty($api_key)) {
    $api_key = 'AIzaSyDJh2X5LiLBOQr9mN-w9NIpbQlxfhYT-8M';
    set_env('GOOGLE_CLOUD_API_KEY', $api_key);
    error_log("Using hardcoded API key for testing");
}

define('GOOGLE_CLOUD_API_KEY', $api_key);

// API endpoint
define('GOOGLE_VISION_API_ENDPOINT', 'https://vision.googleapis.com/v1/images:annotate');

// Maximum number of results to return for text detection
define('GOOGLE_VISION_MAX_RESULTS', 10);

// Enable/disable Google Cloud Vision integration
// Set to false if you don't have an API key or don't want to use the service
define('GOOGLE_VISION_ENABLED', !empty($api_key));

// Log configuration status
error_log("Google Vision Config - API Key: " . substr($api_key, 0, 5) . '...' . substr($api_key, -5) . 
          ", Enabled: " . (!empty($api_key) ? 'Yes' : 'No'));

// Google Cloud Vision Project ID
define('GOOGLE_CLOUD_VISION_PROJECT_ID', env('GOOGLE_CLOUD_VISION_PROJECT_ID', 'foteam-cloud-vision'));

// Google Cloud Vision Client Email
define('GOOGLE_CLOUD_VISION_CLIENT_EMAIL', env('GOOGLE_CLOUD_VISION_CLIENT_EMAIL', 'foteam-cloud-vision@foteam-cloud-vision.iam.gserviceaccount.com'));
?>
