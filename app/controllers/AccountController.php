<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Order;

class AccountController extends BaseController {
    public function __construct() {
        $this->requireLogin();
    }
    
    public function index() {
        $userId = $_SESSION['user_id'];
        $user = User::find($userId);
        
        // Get recent orders
        $recentOrders = Order::getByUserId($userId, 5);
        
        // Get order statistics
        $orderStats = [
            'total_orders' => Order::countByUser($userId),
            'pending_orders' => Order::countByUserAndStatus($userId, 'pending'),
            'completed_orders' => Order::countByUserAndStatus($userId, 'completed'),
            'total_spent' => Order::totalSpentByUser($userId)
        ];
        
        echo $this->view('account/index', [
            'title' => 'Mi Cuenta - FoTeam',
            'user' => $user,
            'recent_orders' => $recentOrders,
            'order_stats' => $orderStats
        ]);
    }
    
    public function orders() {
        $userId = $_SESSION['user_id'];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;
        
        $orders = Order::getByUserId($userId, $perPage, ($page - 1) * $perPage);
        $totalOrders = Order::countByUser($userId);
        
        echo $this->view('account/orders', [
            'title' => 'Mis Pedidos - FoTeam',
            'orders' => $orders,
            'current_page' => $page,
            'total_pages' => ceil($totalOrders / $perPage)
        ]);
    }
    
    public function orderDetails($orderId) {
        $userId = $_SESSION['user_id'];
        $order = Order::find($orderId);
        
        // Verify order belongs to user
        if (!$order || $order['user_id'] != $userId) {
            $_SESSION['error'] = 'Pedido no encontrado';
            $this->redirect('/account/orders');
        }
        
        // Get order items
        $orderItems = Order::getItems($orderId);
        
        echo $this->view('account/order_details', [
            'title' => 'Detalles del Pedido #' . $order['order_number'],
            'order' => $order,
            'order_items' => $orderItems
        ]);
    }
    
    public function downloads() {
        $userId = $_SESSION['user_id'];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 12;
        
        // Get user's purchased photos
        $downloads = Order::getUserDownloads($userId, $perPage, ($page - 1) * $perPage);
        $totalDownloads = Order::countUserDownloads($userId);
        
        echo $this->view('account/downloads', [
            'title' => 'Mis Descargas - FoTeam',
            'downloads' => $downloads,
            'current_page' => $page,
            'total_pages' => ceil($totalDownloads / $perPage)
        ]);
    }
    
    public function downloadPhoto($orderItemId) {
        $userId = $_SESSION['user_id'];
        
        // Verify the order item belongs to the user and is paid
        $download = Order::getDownload($orderItemId, $userId);
        
        if (!$download) {
            $_SESSION['error'] = 'No tienes permiso para descargar esta foto o el pedido no está completo';
            $this->redirect('/account/downloads');
        }
        
        // Check if download limit is reached (if applicable)
        if ($download['download_limit'] > 0 && $download['downloads_count'] >= $download['download_limit']) {
            $_SESSION['error'] = 'Has alcanzado el límite de descargas para esta foto';
            $this->redirect('/account/downloads');
        }
        
        $filePath = ROOT_PATH . $download['file_path'];
        
        if (!file_exists($filePath)) {
            $_SESSION['error'] = 'El archivo solicitado no existe';
            $this->redirect('/account/downloads');
        }
        
        // Increment download count
        Order::incrementDownloadCount($orderItemId);
        
        // Set headers for download
        header('Content-Description: File Transfer');
        header('Content-Type: ' . mime_content_type($filePath));
        header('Content-Disposition: attachment; filename=' . basename($download['original_filename']));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        
        // Clear output buffer
        ob_clean();
        flush();
        
        // Output file
        readfile($filePath);
        exit;
    }
    
    public function settings() {
        $userId = $_SESSION['user_id'];
        $user = User::find($userId);
        
        echo $this->view('account/settings', [
            'title' => 'Configuración de la Cuenta - FoTeam',
            'user' => $user
        ]);
    }
    
