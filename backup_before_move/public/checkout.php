<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/bancard.php';

// Redirect if cart is empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    set_flash_message('Tu carrito está vacío', 'warning');
    redirect('cart.php');
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
        
        // Start transaction
        $db->exec('BEGIN TRANSACTION');
        
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
            
            // Commit transaction
            $db->exec('COMMIT');
            
            // Initialize Bancard payment if selected
            if ($payment_method === 'bancard') {
                try {
                    $payment_response = init_bancard_payment($order_id, $total);
                    
                    // Store payment info in session
                    $_SESSION['bancard_process_id'] = $payment_response['process_id'];
                    $_SESSION['order_id'] = $order_id;
                    
                    // Redirect to Bancard payment page
                    redirect($payment_response['process_url']);
                } catch (Exception $e) {
                    set_flash_message('Error al iniciar el pago: ' . $e->getMessage(), 'danger');
                    redirect('checkout.php');
                }
            }
            
            // Clear cart
            $_SESSION['cart'] = [];
            
            // Log activity if logged in
            if ($user_id) {
                log_activity($user_id, 'create_order', "Created order #$order_id");
            }
            
            // Redirect to order confirmation
            redirect('order_confirmation.php?id=' . $order_id);
        } catch (Exception $e) {
            // Rollback transaction
            $db->exec('ROLLBACK');
            set_flash_message('Error al procesar el pedido: ' . $e->getMessage(), 'danger');
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
include '../includes/header.php';
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

<?php include '../includes/footer.php'; ?>
