<?php

/**
 * Frontend customer order tracking UI partial.
 *
 * Expects:
 * - $order (WC_Order)
 * - $customer_track_url (string|null)
 */

if (! $order instanceof WC_Order) {
    return;
}
?>

<section class="shipflo-tracking-section woocommerce-order-tracking">
    <h2 class="woocommerce-order-tracking__title">
        <?php esc_html_e('Track Your Order', 'shipflo-wc'); ?>
    </h2>
    <div class="tracking-content">
        <?php if (! filter_var($customer_track_url, FILTER_VALIDATE_URL)): ?>
            <p>
                <?php esc_html_e(
                    'Your order is being processed. Tracking information will be available shortly and will be sent to your email.',
                    'shipflo-wc'
                ); ?>
            </p>
        <?php else: ?>
            <p>
                <?php esc_html_e('You can track the live status of your order delivery here:', 'shipflo-wc'); ?>
            </p>
            <a class="button shipflo-track-button"
                href="<?php echo esc_url($customer_track_url); ?>"
                target="_blank"
            ><span class="dashicons dashicons-tag"></span>
                <?php esc_html_e('Track My Order Live', 'shipflo-wc'); ?>
            </a>
        <?php endif; ?>
    </div>
</section>