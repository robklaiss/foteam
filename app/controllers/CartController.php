<?php

namespace App\Controllers;

use App\Models\Cart;
use App\Models\Photo;

class CartController extends BaseController {
    public function __construct() {
        // Require authentication for all cart actions
        $this->requireLogin();
    }
    
    public function index() {
        $cart = new Cart();
        $items = $cart->getItems();
        $total = 0;
        $photos = [];
        
        // Get photo details for each item in cart
        foreach ($items as $item) {
            $photo = Photo::find($item['photo_id']);
            if ($photo) {
                $photo['quantity'] = $item['quantity'];
                $photo['item_id'] = $item['id'];
                $photos[] = $photo;
                $total += $photo['price'] * $item['quantity'];
            }
        }
        
        echo $this->view('cart/index', [
            'title' => 'Carrito de Compras - FoTeam',
            'items' => $photos,
            'subtotal' => $total,
            'tax' => $total * 0.10, // 10% tax
            'total' => $total * 1.10 // Total with tax
        ]);
    }
    
    public function add() {
        if (!$this->isPost()) {
            $this->json(['success' => false, 'message' => 'Invalid request method'], 405);
        }
        
        $photoId = (int)($_POST['photo_id'] ?? 0);
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        
        // Validate photo exists and is available for purchase
        $photo = Photo::find($photoId);
        if (!$photo || !$photo['is_available']) {
            $this->json(['success' => false, 'message' => 'Photo not available'], 404);
        }
        
        // Add to cart
        $cart = new Cart();
        $result = $cart->addItem($photoId, $quantity);
        
        if ($result) {
            $this->json([
                'success' => true,
                'message' => 'Added to cart',
                'cart_count' => $cart->getItemCount()
            ]);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to add to cart'], 500);
        }
    }
    
    public function remove($itemId) {
        $cart = new Cart();
        $result = $cart->removeItem($itemId);
        
        if ($result) {
            $_SESSION['success'] = 'Item removed from cart';
        } else {
            $_SESSION['error'] = 'Failed to remove item from cart';
        }
        
        $this->redirect('/cart');
    }
    
    public function update() {
        if (!$this->isPost()) {
            $this->json(['success' => false, 'message' => 'Invalid request method'], 405);
        }
        
        $itemId = (int)($_POST['item_id'] ?? 0);
        $quantity = max(0, (int)($_POST['quantity'] ?? 0));
        
        $cart = new Cart();
        
        if ($quantity === 0) {
            // Remove item if quantity is 0
            $result = $cart->removeItem($itemId);
        } else {
            // Update quantity
            $result = $cart->updateItem($itemId, $quantity);
        }
        
        if ($result) {
            $this->json([
                'success' => true,
                'message' => 'Cart updated',
                'cart_count' => $cart->getItemCount(),
                'subtotal' => $cart->getSubtotal(),
                'tax' => $cart->getTax(),
                'total' => $cart->getTotal()
            ]);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to update cart'], 500);
        }
    }
    
    public function getCartCount() {
        $cart = new Cart();
        $this->json([
            'success' => true,
            'count' => $cart->getItemCount()
        ]);
    }
    
    private function requireLogin() {
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            $this->redirect('/auth/login');
        }
    }
}
