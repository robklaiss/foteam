<?php
/**
 * Direct Configuration Override
 * 
 * This file provides direct configuration values that override
 * environment variables for testing purposes.
 */

// Set API keys directly
$_ENV['GOOGLE_CLOUD_API_KEY'] = 'AIzaSyC3ukUv1YwP6eGEgw8JZTUjXk4rBeEYeOo';
$_SERVER['GOOGLE_CLOUD_API_KEY'] = 'AIzaSyC3ukUv1YwP6eGEgw8JZTUjXk4rBeEYeOo';
putenv('GOOGLE_CLOUD_API_KEY=AIzaSyC3ukUv1YwP6eGEgw8JZTUjXk4rBeEYeOo');

// Set Google Cloud Vision project info
$_ENV['GOOGLE_CLOUD_VISION_PROJECT_ID'] = 'foteam-cloud-vision';
$_SERVER['GOOGLE_CLOUD_VISION_PROJECT_ID'] = 'foteam-cloud-vision';
putenv('GOOGLE_CLOUD_VISION_PROJECT_ID=foteam-cloud-vision');

$_ENV['GOOGLE_CLOUD_VISION_CLIENT_EMAIL'] = 'foteam-cloud-vision@foteam-cloud-vision.iam.gserviceaccount.com';
$_SERVER['GOOGLE_CLOUD_VISION_CLIENT_EMAIL'] = 'foteam-cloud-vision@foteam-cloud-vision.iam.gserviceaccount.com';
putenv('GOOGLE_CLOUD_VISION_CLIENT_EMAIL=foteam-cloud-vision@foteam-cloud-vision.iam.gserviceaccount.com');

// Log configuration
error_log("Direct configuration applied - API Key: " . substr($_ENV['GOOGLE_CLOUD_API_KEY'], 0, 5) . '...' . substr($_ENV['GOOGLE_CLOUD_API_KEY'], -5));
?>
