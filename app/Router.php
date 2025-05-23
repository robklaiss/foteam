<?php

namespace App;

class Router {
    private $routes = [];
    private $notFoundCallback;
    private $basePath = '';
    private $middleware = [];

    public function __construct($basePath = '') {
        $this->basePath = rtrim($basePath, '/');
    }

    public function addRoute($method, $path, $handler, $middleware = []) {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $this->basePath . $path,
            'handler' => $handler,
            'middleware' => (array)$middleware
        ];
    }

    public function get($path, $handler, $middleware = []) {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post($path, $handler, $middleware = []) {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put($path, $handler, $middleware = []) {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete($path, $handler, $middleware = []) {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function group($basePath, $callback) {
        $router = new self($this->basePath . $basePath);
        $callback($router);
        $this->routes = array_merge($this->routes, $router->getRoutes());
    }

    public function middleware($middleware, $callback) {
        $router = new self($this->basePath);
        $router->setMiddleware((array)$middleware);
        $callback($router);
        $this->routes = array_merge($this->routes, $router->getRoutes());
    }

    public function setNotFound($handler) {
        $this->notFoundCallback = $handler;
    }

    public function setMiddleware($middleware) {
        $this->middleware = $middleware;
    }

    public function getRoutes() {
        return $this->routes;
    }

    public function dispatch() {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $requestUri = rtrim($requestUri, '/');

        foreach ($this->routes as $route) {
            $pattern = $this->convertToRegex($route['path']);
            
            if ($route['method'] === $requestMethod && preg_match($pattern, $requestUri, $matches)) {
                // Remove the full match from matches
                array_shift($matches);
                
                // Merge middleware
                $middleware = array_merge($this->middleware, $route['middleware']);
                
                // Execute middleware
                if ($this->executeMiddleware($middleware) === false) {
                    return;
                }
                
                // Execute route handler
                $this->executeHandler($route['handler'], $matches);
                return;
            }
        }

        // No route matched
        if ($this->notFoundCallback) {
            call_user_func($this->notFoundCallback);
        } else {
            header("HTTP/1.0 404 Not Found");
            echo '404 Not Found';
        }
    }

    private function convertToRegex($path) {
        // Convert route parameters to regex patterns
        $pattern = preg_replace('/\{([^\/]+)\}/', '([^\/]+)', $path);
        // Escape forward slashes
        $pattern = str_replace('/', '\/', $pattern);
        // Allow optional trailing slash
        return '/^' . $pattern . '\/?$/';
    }

    private function executeMiddleware($middleware) {
        foreach ($middleware as $m) {
            if (is_callable($m)) {
                if (call_user_func($m) === false) {
                    return false;
                }
            } elseif (is_string($m) && class_exists($m)) {
                $instance = new $m();
                if (method_exists($instance, 'handle')) {
                    if ($instance->handle() === false) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    private function executeHandler($handler, $params) {
        if (is_callable($handler)) {
            call_user_func_array($handler, $params);
        } elseif (is_string($handler) && strpos($handler, '@') !== false) {
            list($class, $method) = explode('@', $handler, 2);
            $controller = new $class();
            if (method_exists($controller, $method)) {
                call_user_func_array([$controller, $method], $params);
            }
        }
    }
}
