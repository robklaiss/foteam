<?php

namespace App\Controllers;

class BaseController {
    protected $viewPath = __DIR__ . '/../../views/';
    
    protected function view($view, $data = []) {
        // Extract data to variables
        extract($data);
        
        // Start output buffering
        ob_start();
        
        // Include the view file
        $viewFile = $this->viewPath . $view . '.php';
        if (file_exists($viewFile)) {
            include $viewFile;
        } else {
            throw new \Exception("View file not found: " . $view);
        }
        
        // Get the contents of the buffer and clean it
        return ob_get_clean();
    }
    
    protected function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    protected function redirect($url, $statusCode = 302) {
        header('Location: ' . $url, true, $statusCode);
        exit;
    }
    
    protected function isPost() {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
    
    protected function isGet() {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }
    
    protected function getPostData() {
        return $_POST;
    }
    
    protected function getQueryParams() {
        return $_GET;
    }
}
