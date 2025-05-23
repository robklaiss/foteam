<?php
/**
 * Session Diagnostic Tool
 * This file will diagnose session issues by tracing file inclusion and session status
 */

// Turn off error reporting temporarily to analyze the errors ourselves
error_reporting(0);

// Output buffer to collect all output
ob_start();

echo "<h1>Session Diagnostic</h1>";
echo "<p>Time: " . date('Y-m-d H:i:s') . "</p>";

// Check current session status
echo "<h2>Initial Session Status</h2>";
echo "<p>Session status: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'NOT ACTIVE') . "</p>";
echo "<p>Session ID: " . session_id() . "</p>";

// Create a custom include function to track inclusions
function diagnostic_include($file) {
    echo "<div style='margin-left: 20px; border-left: 1px solid #ccc; padding-left: 10px;'>";
    echo "<h3>Including: $file</h3>";
    
    // Check session before inclusion
    echo "<p>Session status before: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'NOT ACTIVE') . "</p>";
    
    // Include the file and capture any errors
    ob_start();
    $result = @include_once($file);
    $include_output = ob_get_clean();
    
    // Check session after inclusion
    echo "<p>Session status after: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'NOT ACTIVE') . "</p>";
    
    // Report any errors
    if (!$result) {
        echo "<p style='color: red;'>Error including file!</p>";
    }
    
    // Display any output from the included file
    if (trim($include_output)) {
        echo "<div style='background-color: #f0f0f0; padding: 10px; border: 1px solid #ccc;'>";
        echo "<p><strong>Output from included file:</strong></p>";
        echo $include_output;
        echo "</div>";
    }
    
    echo "</div>";
    
    return $result;
}

// List all bootstrap and config related files
echo "<h2>Files to Check</h2>";
$files_to_check = [
    __DIR__ . '/includes/bootstrap.php',
    __DIR__ . '/includes/config.php',
    __DIR__ . '/includes/session_fresh.php',
    __DIR__ . '/includes/functions.php',
];

foreach ($files_to_check as $file) {
    echo "<p>" . (file_exists($file) ? '✅' : '❌') . " $file</p>";
}

// Test each include scenario
echo "<h2>Testing Bootstrap Only</h2>";

// Clear any session first
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

diagnostic_include(__DIR__ . '/includes/bootstrap.php');

// Clear session again and try config only
echo "<h2>Testing Config Only</h2>";
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

diagnostic_include(__DIR__ . '/includes/config.php');

// Try bootstrap then config (correct order)
echo "<h2>Testing Bootstrap then Config (correct order)</h2>";
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

diagnostic_include(__DIR__ . '/includes/bootstrap.php');
diagnostic_include(__DIR__ . '/includes/config.php');

// Try session_fresh then config (incorrect order)
echo "<h2>Testing session_fresh then config (incorrect order)</h2>";
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

diagnostic_include(__DIR__ . '/includes/session_fresh.php');
diagnostic_include(__DIR__ . '/includes/config.php');

// Display final session status
echo "<h2>Final Session Status</h2>";
echo "<p>Session status: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'NOT ACTIVE') . "</p>";
echo "<p>Session ID: " . session_id() . "</p>";

// Output any current errors
echo "<h2>Current Error Information</h2>";
$error = error_get_last();
if ($error) {
    echo "<pre>" . print_r($error, true) . "</pre>";
} else {
    echo "<p>No errors recorded.</p>";
}

// End output buffer and display all at once
$output = ob_get_clean();
echo $output;
?>
