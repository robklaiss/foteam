<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Marathon;
use App\Models\Photo;
use App\Models\Order;

class AdminController extends BaseController {
    public function __construct() {
        $this->requireLogin();
        $this->requireAdmin();
    }
    
    public function dashboard() {
        // Get statistics for the admin dashboard
        $stats = [
            'total_users' => User::count(),
            'total_marathons' => Marathon::count(),
            'total_photos' => Photo::count(),
            'total_orders' => Order::count(),
            'total_revenue' => Order::totalRevenue(),
            'pending_orders' => Order::countByStatus('pending'),
            'recent_orders' => Order::getRecent(5),
            'recent_users' => User::getRecent(5)
        ];
        
        echo $this->view('admin/dashboard', [
            'title' => 'Panel de Administración - FoTeam',
            'stats' => $stats
        ]);
    }
    
    public function users() {
        $users = User::getAll();
        
        echo $this->view('admin/users/index', [
            'title' => 'Usuarios - Panel de Administración',
            'users' => $users
        ]);
    }
    
    public function editUser($userId) {
        $user = User::find($userId);
        
        if (!$user) {
            $_SESSION['error'] = 'Usuario no encontrado';
            $this->redirect('/admin/users');
        }
        
        echo $this->view('admin/users/edit', [
            'title' => 'Editar Usuario - Panel de Administración',
            'user' => $user
        ]);
    }
    
    public function updateUser($userId) {
        if (!$this->isPost()) {
            $this->redirect("/admin/users/{$userId}/edit");
        }
        
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error'] = 'Token CSRF inválido';
            $this->redirect("/admin/users/{$userId}/edit");
        }
        
        // Get user data
        $user = User::find($userId);
        if (!$user) {
            $_SESSION['error'] = 'Usuario no encontrado';
            $this->redirect('/admin/users');
        }
        
