<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/session_debug.php';

// Log session information
$debug_info = log_session_debug('cart_diagnostics.php', 'diagnostics_run');

// Output HTML header
echo '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Carrito</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
    <div class="container">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h2 class="mb-0">Diagnóstico de Sesión y Carrito</h2>
            </div>
            <div class="card-body">';

// Display session information
echo '<h4>Información de Sesión</h4>';
echo '<table class="table table-bordered">
    <tr>
        <th>Session ID</th>
        <td>' . session_id() . '</td>
    </tr>
    <tr>
        <th>Cookie de Sesión</th>
        <td>' . (isset($_COOKIE[session_name()]) ? $_COOKIE[session_name()] : 'no disponible') . '</td>
    </tr>
    <tr>
        <th>Nombre de Sesión</th>
        <td>' . session_name() . '</td>
    </tr>
    <tr>
        <th>Estado de Sesión</th>
        <td>' . session_status() . ' (1=DISABLED, 2=NONE, 3=ACTIVE)</td>
    </tr>
</table>';

// Display cart information
echo '<h4 class="mt-4">Información del Carrito</h4>';
if (isset($_SESSION['cart'])) {
    if (count($_SESSION['cart']) > 0) {
        echo '<div class="alert alert-success">El carrito contiene ' . count($_SESSION['cart']) . ' artículo(s).</div>';
        echo '<table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Precio</th>
                    <th>Detalles</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($_SESSION['cart'] as $index => $item) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($item['id']) . '</td>';
            echo '<td>' . format_money($item['price']) . '</td>';
            echo '<td><pre>' . htmlspecialchars(json_encode($item, JSON_PRETTY_PRINT)) . '</pre></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    } else {
        echo '<div class="alert alert-warning">El carrito existe pero está vacío.</div>';
    }
} else {
    echo '<div class="alert alert-danger">No hay carrito en la sesión.</div>';
}

// Test cart functionality
echo '<h4 class="mt-4">Acciones de Prueba</h4>
<div class="row">';

// Add test item action
echo '<div class="col-md-4 mb-3">
    <a href="cart_diagnostics.php?action=add_test" class="btn btn-primary btn-block">Añadir Artículo de Prueba</a>
</div>';

// Clear cart action
echo '<div class="col-md-4 mb-3">
    <a href="cart_diagnostics.php?action=clear" class="btn btn-danger btn-block">Vaciar Carrito</a>
</div>';

// Refresh session action
echo '<div class="col-md-4 mb-3">
    <a href="cart_diagnostics.php?action=refresh_session" class="btn btn-info btn-block">Actualizar Sesión</a>
</div>';

echo '</div>';

// Navigation
echo '<div class="mt-4">
    <a href="cart.php" class="btn btn-outline-primary me-2">Ver Carrito</a>
    <a href="checkout.php" class="btn btn-success me-2">Ir a Checkout</a>
</div>';

// Process actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'add_test') {
        // Add a test item to the cart
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        $_SESSION['cart'][] = [
            'id' => 999,
            'price' => PHOTO_PRICE,
            'debug' => 'test_item_' . time()
        ];
        
        echo '<div class="alert alert-success mt-4">Artículo de prueba añadido al carrito.</div>';
        log_session_debug('cart_diagnostics.php', 'test_item_added');
        
        // Force session write
        session_write_close();
        session_start();
    } else if ($action === 'clear') {
        // Clear the cart
        $_SESSION['cart'] = [];
        echo '<div class="alert alert-warning mt-4">Carrito vaciado.</div>';
        log_session_debug('cart_diagnostics.php', 'cart_cleared');
    } else if ($action === 'refresh_session') {
        // Force session refresh
        session_write_close();
        session_start();
        echo '<div class="alert alert-info mt-4">Sesión actualizada.</div>';
        log_session_debug('cart_diagnostics.php', 'session_refreshed');
    }
}

// Session variables overview
echo '<h4 class="mt-4">Variables de Sesión</h4>';
echo '<div class="card"><div class="card-body"><pre>';
print_r($_SESSION);
echo '</pre></div></div>';

// Cookie information
echo '<h4 class="mt-4">Cookies</h4>';
echo '<div class="card"><div class="card-body"><pre>';
print_r($_COOKIE);
echo '</pre></div></div>';

// Configuration information
echo '<h4 class="mt-4">Configuración PHP</h4>';
echo '<table class="table table-bordered">
    <tr>
        <th>PHP Version</th>
        <td>' . phpversion() . '</td>
    </tr>
    <tr>
        <th>session.save_path</th>
        <td>' . ini_get('session.save_path') . '</td>
    </tr>
    <tr>
        <th>session.cookie_path</th>
        <td>' . ini_get('session.cookie_path') . '</td>
    </tr>
    <tr>
        <th>session.cookie_domain</th>
        <td>' . ini_get('session.cookie_domain') . '</td>
    </tr>
    <tr>
        <th>session.cookie_secure</th>
        <td>' . ini_get('session.cookie_secure') . '</td>
    </tr>
    <tr>
        <th>session.cookie_httponly</th>
        <td>' . ini_get('session.cookie_httponly') . '</td>
    </tr>
    <tr>
        <th>session.use_cookies</th>
        <td>' . ini_get('session.use_cookies') . '</td>
    </tr>
    <tr>
        <th>session.use_only_cookies</th>
        <td>' . ini_get('session.use_only_cookies') . '</td>
    </tr>
</table>';

// Test link to checkout
echo '<div class="mt-4">
    <h4>Enlaces de Prueba</h4>
    <a href="checkout.php" class="btn btn-lg btn-success mb-3 d-block">Probar Checkout</a>
    <a href="cart.php" class="btn btn-lg btn-outline-secondary d-block">Volver al Carrito</a>
</div>';

// End HTML
echo '</div>
        </div>
    </div>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
?>
