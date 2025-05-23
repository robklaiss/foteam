<?php
// Define the root directory path for includes
$root_path = dirname(__DIR__);

// 1. Include bootstrap first to configure environment and settings
if (!defined('BOOTSTRAP_LOADED')) {
    require_once $root_path . '/includes/bootstrap.php';
}

// 2. Include config
if (!defined('CONFIG_LOADED')) {
    require_once $root_path . '/includes/config.php';
}

// 3. Include functions before global_session since it might need them
require_once $root_path . '/includes/functions.php';

// 4. Include global session manager
require_once $root_path . '/includes/global_session.php';

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Get cart count for navbar
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart_count = count($_SESSION['cart']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoTeam - Fotografía de Maratones</title>
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/'); ?>/public/assets/css/style.css">
    
    <!-- Ensure jQuery is loaded before Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom fixed-top">
        <div class="container">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>">
                <img src="<?php echo BASE_URL; ?>/public/assets/img/logo.png" alt="FoTeam" height="40">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <!-- Public Site -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo rtrim(BASE_URL, '/'); ?>/index.php">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo rtrim(BASE_URL, '/'); ?>/gallery.php">Galería</a>
                    </li>
                    
                    <!-- Photographer Section -->
                    <?php if (is_logged_in()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="photographerDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Fotógrafo
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="photographerDropdown">
                            <li><a class="dropdown-item" href="<?php echo rtrim(BASE_URL, '/'); ?>/upload.php">Subir Fotos</a></li>
                            <li><a class="dropdown-item" href="<?php echo rtrim(BASE_URL, '/'); ?>/manage_marathons.php">Mis Maratones</a></li>
                            <li><a class="dropdown-item" href="<?php echo rtrim(BASE_URL, '/'); ?>/fotografos/">Área de Fotógrafos</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <!-- Admin Section -->
                    <?php if (is_admin()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Administración
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                            <li><a class="dropdown-item" href="<?php echo rtrim(BASE_URL, '/'); ?>/admin/">Panel de Control</a></li>
                            <li><a class="dropdown-item" href="<?php echo rtrim(BASE_URL, '/'); ?>/admin/users.php">Usuarios</a></li>
                            <li><a class="dropdown-item" href="<?php echo rtrim(BASE_URL, '/'); ?>/admin/reports.php">Reportes</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if (is_logged_in()): ?>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="<?php echo rtrim(BASE_URL, '/'); ?>/cart.php">
                            <i class="bi bi-cart"></i> Carrito
                            <?php if ($cart_count > 0): ?>
                            <span class="cart-badge"><?php echo $cart_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/profile.php">
                            <i class="bi bi-person-circle"></i> <?php echo h($_SESSION['username']); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Salir
                        </a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/login.php">
                            <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/register.php">
                            <i class="bi bi-person-plus"></i> Registrarse
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    <?php $flash = get_flash_message(); ?>
    <?php if ($flash): ?>
    <div class="container mt-4">
        <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $flash['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="container mt-4">
