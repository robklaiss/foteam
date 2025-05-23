<?php
// Initialize database script
require_once 'includes/config.php';

// Display all PHP errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Initializing FoTeam Database</h1>";

try {
    // Check if database file exists and delete it to start fresh
    if (file_exists(DB_PATH)) {
        unlink(DB_PATH);
        echo "<p>Removed existing database file.</p>";
    }
    
    // Create a new database connection
    $db = db_connect();
    echo "<p>Database connection established.</p>";
    
    // Create users table
    $db->exec('CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        email TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        is_admin INTEGER DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )');
    echo "<p>Created users table.</p>";
    
    // Create marathons table
    $db->exec('CREATE TABLE marathons (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        event_date DATE NOT NULL,
        is_public INTEGER DEFAULT 0,
        user_id INTEGER NOT NULL,
        location TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )');
    echo "<p>Created marathons table.</p>";
    
    // Create trigger for updated_at in marathons
    $db->exec('CREATE TRIGGER update_marathons_timestamp
        AFTER UPDATE ON marathons
        FOR EACH ROW
        BEGIN
            UPDATE marathons SET updated_at = CURRENT_TIMESTAMP WHERE id = OLD.id;
        END');
    echo "<p>Created marathons trigger.</p>";
    
    // Create images table
    $db->exec('CREATE TABLE images (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        filename TEXT NOT NULL,
        original_path TEXT NOT NULL,
        thumbnail_path TEXT NOT NULL,
        marathon_id INTEGER,
        user_id INTEGER NOT NULL,
        detected_numbers TEXT,
        upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (marathon_id) REFERENCES marathons(id) ON DELETE SET NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )');
    echo "<p>Created images table.</p>";
    
    // Create cart_items table
    $db->exec('CREATE TABLE cart_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        image_id INTEGER NOT NULL,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE CASCADE,
        UNIQUE(user_id, image_id)
    )');
    echo "<p>Created cart_items table.</p>";
    
    // Create orders table
    $db->exec('CREATE TABLE orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        total_amount REAL NOT NULL,
        status TEXT CHECK(status IN ("pending", "completed", "cancelled")) DEFAULT "pending",
        customer_name TEXT NOT NULL,
        customer_email TEXT NOT NULL,
        customer_phone TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )');
    echo "<p>Created orders table.</p>";
    
    // Create order_items table
    $db->exec('CREATE TABLE order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL,
        image_id INTEGER NOT NULL,
        price REAL NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE CASCADE
    )');
    echo "<p>Created order_items table.</p>";
    
    echo "<p>Database schema created successfully.</p>";
    
    // Create a test admin user
    $username = "admin";
    $email = "admin@example.com";
    $password = password_hash("admin123", PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("INSERT INTO users (username, email, password, is_admin) VALUES (:username, :email, :password, 1)");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->bindValue(':password', $password, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    if ($result) {
        echo "<p>Created test admin user:</p>";
        echo "<ul>";
        echo "<li>Username: admin</li>";
        echo "<li>Password: admin123</li>";
        echo "</ul>";
    } else {
        echo "<p>Error creating admin user: " . $db->lastErrorMsg() . "</p>";
    }
    
    // Create a test marathon
    $stmt = $db->prepare("INSERT INTO marathons (name, event_date, is_public, user_id, location) 
                         VALUES (:name, :event_date, 1, 1, :location)");
    $stmt->bindValue(':name', "Test Marathon 2025", SQLITE3_TEXT);
    $stmt->bindValue(':event_date', "2025-05-15", SQLITE3_TEXT);
    $stmt->bindValue(':location', "San Francisco, CA", SQLITE3_TEXT);
    $result = $stmt->execute();
    
    if ($result) {
        echo "<p>Created test marathon.</p>";
    } else {
        echo "<p>Error creating test marathon: " . $db->lastErrorMsg() . "</p>";
    }
    
    // Close the database connection
    $db->close();
    
    echo "<p>Database initialization complete! <a href='index.php'>Go to homepage</a></p>";
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
