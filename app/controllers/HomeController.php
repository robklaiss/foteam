<?php

namespace App\Controllers;

use App\Models\Marathon;

class HomeController extends BaseController {
    public function index() {
        // Get recent public marathons
        $marathons = Marathon::getRecentPublic(6);
        
        // Get all public marathons for the search dropdown
        $allMarathons = Marathon::getAllPublic();
        
        // Render the home view
        echo $this->view('home/index', [
            'recent_marathons' => $marathons,
            'all_marathons' => $allMarathons,
            'title' => 'Inicio - FoTeam'
        ]);
    }
    
    public function about() {
        echo $this->view('home/about', [
            'title' => 'Sobre Nosotros - FoTeam'
        ]);
    }
    
    public function contact() {
        echo $this->view('home/contact', [
            'title' => 'Contacto - FoTeam'
        ]);
    }
    
    public function privacy() {
        echo $this->view('home/privacy', [
            'title' => 'Política de Privacidad - FoTeam'
        ]);
    }
    
    public function terms() {
        echo $this->view('home/terms', [
            'title' => 'Términos de Servicio - FoTeam'
        ]);
    }
}
