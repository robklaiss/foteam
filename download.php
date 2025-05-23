<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Redirect to login if not logged in
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = 'orders.php';
    set_flash_message('Debe iniciar sesión para descargar fotos', 'warning');
    redirect('login.php');
}

// Get parameters
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$photo_id = isset($_GET['photo_id']) ? (int)$_GET['photo_id'] : 0;

if ($order_id <= 0 || $photo_id <= 0) {
    set_flash_message('Parámetros inválidos', 'danger');
    redirect('orders.php');
}

// Verify that the user owns this order and photo
$conn = db_connect();

$stmt = $conn->prepare("
    SELECT i.original_path, i.filename
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN images i ON oi.image_id = i.id
    WHERE o.id = :order_id AND i.id = :photo_id AND o.user_id = :user_id
");
$stmt->bindValue(':order_id', $order_id, SQLITE3_INTEGER);
$stmt->bindValue(':photo_id', $photo_id, SQLITE3_INTEGER);
$stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
$result = $stmt->execute();

$photo = $result->fetchArray(SQLITE3_ASSOC);
if (!$photo) {
    $result->finalize();
    $conn->close();
    set_flash_message('Foto no encontrada o no tiene permiso para descargarla', 'danger');
    redirect('orders.php');
}

$result->finalize();
$conn->close();

// Check if file exists
if (!file_exists($photo['original_path'])) {
    set_flash_message('El archivo de imagen no se encuentra en el servidor', 'danger');
    redirect('order_confirmation.php?id=' . $order_id);
}

// Set headers for download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $photo['filename'] . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($photo['original_path']));

// Clear output buffer
ob_clean();
flush();

// Read file and output
readfile($photo['original_path']);
exit;
?>
