<?php
/**
 * FRESH TEST PAGE
 * Created at 21:39 to completely isolate session handling issues
 */

// Just include our new fresh session file
require_once __DIR__ . '/includes/session_fresh.php';

// Output a success message if we got here
echo '<h1>SUCCESS! This page loaded without session warnings</h1>';
echo '<p>Session ID: ' . session_id() . '</p>';
echo '<pre>SESSION: ' . print_r($_SESSION, true) . '</pre>';

// Create a link to cart.php
echo '<p><a href="cart.php">Go to cart.php</a></p>';
?>
