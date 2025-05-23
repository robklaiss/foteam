<?php
// Currency settings
define('CURRENCY_SYMBOL', '₲');
define('CURRENCY_CODE', 'PYG');
define('PHOTO_PRICE', 15000); // Price in Guaraníes
define('PRICE_DECIMALS', 0); // Guaraníes don't use decimals

// Format money according to PYG currency
function format_money($amount) {
    return CURRENCY_SYMBOL . ' ' . number_format($amount, PRICE_DECIMALS, ',', '.');
}
?>
