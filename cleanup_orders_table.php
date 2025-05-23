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
    // 1. Copy data from old columns to new columns if new columns are empty
    $updates = [
        "UPDATE orders SET name = customer_name WHERE (name IS NULL OR name = '') AND customer_name IS NOT NULL",
        "UPDATE orders SET email = customer_email WHERE (email IS NULL OR email = '') AND customer_email IS NOT NULL",
        "UPDATE orders SET phone = customer_phone WHERE (phone IS NULL OR phone = '') AND customer_phone IS NOT NULL",
    ];
    
    foreach ($updates as $sql) {
        $db->exec($sql);
    }
    
    // 2. Ensure required fields have values
    $db->exec("UPDATE orders SET name = 'Customer' WHERE name IS NULL OR name = ''");
    $db->exec("UPDATE orders SET email = 'no-email@example.com' WHERE email IS NULL OR email = ''");
    $db->exec("UPDATE orders SET phone = '' WHERE phone IS NULL");
    
    // 3. Set default values for new columns if they're NULL
    $db->exec("UPDATE orders SET subtotal = total_amount WHERE subtotal IS NULL");
    $db->exec("UPDATE orders SET tax = 0.0 WHERE tax IS NULL");
    $db->exec("UPDATE orders SET payment_method = 'credit_card' WHERE payment_method IS NULL");
    
    // 4. Create a backup of the current table
    $db->exec("CREATE TABLE IF NOT EXISTS orders_backup AS SELECT * FROM orders");
    
    // 5. Create a new table with the correct structure
    $db->exec("CREATE TABLE orders_new (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        phone TEXT NOT NULL,
        address TEXT DEFAULT '',
        payment_method TEXT NOT NULL DEFAULT 'credit_card',
        subtotal REAL NOT NULL DEFAULT 0.0,
        tax REAL NOT NULL DEFAULT 0.0,
        total_amount REAL NOT NULL,
        status TEXT CHECK(status IN ('pending', 'processing', 'completed', 'cancelled', 'refunded')) DEFAULT 'pending',
        order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )");
    
    // 6. Copy data to the new table
    $db->exec("INSERT INTO orders_new (
        id, user_id, name, email, phone, address, payment_method, 
        subtotal, tax, total_amount, status, order_date
    ) SELECT 
        id, user_id, name, email, phone, address, payment_method,
        subtotal, tax, total_amount, status, order_date
    FROM orders");
    
    // 7. Drop the old table and rename the new one
    $db->exec("DROP TABLE orders");
    $db->exec("ALTER TABLE orders_new RENAME TO orders");
    
    // 8. Recreate indexes
    $db->exec("CREATE INDEX IF NOT EXISTS idx_orders_user_id ON orders(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status)");
    
    // Commit transaction
    $db->exec('COMMIT');
    
    echo "Orders table has been successfully cleaned up and restructured.\n";
    
    // Show the final structure
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
