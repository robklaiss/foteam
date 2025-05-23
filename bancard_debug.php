<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/bancard.php';

// Check bancard environment
$debug = [
    'bancard_env_variables' => [
        'BANCARD_PUBLIC_KEY' => substr(getenv('BANCARD_PUBLIC_KEY') ?: 'not-set', 0, 4) . '...',
        'BANCARD_PRIVATE_KEY' => substr(getenv('BANCARD_PRIVATE_KEY') ?: 'not-set', 0, 4) . '...',
        'BANCARD_ENVIRONMENT' => getenv('BANCARD_ENVIRONMENT') ?: 'not-set',
        'BANCARD_RETURN_URL' => getenv('BANCARD_RETURN_URL') ?: 'not-set',
        'BANCARD_CANCEL_URL' => getenv('BANCARD_CANCEL_URL') ?: 'not-set',
    ],
    'server_info' => [
        'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
        'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'php_version' => phpversion(),
    ],
    'file_checks' => [
        'bancard_logo' => file_exists('assets/img/bancard-logo.png') ? 'found' : 'missing',
        'bancard_php' => file_exists('includes/bancard.php') ? 'found' : 'missing',
    ]
];

// Output debug info in a clean format
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bancard Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container my-5">
        <h1>Bancard Integration Debug</h1>
        <div class="card mb-4">
            <div class="card-header">Environment Variables</div>
            <div class="card-body">
                <pre><?php echo json_encode($debug['bancard_env_variables'], JSON_PRETTY_PRINT); ?></pre>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">Server Information</div>
            <div class="card-body">
                <pre><?php echo json_encode($debug['server_info'], JSON_PRETTY_PRINT); ?></pre>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">File Checks</div>
            <div class="card-body">
                <pre><?php echo json_encode($debug['file_checks'], JSON_PRETTY_PRINT); ?></pre>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">Checkout Page Test</div>
            <div class="card-body">
                <p>This is what the payment method section should look like:</p>
                <div class="mb-4">
                    <label class="form-label">Método de Pago</label>
                    
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="payment_method" id="payment_method_bancard" value="bancard" checked required>
                        <label class="form-check-label" for="payment_method_bancard">
                            Tarjeta de Crédito/Débito (Bancard)
                            <img src="assets/img/bancard-logo.png" alt="Bancard" height="24" class="ms-2">
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
