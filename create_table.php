<?php
require_once 'includes/config.php';

echo '<h2>Creating Missing Table: photographer_marathons</h2>';

try {
    // Connect to the database
    $db = db_connect();
    
    // Check if the table already exists
    $table_exists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='photographer_marathons'");
    
    if ($table_exists) {
        echo "<p>Table 'photographer_marathons' already exists. No action taken.</p>";
    } else {
        // Create the missing table
        $sql = "
        CREATE TABLE photographer_marathons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            marathon_id INTEGER NOT NULL,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (marathon_id) REFERENCES marathons(id) ON DELETE CASCADE,
            UNIQUE(user_id, marathon_id)
        )";
        
        $result = $db->exec($sql);
        
        if ($result !== false) {
            echo "<p>Success: Table 'photographer_marathons' was created.</p>";
            
            // Add some example data to test the table
            $add_example = $db->exec("
                INSERT INTO photographer_marathons (user_id, marathon_id)
                SELECT u.id, m.id
                FROM users u, marathons m
                WHERE u.is_admin = 1
                LIMIT 1
            ");
            
            if ($add_example !== false) {
                echo "<p>Added example data to the table for testing.</p>";
            }
        } else {
            echo "<p>Error: Failed to create the table.</p>";
            echo "<p>Error message: " . $db->lastErrorMsg() . "</p>";
        }
    }
    
    // Show existing tables for reference
    echo "<h3>All Tables in Database:</h3>";
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    echo "<ul>";
    while ($table = $tables->fetchArray(SQLITE3_ASSOC)) {
        echo "<li>" . $table['name'] . "</li>";
    }
    echo "</ul>";
    
    $db->close();
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

// Add a link back to the admin page
echo '<p><a href="admin/manage_marathons.php">Go to Manage Marathons</a></p>';
echo '<p><a href="index.php">Return to Homepage</a></p>';
?>
