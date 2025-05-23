<?php
require_once 'config.php';
require_once 'functions.php';

// Admin functions

/**
 * Get all users
 */
function get_all_users() {
    $db = db_connect();
    
    $stmt = $db->prepare("
        SELECT * FROM users
        ORDER BY username ASC
    ");
    $result = $stmt->execute();
    
    $users = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $users[] = $row;
    }
    
    $result->finalize();
    $db->close();
    
    return $users;
}

/**
 * Get all photographers
 * 
 * Since we don't have a dedicated is_photographer column,
 * we'll identify photographers based on a role field we'll add
 */
function get_all_photographers() {
    $db = db_connect();
    
    // First, let's add a role column if it doesn't exist
    $db->exec("PRAGMA table_info(users)");
    $result = $db->query("PRAGMA table_info(users)");
    $has_role_column = false;
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if ($row['name'] === 'role') {
            $has_role_column = true;
            break;
        }
    }
    
    if (!$has_role_column) {
        $db->exec("ALTER TABLE users ADD COLUMN role TEXT DEFAULT 'customer'");
    }
    
    // Now get all users with photographer role
    $stmt = $db->prepare("
        SELECT * FROM users
        WHERE role = 'photographer' OR username LIKE '%photographer%' OR username LIKE '%fotografo%'
        ORDER BY username ASC
    ");
    $result = $stmt->execute();
    
    $photographers = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $photographers[] = $row;
    }
    
    $result->finalize();
    $db->close();
    
    return $photographers;
}

/**
 * Create a photographer user
 */
function create_photographer($username, $email, $password) {
    $db = db_connect();
    
    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    if ($result->fetchArray()) {
        set_flash_message('El nombre de usuario o email ya está en uso', 'danger');
        return false;
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new photographer with role
    $stmt = $db->prepare("INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, 'photographer')");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->bindValue(':password', $hashed_password, SQLITE3_TEXT);
    
    $result = $stmt->execute();
    
    if ($result) {
        set_flash_message('Fotógrafo creado exitosamente', 'success');
        return $db->lastInsertRowID();
    } else {
        set_flash_message('Error al crear el fotógrafo', 'danger');
        return false;
    }
    
    $db->close();
}

/**
 * Update user role
 */
function update_user_role($user_id, $is_admin = null, $role = null) {
    $db = db_connect();
    
    try {
        $updates = [];
        $params = [];
        
        if ($is_admin !== null) {
            $updates[] = "is_admin = :is_admin";
            $params[':is_admin'] = $is_admin ? 1 : 0;
        }
        
        if ($role !== null) {
            $updates[] = "role = :role";
            $params[':role'] = $role;
        }
        
        if (empty($updates)) {
            $db->close();
            set_flash_message('No se especificaron actualizaciones', 'warning');
            return false;
        }
        
        $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = :user_id";
        $stmt = $db->prepare($sql);
        
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
        }
        
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $db->close();
        
        if ($result) {
            set_flash_message('Rol de usuario actualizado exitosamente', 'success');
            return true;
        } else {
            set_flash_message('Error al actualizar el rol de usuario', 'danger');
            return false;
        }
    } catch (Exception $e) {
        $db->close();
        set_flash_message('Error: ' . $e->getMessage(), 'danger');
        return false;
    }
}

/**
 * Get all orders
 */
function get_all_orders($limit = 50) {
    $db = db_connect();
    
    $stmt = $db->prepare("
        SELECT o.*, COUNT(oi.id) as item_count, u.username
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN users u ON o.user_id = u.id
        GROUP BY o.id
        ORDER BY o.order_date DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $orders = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $orders[] = $row;
    }
    
    $result->finalize();
    $db->close();
    
    return $orders;
}

/**
 * Get order details (admin version)
 */
