<?php

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/** Global Plugin State */
$shipflo_debug_url = '';
$shipflo_debug_flag = false;

/** API URL Helpers */
function shipflo_get_api_base_url(): string 
{
    return 'https://app.shipflo.com/api';
    // return 'http://127.0.0.1:8000/api';
}

function shipflo_get_api_verify_url(): string 
{
    $path = "/verify-api-key";
    return shipflo_get_api_versioned_url($path);
}

function shipflo_get_api_postal_codes(): string 
{
    $path = "/postal-codes";
    return shipflo_get_api_versioned_url($path);
}

function shipflo_get_api_orders_url(): string 
{
    $path = "/orders/add";
    return shipflo_get_api_versioned_url($path);
}

function shipflo_get_api_versioned_url(string $path): string
{
    return trailingslashit(shipflo_get_api_base_url()) . SHIPFLO_API_VERSION . '/' . ltrim($path, '/');
}

function shipflo_get_api_logs_url(): string 
{
    return trailingslashit(shipflo_get_api_base_url()) . 'logs';
}

function shipflo_get_debug_api_url(): string 
{
    global $shipflo_debug_url;
    return $shipflo_debug_url;
}

/** Data Sanitization */
function shipflo_handle_null($text): string {
    return $text !== null ? (string) $text : '';
}

/** Plugin Option Getters */
function shipflo_get_api_key(): string 
{
    $key = get_option(SHIPFLO_API_KEY_OPTION_ID);
    return shipflo_handle_null(shipflo_decrypt_data($key));
}

function shipflo_get_order_manager(): string 
{
    return shipflo_handle_null(get_option(SHIPFLO_ORDER_MANAGE_OPTION_ID));
}

/** Emoji Removal Utility */
function shipflo_remove_emoji($string): string 
{
    $emoji_patterns = [
        '/[\x{1F100}-\x{1F1FF}]/u',
        '/[\x{1F300}-\x{1F5FF}]/u',
        '/[\x{1F600}-\x{1F64F}]/u',
        '/[\x{1F680}-\x{1F6FF}]/u',
        '/[\x{1F900}-\x{1F9FF}]/u',
        '/[\x{2600}-\x{26FF}]/u',
        '/[\x{2700}-\x{27BF}]/u',
    ];

    return preg_replace($emoji_patterns, '', $string);
}

/** Encryption Key Management */
function shipflo_generate_encryption_key(): bool 
{
    if (!get_option(SHIPFLO_PLUGIN_ENCRYPTION_KEY_OPTION_ID)) {
        if (function_exists('random_bytes')) {
            $key = bin2hex(random_bytes(32));
            update_option(SHIPFLO_PLUGIN_ENCRYPTION_KEY_OPTION_ID, $key, false);
            shipflo_logger('info', '[ShipFlo] Plugin encryption key generated using random_bytes().');
            shipflo_logger('notice', '[ShipFlo] Plugin encryption key generated');
            return true;

        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $key = bin2hex(openssl_random_pseudo_bytes(32));
            update_option(SHIPFLO_PLUGIN_ENCRYPTION_KEY_OPTION_ID, $key, false);
            shipflo_logger('warning', '[ShipFlo] Plugin encryption key generated using openssl_random_pseudo_bytes().');
            shipflo_logger('notice', '[ShipFlo] Plugin encryption key generated.');
            return true;

        } else {
            shipflo_logger('error', '[ShipFlo] No secure random function available. Plugin cannot generate encryption key.');
            return false;
        }
    }

    return true;
}

