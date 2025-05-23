<?php
require_once 'config.php';

// User authentication functions
function register_user($username, $email, $password) {
    $db = db_connect();
    
    // Check if username or email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    if ($result->fetchArray(SQLITE3_ASSOC)) {
        $result->finalize();
        $db->close();
        return ['success' => false, 'message' => 'Username or email already exists'];
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $stmt = $db->prepare("INSERT INTO users (username, email, password) VALUES (:username, :email, :password)");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->bindValue(':password', $hashed_password, SQLITE3_TEXT);
    
    try {
        $stmt->execute();
        $user_id = $db->lastInsertRowID();
        $db->close();
        return ['success' => true, 'user_id' => $user_id];
    } catch (Exception $e) {
        $db->close();
        return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
    }
}

function login_user($email, $password) {
    $db = db_connect();
    
    $stmt = $db->prepare("SELECT id, username, password, is_admin FROM users WHERE email = :email");
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    $user = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = $user['is_admin'];
        
        $result->finalize();
        $db->close();
        return ['success' => true];
    }
    
    $result->finalize();
    $db->close();
    return ['success' => false, 'message' => 'Invalid email or password'];
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function logout_user() {
    // Unset all session variables
    $_SESSION = [];
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
}

// Marathon functions
function create_marathon($name, $event_date, $is_public, $user_id, $location = '') {
    $db = db_connect();
    
    $stmt = $db->prepare("INSERT INTO marathons (name, event_date, is_public, user_id, location) VALUES (:name, :event_date, :is_public, :user_id, :location)");
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':event_date', $event_date, SQLITE3_TEXT);
    $stmt->bindValue(':is_public', $is_public, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':location', $location, SQLITE3_TEXT);
    
    try {
        $stmt->execute();
        $marathon_id = $db->lastInsertRowID();
        $db->close();
        return ['success' => true, 'marathon_id' => $marathon_id];
    } catch (Exception $e) {
        $db->close();
        return ['success' => false, 'message' => 'Failed to create marathon: ' . $e->getMessage()];
    }
}

function get_user_marathons($user_id) {
    $db = db_connect();
    
    $stmt = $db->prepare("SELECT * FROM marathons WHERE user_id = :user_id ORDER BY event_date DESC");
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

function get_public_marathons() {
    $db = db_connect();
    
    $stmt = $db->prepare("SELECT * FROM marathons WHERE is_public = 1 ORDER BY event_date DESC");
    $result = $stmt->execute();
    
    $marathons = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $marathons[] = $row;
    }
    
    $result->finalize();
    $db->close();
    return $marathons;
}

function get_marathon($marathon_id) {
    $db = db_connect();
    
    $stmt = $db->prepare("SELECT * FROM marathons WHERE id = :id");
    $stmt->bindValue(':id', $marathon_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $marathon = $result->fetchArray(SQLITE3_ASSOC);
    $result->finalize();
    $db->close();
    
    return $marathon ?: null;
}

function update_marathon($marathon_id, $name, $event_date, $is_public, $location = '') {
    $db = db_connect();
    
    $stmt = $db->prepare("UPDATE marathons SET name = :name, event_date = :event_date, is_public = :is_public, location = :location WHERE id = :id");
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':event_date', $event_date, SQLITE3_TEXT);
    $stmt->bindValue(':is_public', $is_public, SQLITE3_INTEGER);
    $stmt->bindValue(':location', $location, SQLITE3_TEXT);
    $stmt->bindValue(':id', $marathon_id, SQLITE3_INTEGER);
    
    try {
        $stmt->execute();
        $changes = $db->changes();
        $db->close();
        return $changes > 0;
    } catch (Exception $e) {
        $db->close();
        return false;
    }
}

