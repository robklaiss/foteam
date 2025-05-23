<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

echo '<h2>Login Test</h2>';

// Display current session info before login attempt
echo '<h3>Before Login:</h3>';
echo '<pre>';
echo 'Session ID: ' . session_id() . "\n";
echo 'Session Status: ' . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'NOT ACTIVE') . "\n";
echo 'is_logged_in(): ' . (is_logged_in() ? 'TRUE' : 'FALSE') . "\n";
echo 'Session Contents:' . "\n";
print_r($_SESSION);
echo '</pre>';

// Test login with hardcoded test credentials
// IMPORTANT: Change these to valid credentials for your site
$email = 'test@example.com';  // Replace with a valid email in your database
$password = 'password123';    // Replace with the correct password

echo '<h3>Attempting Login:</h3>';
echo "Email: $email<br>";
echo "Password: [hidden for security]<br>";

$result = login_user($email, $password);

echo '<pre>';
echo 'Login Result: ';
print_r($result);
echo '</pre>';

// Display session info after login attempt
echo '<h3>After Login:</h3>';
echo '<pre>';
echo 'Session ID: ' . session_id() . "\n";
echo 'Session Status: ' . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'NOT ACTIVE') . "\n";
echo 'is_logged_in(): ' . (is_logged_in() ? 'TRUE' : 'FALSE') . "\n";
echo 'Session Contents:' . "\n";
print_r($_SESSION);
echo '</pre>';

// Link to check header page
echo '<p><a href="index.php">Go to homepage</a> to check if login state is preserved</p>';
echo '<p><a href="session_test.php">Check session info</a></p>';
?>
