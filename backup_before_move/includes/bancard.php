<?php
require_once 'config.php';
require_once 'app_config.php';
require_once 'bancard_config.php';

function generate_bancard_token($shop_process_id, $amount) {
    $private_key = getenv('BANCARD_PRIVATE_KEY');
    $token_raw = $private_key . $shop_process_id . $amount . CURRENCY_CODE;
    return md5($token_raw);
}

function init_bancard_payment($order_id, $amount) {
    $public_key = getenv('BANCARD_PUBLIC_KEY');
    $shop_process_id = strval($order_id);
    $token = generate_bancard_token($shop_process_id, $amount);
    
    $api_url = getenv('BANCARD_ENVIRONMENT') === 'production' 
        ? getenv('BANCARD_API_URL_PRODUCTION') 
        : getenv('BANCARD_API_URL_STAGING');
    
    $data = [
        'public_key' => $public_key,
        'operation' => [
            'token' => $token,
            'shop_process_id' => $shop_process_id,
            'currency' => CURRENCY_CODE,
            'amount' => number_format($amount, 0, '.', ''), // No decimals for PYG
            'description' => "Orden #$order_id - FoTeam",
            'return_url' => getenv('BANCARD_RETURN_URL'),
            'cancel_url' => getenv('BANCARD_CANCEL_URL')
        ]
    ];
    
    $ch = curl_init($api_url . '/vpos/api/0.3/single_buy');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception('Error al conectar con Bancard: ' . $response);
    }
    
    $result = json_decode($response, true);
    if ($result['status'] !== 'success') {
        throw new Exception('Error en la respuesta de Bancard: ' . $response);
    }
    
    return $result['process_id'];
}

function verify_bancard_payment($shop_process_id) {
    $private_key = getenv('BANCARD_PRIVATE_KEY');
    $token = md5($private_key . $shop_process_id . 'get_confirmation');
    
    $api_url = getenv('BANCARD_ENVIRONMENT') === 'production' 
        ? getenv('BANCARD_API_URL_PRODUCTION') 
        : getenv('BANCARD_API_URL_STAGING');
    
    $data = [
        'public_key' => getenv('BANCARD_PUBLIC_KEY'),
        'operation' => [
            'token' => $token,
            'shop_process_id' => $shop_process_id
        ]
    ];
    
    $ch = curl_init($api_url . '/vpos/api/0.3/single_buy/confirmations');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception('Error al verificar pago: ' . $response);
    }
    
    $result = json_decode($response, true);
    return $result;
}
?>
