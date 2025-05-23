<?php
require_once 'config.php';

/**
 * Get order status text
 */
function get_order_status_text($status) {
    $statuses = [
        'pending' => 'Pendiente',
        'processing' => 'Procesando',
        'completed' => 'Completado',
        'cancelled' => 'Cancelado',
        'refunded' => 'Reembolsado'
    ];
    
    return $statuses[$status] ?? 'Desconocido';
}

/**
 * Get order status color
 */
function get_order_status_color($status) {
    $colors = [
        'pending' => 'warning',
        'processing' => 'info',
        'completed' => 'success',
        'cancelled' => 'danger',
        'refunded' => 'secondary'
    ];
    
    return $colors[$status] ?? 'secondary';
}

/**
 * Get payment method text
 */
function get_payment_method_text($method) {
    $methods = [
        'bancard' => 'Tarjeta de Crédito/Débito (Bancard)',
        'credit_card' => 'Tarjeta de Crédito',
        'paypal' => 'PayPal',
        'bank_transfer' => 'Transferencia Bancaria'
    ];
    
    return $methods[$method] ?? 'Desconocido';
}

// CSRF functions are already defined in config.php

/**
 * Check if user is a photographer
 * 
 * For simplicity, we'll consider users with specific usernames as photographers
 * In a real application, this would use a proper role system
 */
