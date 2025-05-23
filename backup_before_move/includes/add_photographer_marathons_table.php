<?php
/**
 * Database migration script to add photographer_marathons table
 */
require_once 'includes/config.php';
require_once 'includes/functions.php';

echo "Starting database migration...\n";

// Connect to the database
$db = db_connect();

// Check if the table already exists
$tableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='photographer_marathons'");

if (!$tableExists) {
    echo "Creating photographer_marathons table...\n";
    
    // Create the photographer_marathons table
    $db->exec("
        CREATE TABLE photographer_marathons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            marathon_id INTEGER NOT NULL,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (marathon_id) REFERENCES marathons(id) ON DELETE CASCADE,
            UNIQUE(user_id, marathon_id)
        )
    ");
    
    echo "Table created successfully.\n";
} else {
    echo "Table photographer_marathons already exists.\n";
}

$db->close();
echo "Migration completed.\n";
?>