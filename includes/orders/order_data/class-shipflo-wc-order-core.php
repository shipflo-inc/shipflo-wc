<?php

// If this file is called directly, abort.
defined('ABSPATH') || exit;

/**
 * Handles structuring WooCommerce Order data relevant for ShipFlo Delivery API.
 * 
 * Excludes all pricing, tax, discount, or merchant-customer payment information.
 * 
 * Focuses on:
 * - Pickup & drop-off addresses
 * - Contact details
 * - Delivery instructions
 * - Package dimensions & weight
 * - Order identifiers for tracking
 * - Delivery method (e.g., Cash Pickup vs Paid)
 */
class ShipFlo_WC_Order_Core
{
    protected $order;

    public static function add_calling_country_code($phone_number, $country_code)
    {
        return $phone_number;
    }

    function get_customer_info()
    {
        $firstName    = sanitize_user(shipflo_handle_null($this->order->get_billing_first_name()));
        $lastName     = sanitize_user(shipflo_handle_null($this->order->get_billing_last_name()));
        $address1     = shipflo_handle_null($this->order->get_billing_address_1());
        $address2     = shipflo_handle_null($this->order->get_billing_address_2());
        $city         = shipflo_handle_null($this->order->get_billing_city());
        $state_code   = shipflo_handle_null($this->order->get_billing_state());
        $post_code    = shipflo_handle_null($this->order->get_billing_postcode());
        $country_code = shipflo_handle_null($this->order->get_billing_country());

        // $state   = $this->to_state_name($state_code, $country_code);
        // $country = $this->to_country_name($country_code);

        $phoneNumber  = $this->add_calling_country_code(shipflo_handle_null($this->order->get_billing_phone()), $country_code);
        $emailAddress = shipflo_handle_null($this->order->get_billing_email());

        return [
            "customer_name"        => "{$firstName} {$lastName}",
            "email"                => $emailAddress,
            "customer_phone"       => $phoneNumber,
            "customer_address"     => $address1,
            "customer_address2"    => $address2,
            "customer_city"        => $city,
            "customer_region"      => $state_code,
            "customer_country"     => $country_code,
            "customer_zip_code"    => $post_code,
        ];
    }

    function get_shipping_address()
    {
        if (!$this->order->has_shipping_address()) {
            return $this->get_customer_info();
        }

        $firstName    = sanitize_user(shipflo_handle_null($this->order->get_shipping_first_name()));
        $lastName     = sanitize_user(shipflo_handle_null($this->order->get_shipping_last_name()));
        $address1     = shipflo_handle_null($this->order->get_shipping_address_1());
        $address2     = shipflo_handle_null($this->order->get_shipping_address_2());
        $city         = shipflo_handle_null($this->order->get_shipping_city());
        $state_code   = shipflo_handle_null($this->order->get_shipping_state());
        $post_code    = shipflo_handle_null($this->order->get_shipping_postcode());
        $country_code = shipflo_handle_null($this->order->get_shipping_country());

        // $state        = $this->to_state_name($state_code, $country_code);
        // $country      = $this->to_country_name($country_code);

        $phoneNumber  = !empty($this->order->shipping_phone)
            ? $this->add_calling_country_code($this->order->shipping_phone, $country_code)
            : $this->add_calling_country_code(shipflo_handle_null($this->order->get_billing_phone()), $this->order->get_billing_country());

        $emailAddress = !empty($this->order->shipping_email)
            ? $this->order->shipping_email
            : shipflo_handle_null($this->order->get_billing_email());

        return [
            "customer_name"        => "{$firstName} {$lastName}",
            "email"                => $emailAddress,
            "customer_phone"       => $phoneNumber,
            "customer_address"     => $address1,
            "customer_address2"    => $address2,
            "customer_city"        => $city,
            "customer_region"      => $state_code,
            "customer_country"     => $country_code,
            "customer_zip_code"    => $post_code,
        ];
    }

    function get_order_items($items = null)
    {
        if ($items === null) $items = $this->order->get_items();
        $orderItem = [];

        foreach ($items as $item_id => $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);

            $length = $width = $height = $weight = '';
            if ($product instanceof WC_Product) {
                $length = $product->get_length();
                $width  = $product->get_width();
                $height = $product->get_height();
                $weight = $product->get_weight();
            }

            $orderItem[] = [
                'package_name'     => $item->get_name(),
                'package_quantity' => $item->get_quantity(),
                'package_length'   => $length,
                'package_width'    => $width,
                'package_height'   => $height,
                'package_weight'   => $weight,
            ];
        }