        // Validate input
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $isAdmin = isset($_POST['is_admin']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
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
        
        // Update password if provided
        $password = trim($_POST['password'] ?? '');
        if (!empty($password)) {
            if (strlen($password) < 8) {
                $errors[] = 'La contraseña debe tener al menos 8 caracteres';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            }
        }
        
        if (!empty($errors)) {
            $_SESSION['error'] = implode('<br>', $errors);
            $this->redirect("/admin/users/{$userId}/edit");
        }
        
        // Prepare user data
        $userData = [
            'name' => $name,
            'email' => $email,
            'is_admin' => $isAdmin,
            'is_active' => $isActive,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Add password to update if provided
        if (!empty($passwordHash)) {
            $userData['password'] = $passwordHash;
        }
        
        // Update user
        $success = User::update($userId, $userData);
        
        if ($success) {
            $_SESSION['success'] = 'Usuario actualizado exitosamente';
        } else {
            $_SESSION['error'] = 'Error al actualizar el usuario. Por favor intente nuevamente.';
        }
        
        $this->redirect("/admin/users/{$userId}/edit");
    }
    
    public function deleteUser($userId) {
        if (!$this->isPost()) {
            $this->json(['success' => false, 'message' => 'Método no permitido'], 405);
        }
        
        // Prevent deleting own account
        if ($userId == $_SESSION['user_id']) {
            $this->json(['success' => false, 'message' => 'No puedes eliminar tu propia cuenta'], 400);
        }
        
        // Delete user
        $success = User::delete($userId);
        
        if ($success) {
            $this->json(['success' => true, 'message' => 'Usuario eliminado exitosamente']);
        } else {
            $this->json(['success' => false, 'message' => 'Error al eliminar el usuario'], 500);
        }
    }
    
    public function reports() {
        // Get report filters
        $startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
        $endDate = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month
        $reportType = $_GET['type'] ?? 'sales';
        
        $reportData = [];
        $reportTitle = '';
        
        switch ($reportType) {
            case 'sales':
                $reportTitle = 'Reporte de Ventas';
                $reportData = Order::getSalesReport($startDate, $endDate);
                break;
                
            case 'users':
                $reportTitle = 'Reporte de Usuarios';
                $reportData = User::getRegistrationReport($startDate, $endDate);
                break;
                
            case 'photos':
                $reportTitle = 'Reporte de Fotos';
                $reportData = Photo::getUploadReport($startDate, $endDate);
                break;
                
            default:
                $reportType = 'sales';
                $reportTitle = 'Reporte de Ventas';
                $reportData = Order::getSalesReport($startDate, $endDate);
        }
        
        echo $this->view('admin/reports/index', [
            'title' => $reportTitle . ' - Panel de Administración',
            'reportType' => $reportType,
            'reportTitle' => $reportTitle,
            'reportData' => $reportData,
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
    }
    
    public function exportReport($format = 'csv') {
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');
        $reportType = $_GET['type'] ?? 'sales';
        
        // Get report data
        $data = [];
        $filename = '';
        
        switch ($reportType) {
            case 'sales':
                $data = Order::getSalesReport($startDate, $endDate, true);
                $filename = "ventas_{$startDate}_to_{$endDate}";
                $headers = ['ID', 'Fecha', 'Usuario', 'Total', 'Estado', 'Método de Pago'];
                break;
                
            case 'users':
                $data = User::getRegistrationReport($startDate, $endDate, true);
                $filename = "usuarios_{$startDate}_to_{$endDate}";
                $headers = ['ID', 'Nombre', 'Email', 'Fecha de Registro', 'Rol', 'Estado'];
                break;
                
            case 'photos':
                $data = Photo::getUploadReport($startDate, $endDate, true);
                $filename = "fotos_{$startDate}_to_{$endDate}";
                $headers = ['ID', 'Nombre del Archivo', 'Maratón', 'Fotógrafo', 'Fecha de Subida', 'Estado'];
                break;
                
            default:
                $_SESSION['error'] = 'Tipo de reporte no válido';
                $this->redirect('/admin/reports');
        }
        
        // Export based on format
        if ($format === 'csv') {
            $this->exportToCsv($filename . '.csv', $headers, $data);
        } else if ($format === 'excel') {
            $this->exportToExcel($filename . '.xlsx', $headers, $data);
        } else {
            $_SESSION['error'] = 'Formato de exportación no soportado';
            $this->redirect('/admin/reports');
        }
    }
    
    private function exportToCsv($filename, $headers, $data) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel compatibility with UTF-8
        fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
        
        // Add headers
        fputcsv($output, $headers, ';');
        
        // Add data rows
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }
        
        fclose($output);
        exit;
    }
    
    private function exportToExcel($filename, $headers, $data) {
        // In a real implementation, you would use a library like PhpSpreadsheet
        // For now, we'll just redirect to CSV export
        $_GET['format'] = 'csv';
        $this->exportReport('csv');
    }
    
    public function settings() {
        // Get current settings
        $settings = [
            'site_name' => get_setting('site_name', 'FoTeam'),
            'site_email' => get_setting('site_email', 'info@foteam.com'),
            'items_per_page' => get_setting('items_per_page', 12),
            'currency' => get_setting('currency', 'USD'),
            'tax_rate' => get_setting('tax_rate', 10),
            'allow_registrations' => get_setting('allow_registrations', 1),
            'maintenance_mode' => get_setting('maintenance_mode', 0)
        ];
        
        echo $this->view('admin/settings', [
            'title' => 'Configuración - Panel de Administración',
            'settings' => $settings
        ]);
    }
    
    public function updateSettings() {
        if (!$this->isPost()) {
            $this->redirect('/admin/settings');
        }
        
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error'] = 'Token CSRF inválido';
            $this->redirect('/admin/settings');
        }
        
        // Update settings
        $settings = [
            'site_name' => trim($_POST['site_name'] ?? ''),
            'site_email' => trim($_POST['site_email'] ?? ''),
            'items_per_page' => (int)($_POST['items_per_page'] ?? 12),
            'currency' => trim($_POST['currency'] ?? 'USD'),
            'tax_rate' => (float)($_POST['tax_rate'] ?? 10),
            'allow_registrations' => isset($_POST['allow_registrations']) ? 1 : 0,
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0
        ];
        
        // Validate settings
        $errors = [];
        
        if (empty($settings['site_name'])) {
            $errors[] = 'El nombre del sitio es obligatorio';
        }
        
        if (empty($settings['site_email']) || !filter_var($settings['site_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El correo electrónico del sitio no es válido';
        }
        
        if ($settings['items_per_page'] < 1) {
            $errors[] = 'El número de ítems por página debe ser mayor a 0';
        }
        
        if ($settings['tax_rate'] < 0) {
            $errors[] = 'La tasa de impuestos no puede ser negativa';
        }
        
        if (!empty($errors)) {
            $_SESSION['error'] = implode('<br>', $errors);
            $_SESSION['form_data'] = $settings;
            $this->redirect('/admin/settings');
        }
        
        // Save settings
        foreach ($settings as $key => $value) {
            update_setting($key, $value);
        }
        
        $_SESSION['success'] = 'Configuración actualizada exitosamente';
        $this->redirect('/admin/settings');
    }
    
    private function requireAdmin() {
        if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
            $_SESSION['error'] = 'Acceso denegado. Se requieren privilegios de administrador.';
            $this->redirect('/');
        }
    }
}
