<?php
/**
 * Session Test and Debug Tool
 * 
 * This script provides detailed information about the current session
 * and allows testing session functionality.
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define a constant to prevent direct access to other includes
define('IN_SESSION_TEST', true);

// Include the bootstrap file which handles session initialization
require_once __DIR__ . '/includes/bootstrap.php';

// Include config and functions
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Ensure we have a session started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle test actions
$action = $_GET['action'] ?? '';
$message = '';

switch ($action) {
    case 'set_test':
        $_SESSION['test_key'] = 'Test value at ' . date('Y-m-d H:i:s');
        $message = 'Test value set in session';
        break;
        
    case 'clear_test':
        unset($_SESSION['test_key']);
        $message = 'Test value cleared from session';
        break;
        
    case 'regenerate':
        session_regenerate_id(true);
        $message = 'Session ID regenerated';
        break;
        
    case 'destroy':
        session_destroy();
        session_start(); // Start a new session
        $message = 'Session destroyed and new session started';
        break;
}

// Get session information
$sessionInfo = [
    'Session ID' => session_id(),
    'Session Status' => session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'NOT ACTIVE',
    'Session Name' => session_name(),
    'Session Save Path' => session_save_path(),
    'Session Cookie Parameters' => session_get_cookie_params(),
    'is_logged_in()' => function_exists('is_logged_in') ? (is_logged_in() ? 'TRUE' : 'FALSE') : 'Function not found',
    'Session Data' => $_SESSION
];

// Check for session files
$sessionFiles = [];
$savePath = session_save_path();
if (is_dir($savePath)) {
    $files = glob($savePath . '/sess_*');
    if ($files) {
        foreach ($files as $file) {
            $sessionFiles[] = [
                'name' => basename($file),
                'size' => filesize($file),
                'modified' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Test Tool</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
        pre { background: #f4f4f4; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
        .success { color: green; }
        .error { color: red; }
        .actions { margin: 20px 0; }
        .action-btn { 
            display: inline-block; 
            margin: 5px; 
            padding: 8px 15px; 
            background: #4CAF50; 
            color: white; 
            text-decoration: none; 
            border-radius: 4px;
        }
        .action-btn:hover { background: #45a049; }
        .danger { background: #f44336; }
        .danger:hover { background: #d32f2f; }
    </style>
</head>
<body>
    <h1>Session Test and Debug Tool</h1>
    
    <?php if ($message): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <div class="actions">
        <a href="?action=set_test" class="action-btn">Set Test Value</a>
        <a href="?action=clear_test" class="action-btn">Clear Test Value</a>
        <a href="?action=regenerate" class="action-btn">Regenerate Session ID</a>
        <a href="?action=destroy" class="action-btn danger">Destroy Session</a>
        <a href="?" class="action-btn">Refresh</a>
    </div>
    
    <h2>Session Information</h2>
    <pre><?php 
    foreach ($sessionInfo as $key => $value) {
        if (is_array($value)) {
            echo "$key: \n";
            print_r($value);
            echo "\n";
        } else {
            echo "$key: " . htmlspecialchars(print_r($value, true)) . "\n";
        }
    }
    ?></pre>
    
    <h2>Session Files</h2>
    <?php if (empty($sessionFiles)): ?>
        <p>No session files found in <?php echo htmlspecialchars($savePath); ?></p>
    <?php else: ?>
        <table border="1" cellpadding="8" cellspacing="0">
            <tr>
                <th>Filename</th>
                <th>Size</th>
                <th>Last Modified</th>
            </tr>
            <?php foreach ($sessionFiles as $file): ?>
                <tr>
                    <td><?php echo htmlspecialchars($file['name']); ?></td>
                    <td><?php echo $file['size']; ?> bytes</td>
                    <td><?php echo $file['modified']; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
    
    <h2>PHP Session Configuration</h2>
    <pre><?php 
    $sessionConfigs = [
        'session.save_handler',
        'session.save_path',
        'session.use_strict_mode',
        'session.use_cookies',
        'session.use_only_cookies',
        'session.name',
        'session.auto_start',
        'session.cookie_lifetime',
        'session.cookie_path',
        'session.cookie_domain',
        'session.cookie_httponly',
        'session.cookie_samesite',
        'session.serialize_handler',
        'session.gc_probability',
        'session.gc_divisor',
        'session.gc_maxlifetime',
        'session.referer_check',
        'session.cache_limiter',
        'session.cache_expire',
        'session.use_trans_sid',
        'session.sid_length',
        'session.trans_sid_tags',
        'session.sid_bits_per_character',
    ];
    
    foreach ($sessionConfigs as $setting) {
        echo str_pad($setting, 35) . ': ' . htmlspecialchars(ini_get($setting)) . "\n";
    }
    ?></pre>
    
    <h2>Server Information</h2>
    <pre>
PHP Version: <?php echo phpversion(); ?>
Server Software: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?>
Server Name: <?php echo $_SERVER['SERVER_NAME'] ?? 'N/A'; ?>
Request Method: <?php echo $_SERVER['REQUEST_METHOD'] ?? 'N/A'; ?>
    </pre>
    
    <div class="actions">
        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="action-btn">Refresh Page</a>
    </div>
</body>
</html>