// Image functions
function upload_image($file, $marathon_id, $user_id) {
    try {
        // Check if file was uploaded without errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            error_log("Upload error: " . $file['error']);
            return ['success' => false, 'message' => 'File upload error code: ' . $file['error']];
        }
        
        // Check file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed_types)) {
            error_log("Invalid file type: " . $file['type']);
            return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.'];
        }
        
        // Generate unique filename
        $filename = uniqid() . '_' . basename($file['name']);
        $original_path = UPLOAD_DIR . 'originals/' . $filename;
        $watermarked_path = UPLOAD_DIR . 'watermarked/' . $filename;
        $thumbnail_path = UPLOAD_DIR . 'thumbnails/' . $filename;
        
        // Create directories if they don't exist
        if (!file_exists(UPLOAD_DIR . 'originals/')) {
            if (!mkdir(UPLOAD_DIR . 'originals/', 0755, true)) {
                error_log("Failed to create originals directory");
                return ['success' => false, 'message' => 'Failed to create originals directory'];
            }
        }
        if (!file_exists(UPLOAD_DIR . 'watermarked/')) {
            if (!mkdir(UPLOAD_DIR . 'watermarked/', 0755, true)) {
                error_log("Failed to create watermarked directory");
                return ['success' => false, 'message' => 'Failed to create watermarked directory'];
            }
        }
        if (!file_exists(UPLOAD_DIR . 'thumbnails/')) {
            if (!mkdir(UPLOAD_DIR . 'thumbnails/', 0755, true)) {
                error_log("Failed to create thumbnails directory");
                return ['success' => false, 'message' => 'Failed to create thumbnails directory'];
            }
        }
        
        // Move uploaded file to originals directory
        if (!move_uploaded_file($file['tmp_name'], $original_path)) {
            error_log("Failed to move uploaded file to: " . $original_path);
            return ['success' => false, 'message' => 'Failed to move uploaded file'];
        }
        
        // Add logo watermark to original image and save as watermarked version
        $watermark_result = add_logo_watermark($original_path, $watermarked_path, 'bottom-right', 0.8);
        if (!$watermark_result['success']) {
            error_log("Watermark error: " . $watermark_result['message']);
            return $watermark_result;
        }
        
        // Create thumbnail with watermark
        $thumbnail_result = create_thumbnail($original_path, $thumbnail_path, 300, true);
        if (!$thumbnail_result['success']) {
            error_log("Thumbnail error: " . $thumbnail_result['message']);
            return $thumbnail_result;
        }
        
        // Detect numbers using Google Cloud Vision API
        $detected_numbers = detect_runner_numbers($original_path);
        // Save to database
        $db = db_connect();
        
        $stmt = $db->prepare("INSERT INTO images (filename, original_path, thumbnail_path, marathon_id, user_id, detected_numbers, watermarked_path) 
                               VALUES (:filename, :original_path, :thumbnail_path, :marathon_id, :user_id, :detected_numbers, :watermarked_path)");
        $stmt->bindValue(':filename', $filename, SQLITE3_TEXT);
        $stmt->bindValue(':original_path', $original_path, SQLITE3_TEXT);
        $stmt->bindValue(':thumbnail_path', $thumbnail_path, SQLITE3_TEXT);
        $stmt->bindValue(':marathon_id', $marathon_id, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(':detected_numbers', $detected_numbers, SQLITE3_TEXT);
        $stmt->bindValue(':watermarked_path', $watermarked_path, SQLITE3_TEXT);
        
        $stmt->execute();
        $image_id = $db->lastInsertRowID();
        $db->close();
        return ['success' => true, 'image_id' => $image_id];
    } catch (Exception $e) {
        error_log("Upload error: " . $e->getMessage());
        
        // Delete uploaded files if they exist
        if (isset($original_path) && file_exists($original_path)) {
            unlink($original_path);
        }
        if (isset($watermarked_path) && file_exists($watermarked_path)) {
            unlink($watermarked_path);
        }
        if (isset($thumbnail_path) && file_exists($thumbnail_path)) {
            unlink($thumbnail_path);
        }
        
        // Close database connection if it exists
        if (isset($db)) {
            $db->close();
        }
        
        return ['success' => false, 'message' => 'Upload error: ' . $e->getMessage()];
    }
}

