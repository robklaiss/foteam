<?php

use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\GalleryController;
use App\Controllers\PhotographerController;
use App\Controllers\AdminController;

// Home routes
$app->get('/', [HomeController::class, 'index']);

// Auth routes
$app->group('/auth', function($router) {
    $router->get('/login', [AuthController::class, 'showLoginForm']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->get('/register', [AuthController::class, 'showRegistrationForm']);
    $router->post('/register', [AuthController::class, 'register']);
    $router->post('/logout', [AuthController::class, 'logout']);
});

// Gallery routes
$app->get('/gallery', [GalleryController::class, 'index']);
$app->get('/gallery/marathon/{id}', [GalleryController::class, 'showMarathon']);
$app->get('/gallery/photo/{id}', [GalleryController::class, 'showPhoto']);

// Cart routes
$app->group('/cart', function($router) {
    $router->get('/', [CartController::class, 'index']);
    $router->post('/add', [CartController::class, 'add']);
    $router->post('/remove/{id}', [CartController::class, 'remove']);
    $router->post('/update', [CartController::class, 'update']);
});

// Checkout routes
$app->group('/checkout', function($router) {
    $router->get('/', [CheckoutController::class, 'index']);
    $router->post('/process', [CheckoutController::class, 'process']);
    $router->get('/success', [CheckoutController::class, 'success']);
    $router->get('/cancel', [CheckoutController::class, 'cancel']);
});

// Photographer routes (requires authentication)
$app->group('/photographer', function($router) {
    $router->get('/dashboard', [PhotographerController::class, 'dashboard']);
    $router->get('/marathons', [PhotographerController::class, 'marathons']);
    $router->get('/marathons/create', [PhotographerController::class, 'createMarathon']);
    $router->post('/marathons', [PhotographerController::class, 'storeMarathon']);
    $router->get('/marathons/{id}/edit', [PhotographerController::class, 'editMarathon']);
    $router->put('/marathons/{id}', [PhotographerController::class, 'updateMarathon']);
    $router->delete('/marathons/{id}', [PhotographerController::class, 'deleteMarathon']);
    $router->get('/upload', [PhotographerController::class, 'showUploadForm']);
    $router->post('/upload', [PhotographerController::class, 'upload']);
})->middleware(['auth']);

// Admin routes (requires admin role)
$app->group('/admin', function($router) {
    $router->get('/', [AdminController::class, 'dashboard']);
    $router->get('/users', [AdminController::class, 'users']);
    $router->get('/reports', [AdminController::class, 'reports']);
})->middleware(['auth', 'admin']);

// User account routes
$app->group('/account', function($router) {
    $router->get('/', [AccountController::class, 'index']);
    $router->get('/orders', [AccountController::class, 'orders']);
    $router->get('/orders/{id}', [AccountController::class, 'orderDetails']);
    $router->get('/settings', [AccountController::class, 'settings']);
    $router->post('/settings', [AccountController::class, 'updateSettings']);
})->middleware(['auth']);
