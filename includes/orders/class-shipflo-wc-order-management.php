<?php

// If this file is called directly, abort.
defined('ABSPATH') || exit;

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

        // if (!$order_data_object->is_within_service_area()) {
        //     shipflo_logger('info', '[ShipFlo] ' . $order_id . ': Order filtered out as outside service area');
        //     return;
        // }

        shipflo_logger('info', '[ShipFlo] ' . $order_id . ': Order Management Process post sending starts');

        $success = false;

        try {
            $payloads = $order_data_object->get_payloads();
            $success = shipflo_post_orders($payloads);
        } catch (Exception $e) {
            shipflo_logger('error', "[ShipFlo] $order_id: Order processing failed – {$e->getMessage()}");
        }

        set_transient("shipflo_notice", compact('type', 'msg'), 60);

        if (! $success) {
            shipflo_logger('info', "[ShipFlo] $order_id: Order Management Process Post sending failed — will allow retry");
            self::unregister_as_posted($order_id);
        }

        shipflo_logger('info', '[ShipFlo] ' . $order_id . ': Order Management Process post successfully sent');
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
            $msg = "ShipFlo sync triggered successfully for order #$order_id.";
            $type = 'success';
        } catch (Throwable $e) {
            $msg = "ShipFlo sync failed for order #$order_id. Error: {$e->getMessage()}";
            $type = 'error';
            shipflo_logger('error', "[ShipFlo] $order_id: {$e->getMessage()}");
        }

        set_transient("shipflo_notice", compact('type', 'msg'), 60);

        wp_safe_redirect(wp_get_referer());
        exit;
    }
}
