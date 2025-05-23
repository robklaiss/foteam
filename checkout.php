<?php
// Start output buffering
ob_start();

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/bancard.php';

// Debug session and cart data
error_log('=== CHECKOUT.PHP DEBUG ===');
error_log('Session ID: ' . session_id());
error_log('Session data: ' . print_r($_SESSION, true));

// Debug the session cookie
if (isset($_COOKIE[session_name()])) {
    error_log('Session cookie exists: ' . $_COOKIE[session_name()]);
} else {
    error_log('No session cookie found');}

// Debug cart contents
if (isset($_SESSION['cart'])) {
    error_log('Cart contents: ' . print_r($_SESSION['cart'], true));
    error_log('Cart has ' . count($_SESSION['cart']) . ' items');
    
    // Debug each cart item
    foreach ($_SESSION['cart'] as $index => $item) {
        error_log(sprintf("Cart item %d: %s (ID: %s, Price: %s)", 
            $index, 
            $item['name'] ?? 'No name', 
            $item['id'] ?? 'No ID',
            $item['price'] ?? 'No price'
        ));
    }
} else {
    error_log('Cart is not set in session');
}

// Redirect if cart is empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    error_log('Checkout attempted with empty cart');
    error_log('Session ID at checkout: ' . session_id());
    error_log('Session data at checkout: ' . print_r($_SESSION, true));
    
    // Try to get cart from session file
    $session_file = session_save_path() . '/sess_' . session_id();
    if (file_exists($session_file)) {
        error_log('Session file contents: ' . file_get_contents($session_file));
    } else {
        error_log('Session file does not exist: ' . $session_file);
    }
    
    set_flash_message('Tu carrito está vacío', 'warning');
    redirect('cart.php');
    exit;
}

