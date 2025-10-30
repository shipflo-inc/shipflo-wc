<?php

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

function shipflo_get_pickup_delivery_times(WC_Order $order)
{
    if (
        is_plugin_active('woo-delivery/coderockz-woo-delivery.php')
        || is_plugin_active('coderockz-woocommerce-delivery-date-time-pro/coderockz-woo-delivery.php')
    ) {
        $date_picker_object = new Coderocks_Woo_Delivery($order->get_id());
    } elseif (
        is_plugin_active('order-delivery-date-for-woocommerce/order_delivery_date.php')
        || is_plugin_active('order-delivery-date/order_delivery_date.php')
    ) {
        $date_picker_object = new ShipFlo_Wc_Order_Delivery_Date($order->get_id());
    } 
    
    if (!isset($date_picker_object)) return [];
    
    $times = [
        'pickup_after' => $date_picker_object->get_pickup_datetime_iso(),
        'deliver_after' => $date_picker_object->get_delivery_datetime_iso(),
    ];

    return $times;
}

function shipflo_get_datetime_plugins()
{
    $plugins = [];

    if (is_plugin_active('coderockz-woocommerce-delivery-date-time-pro/coderockz-woo-delivery.php')) {
        $plugins[] = 'CodeRockz Woo Delivery Pro';
    }

    if (is_plugin_active('woo-delivery/coderockz-woo-delivery.php')) {
        $plugins[] = 'CodeRockz Woo Delivery';
    }

    if (is_plugin_active('order-delivery-date-for-woocommerce/order_delivery_date.php')) {
        $plugins[] = 'Tyche Order Delivery Date';
    }

    if (is_plugin_active('order-delivery-date/order_delivery_date.php')) {
        $plugins[] = 'Tyche Order Delivery Date Pro';
    }

    if (is_plugin_active('woocommerce-delivery-area-pro/woocommerce-delivery-area-pro.php')) {
        $plugins[] = 'WooCommerce Delivery Area Pro';
    }

    return $plugins;
}

function shipflo_get_datetime_timezone()
{
    if (is_plugin_active('woo-delivery/coderockz-woo-delivery.php')) {
        return (new Coderockz_Woo_Delivery_Helper())->get_the_timezone();
    }

    if (
        is_plugin_active('order-delivery-date-for-woocommerce/order_delivery_date.php')
        || is_plugin_active('order-delivery-date/order_delivery_date.php')
    ) {
        return wp_timezone_string();
    }

    return wp_timezone_string();
}
