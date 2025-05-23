<?php
require_once 'includes/config.php';

$required_vars = [
    'BANCARD_PUBLIC_KEY',
    'BANCARD_PRIVATE_KEY',
    'BANCARD_ENVIRONMENT',
    'BANCARD_API_URL_PRODUCTION',
    'BANCARD_API_URL_STAGING',
    'BANCARD_RETURN_URL',
    'BANCARD_CANCEL_URL'
];

$missing_vars = [];
$configured_vars = [];

foreach ($required_vars as $var) {
    $value = getenv($var);
    if (empty($value)) {
        $missing_vars[] = $var;
    } else {
        // Only show first/last few characters for sensitive data
        $masked_value = $var === 'BANCARD_PUBLIC_KEY' || $var === 'BANCARD_PRIVATE_KEY' 
            ? substr($value, 0, 4) . '...' . substr($value, -4)
            : $value;
        $configured_vars[$var] = $masked_value;
    }
}

echo "=== Bancard Environment Variables Check ===\n\n";

if (!empty($configured_vars)) {
    echo "Configured variables:\n";
    foreach ($configured_vars as $var => $value) {
        echo "$var: $value\n";
    }
    echo "\n";
}

if (!empty($missing_vars)) {
    echo "Missing variables:\n";
    foreach ($missing_vars as $var) {
        echo "- $var\n";
    }
    echo "\n";
}

echo "Environment check complete.\n";
