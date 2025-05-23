<?php
// Server environment diagnostic tool
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Start session explicitly
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

echo '<h1>Server Environment Debug</h1>';

// Basic PHP Info
echo '<h2>PHP Version</h2>';
echo '<p>' . phpversion() . '</p>';

// Session Configuration
echo '<h2>Session Configuration</h2>';
echo '<pre>';
$session_settings = [
    'session.save_path',
    'session.name',
    'session.save_handler',
    'session.cookie_domain',
    'session.cookie_path',
    'session.cookie_secure',
    'session.cookie_httponly',
    'session.use_strict_mode',
    'session.use_cookies',
    'session.use_only_cookies',
    'session.gc_maxlifetime',
    'session.cookie_lifetime'
];

foreach ($session_settings as $setting) {
    echo $setting . ': ' . ini_get($setting) . "\n";
}
echo '</pre>';

// Server Information
echo '<h2>Server Information</h2>';
echo '<pre>';
echo 'SERVER_NAME: ' . ($_SERVER['SERVER_NAME'] ?? 'Not set') . "\n";
echo 'HTTP_HOST: ' . ($_SERVER['HTTP_HOST'] ?? 'Not set') . "\n";
echo 'HTTPS: ' . (isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : 'Not set') . "\n";
echo 'DOCUMENT_ROOT: ' . ($_SERVER['DOCUMENT_ROOT'] ?? 'Not set') . "\n";
echo 'SCRIPT_FILENAME: ' . ($_SERVER['SCRIPT_FILENAME'] ?? 'Not set') . "\n";
echo '</pre>';

// Session Data
echo '<h2>Current Session Data</h2>';
echo '<pre>';
print_r($_SESSION);
echo '</pre>';

// Environment Variables
echo '<h2>Environment Variables</h2>';
echo '<pre>';
// Show only selected environment variables to avoid exposing secrets
$safe_env_vars = [
    'DB_PATH',
    'BASE_URL',
    'UPLOAD_DIR'
];

foreach ($safe_env_vars as $var) {
    echo $var . ': ' . (defined($var) ? constant($var) : 'Not defined') . "\n";
}
echo '</pre>';

// Test database connection
echo '<h2>Database Connection Test</h2>';
try {
    $db = db_connect();
    echo '<p style="color: green;">Database connection successful!</p>';
    
    // Check tables
    $tables_query = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    echo '<p>Tables in database:</p>';
    echo '<ul>';
    while ($table = $tables_query->fetchArray(SQLITE3_ASSOC)) {
        echo '<li>' . $table['name'] . '</li>';
    }
    echo '</ul>';
    
    $db->close();
} catch (Exception $e) {
    echo '<p style="color: red;">Database connection failed: ' . $e->getMessage() . '</p>';
}
?>