function create_thumbnail($source_path, $thumbnail_path, $width = 300, $add_watermark = false) {
    try {
        if (!file_exists($source_path)) {
            error_log("Thumbnail source file not found: " . $source_path);
            return ['success' => false, 'message' => 'Source image not found'];
        }
        
        $image_info = @getimagesize($source_path);
        if ($image_info === false) {
            error_log("Failed to get image size: " . $source_path);
            return ['success' => false, 'message' => 'Failed to get image size'];
        }
        
        list($src_width, $src_height, $src_type) = $image_info;
        
        // Calculate new height while maintaining aspect ratio
        $height = intval($width * $src_height / $src_width);
        
        // Create new image
        $thumbnail = imagecreatetruecolor($width, $height);
        if (!$thumbnail) {
            error_log("Failed to create true color image");
            return ['success' => false, 'message' => 'Failed to create image canvas'];
        }
        
        // Load source image based on type
        $source = null;
        switch ($src_type) {
            case IMAGETYPE_JPEG:
                $source = @imagecreatefromjpeg($source_path);
                break;
            case IMAGETYPE_PNG:
                $source = @imagecreatefrompng($source_path);
                // Preserve transparency
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
                break;
            case IMAGETYPE_GIF:
                $source = @imagecreatefromgif($source_path);
                break;
            default:
                error_log("Unsupported image type: " . $src_type);
                return ['success' => false, 'message' => 'Unsupported image type'];
        }
        
        if (!$source) {
            error_log("Failed to create image from file: " . $source_path);
            return ['success' => false, 'message' => 'Failed to load source image'];
        }
        
        // Resize
        $resize_result = imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $width, $height, $src_width, $src_height);
        if (!$resize_result) {
            error_log("Failed to resize image");
            return ['success' => false, 'message' => 'Failed to resize image'];
        }
        
        // Add watermark if requested
        if ($add_watermark) {
            // Add text watermark to thumbnail
            $watermark_text = 'FoTeam';
            $font_size = 4;
            $text_color = imagecolorallocatealpha($thumbnail, 255, 255, 255, 70); // Semi-transparent white
            $text_x = 10;
            $text_y = $height - 10;
            
            // Add text watermark
            imagestring($thumbnail, $font_size, $text_x, $text_y, $watermark_text, $text_color);
        }
        
        // Create directory if it doesn't exist
        $thumbnail_dir = dirname($thumbnail_path);
        if (!file_exists($thumbnail_dir)) {
            if (!mkdir($thumbnail_dir, 0755, true)) {
                error_log("Failed to create thumbnail directory: " . $thumbnail_dir);
                return ['success' => false, 'message' => 'Failed to create thumbnail directory'];
            }
        }
        
        // Save thumbnail
        $save_result = false;
        switch ($src_type) {
            case IMAGETYPE_JPEG:
                $save_result = imagejpeg($thumbnail, $thumbnail_path, 85);
                break;
            case IMAGETYPE_PNG:
                $save_result = imagepng($thumbnail, $thumbnail_path, 8);
                break;
            case IMAGETYPE_GIF:
                $save_result = imagegif($thumbnail, $thumbnail_path);
                break;
        }
        
        if (!$save_result) {
            error_log("Failed to save thumbnail: " . $thumbnail_path);
            return ['success' => false, 'message' => 'Failed to save thumbnail'];
        }
        
        // Free memory
        imagedestroy($source);
        imagedestroy($thumbnail);
        
        return ['success' => true];
    } catch (Exception $e) {
        error_log("Thumbnail error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Thumbnail error: ' . $e->getMessage()];
    }
}

/**
 * Add logo watermark to an image
 * 
 * @param string $source_path Path to the source image
 * @param string $dest_path Path where to save the watermarked image
 * @param string $position Position of the watermark (top-left, top-right, bottom-left, bottom-right, center)
 * @param float $opacity Opacity of the watermark (0.0 - 1.0)
 * @return array Associative array with success status and message
 */