function shipflo_get_encryption_key(): string 
{
    $key_hex = get_option(SHIPFLO_PLUGIN_ENCRYPTION_KEY_OPTION_ID);

    if (!$key_hex) {
        shipflo_logger('alert', '[ShipFlo] Encryption key missing. Please reactivate the plugin.');
        wp_die(
            __('ShipFlo Error: Missing encryption key. Please reactivate the plugin to generate it.', 'shipflo-wc'),
            __('ShipFlo Critical Error', 'shipflo-wc'),
            ['back_link' => true]
        );
    }

    $key_binary = hex2bin($key_hex);
    if ($key_binary === false || strlen($key_binary) !== 32) {
        shipflo_logger('alert', '[ShipFlo] Stored encryption key is corrupted or invalid.');
        wp_die(
            __('ShipFlo Error: Corrupted encryption key. Please deactivate and reactivate the plugin to regenerate.', 'shipflo-wc'),
            __('ShipFlo Critical Error', 'shipflo-wc'),
            ['back_link' => true]
        );
    }

    $salt = '&4i|Y1q5Pk8xc^r0';
    return hash('sha256', $key_binary . $salt, true); // 32-byte binary
}

/** Encryption & Decryption */
function shipflo_encrypt_data($data): string 
{
    $key = shipflo_get_encryption_key();
    if (empty($key)) {
        shipflo_logger('error', '[ShipFlo] Encryption key missing or invalid during encryption.');
        return '';
    }

    $cipher = 'aes-256-cbc';
    $iv_len = openssl_cipher_iv_length($cipher);
    $iv = random_bytes($iv_len);

    $encrypted = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        shipflo_logger('error', '[ShipFlo] openssl_encrypt failed.');
        return '';
    }

    return base64_encode($iv . $encrypted);
}

function shipflo_decrypt_data($encrypted_data): string 
{
    $key = shipflo_get_encryption_key();
    if (empty($key)) {
        shipflo_logger('error', '[ShipFlo] Encryption key missing or invalid during decryption.');
        return '';
    }

    $cipher = 'aes-256-cbc';
    $decoded = base64_decode($encrypted_data);

    if ($decoded === false || strlen($decoded) < openssl_cipher_iv_length($cipher)) {
        shipflo_logger('error', '[ShipFlo] Decryption failed: Corrupted or invalid data.');
        return '';
    }

    $iv_len = openssl_cipher_iv_length($cipher);
    $iv = substr($decoded, 0, $iv_len);
    $ciphertext = substr($decoded, $iv_len);

    $decrypted = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) {
        shipflo_logger('error', '[ShipFlo] openssl_decrypt failed.');
        return '';
    }

    return $decrypted;
}

/** Cleanup Utility */
function shipflo_clear_api_key_and_merchant_details(): void 
{
    delete_option(SHIPFLO_API_KEY_OPTION_ID);
    delete_option(SHIPFLO_MERCHANT_ID_OPTION_ID);
    delete_option(SHIPFLO_MERCHANT_EMAIL_OPTION_ID);
    delete_option(SHIPFLO_MERCHANT_NAME_OPTION_ID);
}

function shipflo_is_hpos_enabled(): bool {
    if (! function_exists('wc_get_container')) {
        return false;
    }

    $controller = wc_get_container()->get(
        \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class
    );

    return $controller->custom_orders_table_usage_is_enabled();
}

function shipflo_get_order_meta($order_id, string $key, $default = null)
{
    // Try fetching via WC_Order object (works for HPOS and legacy if WC is fully loaded)
    if (function_exists('wc_get_order')) {
        $order = wc_get_order($order_id);
        if ($order && is_callable([$order, 'get_meta'])) {
            $value = $order->get_meta($key, true);
            if ($value !== '') {
                return $value;
            }
        }
    }

    // Fallback to get_post_meta if order is not an HPOS order
    $value = get_post_meta($order_id, $key, true);
    return $value !== '' ? $value : $default;
}

function shipflo_update_order_meta($order_id, string $key, $value): bool
{
    // Try updating via WC_Order object (works for HPOS and legacy if WC is fully loaded)
    if (function_exists('wc_get_order')) {
        $order = wc_get_order($order_id);
        if ($order && is_callable([$order, 'update_meta_data'])) {
            $order->update_meta_data($key, $value);
            $result = $order->save();
            return $result !== false;
        }
    }

    // Fallback to update_post_meta if order is not an HPOS order
    $result = update_post_meta($order_id, $key, $value);
    return is_int($result) && $result > 0;
}            