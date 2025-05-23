<?php
/**
 * Auto-prepend file to ensure session configuration is loaded first
 * This file should be included at the very beginning of every PHP script
 */

// Include the session configuration early
require_once __DIR__ . '/session_config.php';
?>
