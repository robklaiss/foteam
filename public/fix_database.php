<?php
// Output errors for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Define database path directly
$db_path = __DIR__ . '/../database/foteam.db';

echo "<h1>Database Schema Repair Utility</h1>";

// Check if database exists
if (!file_exists($db_path)) {
    die("<p style='color:red'>Error: Database not found at $db_path</p>");
}

try {
    // Connect to database directly
    $db = new SQLite3($db_path);
    $db->enableExceptions(true);
    
    echo "<p>Successfully connected to database at $db_path</p>";
    
    // Start transaction
    $db->exec('BEGIN TRANSACTION');
    
    // Get current schema for orders table
    $result = $db->query("PRAGMA table_info(orders)");
    $columns = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $columns[] = $row['name'];
    }
    
    echo "<h2>Current columns in orders table:</h2>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>$column</li>";
    }
    echo "</ul>";
    
    // Add missing columns
    $missing_columns = [
        'name' => 'TEXT',
        'email' => 'TEXT',
        'phone' => 'TEXT',
        'address' => 'TEXT',
        'payment_method' => 'TEXT'
    ];
    
    echo "<h2>Adding missing columns:</h2>";
    $added = false;
    
    foreach ($missing_columns as $column => $type) {
        if (!in_array($column, $columns)) {
            try {
                $db->exec("ALTER TABLE orders ADD COLUMN $column $type");
                echo "<p style='color:green'>Added column '$column' ($type) to orders table</p>";
                $added = true;
            } catch (Exception $e) {
                echo "<p style='color:orange'>Could not add column '$column': " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p>Column '$column' already exists</p>";
        }
    }
    
    // Create a trigger to sync columns if not already present
    if ($added) {
        try {
            // Drop existing trigger if it exists
            $db->exec("DROP TRIGGER IF EXISTS sync_customer_fields");
            
            // Create new trigger
            $db->exec("
                CREATE TRIGGER sync_customer_fields
                AFTER INSERT ON orders
                FOR EACH ROW
                WHEN NEW.name IS NOT NULL OR NEW.email IS NOT NULL OR NEW.phone IS NOT NULL
                BEGIN
                    UPDATE orders SET 
                        customer_name = COALESCE(NEW.name, customer_name),
                        customer_email = COALESCE(NEW.email, customer_email),
                        customer_phone = COALESCE(NEW.phone, customer_phone)
                    WHERE id = NEW.id;
                END;
            ");
            echo "<p style='color:green'>Created trigger to sync customer fields</p>";
            
            // Also sync from customer fields to regular fields
            $db->exec("
                CREATE TRIGGER IF NOT EXISTS sync_regular_fields
                AFTER INSERT ON orders
                FOR EACH ROW
                WHEN NEW.customer_name IS NOT NULL OR NEW.customer_email IS NOT NULL OR NEW.customer_phone IS NOT NULL
                BEGIN
                    UPDATE orders SET 
                        name = COALESCE(NEW.customer_name, name),
                        email = COALESCE(NEW.customer_email, email),
                        phone = COALESCE(NEW.customer_phone, phone)
                    WHERE id = NEW.id;
                END;
            ");
            echo "<p style='color:green'>Created trigger to sync regular fields</p>";
        } catch (Exception $e) {
            echo "<p style='color:orange'>Could not create triggers: " . $e->getMessage() . "</p>";
        }
    }
    
    // Commit changes
    $db->exec('COMMIT');
    
    echo "<h2>Database Schema Update Complete</h2>";
    if ($added) {
        echo "<p style='color:green'>Successfully added missing columns to the orders table.</p>";
    } else {
        echo "<p>No changes were needed. All required columns already exist.</p>";
    }
    
    // Verify schema after updates
    $result = $db->query("PRAGMA table_info(orders)");
    $updated_columns = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $updated_columns[] = $row['name'];
    }
    
    echo "<h2>Updated columns in orders table:</h2>";
    echo "<ul>";
    foreach ($updated_columns as $column) {
        echo "<li>$column</li>";
    }
    echo "</ul>";
    
    // Close connection
    $db->close();
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
    if (isset($db)) {
        $db->exec('ROLLBACK');
        $db->close();
    }
}

echo "<p><a href='checkout.php' style='display:inline-block; padding:10px 20px; background-color:#3498db; color:white; text-decoration:none; border-radius:4px;'>Back to Checkout</a></p>";
?>
