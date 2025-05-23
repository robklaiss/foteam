<?php

namespace App\Controllers;

use App\Models\Marathon;
use App\Models\Photo;

class GalleryController extends BaseController {
    public function index() {
        // Get all public marathons
        $marathons = Marathon::getAllPublic();
        
        // Get featured photos (e.g., most viewed or recently added)
        $featuredPhotos = Photo::getFeatured(12);
        
        echo $this->view('gallery/index', [
            'title' => 'Galería de Fotos - FoTeam',
            'marathons' => $marathons,
            'featured_photos' => $featuredPhotos
        ]);
    }
    
    public function showMarathon($marathonId) {
        // Get marathon details
        $marathon = Marathon::find($marathonId);
        
        if (!$marathon) {
            $_SESSION['error'] = 'Maratón no encontrado';
            $this->redirect('/gallery');
        }
        
        // Check if user has access to this marathon
        if (!$marathon['is_public'] && !$this->hasMarathonAccess($marathonId)) {
            $_SESSION['error'] = 'No tienes permiso para ver este maratón';
            $this->redirect('/gallery');
        }
        
        // Get photos for this marathon
        $photos = Photo::getByMarathonId($marathonId);
        
        // Get related marathons
        $relatedMarathons = Marathon::getRelated($marathonId, 4);
        
        echo $this->view('gallery/marathon', [
            'title' => $marathon['name'] . ' - FoTeam',
            'marathon' => $marathon,
            'photos' => $photos,
            'related_marathons' => $relatedMarathons
        ]);
    }
    
    public function showPhoto($photoId) {
        // Get photo details
        $photo = Photo::find($photoId);
        
        if (!$photo) {
            $_SESSION['error'] = 'Foto no encontrada';
            $this->redirect('/gallery');
        }
        
        // Check if user has access to this photo's marathon
        if (!$this->hasMarathonAccess($photo['marathon_id'])) {
            $_SESSION['error'] = 'No tienes permiso para ver esta foto';
            $this->redirect('/gallery');
        }
        
        // Increment view count
        Photo::incrementViewCount($photoId);
        
        // Get related photos
        $relatedPhotos = Photo::getRelated($photoId, 6);
        
        // Get marathon details
        $marathon = Marathon::find($photo['marathon_id']);
        
        echo $this->view('gallery/photo', [
            'title' => 'Foto #' . $photo['id'] . ' - ' . $marathon['name'],
            'photo' => $photo,
            'marathon' => $marathon,
            'related_photos' => $relatedPhotos
        ]);
    }
    
    public function search() {
        $query = trim($_GET['q'] ?? '');
        $marathonId = $_GET['marathon_id'] ?? null;
        $runnerNumber = trim($_GET['runner_number'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 24;
        
        $filters = [];
        
        if (!empty($query)) {
            $filters['query'] = $query;
        }
        
        if (!empty($marathonId)) {
            $filters['marathon_id'] = (int)$marathonId;
        }
        
        if (!empty($runnerNumber)) {
            $filters['runner_number'] = $runnerNumber;
        }
        
        // Get search results
        $searchResults = Photo::search($filters, $page, $perPage);
        
        // Get all marathons for the filter dropdown
        $marathons = Marathon::getAllPublic();
        
        echo $this->view('gallery/search', [
            'title' => 'Buscar Fotos - FoTeam',
            'query' => $query,
            'marathon_id' => $marathonId,
            'runner_number' => $runnerNumber,
            'results' => $searchResults['results'],
            'total' => $searchResults['total'],
            'current_page' => $page,
            'total_pages' => ceil($searchResults['total'] / $perPage),
            'marathons' => $marathons
        ]);
    }
    
    private function hasMarathonAccess($marathonId) {
        // Check if user is logged in and has access to this marathon
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // If user is admin, they have access to all marathons
        if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
            return true;
        }
        
        // Check if user is the photographer who uploaded this marathon
        $marathon = Marathon::find($marathonId);
        if ($marathon && isset($marathon['photographer_id']) && $marathon['photographer_id'] == $_SESSION['user_id']) {
            return true;
        }
        
        // Add additional access checks here if needed
        
        return false;
    }
}
