<?php

namespace App\Controllers;

use App\Models\User;

class AuthController extends BaseController {
    public function showLoginForm() {
        // If user is already logged in, redirect to home
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/');
        }
        
        echo $this->view('auth/login', [
            'title' => 'Iniciar Sesión - FoTeam'
        ]);
    }
    
    public function login() {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error'] = 'Token CSRF inválido';
            $this->redirect('/auth/login');
        }
        
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // Basic validation
        if (empty($email) || empty($password)) {
            $_SESSION['error'] = 'Por favor ingrese su correo y contraseña';
            $this->redirect('/auth/login');
        }
        
        // Find user by email
        $user = User::findByEmail($email);
        
        if (!$user || !password_verify($password, $user['password'])) {
            $_SESSION['error'] = 'Credenciales inválidas';
            $this->redirect('/auth/login');
        }
        
        // Set user session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['username'] = $user['name'];
        $_SESSION['is_admin'] = (bool)($user['is_admin'] ?? false);
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        // Redirect to intended URL or home
        $redirect = $_SESSION['redirect_after_login'] ?? '/';
        unset($_SESSION['redirect_after_login']);
        $this->redirect($redirect);
    }
    
    public function showRegistrationForm() {
        // If user is already logged in, redirect to home
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/');
        }
        
        echo $this->view('auth/register', [
            'title' => 'Registrarse - FoTeam'
        ]);
    }
    
    public function register() {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error'] = 'Token CSRF inválido';
            $this->redirect('/auth/register');
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
            $_SESSION['error'] = implode('<br>', $errors);
            $_SESSION['old'] = [
                'name' => $name,
                'email' => $email
            ];
            $this->redirect('/auth/register');
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
            // Log the user in
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_email'] = $email;
            $_SESSION['username'] = $name;
            $_SESSION['is_admin'] = false;
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            $_SESSION['success'] = '¡Registro exitoso! Bienvenido a FoTeam';
            $this->redirect('/');
        } else {
            $_SESSION['error'] = 'Error al crear la cuenta. Por favor intente nuevamente.';
            $this->redirect('/auth/register');
        }
    }
    
    public function logout() {
        // Unset all session variables
        $_SESSION = [];
        
        // Delete the session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        // Destroy the session
        session_destroy();
        
        // Redirect to home
        $this->redirect('/');
    }
}
