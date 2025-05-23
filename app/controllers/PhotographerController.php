<?php

namespace App\Controllers;

use App\Models\Marathon;
use App\Models\Photo;

class PhotographerController extends BaseController {
    public function __construct() {
        $this->requireLogin();
        // Additional photographer-specific checks can be added here
    }
    
    public function dashboard() {
        $userId = $_SESSION['user_id'];
        
        // Get photographer's marathons
        $marathons = Marathon::getByPhotographerId($userId, 5);
        
        // Get recent uploads
        $recentUploads = Photo::getRecentByPhotographer($userId, 12);
        
        // Get statistics
        $stats = [
            'total_marathons' => Marathon::countByPhotographer($userId),
            'total_photos' => Photo::countByPhotographer($userId),
            'total_downloads' => Photo::countDownloadsByPhotographer($userId),
            'total_earnings' => Photo::getEarningsByPhotographer($userId)
        ];
        
        echo $this->view('photographer/dashboard', [
            'title' => 'Panel de Fotógrafo - FoTeam',
            'marathons' => $marathons,
            'recent_uploads' => $recentUploads,
            'stats' => $stats
        ]);
    }
    
    public function marathons() {
        $userId = $_SESSION['user_id'];
        $marathons = Marathon::getByPhotographerId($userId);
        
        echo $this->view('photographer/marathons/index', [
            'title' => 'Mis Maratones - FoTeam',
            'marathons' => $marathons
        ]);
    }
    
    public function createMarathon() {
        echo $this->view('photographer/marathons/create', [
            'title' => 'Agregar Maratón - FoTeam'
        ]);
    }
    
    public function storeMarathon() {
        if (!$this->isPost()) {
            $this->redirect('/photographer/marathons/create');
        }
        
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error'] = 'Token CSRF inválido';
            $this->redirect('/photographer/marathons/create');
        }
        
