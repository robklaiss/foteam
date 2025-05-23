<?php
// Direct test for Google Cloud Vision API with environment variable support

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set character encoding
header('Content-Type: text/html; charset=UTF-8');

// Load environment variables
require_once 'includes/env_loader.php';

// Get API key from environment variable
$api_key = getenv('GOOGLE_CLOUD_API_KEY');
if (empty($api_key)) {
    die("Error: GOOGLE_CLOUD_API_KEY environment variable is not set.");
}

// Find a test image
$test_images = glob('uploads/originals/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
if (empty($test_images)) {
    die("No test images found in uploads/originals directory");
}
$test_image = $test_images[0];

echo "<h1>Direct Google Cloud Vision API Test</h1>";
echo "<p>Testing with image: $test_image</p>";
echo "<img src='" . str_replace($_SERVER['DOCUMENT_ROOT'], '', $test_image) . "' style='max-width: 500px; max-height: 500px;'>";

// Read and encode the image
$image_content = file_get_contents($test_image);
$encoded_image = base64_encode($image_content);

// Prepare the request payload
$request_data = [
    'requests' => [
        [
            'image' => [
                'content' => $encoded_image
            ],
            'features' => [
                [
                    'type' => 'TEXT_DETECTION',
                    'maxResults' => 10
                ]
            ]
        ]
    ]
];

// Convert the request data to JSON
$json_request = json_encode($request_data);

// Build the API URL with the API key
$api_url = 'https://vision.googleapis.com/v1/images:annotate?key=' . $api_key;

echo "<h2>API Request</h2>";
echo "<pre>URL: $api_url</pre>";

// Set up cURL to make the API request
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $api_url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, $json_request);
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($json_request)
]);

// Execute the request
$response = curl_exec($curl);
$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curl_error = curl_error($curl);
curl_close($curl);

echo "<h2>API Response</h2>";
echo "<p>HTTP Status: $status</p>";

if (!empty($curl_error)) {
    echo "<p style='color: red;'>cURL Error: $curl_error</p>";
} else {
    // Parse the response
    $result = json_decode($response, true);
    
    echo "<h3>Raw Response</h3>";
    echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
    
    // Extract text annotations
    if (isset($result['responses'][0]['textAnnotations'])) {
        $text_annotations = $result['responses'][0]['textAnnotations'];
        
        echo "<h3>Detected Text</h3>";
        echo "<ul>";
        foreach ($text_annotations as $annotation) {
            if (isset($annotation['description'])) {
                echo "<li>" . htmlspecialchars($annotation['description']) . "</li>";
            }
        }
        echo "</ul>";
        
        // Extract potential runner numbers (look for sequences of 3-6 digits)
        $numbers = [];
        $pattern = '/\b\d{3,6}\b/'; // Pattern to match 3-6 digit numbers
        
        foreach ($text_annotations as $annotation) {
            if (isset($annotation['description'])) {
                $text = $annotation['description'];
                if (preg_match_all($pattern, $text, $matches)) {
                    foreach ($matches[0] as $match) {
                        $numbers[] = $match;
                    }
                }
            }
        }
        
        // Remove duplicates
        $unique_numbers = array_unique($numbers);
        
        echo "<h3>Detected Runner Numbers</h3>";
        if (!empty($unique_numbers)) {
            echo "<ul>";
            foreach ($unique_numbers as $number) {
                echo "<li>$number</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>No runner numbers detected</p>";
        }
    } else {
        echo "<p>No text detected in the image</p>";
    }
}
?>
