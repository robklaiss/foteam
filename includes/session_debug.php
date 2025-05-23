<?php
/**
 * Session Debug Utility
 * 
 * This file provides functions for debugging session issues,
 * particularly focusing on cart persistence issues.
 */

// Create logs directory if it doesn't exist
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}

/**
 * Log detailed session information for debugging
 * 
 * @param string $page The page where the log is being called from
 * @param string $action The action being performed (e.g., 'before_redirect', 'page_load')
 * @return void
 */
function log_session_debug($page, $action = 'page_load') {
    $log_file = __DIR__ . '/../logs/session_debug.log';
    
    // Get HTTP headers for cookie debugging
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (substr($key, 0, 5) === 'HTTP_') {
            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))))] = $value;
        }
    }
    
    // Prepare log data
    $log_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'page' => $page,
        'action' => $action,
        'session_id' => session_id(),
        'session_status' => session_status(), // 1=DISABLED, 2=NONE, 3=ACTIVE
        'session_cookie' => $_COOKIE[session_name()] ?? 'not_set',
        'session_name' => session_name(),
        'cart_exists' => isset($_SESSION['cart']) ? 'yes' : 'no',
        'cart_count' => isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0,
        'cart_contents' => isset($_SESSION['cart']) ? json_encode($_SESSION['cart']) : 'not_set',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'http_referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
        'cookie_headers' => isset($headers['Cookie']) ? $headers['Cookie'] : 'not_found',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    // Write to log file
    file_put_contents(
        $log_file, 
        json_encode($log_data, JSON_PRETTY_PRINT) . "\n---END LOG ENTRY---\n\n",
        FILE_APPEND
    );
    
    return $log_data;
}

/**
 * Create a test item in the cart for debugging
 * 
 * @return void
 */
function add_test_item_to_cart() {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Only add test item if cart is empty
    if (empty($_SESSION['cart'])) {
        $_SESSION['cart'][] = [
            'id' => 999,
            'price' => 15000,
            'debug' => 'test_item_' . time()
        ];
    }
}

/**
 * Display session debug information in the browser
 * 
 * @param bool $include_html Whether to include HTML formatting
 * @return string The debug information
 */
function display_session_debug($include_html = true) {
    $debug_data = log_session_debug('debug_display');
    
    if (!$include_html) {
        return json_encode($debug_data, JSON_PRETTY_PRINT);
    }
    
    $output = '<div style="background-color: #f8f9fa; border: 1px solid #ddd; padding: 15px; margin: 15px 0; border-radius: 5px;">';
    $output .= '<h4>Session Debug Information</h4>';
    $output .= '<p><strong>Session ID:</strong> ' . $debug_data['session_id'] . '</p>';
    $output .= '<p><strong>Session Status:</strong> ' . $debug_data['session_status'] . ' (1=DISABLED, 2=NONE, 3=ACTIVE)</p>';
    $output .= '<p><strong>Session Cookie:</strong> ' . $debug_data['session_cookie'] . '</p>';
    $output .= '<p><strong>Cart Status:</strong> ' . $debug_data['cart_exists'] . '</p>';
    $output .= '<p><strong>Cart Item Count:</strong> ' . $debug_data['cart_count'] . '</p>';
    
    if ($debug_data['cart_exists'] === 'yes' && $debug_data['cart_count'] > 0) {
        $output .= '<details>';
        $output .= '<summary>Cart Contents (Click to expand)</summary>';
        $output .= '<pre>' . $debug_data['cart_contents'] . '</pre>';
        $output .= '</details>';
    }
    
    $output .= '</div>';
    return $output;
}
?>