        // Validate input
        $errors = [];
        $name = trim($_POST['name'] ?? '');
        $eventDate = trim($_POST['event_date'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isPublic = isset($_POST['is_public']) ? 1 : 0;
        
        if (empty($name)) {
            $errors[] = 'El nombre del maratón es obligatorio';
        }
        
        if (empty($eventDate) || !strtotime($eventDate)) {
            $errors[] = 'La fecha del evento no es válida';
        }
        
        if (empty($location)) {
            $errors[] = 'La ubicación es obligatoria';
        }
        
        if (!empty($errors)) {
            $_SESSION['error'] = implode('<br>', $errors);
            $_SESSION['form_data'] = $_POST;
            $this->redirect('/photographer/marathons/create');
        }
        
        // Create marathon
        $marathonId = Marathon::create([
            'name' => $name,
            'event_date' => date('Y-m-d', strtotime($eventDate)),
            'location' => $location,
            'description' => $description,
            'is_public' => $isPublic,
            'photographer_id' => $_SESSION['user_id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($marathonId) {
            $_SESSION['success'] = 'Maratón creado exitosamente';
            $this->redirect("/photographer/marathons/{$marathonId}/edit");
        } else {
            $_SESSION['error'] = 'Error al crear el maratón. Por favor intente nuevamente.';
            $_SESSION['form_data'] = $_POST;
            $this->redirect('/photographer/marathons/create');
        }
    }
    
    public function editMarathon($marathonId) {
        $marathon = Marathon::find($marathonId);
        
        // Verify ownership
        if (!$marathon || $marathon['photographer_id'] != $_SESSION['user_id']) {
            $_SESSION['error'] = 'No tienes permiso para editar este maratón';
            $this->redirect('/photographer/marathons');
        }
        
        echo $this->view('photographer/marathons/edit', [
            'title' => 'Editar Maratón - ' . $marathon['name'],
            'marathon' => $marathon
        ]);
    }
    
    public function updateMarathon($marathonId) {
        if (!$this->isPost()) {
            $this->redirect("/photographer/marathons/{$marathonId}/edit");
        }
        
        // Verify ownership
        $marathon = Marathon::find($marathonId);
        if (!$marathon || $marathon['photographer_id'] != $_SESSION['user_id']) {
            $_SESSION['error'] = 'No tienes permiso para editar este maratón';
            $this->redirect('/photographer/marathons');
        }
        
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error'] = 'Token CSRF inválido';
            $this->redirect("/photographer/marathons/{$marathonId}/edit");
        }
        
        // Validate input
        $errors = [];
        $name = trim($_POST['name'] ?? '');
        $eventDate = trim($_POST['event_date'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isPublic = isset($_POST['is_public']) ? 1 : 0;
        
        if (empty($name)) {
            $errors[] = 'El nombre del maratón es obligatorio';
        }
        
        if (empty($eventDate) || !strtotime($eventDate)) {
            $errors[] = 'La fecha del evento no es válida';
        }
        
        if (empty($location)) {
            $errors[] = 'La ubicación es obligatoria';
        }
        
        if (!empty($errors)) {
            $_SESSION['error'] = implode('<br>', $errors);
            $this->redirect("/photographer/marathons/{$marathonId}/edit");
        }
        
        // Update marathon
        $success = Marathon::update($marathonId, [
            'name' => $name,
            'event_date' => date('Y-m-d', strtotime($eventDate)),
            'location' => $location,
            'description' => $description,
            'is_public' => $isPublic,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($success) {
            $_SESSION['success'] = 'Maratón actualizado exitosamente';
        } else {
            $_SESSION['error'] = 'Error al actualizar el maratón. Por favor intente nuevamente.';
        }
        
        $this->redirect("/photographer/marathons/{$marathonId}/edit");
    }
    
    public function deleteMarathon($marathonId) {
        if (!$this->isPost()) {
            $this->redirect('/photographer/marathons');
        }
        
        // Verify ownership
        $marathon = Marathon::find($marathonId);
        if (!$marathon || $marathon['photographer_id'] != $_SESSION['user_id']) {
            $this->json(['success' => false, 'message' => 'No tienes permiso para eliminar este maratón']);
        }
        
        // Check if there are photos in this marathon
        $photoCount = Photo::countByMarathon($marathonId);
        
        if ($photoCount > 0) {
            $this->json([
                'success' => false, 
                'message' => 'No se puede eliminar el maratón porque tiene fotos asociadas. Por favor elimina las fotos primero.'
            ]);
        }
        
        // Delete marathon
        $success = Marathon::delete($marathonId);
        
        if ($success) {
            $_SESSION['success'] = 'Maratón eliminado exitosamente';
            $this->json(['success' => true, 'redirect' => '/photographer/marathons']);
        } else {
            $this->json(['success' => false, 'message' => 'Error al eliminar el maratón. Por favor intente nuevamente.']);
        }
    }
    
    public function showUploadForm() {
        $userId = $_SESSION['user_id'];
        $marathons = Marathon::getByPhotographerId($userId);
        
        if (empty($marathons)) {
            $_SESSION['notice'] = 'Debes crear un maratón antes de subir fotos';
            $this->redirect('/photographer/marathons/create');
        }
        
        echo $this->view('photographer/upload', [
            'title' => 'Subir Fotos - FoTeam',
            'marathons' => $marathons
        ]);
    }
    
    public function upload() {
        if (!$this->isPost()) {
            $this->redirect('/photographer/upload');
        }
        
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error'] = 'Token CSRF inválido';
            $this->redirect('/photographer/upload');
        }
        
        $marathonId = (int)($_POST['marathon_id'] ?? 0);
        $runnerNumber = trim($_POST['runner_number'] ?? '');
        $tags = trim($_POST['tags'] ?? '');
        
        // Verify marathon ownership
        $marathon = Marathon::find($marathonId);
        if (!$marathon || $marathon['photographer_id'] != $_SESSION['user_id']) {
            $_SESSION['error'] = 'Maratón no válido';
            $this->redirect('/photographer/upload');
        }
        
        // Handle file uploads
        $uploadedFiles = $_FILES['photos'] ?? [];
        $uploadDir = UPLOAD_DIR . '/' . $marathonId;
        $thumbnailDir = THUMBNAIL_DIR . '/' . $marathonId;
        
        // Create directories if they don't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        if (!file_exists($thumbnailDir)) {
            mkdir($thumbnailDir, 0755, true);
        }
        
        $uploadedCount = 0;
        $errors = [];
        
        // Process each uploaded file
        if (!empty($uploadedFiles['name'][0])) {
            $fileCount = count($uploadedFiles['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($uploadedFiles['error'][$i] !== UPLOAD_ERR_OK) {
                    $errors[] = 'Error al subir el archivo: ' . $uploadedFiles['name'][$i];
                    continue;
                }
                
                $fileName = uniqid() . '_' . basename($uploadedFiles['name'][$i]);
                $targetPath = $uploadDir . '/' . $fileName;
                $thumbnailPath = $thumbnailDir . '/' . $fileName;
                
                // Move uploaded file
                if (move_uploaded_file($uploadedFiles['tmp_name'][$i], $targetPath)) {
                    // Create thumbnail
                    $this->createThumbnail($targetPath, $thumbnailPath, 300, 200);
                    
                    // Save to database
                    $photoId = Photo::create([
                        'marathon_id' => $marathonId,
                        'photographer_id' => $_SESSION['user_id'],
                        'original_filename' => $uploadedFiles['name'][$i],
                        'stored_filename' => $fileName,
                        'file_path' => str_replace(ROOT_PATH, '', $targetPath),
                        'thumbnail_path' => str_replace(ROOT_PATH, '', $thumbnailPath),
                        'file_size' => $uploadedFiles['size'][$i],
                        'mime_type' => $uploadedFiles['type'][$i],
                        'runner_number' => !empty($runnerNumber) ? $runnerNumber : null,
                        'tags' => !empty($tags) ? $tags : null,
                        'is_approved' => 1, // Auto-approve for now
                        'is_available' => 1, // Available for purchase
                        'price' => 9.99, // Default price
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    if ($photoId) {
                        $uploadedCount++;
                    } else {
                        // Clean up file if database insert failed
                        @unlink($targetPath);
                        @unlink($thumbnailPath);
                        $errors[] = 'Error al guardar la información de la foto: ' . $uploadedFiles['name'][$i];
                    }
                } else {
                    $errors[] = 'Error al mover el archivo: ' . $uploadedFiles['name'][$i];
                }
            }
        }
        
        // Set success/error messages
        if ($uploadedCount > 0) {
            $_SESSION['success'] = "Se subieron exitosamente {$uploadedCount} fotos";
        }
        
        if (!empty($errors)) {
            $_SESSION['error'] = implode('<br>', $errors);
        }
        
        $this->redirect('/photographer/upload');
    }
    
    private function createThumbnail($sourcePath, $targetPath, $maxWidth, $maxHeight) {
        // Get image dimensions
        list($origWidth, $origHeight, $type) = getimagesize($sourcePath);
        
        // Calculate new dimensions
        $ratio = $origWidth / $origHeight;
        
        if ($maxWidth / $maxHeight > $ratio) {
            $newWidth = $maxHeight * $ratio;
            $newHeight = $maxHeight;
        } else {
            $newWidth = $maxWidth;
            $newHeight = $maxWidth / $ratio;
        }
        
        // Create new image
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Load source image based on type
        switch ($type) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($sourcePath);
                // Preserve transparency
                imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($sourcePath);
                // Preserve transparency
                $transparentIndex = imagecolortransparent($sourceImage);
                if ($transparentIndex >= 0) {
                    $transparentColor = imagecolorsforindex($sourceImage, $transparentIndex);
                    $transparentIndex = imagecolorallocate($newImage, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue']);
                    imagefill($newImage, 0, 0, $transparentIndex);
                    imagecolortransparent($newImage, $transparentIndex);
                }
                break;
            default:
                return false;
        }
        
        // Resize image
        imagecopyresampled(
            $newImage, $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $origWidth, $origHeight
        );
        
        // Save thumbnail
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($newImage, $targetPath, 90);
                break;
            case IMAGETYPE_PNG:
                imagepng($newImage, $targetPath, 9);
                break;
            case IMAGETYPE_GIF:
                imagegif($newImage, $targetPath);
                break;
        }
        
        // Free up memory
        imagedestroy($sourceImage);
        imagedestroy($newImage);
        
        return true;
    }
}
