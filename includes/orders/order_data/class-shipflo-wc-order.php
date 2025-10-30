<?php

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

class ShipFlo_WC_Order extends ShipFlo_WC_Order_Core 
{
    protected $order;

    function __construct($order_id)
    {
        $this->order = wc_get_order($order_id);
    }

    public function get_payloads()
    {
        return [ shipflo_get_api_key() => [ $this->get_user_filtered_payload($this->get_payload()) ] ];
    }

    public function get_payload()
    {
        return array_merge(
            $this->get_payload_without_dependant_info(),
            $this->get_store_info(),
            shipflo_get_pickup_delivery_times($this->order),
            $this->get_signature(),
            $this->get_uuid()
        );
    }

    public function get_payload_without_dependant_info()
    {
        return array_merge(
            $this->get_ids(),
            $this->get_shipping_address(),
            // $this->get_dropoff_object(),
            $this->get_order_items(),
            $this->get_payment_info(),
            $this->get_message()
        );
    }

    function get_ids()
    {
        $order_id = $this->order->get_id();
        $uuid = shipflo_get_order_meta($order_id, 'order_uuid', null);
        if (empty($uuid) || !wp_is_uuid($uuid)) {
            $uuid = wp_generate_uuid4();
            shipflo_update_order_meta($order_id, 'order_uuid', $uuid);
        }

        return [
            'order_id' => $order_id,
            'order_uuid' => $uuid
        ];
    }

    public function get_store_info()
    {
        $store_name = shipflo_handle_null(get_bloginfo('name'));

        $address1  = shipflo_handle_null(get_option('woocommerce_store_address'));
        $city      = shipflo_handle_null(get_option('woocommerce_store_city'));
        $post_code  = shipflo_handle_null(get_option('woocommerce_store_postcode'));
        $country_state = shipflo_handle_null(get_option('woocommerce_default_country'));

        [$country_code, $state_code] = array_pad(explode(':', $country_state), 2, '');

        // Build address parts array and filter out empty strings
        $parts = array_filter([
            $address1,
            $city,
            $state_code,
            $post_code,
            $country_code
        ]);

        $full_address = count($parts) > 0 
                        ? "{$address1}, {$city}, {$state_code} {$post_code}, {$country_code}"
                        : null;

        return [
            'store_name'    => html_entity_decode($store_name, ENT_QUOTES),
            'store_address' => $full_address
        ];
    }

    public function get_uuid()
    {
        return [ 'merchant_registered_uuid' => get_option(SHIPFLO_MERCHANT_REGISTERED_UUID) ];
    }

    function get_signature()
    {
        $data = parent::get_signature();
        $data['signature']['plugin'] = 'shipflo-wc';

        return $data;
    }
}