        return ['order_items' => $orderItem];
    }

    function get_payment_info()
    {
        $payment_method = $this->order->get_payment_method();

        if ($payment_method === 'cod') {
            return [
                'payment_method' => 'cash_pickup',
                'cod' => (float) $this->order->get_total(), // total includes tax + shipping
            ];
        }

        return [
            'payment_method' => 'paid',
        ];
    }

    function get_message()
    {
        $address2 = trim(shipflo_handle_null($this->order->get_shipping_address_2()));
        $notes    = trim(shipflo_handle_null($this->order->get_customer_note()));

        $parts = array_filter([$address2, $notes], fn($v) => $v !== '');

        $asap_order = shipflo_get_order_meta($this->order->get_id(), '_shipflo_asap_order');

        if ($asap_order) {
            $parts[] = 'Please deliver as soon as possible';
        }

        return [
            'notes_personal' => count($parts) > 0 ? implode('. ', $parts) : null
        ];
    }

    function get_signature()
    {
        $tz = shipflo_get_datetime_timezone();
        $timezone = new DateTimeZone($tz);

        return [
            'platform' => 'woocommerce',
            'signature' => [
                'woo_version' => WC()->version,
                'plugin_version' => SHIPFLO_WC_VERSION,
                'shipflo_api_version' => SHIPFLO_API_VERSION,
                'site_url' => get_site_url(),
                'site_timezone' => $timezone->getName() // always returns standardized zone name
            ]
        ];
    }

    function is_within_service_area(): bool
    {
        try {
            // 1. Validate and normalize the shipping postal code
            $raw_postal = $this->order->get_shipping_postcode();

            if (empty($raw_postal) || !is_string($raw_postal)) {
                shipflo_logger('warning', '[ShipFlo] Missing or invalid postal code for order.', [
                    'order_id' => $this->order->get_id() ?? null,
                    'raw_postal' => $raw_postal,
                ]);
                return false;
            }

            // Normalize postal code: remove spaces, hyphens, and uppercase
            $postal_code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $raw_postal));

            // 2. Fetch active postal codes from API/config/cache
            $active_postal_codes = shipflo_get_postal_codes();

            if (!is_array($active_postal_codes) || empty($active_postal_codes)) {
                shipflo_logger('warning', '[ShipFlo] No active postal codes available for service area check.', [
                    'order_id' => $this->order->get_id() ?? null,
                ]);
                return false;
            }

            // 3. Normalize list of postal codes (ensure strings, clean formatting)
            $normalized_active = array_filter(array_map(function ($pc) {
                if (!is_string($pc)) return null;
                return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $pc));
            }, $active_postal_codes));

            // 4. Short-circuit if the normalized list is empty
            if (empty($normalized_active)) {
                shipflo_logger('warning', '[ShipFlo] Active postal code list is empty after normalization.', [
                    'order_id' => $this->order->get_id() ?? null,
                ]);
                return false;
            }

            // 5. Perform the lookup
            $in_area = in_array($postal_code, $normalized_active, true);

            if (!$in_area) {
                shipflo_logger('info', '[ShipFlo] Order postal code outside service area.', [
                    'order_id' => $this->order->get_id() ?? null,
                    'postal_code' => $postal_code,
                ]);
            }

            return $in_area;
        } catch (Throwable $e) {
            // 6. Fail-safe: never throw inside validation
            shipflo_logger('error', '[ShipFlo] Exception during service area check.', [
                'error' => $e->getMessage(),
                'order_id' => $this->order->get_id() ?? null,
            ]);
            return false;
        }
    }

    function is_pickup_order()
    {
        foreach ($this->order->get_shipping_methods() as $shipping_method) {
            if ($shipping_method->get_method_id() === 'local_pickup') {
                return true;
            }
        }

        return false;
    }

    public static function shipflo_handle_asap_delivery_order_note($order_id)
    {
        if (!function_exists('wc_get_order')) return;

        shipflo_update_order_meta($order_id, '_shipflo_asap_order', true);
        shipflo_logger('info', "[ShipFlo] Added ASAP order note for order #$order_id");
    }

    // protected function has_asap_note()
    // {
    //     $notes = wc_get_order_notes([
    //         'order_id' => $this->order->get_id(),
    //         'type'     => 'internal',
    //     ]);

    //     foreach ($notes as $note) {
    //         if (stripos($note->content, 'urgent delivery (ASAP)') !== false) {
    //             return $note->content;
    //         }
    //     }

    //     return null;
    // }

    public function get_user_filtered_payload($payload)
    {
        return apply_filters('shipflo_order_data_filter', $payload, $this->order->get_id());
    }
}
