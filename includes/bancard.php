<?php
require_once 'config.php';
require_once 'app_config.php';
require_once 'bancard_config.php';

function generate_bancard_token($shop_process_id, $amount) {
    $private_key = getenv('BANCARD_PRIVATE_KEY');
    
    // Format amount with exactly two decimal places and ensure it's a string
    $amount_formatted = number_format((float)$amount, 2, '.', '');
    
    // Ensure shop_process_id is a string
    $shop_process_id = (string)$shop_process_id;
    
    // Create the raw token string - exactly as per Bancard's requirements
    $token_raw = $private_key . $shop_process_id . $amount_formatted . CURRENCY_CODE;
    
    // Debug log the token generation
    error_log('Generating token with values:');
    error_log('Private Key: ' . $private_key);
    error_log('Shop Process ID: ' . $shop_process_id . ' (type: ' . gettype($shop_process_id) . ')');
    error_log('Amount: ' . $amount_formatted . ' (type: ' . gettype($amount_formatted) . ')');
    error_log('Currency: ' . CURRENCY_CODE);
    error_log('Token Raw: ' . $token_raw);
    
    // Generate MD5 hash
    $token = md5($token_raw);
    error_log('Generated Token: ' . $token);
    
    return $token;
}

function init_bancard_payment($order_id, $amount) {
    // Set up detailed logging
    $log = [];
    $log[] = '=== Starting Bancard Payment Initialization ===';
    $log[] = 'Order ID: ' . $order_id . ' (type: ' . gettype($order_id) . ')';
    $log[] = 'Amount: ' . $amount . ' (type: ' . gettype($amount) . ')';
    $log[] = 'Public Key: ' . (getenv('BANCARD_PUBLIC_KEY') ? 'Set' : 'Not Set');
    $log[] = 'Private Key: ' . (getenv('BANCARD_PRIVATE_KEY') ? 'Set' : 'Not Set');
    
    try {
        $public_key = getenv('BANCARD_PUBLIC_KEY');
        
        // Ensure order_id is a simple numeric value
        // Use the order_id directly if it's numeric, otherwise generate a new one
        $shop_process_id = is_numeric($order_id) ? (int)$order_id : (int)(time() . mt_rand(1000, 9999));
        $amount = (float)$amount;
        
        // Store the mapping between order_id and shop_process_id in the database
        // This ensures we can reference the original order later
        // You'll need to implement this based on your database structure
        // Example: save_bancard_reference($order_id, $shop_process_id);
        
        $log[] = 'Formatted Shop Process ID: ' . $shop_process_id . ' (type: ' . gettype($shop_process_id) . ')';
        $log[] = 'Formatted Amount: ' . $amount . ' (type: ' . gettype($amount) . ')';
        
        // Format amount with exactly two decimal places for the token generation
        $amount_formatted = number_format($amount, 2, '.', '');
        
        // Generate token with consistent amount formatting
        $token = generate_bancard_token($shop_process_id, $amount_formatted);
        $log[] = 'Generated Token: ' . $token;
        $log[] = 'Token generated with amount: ' . $amount_formatted;
        
        // Get the correct API URL based on environment
        $environment = getenv('BANCARD_ENVIRONMENT');
        $api_url = $environment === 'production' 
            ? getenv('BANCARD_API_URL_PRODUCTION') 
            : getenv('BANCARD_API_URL_STAGING');
        
        $log[] = 'Environment: ' . $environment;
        $log[] = 'API URL: ' . $api_url;
        
        // Log the formatted amount being used
        $log[] = 'Formatted Amount for Request: ' . $amount_formatted . ' (type: ' . gettype($amount_formatted) . ')';
        
        $data = [
            'public_key' => $public_key,
            'operation' => [
                'token' => $token,
                'shop_process_id' => $shop_process_id,
                'currency' => CURRENCY_CODE,
                // Ensure amount is a string with exactly 2 decimal places
                'amount' => number_format($amount, 2, '.', ''),
                'description' => "Orden #$order_id - FoTeam",
                'return_url' => getenv('BANCARD_RETURN_URL'),
                'cancel_url' => getenv('BANCARD_CANCEL_URL'),
                'additional_data' => '',
                'zimple' => 'N'
            ]
        ];
        
        // Log the request data (with sensitive information redacted)
        $debug_data = $data;
        $debug_data['operation']['token'] = '***REDACTED***';
        $log[] = 'Request Data: ' . json_encode($debug_data, JSON_PRETTY_PRINT);
        
        // Initialize cURL
        $ch = curl_init($api_url . '/vpos/api/0.3/single_buy');
        $json_data = json_encode($data);
        
        $log[] = 'Sending request to: ' . $api_url . '/vpos/api/0.3/single_buy';
        $log[] = 'Request data: ' . $json_data;
        
        // Set cURL options
        curl_setopt_array($ch, [
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $json_data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json_data),
                'Accept: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);
        
        // Execute the request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Log the response
        $log[] = 'HTTP Status: ' . $http_code;
        $log[] = 'Response: ' . $response;
        
        // Write all logs to error log
        error_log(implode("\n", $log));
        
        if ($error) {
            throw new Exception('CURL Error: ' . $error);
        }
        
        if ($http_code !== 200) {
            throw new Exception('HTTP Error ' . $http_code . ' connecting to Bancard: ' . $response);
        }
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from Bancard: ' . $response);
        }
        
        if (!isset($result['status'])) {
            throw new Exception('Invalid response format from Bancard: ' . $response);
        }
        
        if ($result['status'] !== 'success') {
            $error_message = 'Bancard API Error';
            if (isset($result['messages']) && is_array($result['messages'])) {
                $errors = array_column($result['messages'], 'dsc');
                $error_message = implode('; ', $errors);
            }
            throw new Exception($error_message . ' (Response: ' . $response . ')');
        }
        
        if (!isset($result['process_id'])) {
            throw new Exception('No process_id in Bancard response: ' . $response);
        }
        
        return $result['process_id'];
        
    } catch (Exception $e) {
        // Log the error
        error_log('Error in init_bancard_payment: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        
        // Re-throw the exception to be handled by the caller
        throw $e;
    }
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