function get_admin_order_details($order_id) {
    $db = db_connect();
    
    // Get order info
    $order_stmt = $db->prepare("
        SELECT o.*, u.username
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = :order_id
    ");
    $order_stmt->bindValue(':order_id', $order_id, SQLITE3_INTEGER);
    $order_result = $order_stmt->execute();
    
    $order = $order_result->fetchArray(SQLITE3_ASSOC);
    $order_result->finalize();
    
    if (!$order) {
        $db->close();
        return null;
    }
    
    // Get order items
    $items_stmt = $db->prepare("
        SELECT oi.*, i.filename, i.original_path, i.thumbnail_path, i.detected_numbers,
               u.username as photographer_name
        FROM order_items oi
        JOIN images i ON oi.image_id = i.id
        JOIN users u ON i.user_id = u.id
        WHERE oi.order_id = :order_id
    ");
    $items_stmt->bindValue(':order_id', $order_id, SQLITE3_INTEGER);
    $items_result = $items_stmt->execute();
    
    $items = [];
    while ($row = $items_result->fetchArray(SQLITE3_ASSOC)) {
        // Convert paths to URLs
        $row['url'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $row['original_path']);
        $row['thumbnail_url'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $row['thumbnail_path']);
        $items[] = $row;
    }
    
    $items_result->finalize();
    $db->close();
    
    $order['items'] = $items;
    return $order;
}

/**
 * Update order status
 */
function update_order_status($order_id, $status) {
    $db = db_connect();
    
    try {
        $stmt = $db->prepare("
            UPDATE orders 
            SET status = :status 
            WHERE id = :order_id
        ");
        $stmt->bindValue(':status', $status, SQLITE3_TEXT);
        $stmt->bindValue(':order_id', $order_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        $db->close();
        return ['success' => true];
    } catch (Exception $e) {
        $db->close();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get site statistics
 */
function get_site_statistics() {
    $db = db_connect();
    
    // Total users
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
    $result = $stmt->execute();
    $stats['total_users'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    $result->finalize();
    
    // Total photographers
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE is_photographer = 1");
    $result = $stmt->execute();
    $stats['total_photographers'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    $result->finalize();
    
    // Total marathons
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM marathons");
    $result = $stmt->execute();
    $stats['total_marathons'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    $result->finalize();
    
    // Total images
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM images");
    $result = $stmt->execute();
    $stats['total_images'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    $result->finalize();
    
    // Total orders
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM orders");
    $result = $stmt->execute();
    $stats['total_orders'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    $result->finalize();
    
    // Total revenue
    $stmt = $db->prepare("SELECT SUM(total_amount) as total FROM orders WHERE status = 'completed'");
    $result = $stmt->execute();
    $stats['total_revenue'] = $result->fetchArray(SQLITE3_ASSOC)['total'] ?: 0;
    $result->finalize();
    
    $db->close();
    
    return $stats;
}

/**
 * Log activity
 */
function log_activity($user_id, $action, $details = '', $ip_address = '') {
    $db = db_connect();
    
    if (empty($ip_address)) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO activity_log (user_id, action, details, ip_address)
            VALUES (:user_id, :action, :details, :ip_address)
        ");
        $stmt->bindValue(':user_id', $user_id, $user_id ? SQLITE3_INTEGER : SQLITE3_NULL);
        $stmt->bindValue(':action', $action, SQLITE3_TEXT);
        $stmt->bindValue(':details', $details, SQLITE3_TEXT);
        $stmt->bindValue(':ip_address', $ip_address, SQLITE3_TEXT);
        $stmt->execute();
        
        $db->close();
        return true;
    } catch (Exception $e) {
        $db->close();
        return false;
    }
}

/**
 * Get recent activity
 */
function get_recent_activity($limit = 20) {
    $db = db_connect();
    
    $stmt = $db->prepare("
        SELECT a.*, u.username
        FROM activity_log a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $activities = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $activities[] = $row;
    }
    
    $result->finalize();
    $db->close();
    
    return $activities;
}
?>
