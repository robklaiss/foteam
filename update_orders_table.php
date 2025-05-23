<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once 'includes/config.php';

// Connect to the database
$db = db_connect();

if (!$db) {
    die("Error connecting to the database");
}

// Read the migration SQL file
$migration_sql = file_get_contents('database/migrations/add_columns_to_orders_table.sql');

if ($migration_sql === false) {
    die("Error reading migration file");
}

try {
    // Execute the migration
    $result = $db->exec($migration_sql);
    
    if ($result === false) {
        echo "Error executing migration: " . $db->lastErrorMsg() . "\n";
    } else {
        echo "Successfully updated the orders table.\n";
        
        // Verify the changes
        $result = $db->query("PRAGMA table_info(orders)");
        $columns = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $columns[] = $row['name'];
        }
        
        echo "Current columns in orders table: " . implode(', ', $columns) . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    
    // In case of error, rollback any changes
    $db->exec('ROLLBACK');
    echo "Changes rolled back due to error.\n";
}

$db->close();
?>
