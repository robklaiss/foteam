<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Ensure user is logged in
if (!is_logged_in()) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Directory to store temporary chunks
$chunks_dir = __DIR__ . '/uploads/chunks';
if (!file_exists($chunks_dir)) {
    mkdir($chunks_dir, 0755, true);
}

// Handle chunk upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get chunk information
    $chunk_number = isset($_POST['chunk']) ? intval($_POST['chunk']) : 0;
    $total_chunks = isset($_POST['chunks']) ? intval($_POST['chunks']) : 0;
    $filename = isset($_POST['name']) ? $_POST['name'] : '';
    $upload_id = isset($_POST['upload_id']) ? $_POST['upload_id'] : '';
    $marathon_id = isset($_POST['marathon_id']) ? intval($_POST['marathon_id']) : 0;
    
    // Validate parameters
    if (empty($upload_id) || empty($filename) || $marathon_id <= 0) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }
    
    // Create user-specific directory for chunks
    $user_chunks_dir = $chunks_dir . '/' . $_SESSION['user_id'] . '/' . $upload_id;
    if (!file_exists($user_chunks_dir)) {
        mkdir($user_chunks_dir, 0755, true);
    }
    
    // Process uploaded chunk
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $chunk_file = $user_chunks_dir . '/' . $chunk_number;
        
        // Move uploaded chunk to storage
        if (move_uploaded_file($_FILES['file']['tmp_name'], $chunk_file)) {
            // Check if all chunks have been uploaded
            if ($chunk_number === $total_chunks - 1) {
                // All chunks received, combine them
                $final_file = $user_chunks_dir . '/' . $filename;
                $out = fopen($final_file, 'wb');
                
                if ($out) {
                    for ($i = 0; $i < $total_chunks; $i++) {
                        $in = fopen($user_chunks_dir . '/' . $i, 'rb');
                        if ($in) {
                            while ($buff = fread($in, 4096)) {
                                fwrite($out, $buff);
                            }
                            fclose($in);
                            unlink($user_chunks_dir . '/' . $i); // Remove chunk file
                        }
                    }
                    fclose($out);
                    
                    // Process the complete file based on type
                    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    if ($file_ext === 'zip') {
                        // Process ZIP file
                        require_once 'includes/zip_handler.php';
                        
                        $file_info = [
                            'name' => $filename,
                            'type' => 'application/zip',
                            'tmp_name' => $final_file,
                            'error' => 0,
                            'size' => filesize($final_file)
                        ];
                        
                        $result = process_zip_file($file_info, $marathon_id, $_SESSION['user_id']);
                        
                        // Clean up the final zip file
                        unlink($final_file);
                        rmdir($user_chunks_dir);
                        
                        echo json_encode($result);
                        exit;
                    } else if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                        // Process image file
                        $file_info = [
                            'name' => $filename,
                            'type' => mime_content_type($final_file),
                            'tmp_name' => $final_file,
                            'error' => 0,
                            'size' => filesize($final_file)
                        ];
                        
                        $result = upload_image($file_info, $marathon_id, $_SESSION['user_id']);
                        
                        // Clean up the final file
                        unlink($final_file);
                        rmdir($user_chunks_dir);
                        
                        echo json_encode($result);
                        exit;
                    } else {
                        // Unsupported file type
                        unlink($final_file);
                        rmdir($user_chunks_dir);
                        
                        echo json_encode([
                            'success' => false,
                            'message' => 'Tipo de archivo no soportado: ' . $file_ext
                        ]);
                        exit;
                    }
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al crear el archivo final'
                    ]);
                    exit;
                }
            } else {
                // Chunk received, waiting for more
                echo json_encode([
                    'success' => true,
                    'message' => 'Chunk ' . ($chunk_number + 1) . ' of ' . $total_chunks . ' received'
                ]);
                exit;
            }
        } else {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode([
                'success' => false,
                'message' => 'Error al guardar el fragmento del archivo'
            ]);
            exit;
        }
    } else {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode([
            'success' => false,
            'message' => 'No se recibió ningún archivo o hubo un error en la subida'
        ]);
        exit;
    }
} else {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}
?>
