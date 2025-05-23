<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\User;

class AuthApiController extends BaseController {
    public function login() {
        if (!$this->isPost()) {
            return $this->json(['success' => false, 'message' => 'Método no permitido'], 405);
        }
        
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Basic validation
        if (empty($email) || empty($password)) {
            return $this->json(['success' => false, 'message' => 'Por favor ingrese su correo y contraseña'], 400);
        }
        
        // Find user by email
        $user = User::findByEmail($email);
        
        if (!$user || !password_verify($password, $user['password'])) {
            return $this->json(['success' => false, 'message' => 'Credenciales inválidas'], 401);
        }
        
        // Generate API token (in a real app, use JWT or similar)
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 day'));
        
        // Save token to database
        User::update($user['id'], [
            'api_token' => $token,
            'api_token_expires_at' => $expiresAt
        ]);
        
        // Return token and user info
        return $this->json([
            'success' => true,
            'token' => $token,
            'expires_at' => $expiresAt,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'is_admin' => (bool)$user['is_admin']
            ]
        ]);
    }
    
    public function register() {
        if (!$this->isPost()) {
            return $this->json(['success' => false, 'message' => 'Método no permitido'], 405);
        }
        
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Basic validation
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'El nombre es requerido';
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Por favor ingrese un correo electrónico válido';
        }
        
        if (strlen($password) < 8) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres';
        }
        
        if ($password !== $confirmPassword) {
            $errors[] = 'Las contraseñas no coinciden';
        }
        
        // Check if email already exists
        if (User::emailExists($email)) {
            $errors[] = 'Este correo electrónico ya está registrado';
        }
        
        if (!empty($errors)) {
            return $this->json(['success' => false, 'errors' => $errors], 400);
        }
        
        // Create user
        $userId = User::create([
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'is_admin' => false,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($userId) {
            // Generate API token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 day'));
            
            User::update($userId, [
                'api_token' => $token,
                'api_token_expires_at' => $expiresAt
            ]);
            
            return $this->json([
                'success' => true,
                'message' => 'Registro exitoso',
                'token' => $token,
                'expires_at' => $expiresAt,
                'user' => [
                    'id' => $userId,
                    'name' => $name,
                    'email' => $email,
                    'is_admin' => false
                ]
            ], 201);
        } else {
            return $this->json(['success' => false, 'message' => 'Error al crear la cuenta'], 500);
        }
    }
    
    public function logout() {
        // In a real app, you would invalidate the token
        // For now, we'll just return success
        return $this->json(['success' => true, 'message' => 'Sesión cerrada exitosamente']);
    }
    
    public function getUser() {
        $token = $this->getBearerToken();
        
        if (!$token) {
            return $this->json(['success' => false, 'message' => 'No autorizado'], 401);
        }
        
        // Find user by token
        $user = User::findByToken($token);
        
        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Token inválido o expirado'], 401);
        }
        
        // Return user info (without sensitive data)
        return $this->json([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'is_admin' => (bool)$user['is_admin']
            ]
        ]);
    }
    
    private function getBearerToken() {
        $headers = $this->getAuthorizationHeader();
        
        // HEADER: Get the access token from the header
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    private function getAuthorizationHeader() {
        $headers = null;
        
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { // Nginx or fast CGI
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } else if (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this is that it's easier to test this code)
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        
        return $headers;
    }
}