function add_logo_watermark($source_path, $dest_path, $position = 'bottom-right', $opacity = 0.5) {
    try {
        // Check if source file exists
        if (!file_exists($source_path)) {
            error_log("Watermark source file not found: " . $source_path);
            return ['success' => false, 'message' => 'Source image not found'];
        }
        
        // Check if logo exists
        $logo_path = __DIR__ . '/../assets/img/logo.png';
        if (!file_exists($logo_path)) {
            error_log("Logo file not found: " . $logo_path);
            // If logo doesn't exist, just copy the original file
            if (copy($source_path, $dest_path)) {
                return ['success' => true];
            } else {
                return ['success' => false, 'message' => 'Failed to copy original image'];
            }
        }
        
        // Create directory if it doesn't exist
        $dest_dir = dirname($dest_path);
        if (!file_exists($dest_dir)) {
            if (!mkdir($dest_dir, 0755, true)) {
                error_log("Failed to create destination directory: " . $dest_dir);
                return ['success' => false, 'message' => 'Failed to create destination directory'];
            }
        }
        
        // Get image info
        $image_info = @getimagesize($source_path);
        if ($image_info === false) {
            error_log("Failed to get image size: " . $source_path);
            return ['success' => false, 'message' => 'Failed to get image size'];
        }
        
        list($src_width, $src_height, $src_type) = $image_info;
        
        // Load source image
        $source = null;
        switch ($src_type) {
            case IMAGETYPE_JPEG:
                $source = @imagecreatefromjpeg($source_path);
                break;
            case IMAGETYPE_PNG:
                $source = @imagecreatefrompng($source_path);
                imagealphablending($source, true);
                imagesavealpha($source, true);
                break;
            case IMAGETYPE_GIF:
                $source = @imagecreatefromgif($source_path);
                break;
            default:
                error_log("Unsupported image type: " . $src_type);
                return ['success' => false, 'message' => 'Unsupported image type'];
        }
        
        if (!$source) {
            error_log("Failed to create image from file: " . $source_path);
            return ['success' => false, 'message' => 'Failed to load source image'];
        }
        
        // Load watermark
        $watermark = @imagecreatefrompng($logo_path);
        if (!$watermark) {
            error_log("Failed to load logo image: " . $logo_path);
            imagedestroy($source);
            return ['success' => false, 'message' => 'Failed to load logo image'];
        }
        
        // Get watermark dimensions
        $wm_width = imagesx($watermark);
        $wm_height = imagesy($watermark);
        
        // Calculate watermark size - make it proportional to image size (max 20% of image width)
        $new_wm_width = min($src_width * 0.2, $wm_width);
        $new_wm_height = $wm_height * ($new_wm_width / $wm_width);
        
        // Calculate position
        switch ($position) {
            case 'top-left':
                $x = 10;
                $y = 10;
                break;
            case 'top-right':
                $x = $src_width - $new_wm_width - 10;
                $y = 10;
                break;
            case 'bottom-left':
                $x = 10;
                $y = $src_height - $new_wm_height - 10;
                break;
            case 'center':
                $x = ($src_width - $new_wm_width) / 2;
                $y = ($src_height - $new_wm_height) / 2;
                break;
            case 'bottom-right':
            default:
                $x = $src_width - $new_wm_width - 10;
                $y = $src_height - $new_wm_height - 10;
                break;
        }
        
        // Create a new image for the watermark with transparency
        $temp = imagecreatetruecolor($new_wm_width, $new_wm_height);
        if (!$temp) {
            error_log("Failed to create temporary image");
            imagedestroy($source);
            imagedestroy($watermark);
            return ['success' => false, 'message' => 'Failed to create temporary image'];
        }
        
        imagealphablending($temp, false);
        imagesavealpha($temp, true);
        
        // Resize watermark to desired size
        $resize_result = imagecopyresampled($temp, $watermark, 0, 0, 0, 0, $new_wm_width, $new_wm_height, $wm_width, $wm_height);
        if (!$resize_result) {
            error_log("Failed to resize watermark");
            imagedestroy($source);
            imagedestroy($watermark);
            imagedestroy($temp);
            return ['success' => false, 'message' => 'Failed to resize watermark'];
        }
        
        // Apply watermark with transparency
        $merge_result = imagecopymerge($source, $temp, $x, $y, 0, 0, $new_wm_width, $new_wm_height, $opacity * 100);
        if (!$merge_result) {
            error_log("Failed to apply watermark");
            imagedestroy($source);
            imagedestroy($watermark);
            imagedestroy($temp);
            return ['success' => false, 'message' => 'Failed to apply watermark'];
        }
        
        // Save the watermarked image
        $save_result = false;
        switch ($src_type) {
            case IMAGETYPE_JPEG:
                $save_result = imagejpeg($source, $dest_path, 90);
                break;
            case IMAGETYPE_PNG:
                $save_result = imagepng($source, $dest_path, 8);
                break;
            case IMAGETYPE_GIF:
                $save_result = imagegif($source, $dest_path);
                break;
        }
        
        if (!$save_result) {
            error_log("Failed to save watermarked image: " . $dest_path);
            imagedestroy($source);
            imagedestroy($watermark);
            imagedestroy($temp);
            return ['success' => false, 'message' => 'Failed to save watermarked image'];
        }
        
        // Free memory
        imagedestroy($source);
        imagedestroy($watermark);
        imagedestroy($temp);
        
        return ['success' => true];
    } catch (Exception $e) {
        error_log("Watermark error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Watermark error: ' . $e->getMessage()];
    }
}

/**
 * Detect runner numbers in an image using Google Cloud Vision API
 * 
 * @param string $image_path Path to the image file
 * @return string Detected numbers as a comma-separated string
 */
function detect_runner_numbers($image_path) {
    try {
        // Check if the Google Vision config file exists
        if (!file_exists(__DIR__ . '/google_vision_config.php')) {
            error_log("Google Vision config file not found");
            return '';
        }
        
        // Include Google Vision API configuration
        require_once __DIR__ . '/google_vision_config.php';
        
        // Check if Google Vision integration is enabled
        if (!defined('GOOGLE_VISION_ENABLED') || !GOOGLE_VISION_ENABLED) {
            error_log("Google Vision integration is disabled");
            return '';
        }
        
        // Check if API key is set
        if (!defined('GOOGLE_CLOUD_API_KEY') || empty(GOOGLE_CLOUD_API_KEY)) {
            // Try to get API key directly from environment
            $direct_api_key = env('GOOGLE_CLOUD_API_KEY', '');
            
            if (empty($direct_api_key)) {
                // Last resort - hardcode the API key
                $direct_api_key = 'AIzaSyDJh2X5LiLBOQr9mN-w9NIpbQlxfhYT-8M';
                error_log("Using hardcoded API key as last resort");
            }
            
            // Define the constant
            if (!defined('GOOGLE_CLOUD_API_KEY')) {
                define('GOOGLE_CLOUD_API_KEY', $direct_api_key);
            }
        }
        
        // Debug info
        error_log("Using Google Cloud API Key: " . substr(GOOGLE_CLOUD_API_KEY, 0, 5) . '...' . substr(GOOGLE_CLOUD_API_KEY, -5));
        
        // Check if the image exists
        if (!file_exists($image_path)) {
            error_log("Image file not found for number detection: " . $image_path);
            return '';
        }
        
        // Read image content and encode as base64
        $image_content = file_get_contents($image_path);
        $encoded_image = base64_encode($image_content);
        
        // Prepare the request payload
        $request_data = [
            'requests' => [
                [
                    'image' => [
                        'content' => $encoded_image
                    ],
                    'features' => [
                        [
                            'type' => 'TEXT_DETECTION',
                            'maxResults' => GOOGLE_VISION_MAX_RESULTS
                        ]
                    ]
                ]
            ]
        ];
        
        // Convert the request data to JSON
        $json_request = json_encode($request_data);
        
        // Build the API URL with the API key
        $api_url = GOOGLE_VISION_API_ENDPOINT . '?key=' . GOOGLE_CLOUD_API_KEY;
        
        // Set up cURL to make the API request
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $api_url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json_request);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_request)
        ]);
        
        // Execute the request
        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($curl);
        curl_close($curl);
        
        // Check if cURL had an error
        if (!empty($curl_error)) {
            error_log("cURL error: " . $curl_error);
            return '';
        }
        
        // Check if the request was successful
        if ($status != 200) {
            error_log("Google Vision API request failed with status: " . $status);
            error_log("Response: " . $response);
            return '';
        }
        
        // Debug the response
        error_log("Google Vision API response received. Length: " . strlen($response));
        
        // Parse the response
        $result = json_decode($response, true);
        
        // Extract text annotations
        if (!isset($result['responses'][0]['textAnnotations'])) {
            error_log("No text detected in the image");
            return '';
        }
        
        $text_annotations = $result['responses'][0]['textAnnotations'];
        
        // Extract potential runner numbers (look for sequences of 3-6 digits)
        $numbers = [];
        $pattern = '/\b\d{3,6}\b/'; // Pattern to match 3-6 digit numbers
        
        foreach ($text_annotations as $annotation) {
            if (isset($annotation['description'])) {
                $text = $annotation['description'];
                if (preg_match_all($pattern, $text, $matches)) {
                    foreach ($matches[0] as $match) {
                        $numbers[] = $match;
                    }
                }
            }
        }
        
        // Remove duplicates and join as comma-separated string
        $unique_numbers = array_unique($numbers);
        $detected_numbers = implode(',', $unique_numbers);
        
        error_log("Detected runner numbers: " . $detected_numbers);
        return $detected_numbers;
    } catch (Exception $e) {
        error_log("Error detecting runner numbers: " . $e->getMessage());
        return '';
    }
}

