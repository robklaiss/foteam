<?php
/**
 * Session Fix Utility
 * 
 * This script helps diagnose and fix session-related issues.
 * It's now compatible with the new session management system.
 */

echo '<h1>Session Fix Utility</h1>';

// Include bootstrap to initialize the environment
require_once __DIR__ . '/includes/bootstrap.php';

// 1. Check and create session directory
$session_dir = __DIR__ . '/tmp/sessions';
if (!file_exists($session_dir)) {
    echo "<p>Creating session directory at: {$session_dir}</p>";
    $result = mkdir($session_dir, 0755, true);
    echo $result ? "<p style='color:green'>Directory created successfully</p>" : "<p style='color:red'>Failed to create directory</p>";
} else {
    echo "<p>Session directory exists at: {$session_dir}</p>";
}

// 2. Check directory permissions
echo "<p>Checking permissions:</p>";
echo "<p>Directory writable: " . (is_writable($session_dir) ? "Yes ✅" : "No ❌") . "</p>";

// 3. Update permissions if needed
if (!is_writable($session_dir)) {
    echo "<p>Attempting to fix permissions...</p>";
    $result = chmod($session_dir, 0755);
    echo $result ? "<p style='color:green'>Permissions updated</p>" : "<p style='color:red'>Failed to update permissions</p>";
}

// 4. Test session functionality
echo "<h2>Testing Session</h2>";

// Session is already started by bootstrap.php
echo "<p>Session status: " . session_status() . " (2 = PHP_SESSION_ACTIVE)</p>";

// Set a test value
$_SESSION['test_value'] = 'Session is working: ' . date('Y-m-d H:i:s');

// Set a test value
$_SESSION['test_value'] = 'Session is working: ' . date('Y-m-d H:i:s');

// Display session information
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Test value: " . htmlspecialchars($_SESSION['test_value']) . "</p>";
echo "<p>Session save path: " . session_save_path() . "</p>";

// 5. Provide solutions
echo "<h2>Session Information</h2>";
echo "<p>Session management is now handled by bootstrap.php. Here's what's configured:</p>";
echo "<ul>";
echo "<li>Session save path: " . session_save_path() . "</li>";
echo "<li>Session name: " . session_name() . "</li>";
echo "<li>Session status: " . session_status() . " (2 = PHP_SESSION_ACTIVE)</li>";
echo "<li>Session cookie parameters: <pre>" . print_r(session_get_cookie_params(), true) . "</pre></li>";
echo "</ul>";

// 6. Test session persistence
if (isset($_SESSION['test_count'])) {
    $_SESSION['test_count']++;
} else {
    $_SESSION['test_count'] = 1;
}
echo "<p>Page views in this session: " . $_SESSION['test_count'] . "</p>";

// 7. Provide troubleshooting tips
echo "<h2>Troubleshooting</h2>";
echo "<p>If you're experiencing session issues:</p>";
echo "<ol>";
echo "<li>Check that the session directory exists and is writable by the web server</li>";
echo "<li>Verify that session configuration in bootstrap.php is correct</li>";
echo "<li>Check PHP error logs for any session-related errors</li>";
echo "<li>Try clearing your browser cookies for this site</li>";
echo "<li>Test in a private/incognito window to rule out caching issues</li>";
echo "</ol>";

// Output a link to test the session
echo "<p><a href='session_test.php'>Click here to test if your session is working</a></p>";
?>
