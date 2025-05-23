<?php
/**
 * Clean test file to verify session handling
 */

// Start with a clean output buffer
ob_start();

// Include bootstrap file directly first
require_once __DIR__ . '/includes/bootstrap.php';

// Output session info
echo "<h1>Session Test Page</h1>";
echo "<h2>Session Status After Bootstrap</h2>";
echo "<p>Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'NOT ACTIVE') . "</p>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>SESSION_BOOTSTRAP_LOADED defined: " . (defined('SESSION_BOOTSTRAP_LOADED') ? 'YES' : 'NO') . "</p>";
echo "<p>CONFIG_LOADED defined: " . (defined('CONFIG_LOADED') ? 'YES' : 'NO') . "</p>";

// Debug the session contents
echo "<h2>Session Contents</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Show any headers we've set
$headers = headers_list();
echo "<h2>Headers</h2>";
echo "<pre>";
print_r($headers);
echo "</pre>";

// Links to other pages
echo "<h2>Navigation</h2>";
echo "<p><a href='index.php'>Go to Index</a></p>";
echo "<p><a href='cart.php'>Go to Cart</a></p>";
echo "<p><a href='verify_cart.php'>Verify Cart</a></p>";

// End the buffer and output
$content = ob_get_clean();
echo $content;
?>
