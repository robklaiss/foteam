<?php
// Include the environment variable loader
require_once 'includes/env_loader.php';

// Test if environment variables are loaded correctly
echo "<h1>Environment Variable Test</h1>";
echo "<p>This script tests if environment variables are loaded correctly from the .env file.</p>";

// Check if .env file was loaded
echo "<h2>Environment File Status</h2>";
if (file_exists(dirname(__FILE__) . '/.env')) {
    echo "<p style='color: green;'>✓ .env file exists</p>";
} else {
    echo "<p style='color: red;'>✗ .env file does not exist</p>";
}

// Check Google Cloud API Key
echo "<h2>Google Cloud Vision API</h2>";
$api_key = env('GOOGLE_CLOUD_API_KEY', '');
if (!empty($api_key)) {
    echo "<p style='color: green;'>✓ GOOGLE_CLOUD_API_KEY is set</p>";
    // Only show first few characters for security
    echo "<p>Value: " . substr($api_key, 0, 5) . "..." . substr($api_key, -5) . " (partially hidden for security)</p>";
} else {
    echo "<p style='color: red;'>✗ GOOGLE_CLOUD_API_KEY is not set</p>";
}

// Check Google Cloud Vision Project ID
$project_id = env('GOOGLE_CLOUD_VISION_PROJECT_ID', '');
if (!empty($project_id)) {
    echo "<p style='color: green;'>✓ GOOGLE_CLOUD_VISION_PROJECT_ID is set</p>";
    echo "<p>Value: " . $project_id . "</p>";
} else {
    echo "<p style='color: red;'>✗ GOOGLE_CLOUD_VISION_PROJECT_ID is not set</p>";
}

// Check Firebase Project ID (if needed)
echo "<h2>Firebase Admin SDK (if needed)</h2>";
$firebase_project_id = env('FIREBASE_PROJECT_ID', '');
if (!empty($firebase_project_id)) {
    echo "<p style='color: green;'>✓ FIREBASE_PROJECT_ID is set</p>";
    echo "<p>Value: " . $firebase_project_id . "</p>";
} else {
    echo "<p style='color: red;'>✗ FIREBASE_PROJECT_ID is not set</p>";
}

// Security recommendations
echo "<h2>Security Recommendations</h2>";
echo "<ul>";
echo "<li>Make sure your .env file is included in .gitignore</li>";
echo "<li>Set proper file permissions for .env file (chmod 600 .env)</li>";
echo "<li>Consider using a secret management service in production</li>";
echo "</ul>";

// Test credentials_manager.php
echo "<h2>Credentials Manager Test</h2>";
require_once 'includes/credentials_manager.php';

$vision_creds = get_google_vision_credentials();
if ($vision_creds !== null) {
    echo "<p style='color: green;'>✓ Google Vision credentials can be generated</p>";
} else {
    echo "<p style='color: red;'>✗ Google Vision credentials cannot be generated</p>";
}

$firebase_creds = get_firebase_credentials();
if ($firebase_creds !== null) {
    echo "<p style='color: green;'>✓ Firebase credentials can be generated</p>";
} else {
    echo "<p style='color: red;'>✗ Firebase credentials cannot be generated</p>";
}
?>
