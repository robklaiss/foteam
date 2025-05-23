<?php

namespace App;

use App\Controllers\BaseController;

class Application {
    private static $instance = null;
    private $router;
    private $config = [];
    
    private function __construct() {
        $this->router = new Router();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getRouter() {
        return $this->router;
    }
    
    public function setConfig($key, $value) {
        $this->config[$key] = $value;
        return $this;
    }
    
    public function getConfig($key, $default = null) {
        return $this->config[$key] ?? $default;
    }
    
    public function run() {
        // Register routes
        $this->registerRoutes();
        
        // Dispatch the request
        $this->router->dispatch();
    }
    
    private function registerRoutes() {
        // Include route files
        $routeFiles = [
            __DIR__ . '/../routes/web.php',
            __DIR__ . '/../routes/api.php',
        ];
        
        foreach ($routeFiles as $file) {
            if (file_exists($file)) {
                $router = $this->router;
                require $file;
            }
        }
    }
    
    public function get($path, $handler, $middleware = []) {
        $this->router->get($path, $handler, $middleware);
        return $this;
    }
    
    public function post($path, $handler, $middleware = []) {
        $this->router->post($path, $handler, $middleware);
        return $this;
    }
    
    public function put($path, $handler, $middleware = []) {
        $this->router->put($path, $handler, $middleware);
        return $this;
    }
    
    public function delete($path, $handler, $middleware = []) {
        $this->router->delete($path, $handler, $middleware);
        return $this;
    }
    
    public function group($basePath, $callback) {
        $this->router->group($basePath, $callback);
        return $this;
    }
    
    public function middleware($middleware, $callback) {
        $this->router->middleware($middleware, $callback);
        return $this;
    }
}
