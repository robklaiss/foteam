<?php
// Update database script to add watermarked_path column
require_once 'includes/config.php';

// Display all PHP errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Updating FoTeam Database</h1>";

try {
    // Create a database connection
    $db = db_connect();
    echo "<p>Database connection established.</p>";
    
    // Check if watermarked_path column already exists
    $result = $db->query("PRAGMA table_info(images)");
    $column_exists = false;
    
    while ($column = $result->fetchArray(SQLITE3_ASSOC)) {
        if ($column['name'] === 'watermarked_path') {
            $column_exists = true;
            break;
        }
    }
    
    if (!$column_exists) {
        // Add watermarked_path column to images table
        $db->exec('ALTER TABLE images ADD COLUMN watermarked_path TEXT');
        echo "<p>Added watermarked_path column to images table.</p>";
    } else {
        echo "<p>Column watermarked_path already exists in images table.</p>";
    }
    
    // Close the database connection
    $db->close();
    
    echo "<p>Database update complete! <a href='index.php'>Go to homepage</a></p>";
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