    public function updateProfile() {
        if (!$this->isPost()) {
            $this->redirect('/account/settings');
        }
        
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error'] = 'Token CSRF inválido';
            $this->redirect('/account/settings');
        }
        
        $userId = $_SESSION['user_id'];
        $user = User::find($userId);
        
        if (!$user) {
            $_SESSION['error'] = 'Usuario no encontrado';
            $this->redirect('/account/settings');
        }
        
        // Get form data
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $postalCode = trim($_POST['postal_code'] ?? '');
        $country = trim($_POST['country'] ?? '');
        
        // Validate input
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'El nombre es obligatorio';
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El correo electrónico no es válido';
        }
        
        // Check if email is already taken by another user
        $existingUser = User::findByEmail($email);
        if ($existingUser && $existingUser['id'] != $userId) {
            $errors[] = 'Este correo electrónico ya está en uso';
        }
        
        if (!empty($errors)) {
            $_SESSION['error'] = implode('<br>', $errors);
            $_SESSION['form_data'] = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'postal_code' => $postalCode,
                'country' => $country
            ];
            $this->redirect('/account/settings#profile');
        }
        
        // Update user profile
        $userData = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'city' => $city,
            'state' => $state,
            'postal_code' => $postalCode,
            'country' => $country,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $success = User::update($userId, $userData);
        
        if ($success) {
            // Update session data
            $_SESSION['username'] = $name;
            $_SESSION['user_email'] = $email;
            
            $_SESSION['success'] = 'Perfil actualizado exitosamente';
        } else {
            $_SESSION['error'] = 'Error al actualizar el perfil. Por favor intente nuevamente.';
        }
        
        $this->redirect('/account/settings#profile');
    }
    
    public function changePassword() {
        if (!$this->isPost()) {
            $this->redirect('/account/settings');
        }
        
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error'] = 'Token CSRF inválido';
            $this->redirect('/account/settings#password');
        }
        
        $userId = $_SESSION['user_id'];
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate input
        $errors = [];
        
        if (empty($currentPassword)) {
            $errors[] = 'La contraseña actual es obligatoria';
        }
        
        if (strlen($newPassword) < 8) {
            $errors[] = 'La nueva contraseña debe tener al menos 8 caracteres';
        }
        
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'Las contraseñas no coinciden';
        }
        
        if (!empty($errors)) {
            $_SESSION['error'] = implode('<br>', $errors);
            $this->redirect('/account/settings#password');
        }
        
        // Verify current password
        $user = User::find($userId);
        if (!password_verify($currentPassword, $user['password'])) {
            $_SESSION['error'] = 'La contraseña actual es incorrecta';
            $this->redirect('/account/settings#password');
        }
        
        // Update password
        $success = User::update($userId, [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($success) {
            $_SESSION['success'] = 'Contraseña actualizada exitosamente';
        } else {
            $_SESSION['error'] = 'Error al actualizar la contraseña. Por favor intente nuevamente.';
        }
        
        $this->redirect('/account/settings#password');
    }
    
    public function deleteAccount() {
        if (!$this->isPost()) {
            $this->json(['success' => false, 'message' => 'Método no permitido'], 405);
        }
        
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $this->json(['success' => false, 'message' => 'Token CSRF inválido'], 400);
        }
        
        $userId = $_SESSION['user_id'];
        $password = $_POST['password'] ?? '';
        
        if (empty($password)) {
            $this->json(['success' => false, 'message' => 'Por favor ingresa tu contraseña para confirmar'], 400);
        }
        
        // Verify password
        $user = User::find($userId);
        if (!password_verify($password, $user['password'])) {
            $this->json(['success' => false, 'message' => 'Contraseña incorrecta'], 400);
        }
        
        // Delete user account (soft delete)
        $success = User::delete($userId);
        
        if ($success) {
            // Logout user
            session_destroy();
            
            $this->json([
                'success' => true, 
                'message' => 'Tu cuenta ha sido eliminada exitosamente',
                'redirect' => '/'
            ]);
        } else {
            $this->json(['success' => false, 'message' => 'Error al eliminar la cuenta. Por favor intente nuevamente.'], 500);
        }
    }
}
