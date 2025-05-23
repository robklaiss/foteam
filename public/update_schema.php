<?php
// Using absolute paths to avoid include issues
$base_dir = realpath(__DIR__ . '/..');
require_once $base_dir . '/includes/config.php';
require_once $base_dir . '/includes/functions.php';

// This script adds missing columns to the orders table
// to match what the code is expecting

// Connect to database
$db = db_connect();

// Start a transaction for safety
$db->exec('BEGIN TRANSACTION');

try {
    // Check if the columns already exist (avoid errors if columns already exist)
    $result = $db->query("PRAGMA table_info(orders)");
    $columns = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $columns[] = $row['name'];
    }
    
    // Add name column if it doesn't exist
    if (!in_array('name', $columns)) {
        $db->exec("ALTER TABLE orders ADD COLUMN name TEXT");
        echo "Added 'name' column to orders table<br>";
    }
    
    // Add email column if it doesn't exist
    if (!in_array('email', $columns)) {
        $db->exec("ALTER TABLE orders ADD COLUMN email TEXT");
        echo "Added 'email' column to orders table<br>";
    }
    
    // Add phone column if it doesn't exist
    if (!in_array('phone', $columns)) {
        $db->exec("ALTER TABLE orders ADD COLUMN phone TEXT");
        echo "Added 'phone' column to orders table<br>";
    }
    
    // Add address column if it doesn't exist
    if (!in_array('address', $columns)) {
        $db->exec("ALTER TABLE orders ADD COLUMN address TEXT");
        echo "Added 'address' column to orders table<br>";
    }
    
    // Add payment_method column if it doesn't exist
    if (!in_array('payment_method', $columns)) {
        $db->exec("ALTER TABLE orders ADD COLUMN payment_method TEXT");
        echo "Added 'payment_method' column to orders table<br>";
    }
    
    // Update customer columns to mirror the name/email/phone columns for compatibility
    $db->exec("
        CREATE TRIGGER IF NOT EXISTS sync_customer_info
        AFTER INSERT ON orders
        FOR EACH ROW
        BEGIN
            UPDATE orders 
            SET 
                customer_name = NEW.name,
                customer_email = NEW.email, 
                customer_phone = NEW.phone
            WHERE id = NEW.id
            AND (NEW.name IS NOT NULL OR NEW.email IS NOT NULL OR NEW.phone IS NOT NULL);
        END;
    ");
    echo "Created trigger to sync customer info between columns<br>";
    
    // Commit changes
    $db->exec('COMMIT');
    echo "<strong style='color:green'>Database schema updated successfully!</strong><br>";
    echo "<a href='checkout.php' class='btn btn-primary'>Return to Checkout</a>";
} catch (Exception $e) {
    // Rollback on error
    $db->exec('ROLLBACK');
    echo "<strong style='color:red'>Error updating database schema: " . $e->getMessage() . "</strong>";
}

// Close database connection
$db->close();
?>
