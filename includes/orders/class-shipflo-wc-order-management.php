<?php

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

class ShipFlo_Wc_Order_Management
{
    private static $persistance_time = 60 * 60 * 24 * 30;

    public static function map_to_transient($order_id)
    {
        return 'ShipFlo_order_posted' . $order_id;
    }

    public static function is_duplicate($order_id)
    {
        return get_transient(self::map_to_transient($order_id));
    }

    public static function register_as_posted($order_id)
    {
        set_transient(self::map_to_transient($order_id), true, self::$persistance_time);
    }

    public static function unregister_as_posted($order_id)
    {
        set_transient(self::map_to_transient($order_id), false, self::$persistance_time);
    }

    public static function process_and_send($order_id)
    {
        if (self::is_duplicate($order_id)) return;

        self::register_as_posted($order_id);
        shipflo_logger('info', '[ShipFlo] ' . $order_id . ': Order Management Process started');

        $order_data_object = new ShipFlo_WC_Order($order_id);

        if ($order_data_object->is_pickup_order()) {
            shipflo_logger('info', '[ShipFlo] ' . $order_id . ': Order filtered out as pickup order');
            return;
        }

        if (!$order_data_object->is_within_service_area()) {
            shipflo_logger('info', '[ShipFlo] ' . $order_id . ': Order filtered out as outside service area');
            return;
        }

        shipflo_logger('info', '[ShipFlo] ' . $order_id . ': Order Management Process post sending starts');

        try {
            $payloads = $order_data_object->get_payloads();
        } catch (Exception $exception) {
            shipflo_logger('info', '[ShipFlo] ' . $order_id . ': Order Management Process get_payloads failed');
        }

        try {
            $success = shipflo_post_orders($payloads);
        } catch (Exception $exception) {
            shipflo_logger('info', '[ShipFlo] ' . $order_id . ': Order Management Process post sending failed');
        }

        if ($success) {
            shipflo_logger('info', '[ShipFlo] ' . $order_id . ': Order Management Process post successfully sent');
        } else {
            shipflo_logger('info', '[ShipFlo] ' . $order_id . ': Order Management Process post sending failed');
            self::unregister_as_posted($order_id);
        }
    }

    public static function handle_ajax_retry()
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized', 403);
        }

        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        check_admin_referer('shipflo_process_order_' . $order_id);

        try {
            self::process_and_send($order_id);
            shipflo_logger('info', "[ShipFlo] Retried order #$order_id");

            set_transient("shipflo_notice", [
                'type' => 'success',
		        'msg'  => "ShipFlo sync triggered successfully for order #$order_id.",
            ], 60);
        } catch (Throwable $e) {
            shipflo_logger('error', urlencode($e->getMessage()));

            set_transient("shipflo_notice", [
                'type' => 'error',
                'msg'  => "ShipFlo sync failed for order #$order_id. Error: {$e->getMessage()}",
            ], 60);
        }

        wp_safe_redirect(wp_get_referer());
        exit;
    }

    // public static function get_failed_shipflo_orders($max_retries = 5)
    // {
    //     global $wpdb;

    //     $meta_key_status = SHIPFLO_DISPATCH_STATUS;
    //     $meta_value_status = 'failed';
    //     $meta_key_retry = 'shipflo_retry_count';

    //     $query = "
    //         SELECT o.ID
    //         FROM {$wpdb->prefix}posts o
    //         INNER JOIN {$wpdb->prefix}postmeta status 
    //             ON o.ID = status.post_id
    //         LEFT JOIN {$wpdb->prefix}postmeta retries 
    //             ON o.ID = retries.post_id AND retries.meta_key = %s
    //         WHERE o.post_type = 'shop_order'
    //             AND o.post_status IN ('wc-processing', 'wc-on-hold', 'wc-pending')
    //             AND status.meta_key = %s
    //             AND status.meta_value = %s
    //             AND (
    //                 retries.meta_value IS NULL
    //                 OR CAST(retries.meta_value AS UNSIGNED) < %d
    //             )";

    //     return $wpdb->get_col($wpdb->prepare(
    //         $query,
    //         $meta_key_retry,
    //         $meta_key_status,
    //         $meta_value_status,
    //         $max_retries
    //     ));
    // }

    // public static function retry_failed_orders($max_retries = 5)
    // {
    //     $failed_orders = self::get_failed_shipflo_orders($max_retries);

    //     if (empty($failed_orders)) {
    //         shipflo_logger('info', '[ShipFlo] No failed orders found to retry.');
    //         return;
    //     }

    //     shipflo_logger('info', '[ShipFlo] Retrying ' . count($failed_orders) . ' failed order(s)...');

    //     foreach ($failed_orders as $order_id) {
    //         try {
    //             ShipFlo_Wc_Order_Management::process_and_send($order_id);
    //             shipflo_logger('info', "[ShipFlo] Retried order #$order_id");
    //         } catch (Throwable $e) {
    //             shipflo_logger('error', "[ShipFlo] Error retrying order #$order_id: " . $e->getMessage());
    //         }
    //     }
    // }
}
