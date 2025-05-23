<?php

namespace App\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use App\Models\User;

class CheckoutController extends BaseController {
    public function __construct() {
        // Require authentication for all checkout actions
        $this->requireLogin();
    }
    
    public function index() {
        $cart = new Cart();
        $items = $cart->getItems();
        
        if (empty($items)) {
            $_SESSION['error'] = 'Tu carrito está vacío';
            $this->redirect('/cart');
        }
        
        // Get user details
        $user = User::find($_SESSION['user_id']);
        
        // Get cart total
        $subtotal = $cart->getSubtotal();
        $tax = $cart->getTax();
        $total = $cart->getTotal();
        
        echo $this->view('checkout/index', [
            'title' => 'Finalizar Compra - FoTeam',
            'user' => $user,
            'items' => $items,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total
        ]);
    }
    
    public function process() {
        if (!$this->isPost()) {
            $this->redirect('/checkout');
        }
        
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error'] = 'Token CSRF inválido';
            $this->redirect('/checkout');
        }
        
        $cart = new Cart();
        $items = $cart->getItems();
        
        if (empty($items)) {
            $_SESSION['error'] = 'Tu carrito está vacío';
            $this->redirect('/cart');
        }
        
        // Validate form data
        $errors = $this->validateCheckoutForm($_POST);
        
        if (!empty($errors)) {
            $_SESSION['error'] = implode('<br>', $errors);
            $_SESSION['form_data'] = $_POST;
            $this->redirect('/checkout');
        }
        
        // Process payment (in a real app, this would integrate with a payment gateway)
        $paymentResult = $this->processPayment($_POST, $cart->getTotal());
        
        if (!$paymentResult['success']) {
            $_SESSION['error'] = $paymentResult['message'];
            $this->redirect('/checkout');
        }
        
        // Create order
        $orderId = $this->createOrder($_POST, $cart);
        
        if ($orderId) {
            // Clear the cart
            $cart->clear();
            
            // Redirect to order confirmation
            $this->redirect("/orders/{$orderId}");
        } else {
            $_SESSION['error'] = 'Error al crear el pedido. Por favor intente nuevamente.';
            $this->redirect('/checkout');
        }
    }
    
    public function success() {
        // In a real app, you would verify the payment here before showing success
        echo $this->view('checkout/success', [
            'title' => '¡Pago Exitoso! - FoTeam'
        ]);
    }
    
    public function cancel() {
        $_SESSION['notice'] = 'El pago fue cancelado. Puedes continuar con tu compra más tarde.';
        $this->redirect('/cart');
    }
    
    private function validateCheckoutForm($data) {
        $errors = [];
        
        // Required fields
        $required = [
            'first_name' => 'Nombre',
            'last_name' => 'Apellido',
            'email' => 'Correo electrónico',
            'phone' => 'Teléfono',
            'address' => 'Dirección',
            'city' => 'Ciudad',
            'state' => 'Estado/Provincia',
            'postal_code' => 'Código postal',
            'country' => 'País',
            'payment_method' => 'Método de pago'
        ];
        
        foreach ($required as $field => $label) {
            if (empty(trim($data[$field] ?? ''))) {
                $errors[] = "El campo {$label} es obligatorio";
            }
        }
        
        // Validate email
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El correo electrónico no es válido';
        }
        
        return $errors;
    }
    
    private function processPayment($data, $amount) {
        // In a real app, this would integrate with a payment gateway like Stripe, PayPal, etc.
        // For now, we'll just simulate a successful payment
        
        // Simulate payment processing delay
        sleep(2);
        
        // Simulate a 5% chance of payment failure
        if (rand(1, 100) <= 5) {
            return [
                'success' => false,
                'message' => 'El pago fue rechazado. Por favor verifica la información e intenta nuevamente.'
            ];
        }
        
        return [
            'success' => true,
            'transaction_id' => 'PAY-' . strtoupper(uniqid()),
            'amount' => $amount,
            'currency' => 'USD',
            'status' => 'completed'
        ];
    }
    
    private function createOrder($data, Cart $cart) {
        $db = db_connect();
        
        try {
            // Start transaction
            $db->exec('BEGIN');
            
            // Create order
            $orderData = [
                'user_id' => $_SESSION['user_id'],
                'order_number' => 'ORD-' . strtoupper(uniqid()),
                'status' => 'pending',
                'subtotal' => $cart->getSubtotal(),
                'tax' => $cart->getTax(),
                'total' => $cart->getTotal(),
                'payment_method' => $data['payment_method'],
                'payment_status' => 'completed',
                'shipping_first_name' => $data['first_name'],
                'shipping_last_name' => $data['last_name'],
                'shipping_email' => $data['email'],
                'shipping_phone' => $data['phone'],
                'shipping_address' => $data['address'],
                'shipping_address2' => $data['address2'] ?? '',
                'shipping_city' => $data['city'],
                'shipping_state' => $data['state'],
                'shipping_postal_code' => $data['postal_code'],
                'shipping_country' => $data['country'],
                'notes' => $data['notes'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $orderId = Order::create($orderData);
            
            if (!$orderId) {
                throw new \Exception('Failed to create order');
            }
            
            // Add order items
            foreach ($cart->getItems() as $item) {
                $photo = Photo::find($item['photo_id']);
                
                if (!$photo) {
                    throw new \Exception('Invalid photo in cart');
                }
                
                $orderItemData = [
                    'order_id' => $orderId,
                    'photo_id' => $photo['id'],
                    'quantity' => $item['quantity'],
                    'price' => $photo['price'],
                    'total' => $photo['price'] * $item['quantity'],
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $orderItemId = OrderItem::create($orderItemData);
                
                if (!$orderItemId) {
                    throw new \Exception('Failed to add order item');
                }
                
                // Update photo download count or mark as sold if needed
                // Photo::incrementDownloadCount($photo['id']);
            }
            
            // Commit transaction
            $db->exec('COMMIT');
            
            return $orderId;
            
        } catch (\Exception $e) {
            // Rollback transaction on error
            $db->exec('ROLLBACK');
            error_log('Order creation failed: ' . $e->getMessage());
            return false;
        }
    }
    
    private function requireLogin() {
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['redirect_after_login'] = '/checkout';
            $this->redirect('/auth/login');
        }
    }
}