function get_marathon_images($marathon_id, $page = 1, $per_page = 12) {
    $db = db_connect();
    
    // Calculate offset
    $offset = ($page - 1) * $per_page;
    
    // Get total count
    $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM images WHERE marathon_id = :marathon_id");
    $count_stmt->bindValue(':marathon_id', $marathon_id, SQLITE3_INTEGER);
    $count_result = $count_stmt->execute();
    $total_row = $count_result->fetchArray(SQLITE3_ASSOC);
    $total = $total_row['total'];
    $count_result->finalize();
    
    // Get images for current page
    $stmt = $db->prepare("SELECT * FROM images WHERE marathon_id = :marathon_id ORDER BY upload_date DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':marathon_id', $marathon_id, SQLITE3_INTEGER);
    $stmt->bindValue(':limit', $per_page, SQLITE3_INTEGER);
    $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $images = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Convert paths to URLs
        $row['url'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $row['original_path']);
        $row['thumbnail_url'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $row['thumbnail_path']);
        $images[] = $row;
    }
    
    $result->finalize();
    $db->close();
    
    return [
        'images' => $images,
        'total' => $total,
        'pages' => ceil($total / $per_page),
        'current_page' => $page
    ];
}

// Cart functions
function add_to_cart($user_id, $image_id) {
    $db = db_connect();
    
    // Check if already in cart
    $check_stmt = $db->prepare("SELECT id FROM cart_items WHERE user_id = :user_id AND image_id = :image_id");
    $check_stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $check_stmt->bindValue(':image_id', $image_id, SQLITE3_INTEGER);
    $check_result = $check_stmt->execute();
    
    if ($check_result->fetchArray(SQLITE3_ASSOC)) {
        $check_result->finalize();
        $db->close();
        return ['success' => false, 'message' => 'Image already in cart'];
    }
    
    $check_result->finalize();
    
    // Add to cart
    try {
        $stmt = $db->prepare("INSERT INTO cart_items (user_id, image_id) VALUES (:user_id, :image_id)");
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(':image_id', $image_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        $db->close();
        return ['success' => true, 'message' => 'Added to cart'];
    } catch (Exception $e) {
        $db->close();
        return ['success' => false, 'message' => 'Failed to add to cart: ' . $e->getMessage()];
    }
}

function remove_from_cart($user_id, $image_id) {
    $db = db_connect();
    
    try {
        $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = :user_id AND image_id = :image_id");
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(':image_id', $image_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        $changes = $db->changes();
        $db->close();
        return $changes > 0;
    } catch (Exception $e) {
        $db->close();
        return false;
    }
}

function get_cart_items($user_id) {
    $db = db_connect();
    
    $stmt = $db->prepare("
        SELECT i.*, c.id as cart_id 
        FROM cart_items c
        JOIN images i ON c.image_id = i.id
        WHERE c.user_id = :user_id
        ORDER BY c.added_at DESC
    ");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $cart_items = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Convert paths to URLs
        $row['url'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $row['original_path']);
        $row['thumbnail_url'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $row['thumbnail_path']);
        $cart_items[] = $row;
    }
    
    $result->finalize();
    $db->close();
    
    return $cart_items;
}

function get_cart_count($user_id) {
    $db = db_connect();
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM cart_items WHERE user_id = :user_id");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $count = $row['count'];
    
    $result->finalize();
    $db->close();
    
    return $count;
}

// Order functions
function create_order($user_id, $customer_name, $customer_email, $customer_phone) {
    $db = db_connect();
    
    // Start transaction
    $db->exec('BEGIN TRANSACTION');
    
    try {
        // Get cart items
        $cart_items = get_cart_items($user_id);
        
        if (empty($cart_items)) {
            throw new Exception("Cart is empty");
        }
        
        // Calculate total (in a real app, you'd have prices for each item)
        $price_per_item = 10.00; // Example price
        $total_amount = count($cart_items) * $price_per_item;
        
        // Create order
        $order_stmt = $db->prepare("
            INSERT INTO orders (user_id, total_amount, customer_name, customer_email, customer_phone)
            VALUES (:user_id, :total_amount, :customer_name, :customer_email, :customer_phone)
        ");
        $order_stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $order_stmt->bindValue(':total_amount', $total_amount, SQLITE3_FLOAT);
        $order_stmt->bindValue(':customer_name', $customer_name, SQLITE3_TEXT);
        $order_stmt->bindValue(':customer_email', $customer_email, SQLITE3_TEXT);
        $order_stmt->bindValue(':customer_phone', $customer_phone, SQLITE3_TEXT);
        $order_stmt->execute();
        $order_id = $db->lastInsertRowID();
        
        // Add order items
        $item_stmt = $db->prepare("INSERT INTO order_items (order_id, image_id, price) VALUES (:order_id, :image_id, :price)");
        
        foreach ($cart_items as $item) {
            $item_stmt->bindValue(':order_id', $order_id, SQLITE3_INTEGER);
            $item_stmt->bindValue(':image_id', $item['id'], SQLITE3_INTEGER);
            $item_stmt->bindValue(':price', $price_per_item, SQLITE3_FLOAT);
            $item_stmt->execute();
        }
        
        // Clear cart
        $clear_stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = :user_id");
        $clear_stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $clear_stmt->execute();
        
        // Commit transaction
        $db->exec('COMMIT');
        
        $db->close();
        return ['success' => true, 'order_id' => $order_id];
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->exec('ROLLBACK');
        $db->close();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function get_user_orders($user_id) {
    $db = db_connect();
    
    $stmt = $db->prepare("
        SELECT o.*, COUNT(oi.id) as item_count
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        WHERE o.user_id = :user_id
        GROUP BY o.id
        ORDER BY o.order_date DESC
    ");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $orders = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $orders[] = $row;
    }
    
    $result->finalize();
    $db->close();
    
    return $orders;
}

function get_order_details($order_id, $user_id) {
    $db = db_connect();
    
    // Get order info
    $order_stmt = $db->prepare("
        SELECT * FROM orders
        WHERE id = :order_id AND user_id = :user_id
    ");
    $order_stmt->bindValue(':order_id', $order_id, SQLITE3_INTEGER);
    $order_stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $order_result = $order_stmt->execute();
    
    $order = $order_result->fetchArray(SQLITE3_ASSOC);
    $order_result->finalize();
    
    if (!$order) {
        $db->close();
        return null;
    }
    
    // Get order items
    $items_stmt = $db->prepare("
        SELECT oi.*, i.filename, i.original_path, i.thumbnail_path, i.detected_numbers
        FROM order_items oi
        JOIN images i ON oi.image_id = i.id
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

// Helper functions
function redirect($url) {
    header("Location: $url");
    exit;
}

function set_flash_message($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        return ['message' => $message, 'type' => $type];
    }
    
    return null;
}

// Function to sanitize output
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>
