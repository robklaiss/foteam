<?php
// Test script for Google Cloud Vision API integration

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Directly set API key for testing
$_ENV['GOOGLE_CLOUD_API_KEY'] = 'AIzaSyDJh2X5LiLBOQr9mN-w9NIpbQlxfhYT-8M';
$_SERVER['GOOGLE_CLOUD_API_KEY'] = 'AIzaSyDJh2X5LiLBOQr9mN-w9NIpbQlxfhYT-8M';
putenv('GOOGLE_CLOUD_API_KEY=AIzaSyDJh2X5LiLBOQr9mN-w9NIpbQlxfhYT-8M');

// Include required files
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/env_loader.php';

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Function to display results in a readable format
function display_section($title, $content) {
    echo "<div style='margin: 20px 0; padding: 15px; border: 1px solid #ccc; border-radius: 5px;'>";
    echo "<h3>$title</h3>";
    echo "<pre>";
    print_r($content);
    echo "</pre>";
    echo "</div>";
}

// Check if environment variables are loaded
echo "<h1>Google Cloud Vision API Test</h1>";

// Check API key
$api_key = defined('GOOGLE_CLOUD_API_KEY') ? GOOGLE_CLOUD_API_KEY : 'Not defined';
$api_key_status = !empty($api_key) && $api_key != 'Not defined' ? 'Available' : 'Not available';

display_section('API Key Status', [
    'Status' => $api_key_status,
    'First 5 chars' => substr($api_key, 0, 5) . '...',
    'Last 5 chars' => '...' . substr($api_key, -5)
]);

// Check if Google Vision is enabled
$vision_enabled = defined('GOOGLE_VISION_ENABLED') ? GOOGLE_VISION_ENABLED : false;
display_section('Google Vision Status', [
    'Enabled' => $vision_enabled ? 'Yes' : 'No'
]);

// Test with a sample image
$test_images = glob('uploads/originals/*.{jpg,jpeg,png,gif}', GLOB_BRACE);

if (empty($test_images)) {
    echo "<p>No test images found in uploads/originals directory</p>";
} else {
    $test_image = $test_images[0];
    
    echo "<h2>Testing with image: $test_image</h2>";
    echo "<img src='" . str_replace($_SERVER['DOCUMENT_ROOT'], '', $test_image) . "' style='max-width: 500px; max-height: 500px;'>";
    
    // Call the runner number detection function
    $start_time = microtime(true);
    $detected_numbers = detect_runner_numbers($test_image);
    $end_time = microtime(true);
    
    display_section('Detection Results', [
        'Detected Numbers' => !empty($detected_numbers) ? $detected_numbers : 'None detected',
        'Processing Time' => round(($end_time - $start_time), 2) . ' seconds'
    ]);
    
    // Show error log
    $error_log = error_get_last();
    if ($error_log) {
        display_section('Last Error', $error_log);
    }
}

// Manual test form
echo "<h2>Test with your own image</h2>";
echo "<form method='post' action='test_vision_api.php' enctype='multipart/form-data'>";
echo "<input type='file' name='test_image' accept='image/*'>";
echo "<button type='submit' style='margin-top: 10px;'>Test Detection</button>";
echo "</form>";

// Process uploaded test image
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_image'])) {
    if ($_FILES['test_image']['error'] === UPLOAD_ERR_OK) {
        $temp_file = $_FILES['test_image']['tmp_name'];
        $filename = 'test_' . uniqid() . '_' . basename($_FILES['test_image']['name']);
        $target_file = UPLOAD_DIR . 'originals/' . $filename;
        
        if (move_uploaded_file($temp_file, $target_file)) {
            echo "<h3>Uploaded Test Image</h3>";
            echo "<img src='uploads/originals/$filename' style='max-width: 500px; max-height: 500px;'>";
            
            // Call the runner number detection function
            $start_time = microtime(true);
            $detected_numbers = detect_runner_numbers($target_file);
            $end_time = microtime(true);
            
            display_section('Detection Results for Uploaded Image', [
                'Detected Numbers' => !empty($detected_numbers) ? $detected_numbers : 'None detected',
                'Processing Time' => round(($end_time - $start_time), 2) . ' seconds'
            ]);
        } else {
            echo "<p>Error uploading test image</p>";
        }
    } else {
        echo "<p>Error: " . $_FILES['test_image']['error'] . "</p>";
    }
}
?>
