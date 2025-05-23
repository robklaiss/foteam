<?php
/**
 * Session Cleanup Script
 * 
 * This script removes old session files that are no longer in use.
 * It should be run periodically via a cron job.
 */

// Set the session directory
$session_dir = __DIR__ . '/tmp/sessions';

// Maximum age of session files in seconds (7 days)
$max_lifetime = 7 * 24 * 60 * 60;

// Make sure the session directory exists and is writable
if (!is_dir($session_dir) || !is_writable($session_dir)) {
    die("Error: Session directory does not exist or is not writable: $session_dir\n");
}

echo "Starting session cleanup in: $session_dir\n";
echo "Maximum session age: " . ($max_lifetime / 86400) . " days\n\n";

// Get all session files
$files = glob("$session_dir/sess_*");
$total = count($files);
$deleted = 0;
$current_time = time();

foreach ($files as $file) {
    // Skip directories
    if (is_dir($file)) {
        continue;
    }
    
    // Get file modification time
    $file_mtime = filemtime($file);
    $file_age = $current_time - $file_mtime;
    
    // Skip files that are not session files
    if (strpos(basename($file), 'sess_') !== 0) {
        continue;
    }
    
    // Delete files older than max lifetime
    if ($file_age > $max_lifetime) {
        if (@unlink($file)) {
            echo "Deleted: " . basename($file) . " (age: " . round($file_age / 86400, 1) . " days)\n";
            $deleted++;
        } else {
            echo "Error deleting: " . basename($file) . "\n";
        }
    }
}

// Output summary
echo "\nSession cleanup complete.\n";
echo "Total session files: $total\n";
echo "Deleted: $deleted\n";
echo "Remaining: " . ($total - $deleted) . "\n";

// Also clean up empty directories (if any)
@rmdir($session_dir); // This will only work if the directory is empty