// Calculate totals
$subtotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += $item['price'];
}
$tax = $subtotal * TAX_RATE;
$total = $subtotal + $tax;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token
    check_csrf_token();
    
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
    
    if (empty($errors)) {
        // Create order
        $db = db_connect();
        
        // Log detailed cart and session info before starting transaction
        error_log('=== CHECKOUT PROCESS STARTED ===');
        error_log('Session ID: ' . session_id());
        error_log('User ID: ' . ($user_id ?? 'guest'));
        error_log('Cart contents: ' . print_r($_SESSION['cart'] ?? 'No cart', true));
        error_log('POST data: ' . print_r($_POST, true));
        
        // Start transaction
        if (!$db->exec('BEGIN TRANSACTION')) {
            throw new Exception('Failed to start transaction: ' . $db->lastErrorMsg());
        }
        
        error_log('Transaction started');
        
        try {
            // Create order
            $stmt = $db->prepare("
                INSERT INTO orders (
                    user_id, name, email, phone, address, 
                    payment_method, subtotal, tax, total_amount, status
                ) VALUES (
                    :user_id, :name, :email, :phone, :address, 
                    :payment_method, :subtotal, :tax, :total_amount, 'pending'
                )
            ");
            
            $user_id = is_logged_in() ? $_SESSION['user_id'] : null;
            
            $stmt->bindValue(':user_id', $user_id, $user_id ? SQLITE3_INTEGER : SQLITE3_NULL);
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
            $stmt->bindValue(':address', $address, SQLITE3_TEXT);
            $stmt->bindValue(':payment_method', $payment_method, SQLITE3_TEXT);
            $stmt->bindValue(':subtotal', $subtotal, SQLITE3_FLOAT);
            $stmt->bindValue(':tax', $tax, SQLITE3_FLOAT);
            $stmt->bindValue(':total_amount', $total, SQLITE3_FLOAT);
            
            $stmt->execute();
            $order_id = $db->lastInsertRowID();
            
            if (!$order_id) {
                throw new Exception('Failed to get order ID after insert');
            }
            
            error_log('Created order with ID: ' . $order_id);
            
            // Add order items
            foreach ($_SESSION['cart'] as $index => $item) {
                error_log(sprintf('Processing cart item %d: %s', $index, print_r($item, true)));
                
                // Check if image exists before trying to insert
                error_log(sprintf('Verifying image ID: %s', $item['id']));
                $check_stmt = $db->prepare("SELECT id, filename FROM images WHERE id = :image_id");
                $check_stmt->bindValue(':image_id', $item['id'], SQLITE3_INTEGER);
                $result = $check_stmt->execute();
                
                if ($result === false) {
                    $error = $db->lastErrorMsg();
                    error_log('Database error when checking image: ' . $error);
                    throw new Exception('Error al verificar la imagen: ' . $error);
                }
                
                $image = $result->fetchArray(SQLITE3_ASSOC);
                
                if (!$image) {
                    $error_msg = sprintf('Image with ID %s does not exist in the database', $item['id']);
                    error_log($error_msg);
                    error_log('Available images: ' . print_r($db->query('SELECT id, filename FROM images LIMIT 5')->fetchArray(SQLITE3_ASSOC), true));
                    throw new Exception('La imagen seleccionada ya no está disponible');
                }
                
                error_log(sprintf('Image verified: ID %s - %s', $image['id'], $image['filename']));
                
                $stmt = $db->prepare("
                    INSERT INTO order_items (order_id, image_id, price)
                    VALUES (:order_id, :image_id, :price)
                ");
                
                $stmt->bindValue(':order_id', $order_id, SQLITE3_INTEGER);
                $stmt->bindValue(':image_id', $item['id'], SQLITE3_INTEGER);
                $stmt->bindValue(':price', $item['price'], SQLITE3_FLOAT);
                
                error_log(sprintf('Inserting order item - Order ID: %d, Image ID: %d, Price: %f', 
                    $order_id, $item['id'], $item['price']));
                
                if (!$stmt->execute()) {
                    $error = $db->lastErrorMsg();
                    error_log('Error inserting order item: ' . $error);
                    throw new Exception('Error al guardar los ítems del pedido: ' . $error);
                }
            }
            
            // Commit transaction
            error_log('Attempting to commit transaction...');
            if (!$db->exec('COMMIT')) {
                $error = $db->lastErrorMsg();
                error_log('Commit failed: ' . $error);
                throw new Exception('Error al confirmar la transacción: ' . $error);
            }
            error_log('Transaction committed successfully');
            
            error_log('Transaction committed successfully');
            
            // Store order ID in session before clearing cart
            $_SESSION['last_order_id'] = $order_id;
            
            // Clear cart
            $cart_count = count($_SESSION['cart']);
            $_SESSION['cart'] = [];
            error_log(sprintf('Cleared cart with %d items', $cart_count));
            
            // Initialize Bancard payment if selected
            if ($payment_method === 'bancard') {
                try {
                    $payment_response = init_bancard_payment($order_id, $total);
                    
                    // Store payment info in session
                    $_SESSION['bancard_process_id'] = $payment_response['process_id'];
                    $_SESSION['order_id'] = $order_id;
                    
                    // Redirect to Bancard payment page
                    $redirect_url = $payment_response['process_url'];
                    error_log('Redirecting to: ' . $redirect_url);
                    redirect($redirect_url);
                } catch (Exception $e) {
                    set_flash_message('Error al iniciar el pago: ' . $e->getMessage(), 'danger');
                    redirect('checkout.php');
                }
            } else {
                // Redirect to success page
                $redirect_url = 'order_success.php?id=' . $order_id;
                error_log('Redirecting to: ' . $redirect_url);
                redirect($redirect_url);
            }
            
            // Log activity if logged in
            if ($user_id) {
                log_activity($user_id, 'create_order', "Created order #$order_id");
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $rollback_error = '';
            if (!$db->exec('ROLLBACK')) {
                $rollback_error = ' (Rollback failed: ' . $db->lastErrorMsg() . ')';
            }
            
            // Log the error with detailed information
            $error_message = 'Error processing order: ' . $e->getMessage() . $rollback_error . "\n";
            $error_message .= 'Order data: ' . print_r([
                'user_id' => is_logged_in() ? $_SESSION['user_id'] : 'guest',
                'cart_count' => count($_SESSION['cart'] ?? []),
                'cart_items' => $_SESSION['cart'] ?? [],
                'post_data' => $_POST,
            ], true);
            
            error_log($error_message);
            
            // Show generic error message to user
            set_flash_message('Error al procesar el pedido. Por favor, intente nuevamente o contacte al soporte.', 'danger');
        }
        
        $db->close();
    } else {
        // Display all errors
        foreach ($errors as $error) {
            set_flash_message($error, 'danger');
        }
    }
}

// Include header
include 'includes/header.php';
?>

<div class="container">
    <div class="jumbotron bg-light p-5 rounded">
        <h1 class="display-4">Finalizar Compra</h1>
        <p class="lead">Complete sus datos para procesar el pedido</p>
        <hr class="my-4">
        
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Datos de Facturación</h5>
                    </div>
                    <div class="card-body">
                        <form action="checkout.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Nombre Completo</label>
                                <input type="text" class="form-control" id="name" name="name" required value="<?php echo isset($_POST['name']) ? h($_POST['name']) : ''; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required value="<?php echo isset($_POST['email']) ? h($_POST['email']) : ''; ?>">
                                <div class="form-text">Recibirás un correo con los detalles de tu pedido y enlaces para descargar las fotos.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" id="phone" name="phone" required value="<?php echo isset($_POST['phone']) ? h($_POST['phone']) : ''; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Dirección (opcional)</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo isset($_POST['address']) ? h($_POST['address']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Método de Pago</label>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="payment_method" id="payment_method_bancard" value="bancard" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] === 'bancard') ? 'checked' : ''; ?> required>
                                    <label class="form-check-label" for="payment_method_bancard">
                                        Tarjeta de Crédito/Débito (Bancard)
                                        <img src="assets/img/bancard-logo.png" alt="Bancard" height="24" class="ms-2">
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success">Completar Pedido</button>
                                <a href="cart.php" class="btn btn-outline-secondary">Volver al Carrito</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Resumen del Pedido</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span>Subtotal:</span>
                            <span><?php echo format_money($subtotal); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Impuestos (<?php echo TAX_RATE * 100; ?>%):</span>
                            <span><?php echo format_money($tax); ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total:</strong>
                            <strong><?php echo format_money($total); ?></strong>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Fotos en tu Carrito</h5>
                    </div>
                    <ul class="list-group list-group-flush">
                        <?php
                        $db = db_connect();
                        foreach ($_SESSION['cart'] as $item):
                            $stmt = $db->prepare("
                                SELECT i.filename, i.thumbnail_path, m.name as marathon_name
                                FROM images i
                                JOIN marathons m ON i.marathon_id = m.id
                                WHERE i.id = :image_id
                            ");
                            $stmt->bindValue(':image_id', $item['id'], SQLITE3_INTEGER);
                            $result = $stmt->execute();
                            $image = $result->fetchArray(SQLITE3_ASSOC);
                            $result->finalize();
                            
                            if ($image):
                                $thumbnail_url = str_replace($_SERVER['DOCUMENT_ROOT'], '', $image['thumbnail_path']);
                        ?>
                            <li class="list-group-item">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <img src="<?php echo $thumbnail_url; ?>" alt="Thumbnail" class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="mb-0"><?php echo h($image['filename']); ?></p>
                                        <small class="text-muted"><?php echo h($image['marathon_name']); ?></small>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <span><?php echo format_money($item['price']); ?></span>
                                    </div>
                                </div>
                            </li>
                        <?php
                            endif;
                        endforeach;
                        $db->close();
                        ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
