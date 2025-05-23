<?php
require_once 'config.php';
require_once 'functions.php';

// Display all PHP errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Updating FoTeam Database Schema</h1>";

try {
    // Connect to the database
    $db = db_connect();
    echo "<p>Database connection established.</p>";
    
    // Start transaction
    $db->exec('BEGIN TRANSACTION');
    
    // Add is_photographer field to users table if it doesn't exist
    $result = $db->query("PRAGMA table_info(users)");
    $columns = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $columns[] = $row['name'];
    }
    
    if (!in_array('is_photographer', $columns)) {
        $db->exec('ALTER TABLE users ADD COLUMN is_photographer INTEGER DEFAULT 0');
        echo "<p>Added is_photographer field to users table.</p>";
    }
    
    // Add watermark_path field to images table if it doesn't exist
    $result = $db->query("PRAGMA table_info(images)");
    $columns = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $columns[] = $row['name'];
    }
    
    if (!in_array('watermark_path', $columns)) {
        $db->exec('ALTER TABLE images ADD COLUMN watermark_path TEXT');
        echo "<p>Added watermark_path field to images table.</p>";
    }
    
    // Create photographer_marathons table if it doesn't exist
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='photographer_marathons'");
    if (!$result->fetchArray()) {
        $db->exec('CREATE TABLE photographer_marathons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            marathon_id INTEGER NOT NULL,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (marathon_id) REFERENCES marathons(id) ON DELETE CASCADE,
            UNIQUE(user_id, marathon_id)
        )');
        echo "<p>Created photographer_marathons table.</p>";
    }
    
    // Create activity_log table if it doesn't exist
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='activity_log'");
    if (!$result->fetchArray()) {
        $db->exec('CREATE TABLE activity_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT NOT NULL,
            details TEXT,
            ip_address TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )');
        echo "<p>Created activity_log table.</p>";
    }
    
    // Commit transaction
    $db->exec('COMMIT');
    echo "<p>Database schema updated successfully!</p>";
    
    // Create test photographer user if none exists
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE is_photographer = 1");
    $result = $stmt->execute();
    $photographer_count = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    if ($photographer_count == 0) {
        $username = "fotografo";
        $email = "fotografo@example.com";
        $password = password_hash("fotografo123", PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("INSERT INTO users (username, email, password, is_photographer) VALUES (:username, :email, :password, 1)");
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':password', $password, SQLITE3_TEXT);
        $result = $stmt->execute();
        
        if ($result) {
            echo "<p>Created test photographer user:</p>";
            echo "<ul>";
            echo "<li>Username: fotografo</li>";
            echo "<li>Password: fotografo123</li>";
            echo "</ul>";
        }
    }
    
    // Close the database connection
    $db->close();
    
    echo "<p>Schema update complete! <a href='/index.php'>Go to homepage</a></p>";
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db)) {
        $db->exec('ROLLBACK');
        $db->close();
    }
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
