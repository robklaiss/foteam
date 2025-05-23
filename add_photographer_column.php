<?php
/**
 * Database migration script to add is_photographer column to users table
 */
require_once 'includes/config.php';
require_once 'includes/functions.php';

echo "Starting database migration...\n";

// Connect to the database
$db = db_connect();

// Check if the column already exists
$result = $db->query("PRAGMA table_info(users)");
$column_exists = false;

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    if ($row['name'] === 'is_photographer') {
        $column_exists = true;
        break;
    }
}

// Add the column if it doesn't exist
if (!$column_exists) {
    echo "Adding is_photographer column to users table...\n";
    $db->exec("ALTER TABLE users ADD COLUMN is_photographer INTEGER DEFAULT 0");
    echo "Column added successfully.\n";
} else {
    echo "Column is_photographer already exists.\n";
}

// Set the current user as a photographer for testing
if (isset($_GET['make_photographer']) && $_GET['make_photographer'] == 1) {
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    
    if ($user_id > 0) {
        echo "Setting user ID $user_id as a photographer...\n";
        $stmt = $db->prepare("UPDATE users SET is_photographer = 1 WHERE id = :user_id");
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        echo "User updated successfully.\n";
    } else {
        echo "Please provide a valid user_id parameter.\n";
    }
}

// Close the database connection
$db->close();

echo "Migration completed.\n";
echo "<p>You can now <a href='fotografos/index.php'>go to the photographers area</a>.</p>";
echo "<p>Or <a href='index.php'>return to the home page</a>.</p>";
?>
