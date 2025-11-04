<?php

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

// Version of the plugin
define( "SHIPFLO_WC_VERSION", '1.0.0' );
// Version of the api
define( "SHIPFLO_API_VERSION", 'v1' );
// Plugin directory constants
define('SHIPFLO_WC_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__)));
define('SHIPFLO_WC_PLUGIN_URL', plugin_dir_url(dirname(__FILE__)));
define('SHIPFLO_WC_PLUGIN_BASENAME', plugin_basename(dirname(__FILE__)));

// API KEY - encrypted
define('SHIPFLO_API_KEY_OPTION_ID', '_shipflo_wc_api_key');
// Order sync and management options
define('SHIPFLO_ORDER_MANAGE_OPTION_ID', '_shipflo_wc_order_manage');
// Merchant information options
define('SHIPFLO_WEBHOOK_SECRET', '_shipflo_wc_webhook_secret');
define('SHIPFLO_MERCHANT_ID_OPTION_ID', '_shipflo_wc_merchant_id');
define('SHIPFLO_MERCHANT_REGISTERED_UUID', '_shipflo_wc_registered_uuid');
// Active Postal Codes
define('SHIPFLO_ACTIVE_POSTAL_CODES_TRANSIENT', 'shipflo_postal_codes');
// Order sync metadata keys
define('SHIPFLO_MERCHANT_TRACK_LINK', '_shipflo_wc_merchant_track_link');
define('SHIPFLO_CUSTOMER_TRACK_LINK', '_shipflo_wc_customer_track_link');
define('SHIPFLO_DISPATCH_STATUS', '_shipflo_wc_dispatch_status');
define('SHIPFLO_ORDER_STATUS', '_shipflo_wc_order_status');
define('SHIPFLO_ORDER_ID', '_shipflo_wc_order_id');
define('SHIPFLO_ERROR', '_shipflo_wc_error');
define('SHIPFLO_LAST_ATTEMPTED', '_shipflo_wc_last_attempted_at');
define('SHIPFLO_RETRY_COUNT', '_shipflo_wc_retry_count');
define('SHIPFLO_MAX_RETRY', 5);