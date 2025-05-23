<?php
// Cart Fix Utility
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Output information as we go
echo '<h1>Cart Fix Utility</h1>';

// Check if session is active
echo '<h2>Session Status</h2>';
echo '<p>Session ID: ' . session_id() . '</p>';
echo '<p>Session Status: ' . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'NOT ACTIVE') . '</p>';
echo '<p>Is Logged In: ' . (is_logged_in() ? 'YES' : 'NO') . '</p>';

// Add improved debugging for session cart
echo '<h2>Session Cart</h2>';
if (isset($_SESSION['cart'])) {
    echo '<p>Session cart exists with ' . count($_SESSION['cart']) . ' items</p>';
    echo '<pre>';
    print_r($_SESSION['cart']);
    echo '</pre>';
} else {
    echo '<p>Session cart does not exist</p>';
    echo '<p>Initializing session cart...</p>';
    $_SESSION['cart'] = [];
    echo '<p>Session cart initialized</p>';
}

// Check if user is logged in before attempting to access database cart
if (is_logged_in()) {
    $user_id = $_SESSION['user_id'];
    echo '<h2>Database Cart for User ID: ' . $user_id . '</h2>';
    
    // Get database cart items
    $db = db_connect();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM cart_items WHERE user_id = :user_id");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $db_cart_count = $row['count'];
    $result->finalize();
    
    echo '<p>Database cart has ' . $db_cart_count . ' items</p>';
    
    // Show database cart items
    $stmt = $db->prepare("
        SELECT c.id as cart_id, c.image_id, i.filename 
        FROM cart_items c
        LEFT JOIN images i ON c.image_id = i.id
        WHERE c.user_id = :user_id
    ");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    echo '<h3>Database Cart Items:</h3>';
    echo '<ul>';
    $has_items = false;
    while ($item = $result->fetchArray(SQLITE3_ASSOC)) {
        echo '<li>Cart ID: ' . $item['cart_id'] . ', Image ID: ' . $item['image_id'] . ', Filename: ' . ($item['filename'] ?? 'Unknown') . '</li>';
        $has_items = true;
    }
    if (!$has_items) {
        echo '<li>No items in database cart</li>';
    }
    echo '</ul>';
    $result->finalize();
    
    // Check for session cart items that need to be migrated to database
    echo '<h2>Cart Synchronization</h2>';
    if (!empty($_SESSION['cart'])) {
        echo '<p>Syncing session cart items to database...</p>';
        $items_added = 0;
        
        foreach ($_SESSION['cart'] as $index => $item) {
            if (isset($item['id']) && is_numeric($item['id'])) {
                $image_id = (int)$item['id'];
                
                // Check if this item is already in the database cart
                $check_stmt = $db->prepare("SELECT id FROM cart_items WHERE user_id = :user_id AND image_id = :image_id");
                $check_stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                $check_stmt->bindValue(':image_id', $image_id, SQLITE3_INTEGER);
                $check_result = $check_stmt->execute();
                
                if (!$check_result->fetchArray(SQLITE3_ASSOC)) {
                    // Add to database cart
                    $insert_stmt = $db->prepare("INSERT INTO cart_items (user_id, image_id) VALUES (:user_id, :image_id)");
                    $insert_stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                    $insert_stmt->bindValue(':image_id', $image_id, SQLITE3_INTEGER);
                    $insert_stmt->execute();
                    $items_added++;
                    echo '<p>Added image ID ' . $image_id . ' to database cart</p>';
                } else {
                    echo '<p>Image ID ' . $image_id . ' already in database cart</p>';
                }
                
                $check_result->finalize();
            }
        }
        
        echo '<p>Migrated ' . $items_added . ' items from session to database</p>';
        
        // Clear session cart after migration
        $_SESSION['cart'] = [];
        echo '<p>Session cart cleared after migration</p>';
    } else {
        echo '<p>No session cart items to migrate</p>';
    }
    
    $db->close();
} else {
    echo '<h2>Database Cart</h2>';
    echo '<p>Not logged in, so no database cart is available</p>';
}

// Provide a link to the cart
echo '<h2>Next Steps</h2>';
echo '<p>Your cart should be fixed now. <a href="cart.php">Click here to view your cart</a></p>';
echo '<p>If you still have issues, <a href="session_test.php">check your session status</a></p>';
?>
