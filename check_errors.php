<?php
// Set custom error log location
$logFile = __DIR__ . '/php_errors.log';
ini_set('error_log', $logFile);
ini_set('log_errors', 1);

// Test error logging
error_log("Testing error logging to: " . $logFile);

echo "Error log test complete. Check this file for errors: " . $logFile . "\n";
?>
