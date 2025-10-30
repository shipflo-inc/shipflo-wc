<?php

/**
 * Admin order tracking UI partial.
 *
 * @var WC_Order|null $order
 */

if (! $order instanceof WC_Order) {
    echo '<p>Order data unavailable.</p>';
    return;
}

$order_id   = $order->get_id();
$track_url  = shipflo_get_order_meta($order_id, SHIPFLO_MERCHANT_TRACK_LINK, true);

?>

<a href="http://127.0.0.1:8000/t/4zST7wPPPE" target="_blank" class="button" style="display: inline-flex;align-items: center;gap: 4px;margin: 10px;"><span class="dashicons dashicons-tag"></span>Track Live via ShipFlo</a>