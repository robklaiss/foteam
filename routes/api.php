<?php

use App\Controllers\Api\AuthApiController;
use App\Controllers\Api\GalleryApiController;
use App\Controllers\Api\CartApiController;

// API Routes (prefixed with /api)
$app->group('/api', function($router) {
    // Authentication
    $router->post('/login', [AuthApiController::class, 'login']);
    $router->post('/register', [AuthApiController::class, 'register']);
    $router->post('/logout', [AuthApiController::class, 'logout'])->middleware(['auth:api']);
    
    // Gallery
    $router->get('/marathons', [GalleryApiController::class, 'getMarathons']);
    $router->get('/marathons/{id}', [GalleryApiController::class, 'getMarathon']);
    $router->get('/photos', [GalleryApiController::class, 'getPhotos']);
    $router->get('/photos/{id}', [GalleryApiController::class, 'getPhoto']);
    
    // Cart
    $router->group('/cart', function($router) {
        $router->get('/', [CartApiController::class, 'getCart'])->middleware(['auth:api']);
        $router->post('/add', [CartApiController::class, 'addToCart'])->middleware(['auth:api']);
        $router->put('/update/{id}', [CartApiController::class, 'updateCartItem'])->middleware(['auth:api']);
        $router->delete('/remove/{id}', [CartApiController::class, 'removeFromCart'])->middleware(['auth:api']);
    });
    
    // User
    $router->get('/user', [AuthApiController::class, 'getUser'])->middleware(['auth:api']);
});
