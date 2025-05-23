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

// Start transaction
$db->exec('BEGIN TRANSACTION');

try {
    // Check if columns exist and add them if they don't
    $columns = [
        'address' => "TEXT DEFAULT ''",
        'payment_method' => "TEXT DEFAULT 'credit_card'",
        'subtotal' => 'REAL DEFAULT 0.0',
        'tax' => 'REAL DEFAULT 0.0',
    ];
    
    // Get current table info
    $result = $db->query("PRAGMA table_info(orders)");
    $existing_columns = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $existing_columns[$row['name']] = true;
    }
    
    // Add missing columns
    foreach ($columns as $column => $definition) {
        if (!isset($existing_columns[$column])) {
            $sql = "ALTER TABLE orders ADD COLUMN $column $definition";
            if (!$db->exec($sql)) {
                throw new Exception("Failed to add column $column: " . $db->lastErrorMsg());
            }
            echo "Added column: $column\n";
        } else {
            echo "Column already exists: $column\n";
        }
    }
    
    // Rename columns if they exist
    $rename_columns = [
        'customer_name' => 'name',
        'customer_email' => 'email',
        'customer_phone' => 'phone',
    ];
    
    foreach ($rename_columns as $old => $new) {
        if (isset($existing_columns[$old]) && !isset($existing_columns[$new])) {
            $sql = "ALTER TABLE orders RENAME COLUMN $old TO $new";
            if (!$db->exec($sql)) {
                throw new Exception("Failed to rename column $old to $new: " . $db->lastErrorMsg());
            }
            echo "Renamed column: $old to $new\n";
        } elseif (isset($existing_columns[$new])) {
            echo "Column $new already exists, not renaming $old\n";
        }
    }
    
    // Commit transaction
    $db->exec('COMMIT');
    echo "Database schema updated successfully!\n";
    
    // Show final table structure
    $result = $db->query("PRAGMA table_info(orders)");
    echo "\nFinal table structure:\n";
    echo str_pad("Column", 20) . "| Type\n";
    echo str_repeat("-", 50) . "\n";
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        echo str_pad($row['name'], 20) . "| " . $row['type'] . 
             ($row['notnull'] ? ' NOT NULL' : '') . 
             ($row['dflt_value'] !== null ? ' DEFAULT ' . $row['dflt_value'] : '') . "\n";
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    $db->exec('ROLLBACK');
    echo "Error: " . $e->getMessage() . "\n";
}

$db->close();
?>
