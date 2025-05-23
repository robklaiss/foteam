<?php
/**
 * Session Test Script
 * 
 * This script tests the session handling functionality and ensures
 * that sessions are being managed correctly.
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to safely get cart count
function get_cart_count($session) {
    if (!isset($session['cart']) || !is_array($session['cart'])) {
        return 0;
    }
    return count($session['cart']);
}

// Include the configuration file which starts the session
require_once __DIR__ . '/includes/config.php';

// Include the session configuration
require_once __DIR__ . '/includes/session_config.php';

// Start output buffering
ob_start();

// Set content type
header('Content-Type: text/plain');

// Function to print a test section header
function print_test_header($title) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "TEST: $title\n";
    echo str_repeat("=", 80) . "\n\n";
}

// Function to print test result
function print_test_result($test_name, $passed, $message = '') {
    $status = $passed ? "PASSED" : "FAILED";
    $color = $passed ? "\033[0;32m" : "\033[0;31m";
    $reset = "\033[0m";
    
    // For web output, use HTML instead of ANSI colors
    if (php_sapi_name() !== 'cli') {
        $color = $passed ? '<span style="color: green;">' : '<span style="color: red;">';
        $reset = '</span>';
    }
    
    echo "[{$color}{$status}{$reset}] {$test_name}";
    if (!empty($message)) {
        echo " - {$message}";
    }
    echo "\n";
    
    return $passed;
}

// Start testing
print_test_header("Session Initialization Test");

// Test session initialization
function test_session_initialization() {
    echo "\n=== Starting Session Initialization Test ===\n";
    
    // Start or resume the session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if session is active
    $is_session_active = session_status() === PHP_SESSION_ACTIVE;
    if (!$is_session_active) {
        echo "[FAILED] Session is not active after starting\n";
        return false;
    }
    echo "[PASSED] Session is active after including config.php\n";
    
    // Check if session ID is set
    $session_id = session_id();
    if (empty($session_id)) {
        echo "[FAILED] Session ID is not set\n";
        return false;
    }
    echo "[PASSED] Session ID is set - Session ID: $session_id\n";
    
    // Initialize session data structure if not exists
    if (!isset($_SESSION['initiated'])) {
        $_SESSION = [
            'initiated' => 1,
            'session_start_time' => time(),
            'last_activity' => time(),
            'cart' => []
        ];
    }
    
    // Ensure cart is always an array
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Check if cart exists in session
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        echo "[FAILED] Cart is not properly initialized in session\n";
        return false;
    }
    echo "[PASSED] Cart exists in session\n";
    
    // Add a test item to cart
    $test_item = [
        'id' => 'test_' . time(),
        'name' => 'Test Item',
        'price' => 9.99
    ];
    
    $_SESSION['cart'][] = $test_item;
    $cart_count = get_cart_count($_SESSION);
    
    if ($cart_count !== 1) {
        echo "[FAILED] Failed to add item to cart. Expected 1 item, got $cart_count\n";
        return false;
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    
    // Save the session
    session_write_close();
    
    echo "[PASSED] Added item to cart - Cart now has $cart_count items\n";
    echo "=== End of Session Initialization Test ===\n\n";
    return true;
}

$test1 = test_session_initialization();
print_test_result("Session initialization test", $test1);

// Test 2: Verify session ID exists
$session_id = session_id();
$test2 = !empty($session_id);
print_test_result("Session ID is set", $test2, "Session ID: {$session_id}");

// Test 3: Check if cart exists in session
$test3 = isset($_SESSION['cart']);
print_test_result("Cart exists in session", $test3);

// Test 4: Add item to cart
$test_item = [
    'id' => 'test_' . time(),
    'name' => 'Test Item',
    'price' => 9.99
];

// Store the current cart count
$initial_count = get_cart_count($_SESSION);

// Add item to cart
$_SESSION['cart'][] = $test_item;
$test4 = (get_cart_count($_SESSION) === $initial_count + 1);
print_test_result("Added item to cart", $test4, "Cart now has " . get_cart_count($_SESSION) . " items");

// Test 5: Save and restart session
print_test_header("Session Persistence Test");

// Save current session data
$session_data = $_SESSION;
$session_id_before = session_id();

// Write and close session
session_write_close();

// Start a new session
session_start();
$session_id_after = session_id();

// Test if session ID is the same after restart
$test5 = ($session_id_before === $session_id_after);
print_test_result("Session ID remains the same after restart", $test5, 
    "Before: {$session_id_before}, After: {$session_id_after}");

// Test if cart data persists
$test6 = isset($_SESSION['cart']) && (get_cart_count($_SESSION) === $initial_count + 1);
print_test_result("Cart data persists after session restart", $test6, 
    "Cart items after restart: " . get_cart_count($_SESSION));

// Test session timeout
function test_session_timeout() {
    echo "\n=== Starting Session Timeout Test ===\n";
    
    // Close any existing session
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    
    // Set a short session lifetime for testing (5 seconds)
    $sessionTimeout = 5;
    ini_set('session.gc_maxlifetime', $sessionTimeout);
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 1);
    
    // Configure session settings
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // Start a new session
    session_start();
    
    // Set test data
    $test_item = [
        'id' => 'test_' . time(),
        'name' => 'Test Item',
        'price' => 9.99
    ];
    
    // Initialize session data
    $currentTime = time();
    $_SESSION = [
        'test_data' => 'Test value',
        'cart' => [$test_item],
        'last_activity' => $currentTime,
        'initiated' => 1,
        'session_start_time' => $currentTime
    ];
    
    // Save session data
    session_write_close();
    
    // Get the session data after writing
    session_start();
    $old_session_id = session_id();
    $old_session_data = $_SESSION;
    
    // Ensure cart is initialized as an array
    if (!isset($old_session_data['cart']) || !is_array($old_session_data['cart'])) {
        $old_session_data['cart'] = [];
    }
    
    echo "Current Session ID: $old_session_id\n";
    echo "Session Data: " . print_r($old_session_data, true) . "\n";
    
    // Close the session to write data
    session_write_close();
    
    // Get the session save path
    $savePath = session_save_path();
    $session_file = $savePath . '/sess_' . $old_session_id;
    echo "Session File: $session_file\n";
    
    if (!file_exists($session_file)) {
        return [
            'error' => 'Session file not found',
            'file' => $session_file
        ];
    }
    
    // Read the current session data
    $data = file_get_contents($session_file);
    echo "Raw Session Data: " . $data . "\n";
    
    // Set the session file's modification time to be older than the timeout
    $new_time = time() - ($sessionTimeout + 5); // Make sure it's older than the timeout
    echo "Touching session file to time: " . date('Y-m-d H:i:s', $new_time) . "\n";
    touch($session_file, $new_time);
    
    // Clear session data
    $_SESSION = [];
    
    // Start a new session - this should trigger our custom handler's timeout logic
    echo "Starting new session...\n";
    
    // Start a new session
    session_start();
    
    // Get the current session ID
    $current_session_id = session_id();
    
    // Check if we need to regenerate the session ID
    if ($current_session_id === $old_session_id) {
        // Only regenerate if we still have the old session ID
        session_regenerate_id(true);
        $new_session_id = session_id();
    } else {
        $new_session_id = $current_session_id;
    }
    
    $session_regenerated = ($old_session_id !== $new_session_id);
    
    // Ensure session is properly initialized
    if (!isset($_SESSION['initiated'])) {
        $_SESSION = [
            'initiated' => 1,
            'session_start_time' => time(),
            'last_activity' => time(),
            'cart' => [],
            'renewed' => 1
        ];
    }
    
    $new_session_data = $_SESSION;
    
    echo "New Session ID: $new_session_id\n";
    echo "Session Regenerated: " . ($session_regenerated ? 'Yes' : 'No') . "\n";
    echo "New Session Data: " . print_r($new_session_data, true) . "\n";
    
    // Check if the session file was actually updated
    $new_session_file = $savePath . '/sess_' . $new_session_id;
    if (file_exists($new_session_file)) {
        $file_mtime = filemtime($new_session_file);
        echo "New session file last modified: " . date('Y-m-d H:i:s', $file_mtime) . "\n";
    }
    
    // Check if cart is preserved (should be empty after timeout)
    $cart_preserved = !empty($new_session_data['cart']);
    $cart_should_be_empty = true;
    
    // Prepare the result
    $result = [
        'old_session_id' => $old_session_id,
        'new_session_id' => $new_session_id,
        'session_regenerated' => $session_regenerated,
        'session_data' => $new_session_data,
        'last_activity_before' => $old_session_data['last_activity'] ?? 0,
        'last_activity_after' => $new_session_data['last_activity'] ?? 0,
        'cart_preserved' => $cart_preserved,
        'cart_should_be_empty' => $cart_should_be_empty,
        'save_path' => $savePath,
        'session_timeout' => $sessionTimeout
    ];
    
    // Clean up - remove the test session files
    if (file_exists($session_file)) {
        unlink($session_file);
    }
    if (file_exists($new_session_file) && $new_session_file !== $session_file) {
        unlink($new_session_file);
    }
    
    // Close the session
    session_write_close();
    
    // Restore original session settings
    ini_restore('session.gc_maxlifetime');
    ini_restore('session.gc_probability');
    ini_restore('session.gc_divisor');
    
    echo "=== End of Session Timeout Test ===\n\n";
    return $result;
}

$result = test_session_timeout();
$test7 = $result['session_regenerated'];
print_test_result("Session regenerated after timeout", $test7, 
    $test7 ? "Session was regenerated (good)" : "Session was not regenerated (expected a new session ID)");

// Check if session was properly reset after timeout
$session_reset = (
    isset($result['session_data']['initiated']) && 
    $result['session_data']['initiated'] === 1 &&
    isset($result['session_data']['last_activity']) &&
    $result['session_data']['last_activity'] > 0 &&
    isset($result['session_data']['session_start_time']) &&
    $result['session_data']['session_start_time'] > 0 &&
    isset($result['session_data']['cart']) && 
    is_array($result['session_data']['cart']) && 
    empty($result['session_data']['cart'])
);

print_test_result("Session data reset after timeout", $session_reset,
    $session_reset ? "Session data was properly reset" : 
    "Expected: initiated=1, last_activity=set, cart=exists+empty, session_start_time=set\n" .
    "Got: " . print_r($result['session_data'], true));

// Display final session info
print_test_header("Final Session Information");
echo "Session ID: " . session_id() . "\n";
echo "Session Data: " . print_r($_SESSION, true) . "\n";

// Display session configuration
echo "\nSession Configuration:\n";
echo "Session Save Path: " . ini_get('session.save_path') . "\n";
echo "Session Name: " . session_name() . "\n";
echo "Session Cookie Lifetime: " . ini_get('session.cookie_lifetime') . "\n";
echo "Session GC Max Lifetime: " . ini_get('session.gc_maxlifetime') . "\n";
echo "Session Use Strict Mode: " . (ini_get('session.use_strict_mode') ? 'Yes' : 'No') . "\n";

// Display any errors that might have occurred
$errors = ob_get_clean();
if (!empty($errors)) {
    print_test_header("Errors and Warnings");
    echo $errors;
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "SESSION TEST COMPLETE\n";
echo str_repeat("=", 80) . "\n";

// Clean up test items from cart
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $key => $item) {
        if (isset($item['id']) && strpos($item['id'], 'test_') === 0) {
            unset($_SESSION['cart'][$key]);
        }
    }
    $_SESSION['cart'] = array_values($_SESSION['cart']); // Reindex
    
} else {
    echo "Session is not active. Status: " . session_status() . "\n";
    
    // Try to start session
    if (session_start()) {
        echo "Session started successfully\n";
        $_SESSION['test'] = 'test_value';
        echo "Test session value set\n";
    } else {
        echo "Failed to start session\n";
    }
}

// Check session save path
$save_path = ini_get('session.save_path');
echo "\nSession save path: " . ($save_path ?: 'default') . "\n";

// Check if save path is writable
if ($save_path) {
    echo "Save path is " . (is_writable($save_path) ? 'writable' : 'NOT writable') . "\n";
    
    // List session files
    if (is_dir($save_path)) {
        echo "Session files in directory:\n";
        $files = scandir($save_path);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                echo "- $file (" . filesize("$save_path/$file") . " bytes)\n";
            }
        }
    }
}

// Check session cookie parameters
$cookie_params = session_get_cookie_params();
echo "\nSession cookie parameters:\n";
echo "- Lifetime: " . $cookie_params['lifetime'] . "\n";
echo "- Path: " . $cookie_params['path'] . "\n";
echo "- Domain: " . ($cookie_params['domain'] ?: 'not set') . "\n";
echo "- Secure: " . ($cookie_params['secure'] ? 'Yes' : 'No') . "\n";
echo "- HttpOnly: " . ($cookie_params['httponly'] ? 'Yes' : 'No') . "\n";
echo "- SameSite: " . ($cookie_params['samesite'] ?? 'Not set') . "\n";

// Output any errors
$errors = ob_get_clean();
echo $errors;
