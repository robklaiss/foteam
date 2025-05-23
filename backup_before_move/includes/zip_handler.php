<?php
/**
 * ZIP File Handler
 * 
 * Handles extraction and processing of ZIP files containing images
 */

/**
 * Extract images from a ZIP file and process them
 * 
 * @param array $zip_file The uploaded ZIP file array
 * @param int $marathon_id The marathon ID to associate images with
 * @param int $user_id The user ID of the uploader
 * @return array Result with success status, counts, and any error messages
 */
function process_zip_file($zip_file, $marathon_id, $user_id) {
    // Check if ZIP extension is loaded
    if (!extension_loaded('zip')) {
        return [
            'success' => false,
            'message' => 'La extensión ZIP no está disponible en el servidor.'
        ];
    }
    
    // Create a temporary directory to extract files
    $temp_dir = sys_get_temp_dir() . '/foteam_' . uniqid();
    if (!mkdir($temp_dir, 0755, true)) {
        return [
            'success' => false,
            'message' => 'No se pudo crear un directorio temporal para extraer el archivo ZIP.'
        ];
    }
    
    // Open the ZIP file
    $zip = new ZipArchive();
    $res = $zip->open($zip_file['tmp_name']);
    
    if ($res !== true) {
        rmdir($temp_dir);
        return [
            'success' => false,
            'message' => 'No se pudo abrir el archivo ZIP. Código de error: ' . $res
        ];
    }
    
    // Extract the ZIP file to the temporary directory
    $zip->extractTo($temp_dir);
    $zip->close();
    
    // Process each extracted image
    $success_count = 0;
    $error_count = 0;
    $error_messages = [];
    
    // Get all image files recursively
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    $image_files = [];
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($temp_dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $extension = strtolower(pathinfo($file->getPathname(), PATHINFO_EXTENSION));
            if (in_array($extension, $allowed_extensions)) {
                $image_files[] = $file->getPathname();
            }
        }
    }
    
    // Process each image file
    foreach ($image_files as $image_path) {
        $file_info = [
            'name' => basename($image_path),
            'type' => mime_content_type($image_path),
            'tmp_name' => $image_path,
            'error' => 0,
            'size' => filesize($image_path)
        ];
        
        $result = upload_image($file_info, $marathon_id, $user_id);
        
        if ($result['success']) {
            $success_count++;
        } else {
            $error_count++;
            $error_messages[] = basename($image_path) . ': ' . $result['message'];
        }
    }
    
    // Clean up the temporary directory
    array_map('unlink', glob("$temp_dir/*.*"));
    rmdir($temp_dir);
    
    return [
        'success' => $success_count > 0,
        'success_count' => $success_count,
        'error_count' => $error_count,
        'error_messages' => $error_messages,
        'message' => "Se procesaron $success_count imágenes correctamente" . 
                    ($error_count > 0 ? " y $error_count con errores" : "")
    ];
}
?>
