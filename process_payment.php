<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/bancard.php';

// Verify CSRF token
check_csrf_token();

// Get cart data
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    set_flash_message('Tu carrito está vacío', 'warning');
    redirect('cart.php');
}

// Calculate total
$total = 0;
foreach ($_SESSION['cart'] as $item) {
    $total += $item['price'];
}

// Get form data
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$address = $_POST['address'] ?? '';
$payment_method = $_POST['payment_method'] ?? '';

// Validate input
$errors = [];

if (empty($name)) {
    $errors[] = 'El nombre es obligatorio';
}

if (empty($email)) {
    $errors[] = 'El email es obligatorio';
} else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'El email no es válido';
}

if (empty($phone)) {
    $errors[] = 'El teléfono es obligatorio';
}

if (empty($payment_method)) {
    $errors[] = 'Debe seleccionar un método de pago';
}

if (!empty($errors)) {
    foreach ($errors as $error) {
        set_flash_message($error, 'danger');
    }
    redirect('checkout.php');
}

try {
    $db = db_connect();
    $db->exec('BEGIN TRANSACTION');
    
    // Create pending order
    $stmt = $db->prepare("
        INSERT INTO orders (
            user_id, name, email, phone, address, 
            payment_method, total_amount, status
        ) VALUES (
            :user_id, :name, :email, :phone, :address, 
            :payment_method, :total_amount, 'pending'
        )
    ");
    
    $user_id = is_logged_in() ? $_SESSION['user_id'] : null;
    
    $stmt->bindValue(':user_id', $user_id, $user_id ? SQLITE3_INTEGER : SQLITE3_NULL);
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
    $stmt->bindValue(':address', $address, SQLITE3_TEXT);
    $stmt->bindValue(':payment_method', $payment_method, SQLITE3_TEXT);
    $stmt->bindValue(':total_amount', $total, SQLITE3_FLOAT);
    
    $stmt->execute();
    $order_id = $db->lastInsertRowID();
    
    // Add order items
    foreach ($_SESSION['cart'] as $item) {
        $stmt = $db->prepare("
            INSERT INTO order_items (order_id, image_id, price)
            VALUES (:order_id, :image_id, :price)
        ");
        
        $stmt->bindValue(':order_id', $order_id, SQLITE3_INTEGER);
        $stmt->bindValue(':image_id', $item['id'], SQLITE3_INTEGER);
        $stmt->bindValue(':price', $item['price'], SQLITE3_FLOAT);
        
        $stmt->execute();
    }
    
    $db->exec('COMMIT');
    
    // Initialize Bancard payment
    $process_id = init_bancard_payment($order_id, $total);
    
    // Store process_id in session for verification
    $_SESSION['bancard_process_id'] = $process_id;
    $_SESSION['order_id'] = $order_id;
    
    // Redirect to Bancard payment page
    $payment_url = getenv('BANCARD_ENVIRONMENT') === 'production'
        ? 'https://vpos.infonet.com.py'
        : 'https://vpos.infonet.com.py:8888';
    
    redirect($payment_url . '/payment/single_buy?process_id=' . $process_id);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->exec('ROLLBACK');
    }
    set_flash_message('Error al procesar el pago: ' . $e->getMessage(), 'danger');
    redirect('checkout.php');
}
?>
