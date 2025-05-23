<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\Marathon;
use App\Models\Photo;
use App\Models\User;

class GalleryApiController extends BaseController {
    public function getMarathons() {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 12;
        $search = trim($_GET['search'] ?? '');
        $category = trim($_GET['category'] ?? '');
        $sort = trim($_GET['sort'] ?? 'newest');
        $userId = $_SESSION['user_id'] ?? null;
        
        // Build query conditions
        $conditions = [];
        $params = [];
        
        // Only show public marathons or those the user has access to
        if ($userId) {
            $conditions[] = "(is_public = 1 OR user_id = :user_id)";
            $params[':user_id'] = $userId;
        } else {
            $conditions[] = "is_public = 1";
        }
        
        // Add search filter
        if (!empty($search)) {
            $conditions[] = "(name LIKE :search OR description LIKE :search)";
            $params[':search'] = "%$search%";
        }
        
        // Add category filter
        if (!empty($category)) {
            $conditions[] = "category = :category";
            $params[':category'] = $category;
        }
        
        // Build WHERE clause
        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        // Build ORDER BY clause
        $orderBy = 'ORDER BY ';
        switch ($sort) {
            case 'oldest':
                $orderBy .= 'date ASC';
                break;
            case 'name_asc':
                $orderBy .= 'name ASC';
                break;
            case 'name_desc':
                $orderBy .= 'name DESC';
                break;
            case 'newest':
            default:
                $orderBy .= 'date DESC';
                break;
        }
        
        // Get paginated marathons
        $offset = ($page - 1) * $perPage;
        $marathons = Marathon::query("SELECT * FROM marathons $where $orderBy LIMIT $perPage OFFSET $offset", $params);
        
        // Get total count for pagination
        $total = Marathon::count($where, $params);
        
        // Format response
        $formattedMarathons = [];
        foreach ($marathons as $marathon) {
            $formattedMarathons[] = [
                'id' => $marathon['id'],
                'name' => $marathon['name'],
                'description' => $marathon['description'],
                'date' => $marathon['date'],
                'location' => $marathon['location'],
                'cover_image' => $marathon['cover_image'],
                'photo_count' => Photo::countByMarathon($marathon['id']),
                'is_public' => (bool)$marathon['is_public'],
                'created_at' => $marathon['created_at']
            ];
        }
        
        return $this->json([
            'success' => true,
            'data' => $formattedMarathons,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage)
            ]
        ]);
    }
    
    public function getMarathon($marathonId) {
        $marathon = Marathon::find($marathonId);
        
        if (!$marathon) {
            return $this->json(['success' => false, 'message' => 'Maratón no encontrado'], 404);
        }
        
        // Check if user has access to this marathon
        $userId = $_SESSION['user_id'] ?? null;
        if (!$marathon['is_public'] && (!$userId || $marathon['user_id'] != $userId)) {
            return $this->json(['success' => false, 'message' => 'No tienes permiso para ver este maratón'], 403);
        }
        
        // Get marathon photos
        $photos = Photo::getByMarathon($marathonId);
        
        // Format response
        $formattedPhotos = [];
        foreach ($photos as $photo) {
            $formattedPhotos[] = [
                'id' => $photo['id'],
                'filename' => $photo['filename'],
                'title' => $photo['title'],
                'description' => $photo['description'],
                'price' => (float)$photo['price'],
                'is_featured' => (bool)$photo['is_featured'],
                'created_at' => $photo['created_at'],
                'urls' => [
                    'thumbnail' => '/uploads/thumbnails/' . $photo['filename'],
                    'medium' => '/uploads/medium/' . $photo['filename'],
                    'original' => '/uploads/original/' . $photo['filename']
                ]
            ];
        }
        
        // Get photographer info
        $photographer = User::find($marathon['user_id']);
        
        return $this->json([
            'success' => true,
            'data' => [
                'id' => $marathon['id'],
                'name' => $marathon['name'],
                'description' => $marathon['description'],
                'date' => $marathon['date'],
                'location' => $marathon['location'],
                'cover_image' => $marathon['cover_image'],
                'is_public' => (bool)$marathon['is_public'],
                'created_at' => $marathon['created_at'],
                'photographer' => [
                    'id' => $photographer['id'],
                    'name' => $photographer['name'],
                    'avatar' => $photographer['avatar'] ?? '/img/default-avatar.png'
                ],
                'photos' => $formattedPhotos,
                'photo_count' => count($formattedPhotos)
            ]
        ]);
    }
    
    public function getPhotos($marathonId = null) {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 24;
        $search = trim($_GET['search'] ?? '');
        $sort = trim($_GET['sort'] ?? 'newest');
        $userId = $_SESSION['user_id'] ?? null;
        
        // Build query conditions
        $conditions = [];
        $params = [];
        
        // Filter by marathon if specified
        if ($marathonId) {
            $marathon = Marathon::find($marathonId);
            
            if (!$marathon) {
                return $this->json(['success' => false, 'message' => 'Maratón no encontrado'], 404);
            }
            
            // Check if user has access to this marathon
            if (!$marathon['is_public'] && (!$userId || $marathon['user_id'] != $userId)) {
                return $this->json(['success' => false, 'message' => 'No tienes permiso para ver estas fotos'], 403);
            }
            
            $conditions[] = "marathon_id = :marathon_id";
            $params[':marathon_id'] = $marathonId;
        } else {
            // If no marathon specified, only show photos from public marathons
            $publicMarathons = Marathon::getPublicMarathonIds();
            
            if (empty($publicMarathons)) {
                return $this->json([
                    'success' => true,
                    'data' => [],
                    'pagination' => [
                        'total' => 0,
                        'per_page' => $perPage,
                        'current_page' => $page,
                        'last_page' => 0
                    ]
                ]);
            }
            
            $conditions[] = "marathon_id IN (" . implode(',', array_fill(0, count($publicMarathons), '?')) . ")";
            $params = array_merge($publicMarathons);
        }
        
        // Add search filter
        if (!empty($search)) {
            $conditions[] = "(title LIKE ? OR description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        // Build WHERE clause
        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        // Build ORDER BY clause
        $orderBy = 'ORDER BY ';
        switch ($sort) {
            case 'oldest':
                $orderBy .= 'created_at ASC';
                break;
            case 'price_asc':
                $orderBy .= 'price ASC';
                break;
            case 'price_desc':
                $orderBy .= 'price DESC';
                break;
            case 'featured':
                $orderBy .= 'is_featured DESC, created_at DESC';
                break;
            case 'newest':
            default:
                $orderBy .= 'created_at DESC';
                break;
        }
        
        // Get paginated photos
        $offset = ($page - 1) * $perPage;
        $photos = Photo::query("SELECT * FROM photos $where $orderBy LIMIT $perPage OFFSET $offset", $params);
        
        // Get total count for pagination
        $total = Photo::count($where, $params);
        
        // Format response
        $formattedPhotos = [];
        foreach ($photos as $photo) {
            $formattedPhotos[] = [
                'id' => $photo['id'],
                'title' => $photo['title'],
                'description' => $photo['description'],
                'price' => (float)$photo['price'],
                'is_featured' => (bool)$photo['is_featured'],
                'created_at' => $photo['created_at'],
                'urls' => [
                    'thumbnail' => '/uploads/thumbnails/' . $photo['filename'],
                    'medium' => '/uploads/medium/' . $photo['filename'],
                    'original' => '/uploads/original/' . $photo['filename']
                ],
                'marathon' => [
                    'id' => $photo['marathon_id'],
                    'name' => Marathon::find($photo['marathon_id'])['name'] ?? 'Desconocido'
                ]
            ];
        }
        
        return $this->json([
            'success' => true,
            'data' => $formattedPhotos,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage)
            ]
        ]);
    }
    
    public function getPhoto($photoId) {
        $photo = Photo::find($photoId);
        
        if (!$photo) {
            return $this->json(['success' => false, 'message' => 'Foto no encontrada'], 404);
        }
        
        // Get marathon info
        $marathon = Marathon::find($photo['marathon_id']);
        
        if (!$marathon) {
            return $this->json(['success' => false, 'message' => 'Maratón no encontrado'], 404);
        }
        
        // Check if user has access to this photo
        $userId = $_SESSION['user_id'] ?? null;
        if (!$marathon['is_public'] && (!$userId || $marathon['user_id'] != $userId)) {
            return $this->json(['success' => false, 'message' => 'No tienes permiso para ver esta foto'], 403);
        }
        
        // Get photographer info
        $photographer = User::find($marathon['user_id']);
        
        // Get related photos (from the same marathon)
        $relatedPhotos = Photo::getByMarathon($marathon['id'], 4, $photoId);
        $formattedRelatedPhotos = [];
        
        foreach ($relatedPhotos as $relatedPhoto) {
            $formattedRelatedPhotos[] = [
                'id' => $relatedPhoto['id'],
                'title' => $relatedPhoto['title'],
                'thumbnail_url' => '/uploads/thumbnails/' . $relatedPhoto['filename'],
                'url' => "/foto/" . $relatedPhoto['id']
            ];
        }
        
        // Format response
        return $this->json([
            'success' => true,
            'data' => [
                'id' => $photo['id'],
                'title' => $photo['title'],
                'description' => $photo['description'],
                'price' => (float)$photo['price'],
                'is_featured' => (bool)$photo['is_featured'],
                'created_at' => $photo['created_at'],
                'urls' => [
                    'thumbnail' => '/uploads/thumbnails/' . $photo['filename'],
                    'medium' => '/uploads/medium/' . $photo['filename'],
                    'original' => '/uploads/original/' . $photo['filename']
                ],
                'marathon' => [
                    'id' => $marathon['id'],
                    'name' => $marathon['name'],
                    'date' => $marathon['date'],
                    'location' => $marathon['location'],
                    'url' => "/maraton/" . $marathon['id']
                ],
                'photographer' => [
                    'id' => $photographer['id'],
                    'name' => $photographer['name'],
                    'avatar' => $photographer['avatar'] ?? '/img/default-avatar.png',
                    'bio' => $photographer['bio'] ?? '',
                    'website' => $photographer['website'] ?? ''
                ],
                'related_photos' => $formattedRelatedPhotos
            ]
        ]);
    }
    
    public function addToCart($photoId) {
        $this->requireLogin();
        
        $photo = Photo::find($photoId);
        
        if (!$photo) {
            return $this->json(['success' => false, 'message' => 'Foto no encontrada'], 404);
        }
        
        // Check if photo is already in cart
        $cartItem = $this->getCartItem($photoId);
        
        if ($cartItem) {
            return $this->json([
                'success' => false, 
                'message' => 'Esta foto ya está en tu carrito',
                'cart_item' => $cartItem
            ], 400);
        }
        
        // Add to cart
        $cartId = $this->addToUserCart($photoId, $photo['price']);
        
        if ($cartId) {
            return $this->json([
                'success' => true,
                'message' => 'Foto agregada al carrito',
                'cart_item' => [
                    'id' => $cartId,
                    'photo_id' => $photo['id'],
                    'title' => $photo['title'],
                    'price' => (float)$photo['price'],
                    'thumbnail_url' => '/uploads/thumbnails/' . $photo['filename']
                ],
                'cart_count' => $this->getCartCount()
            ]);
        } else {
            return $this->json(['success' => false, 'message' => 'Error al agregar al carrito'], 500);
        }
    }
    
    public function removeFromCart($cartItemId) {
        $this->requireLogin();
        
        $success = $this->removeFromUserCart($cartItemId);
        
        if ($success) {
            return $this->json([
                'success' => true,
                'message' => 'Foto eliminada del carrito',
                'cart_count' => $this->getCartCount()
            ]);
        } else {
            return $this->json(['success' => false, 'message' => 'Error al eliminar del carrito'], 500);
        }
    }
    
    public function getCart() {
        $this->requireLogin();
        
        $cartItems = $this->getUserCart();
        $total = 0;
        
        // Calculate total and format items
        $formattedItems = [];
        foreach ($cartItems as $item) {
            $formattedItems[] = [
                'id' => $item['id'],
                'photo_id' => $item['photo_id'],
                'title' => $item['title'],
                'price' => (float)$item['price'],
                'thumbnail_url' => $item['thumbnail_url'],
                'url' => "/foto/" . $item['photo_id']
            ];
            $total += $item['price'];
        }
        
        return $this->json([
            'success' => true,
            'data' => [
                'items' => $formattedItems,
                'total' => $total,
                'item_count' => count($formattedItems)
            ]
        ]);
    }
    
    public function updateCartItem($cartItemId) {
        $this->requireLogin();
        
        if (!$this->isPost()) {
            return $this->json(['success' => false, 'message' => 'Método no permitido'], 405);
        }
        
        $quantity = (int)($_POST['quantity'] ?? 1);
        
        if ($quantity < 1) {
            return $this->json(['success' => false, 'message' => 'La cantidad debe ser al menos 1'], 400);
        }
        
        $success = $this->updateCartItemQuantity($cartItemId, $quantity);
        
        if ($success) {
            $cart = $this->getUserCart();
            $total = array_sum(array_column($cart, 'price'));
            
            return $this->json([
                'success' => true,
                'message' => 'Carrito actualizado',
                'cart' => [
                    'total' => $total,
                    'item_count' => count($cart)
                ]
            ]);
        } else {
            return $this->json(['success' => false, 'message' => 'Error al actualizar el carrito'], 500);
        }
    }
    
    public function clearCart() {
        $this->requireLogin();
        
        $success = $this->clearUserCart();
        
        if ($success) {
            return $this->json([
                'success' => true,
                'message' => 'Carrito vaciado',
                'cart' => [
                    'total' => 0,
                    'item_count' => 0
                ]
            ]);
        } else {
            return $this->json(['success' => false, 'message' => 'Error al vaciar el carrito'], 500);
        }
    }
    
    public function getCartCount() {
        $this->requireLogin();
        
        $count = $this->getUserCartCount();
        
        return $this->json([
            'success' => true,
            'count' => $count
        ]);
    }
    
    // Helper methods for cart operations
    private function getCartItem($photoId) {
        // Implementation depends on your cart storage (session, database, etc.)
        // This is a placeholder implementation
        $cart = $_SESSION['cart'] ?? [];
        return $cart[$photoId] ?? null;
    }
    
    private function addToUserCart($photoId, $price) {
        // Implementation depends on your cart storage
        // This is a placeholder implementation
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        $cartId = uniqid('cart_', true);
        
        $_SESSION['cart'][$cartId] = [
            'id' => $cartId,
            'photo_id' => $photoId,
            'price' => $price,
            'added_at' => time()
        ];
        
        return $cartId;
    }
    
    private function removeFromUserCart($cartItemId) {
        // Implementation depends on your cart storage
        // This is a placeholder implementation
        if (isset($_SESSION['cart'][$cartItemId])) {
            unset($_SESSION['cart'][$cartItemId]);
            return true;
        }
        return false;
    }
    
    private function updateCartItemQuantity($cartItemId, $quantity) {
        // Implementation depends on your cart storage
        // This is a placeholder implementation
        if (isset($_SESSION['cart'][$cartItemId])) {
            $_SESSION['cart'][$cartItemId]['quantity'] = $quantity;
            return true;
        }
        return false;
    }
    
    private function clearUserCart() {
        // Implementation depends on your cart storage
        // This is a placeholder implementation
        $_SESSION['cart'] = [];
        return true;
    }
    
    private function getUserCart() {
        // Implementation depends on your cart storage
        // This is a placeholder implementation
        $cart = $_SESSION['cart'] ?? [];
        
        // Add photo details to cart items
        $result = [];
        foreach ($cart as $item) {
            $photo = Photo::find($item['photo_id']);
            if ($photo) {
                $result[] = [
                    'id' => $item['id'],
                    'photo_id' => $photo['id'],
                    'title' => $photo['title'],
                    'price' => (float)$photo['price'],
                    'thumbnail_url' => '/uploads/thumbnails/' . $photo['filename']
                ];
            }
        }
        
        return $result;
    }
    
    private function getUserCartCount() {
        // Implementation depends on your cart storage
        // This is a placeholder implementation
        return count($_SESSION['cart'] ?? []);
    }
}