function is_photographer() {
    if (!is_logged_in()) {
        return false;
    }
    
    // Check if the username contains 'fotografo' or 'photographer'
    $db = db_connect();
    $stmt = $db->prepare("
        SELECT username 
        FROM users 
        WHERE id = :user_id
    ");
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $result->finalize();
    $db->close();
    
    if (!$row) {
        return false;
    }
    
    $username = strtolower($row['username']);
    return strpos($username, 'fotografo') !== false || 
           strpos($username, 'photographer') !== false;
}

// is_admin function is already defined in functions.php

/**
 * Upload image with watermark
 */
function upload_image_with_watermark($file, $marathon_id, $user_id) {
    // Create uploads directory if it doesn't exist
    $uploads_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads';
    $originals_dir = $uploads_dir . '/originals';
    $thumbnails_dir = $uploads_dir . '/thumbnails';
    $watermarked_dir = $uploads_dir . '/watermarked';
    
    if (!file_exists($uploads_dir)) {
        mkdir($uploads_dir, 0755, true);
    }
    
    if (!file_exists($originals_dir)) {
        mkdir($originals_dir, 0755, true);
    }
    
    if (!file_exists($thumbnails_dir)) {
        mkdir($thumbnails_dir, 0755, true);
    }
    
    if (!file_exists($watermarked_dir)) {
        mkdir($watermarked_dir, 0755, true);
    }
    
    // Check if file is an image
    $check = getimagesize($file['tmp_name']);
    if ($check === false) {
        return ['success' => false, 'message' => 'El archivo no es una imagen válida'];
    }
    
    // Generate unique filename
    $filename = basename($file['name']);
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $unique_filename = uniqid() . '_' . $filename;
    
    // Set paths
    $original_path = $originals_dir . '/' . $unique_filename;
    $thumbnail_path = $thumbnails_dir . '/' . $unique_filename;
    $watermarked_path = $watermarked_dir . '/' . $unique_filename;
    
    // Move uploaded file to originals directory
    if (!move_uploaded_file($file['tmp_name'], $original_path)) {
        return ['success' => false, 'message' => 'Error al mover el archivo subido'];
    }
    
    // Create thumbnail
    create_thumbnail($original_path, $thumbnail_path, 300);
    
    // Add watermark
    add_watermark($original_path, $watermarked_path);
    
    // Detect numbers in image (simulated for now)
    $detected_numbers = detect_numbers_in_image($original_path);
    
    // Save image info to database
    $db = db_connect();
    $stmt = $db->prepare("
        INSERT INTO images (
            filename, original_path, thumbnail_path, watermarked_path,
            marathon_id, user_id, detected_numbers
        ) VALUES (
            :filename, :original_path, :thumbnail_path, :watermarked_path,
            :marathon_id, :user_id, :detected_numbers
        )
    ");
    
    $stmt->bindValue(':filename', $filename, SQLITE3_TEXT);
    $stmt->bindValue(':original_path', $original_path, SQLITE3_TEXT);
    $stmt->bindValue(':thumbnail_path', $thumbnail_path, SQLITE3_TEXT);
    $stmt->bindValue(':watermarked_path', $watermarked_path, SQLITE3_TEXT);
    $stmt->bindValue(':marathon_id', $marathon_id, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':detected_numbers', $detected_numbers, SQLITE3_TEXT);
    
    try {
        $stmt->execute();
        $image_id = $db->lastInsertRowID();
        $db->close();
        
        return [
            'success' => true,
            'image_id' => $image_id,
            'original_path' => $original_path,
            'thumbnail_path' => $thumbnail_path,
            'watermarked_path' => $watermarked_path
        ];
    } catch (Exception $e) {
        $db->close();
        
        // Clean up files if database insert fails
        if (file_exists($original_path)) {
            unlink($original_path);
        }
        
        if (file_exists($thumbnail_path)) {
            unlink($thumbnail_path);
        }
        
        if (file_exists($watermarked_path)) {
            unlink($watermarked_path);
        }
        
        return ['success' => false, 'message' => 'Error al guardar la imagen: ' . $e->getMessage()];
    }
}

/**
 * Add watermark to image
 */
function add_watermark($source_path, $destination_path, $watermark_text = 'FoTeam') {
    // Get image info
    $info = getimagesize($source_path);
    $mime = $info['mime'];
    
    // Create image from source
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source_path);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source_path);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source_path);
            break;
        default:
            return false;
    }
    
    // Set watermark properties
    $font_size = 24;
    $font_path = __DIR__ . '/arial.ttf'; // Make sure this font exists
    
    // If font doesn't exist, use default
    if (!file_exists($font_path)) {
        $font_path = 5; // Use built-in font
    }
    
    // Get image dimensions
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Set watermark color (semi-transparent white)
    $color = imagecolorallocatealpha($image, 255, 255, 255, 70);
    
    // Calculate text size and position
    if (is_string($font_path)) {
        $text_box = imagettfbbox($font_size, 0, $font_path, $watermark_text);
        $text_width = $text_box[2] - $text_box[0];
        $text_height = $text_box[7] - $text_box[1];
        $x = ($width - $text_width) / 2;
        $y = ($height + $text_height) / 2;
        
        // Add watermark text
        imagettftext($image, $font_size, 0, $x, $y, $color, $font_path, $watermark_text);
    } else {
        // Use built-in font
        $text_width = strlen($watermark_text) * imagefontwidth($font_path);
        $text_height = imagefontheight($font_path);
        $x = ($width - $text_width) / 2;
        $y = ($height + $text_height) / 2;
        
        // Add watermark text
        imagestring($image, $font_path, $x, $y, $watermark_text, $color);
    }
    
    // Save watermarked image
    switch ($mime) {
        case 'image/jpeg':
            imagejpeg($image, $destination_path, 90);
            break;
        case 'image/png':
            imagepng($image, $destination_path, 9);
            break;
        case 'image/gif':
            imagegif($image, $destination_path);
            break;
    }
    
    // Free memory
    imagedestroy($image);
    
    return true;
}

/**
 * Detect numbers in image (simulated for now)
 * In a real application, this would use OCR or AI to detect runner numbers
 */
function detect_numbers_in_image($image_path) {
    // This is a placeholder function that would normally use OCR or AI
    // For demonstration purposes, we'll generate random numbers
    
    // Seed random number generator with image path to get consistent results for the same image
    srand(crc32($image_path));
    
    // 50% chance of detecting numbers
    if (rand(0, 1) == 0) {
        return '';
    }
    
    // Generate 1-3 random numbers
    $count = rand(1, 3);
    $numbers = [];
    
    for ($i = 0; $i < $count; $i++) {
        $numbers[] = rand(100, 999);
    }
    
    return implode(',', $numbers);
}

/**
 * Get photographer marathons
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
 * Format file size
 */
function format_file_size($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Get image dimensions
 */
function get_image_dimensions($path) {
    $info = getimagesize($path);
    
    if ($info) {
        return [
            'width' => $info[0],
            'height' => $info[1]
        ];
    }
    
    return [
        'width' => 0,
        'height' => 0
    ];
}

/**
 * Generate random password
 */
function generate_random_password($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    
    return $password;
}
?>
