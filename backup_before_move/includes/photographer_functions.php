<?php
require_once 'config.php';
require_once 'functions.php';

// Photographer functions

/**
 * Check if user is a photographer
 */
function is_photographer() {
    if (!is_logged_in()) {
        return false;
    }
    
    $db = db_connect();
    $stmt = $db->prepare("SELECT is_photographer FROM users WHERE id = :user_id");
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    $result->finalize();
    $db->close();
    
    return isset($user['is_photographer']) && $user['is_photographer'] == 1;
}

/**
 * Get marathons assigned to a photographer
 */
function get_photographer_marathons($user_id) {
    $db = db_connect();
    
    $stmt = $db->prepare("
        SELECT m.* 
        FROM marathons m
        JOIN photographer_marathons pm ON m.id = pm.marathon_id
        WHERE pm.user_id = :user_id
        ORDER BY m.event_date DESC
    ");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $marathons = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $marathons[] = $row;
    }
    
    $result->finalize();
    $db->close();
    
    return $marathons;
}

/**
 * Assign a marathon to a photographer
 */
function assign_marathon_to_photographer($marathon_id, $user_id) {
    $db = db_connect();
    
    try {
        $stmt = $db->prepare("
            INSERT INTO photographer_marathons (marathon_id, user_id)
            VALUES (:marathon_id, :user_id)
        ");
        $stmt->bindValue(':marathon_id', $marathon_id, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        $db->close();
        return ['success' => true];
    } catch (Exception $e) {
        $db->close();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Remove a marathon assignment from a photographer
 */
function remove_marathon_from_photographer($marathon_id, $user_id) {
    $db = db_connect();
    
    try {
        $stmt = $db->prepare("
            DELETE FROM photographer_marathons 
            WHERE marathon_id = :marathon_id AND user_id = :user_id
        ");
        $stmt->bindValue(':marathon_id', $marathon_id, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        $db->close();
        return ['success' => true];
    } catch (Exception $e) {
        $db->close();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Upload image with watermark
 */
function upload_image_with_watermark($file, $marathon_id, $user_id) {
    // First upload the original image
    $upload_result = upload_image($file, $marathon_id, $user_id);
    
    if (!$upload_result['success']) {
        return $upload_result;
    }
    
    $image_id = $upload_result['image_id'];
    $original_path = $upload_result['original_path'];
    
    // Create watermarked version
    $watermark_dir = dirname($original_path) . '/watermarked/';
    if (!file_exists($watermark_dir)) {
        mkdir($watermark_dir, 0755, true);
    }
    
    $watermark_path = $watermark_dir . basename($original_path);
    
    // Apply watermark
    $result = apply_watermark($original_path, $watermark_path);
    
    if ($result['success']) {
        // Update image record with watermark path
        $db = db_connect();
        $stmt = $db->prepare("
            UPDATE images 
            SET watermark_path = :watermark_path 
            WHERE id = :image_id
        ");
        $stmt->bindValue(':watermark_path', $watermark_path, SQLITE3_TEXT);
        $stmt->bindValue(':image_id', $image_id, SQLITE3_INTEGER);
        $stmt->execute();
        $db->close();
        
        return [
            'success' => true, 
            'image_id' => $image_id,
            'original_path' => $original_path,
            'watermark_path' => $watermark_path
        ];
    }
    
    return $result;
}

/**
 * Apply watermark to an image
 */
function apply_watermark($source_path, $destination_path) {
    // Check if GD extension is available
    if (!extension_loaded('gd')) {
        return ['success' => false, 'message' => 'GD extension is not available'];
    }
    
    // Get image information
    $image_info = getimagesize($source_path);
    if (!$image_info) {
        return ['success' => false, 'message' => 'Could not get image information'];
    }
    
    // Create image resource based on file type
    switch ($image_info[2]) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($source_path);
            break;
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($source_path);
            break;
        default:
            return ['success' => false, 'message' => 'Unsupported image type'];
    }
    
    // Create watermark text
    $font_size = 16;
    $text = "FoTeam";
    
    // Calculate text position (bottom right corner with padding)
    $text_color = imagecolorallocate($image, 255, 255, 255); // White text
    $text_shadow = imagecolorallocate($image, 0, 0, 0); // Black shadow
    
    // Get image dimensions
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Calculate text position
    $padding = 10;
    $text_x = $width - $padding - (strlen($text) * $font_size / 2);
    $text_y = $height - $padding;
    
    // Add text shadow
    imagestring($image, $font_size, $text_x + 1, $text_y + 1, $text, $text_shadow);
    
    // Add text
    imagestring($image, $font_size, $text_x, $text_y, $text, $text_color);
    
    // Save the watermarked image
    switch ($image_info[2]) {
        case IMAGETYPE_JPEG:
            imagejpeg($image, $destination_path, 90); // 90% quality
            break;
        case IMAGETYPE_PNG:
            imagepng($image, $destination_path, 9); // Maximum compression
            break;
        case IMAGETYPE_GIF:
            imagegif($image, $destination_path);
            break;
    }
    
    // Free memory
    imagedestroy($image);
    
    return ['success' => true];
}

/**
 * Count images uploaded by a user
 */
function count_user_images($user_id) {
    $db = db_connect();
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM images WHERE user_id = :user_id");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    $result->finalize();
    $db->close();
    
    return $count;
}

/**
 * Count images sold by a user
 */
function count_user_sold_images($user_id) {
    $db = db_connect();
    
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT oi.image_id) as count 
        FROM order_items oi
        JOIN images i ON oi.image_id = i.id
        JOIN orders o ON oi.order_id = o.id
        WHERE i.user_id = :user_id AND o.status = 'completed'
    ");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    $result->finalize();
    $db->close();
    
    return $count;
}

/**
 * Count marathons assigned to a user
 */
function count_user_marathons($user_id) {
    $db = db_connect();
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM photographer_marathons 
        WHERE user_id = :user_id
    ");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    $result->finalize();
    $db->close();
    
    return $count;
}
?>
