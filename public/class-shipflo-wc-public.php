<?php

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://sgssandhu.com
 * @since      1.0.0
 *
 * @package    ShipFlo_Wc
 * @subpackage ShipFlo_Wc/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    ShipFlo_Wc
 * @subpackage ShipFlo_Wc/public
 * @author     SGS Sandhu <sgs.sandhu@gmail.com>
 */
class ShipFlo_Wc_Public
{
	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($plugin_name, $version)
	{
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles()
	{
		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/shipflo-wc-public.css', array(), $this->version, 'all');
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts()
	{
		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/shipflo-wc-public.js', array('jquery'), $this->version, false);
	}

	/**
	 * Register the FontAwesome Icons CDN Link for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	// public function enqueue_font_awesome() 
	// {
	// 	wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css', [], '6.7.2');
	// }

	/**
	 * Register the DashIcons for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function shipflo_enqueue_dashicons_frontend() 
	{
		wp_enqueue_style('dashicons');
	}


	public function shipflo_add_track_action($actions, $order)
	{
		$order_id = $order->get_id();
		$track_url = shipflo_get_order_meta($order_id, SHIPFLO_CUSTOMER_TRACK_LINK, true);

		if ($track_url && filter_var($track_url, FILTER_VALIDATE_URL)) {
			$actions['track'] = [
				'url'  => esc_url($track_url),
				'name' => __('Track Order', 'shipflo-wc'),
				'action' => 'track', 
			];
		}

		return $actions;
	}

	public function shipflo_display_customer_track_link($order)
	{
		$customer_track_url = null;
		if (
			is_user_logged_in() &&
			$order instanceof WC_Order &&
			$order->get_customer_id() === get_current_user_id()
		) {
			$customer_track_url = shipflo_get_order_meta($order->get_id(), SHIPFLO_CUSTOMER_TRACK_LINK, true);
		}

		include SHIPFLO_WC_PLUGIN_DIR . 'public/partials/shipflo-wc-public-display.php';
	}

	public function shipflo_register_webhook_endpoint()
	{
		register_rest_route('shipflo/v1', '/order-updated', [
			'methods'             => 'POST',
			'callback'            => [$this, 'handle_shipflo_webhook'],
			'permission_callback' => '__return_true',
		]);
	}

	public function handle_shipflo_webhook(WP_REST_Request $request)
	{
		if (!defined('SHIPFLO_WEBHOOK_SECRET')) {
			shipflo_logger('error', '[ShipFlo] Missing SHIPFLO_WEBHOOK_SECRET constant.');
			return new WP_REST_Response(['error' => 'Webhook secret not configured.'], 500);
		}

		$receivedSignature = $request->get_header('X-Shipflo-Signature');
		if (!$receivedSignature) {
			shipflo_logger('error', '[ShipFlo] Missing X-Shipflo-Signature header.');
			return new WP_REST_Response(['error' => 'Unauthorized'], 401);
		}

		$expectedSignature = hash_hmac('sha256', $request->get_body(), SHIPFLO_WEBHOOK_SECRET);

		if (!hash_equals($expectedSignature, $receivedSignature)) {
			shipflo_logger('error', '[ShipFlo] Unauthorized access.');
			return new WP_REST_Response(['error' => 'Unauthorized'], 401);
		}

		$data = $request->get_json_params();

		if (!isset($data['success'], $data['order_id'])) {
			shipflo_logger('error', '[ShipFlo] Malformed webhook payload.');
			return new WP_REST_Response(['error' => 'Malformed webhook payload'], 400);
		}

		$order_id = absint($data['order_id']);
		$wc_order = wc_get_order($order_id);

		if (!$wc_order) {
			shipflo_logger('error', "[ShipFlo] Order not found: $order_id.");
			return new WP_REST_Response(['error' => "Order $order_id not found"], 404);
		}

		$order_status = $data['status'];
		shipflo_update_order_meta($order_id, SHIPFLO_ORDER_STATUS, $order_status);

		if (!in_array($order_status, ['new', 'processing'])) {
			$status_map = $this->shipflo_status_map();
			$wc_order->update_status( $status_map[$order_status] ); 
			
			shipflo_logger('notice', "[ShipFlo] Order $order_id: Status updated to {$order_status}.");
			return new WP_REST_Response(['success' => true], 200);
		}

		if (!empty($data['success'])) {
			$merchant_track_link = $data['merchant_tracking_link'] ?? '';
			$customer_track_link = $data['customer_tracking_link'] ?? '';
			$shipflo_id = $data['shipflo_order_id'] ?? '';

			shipflo_update_order_meta($order_id, SHIPFLO_MERCHANT_TRACK_LINK, sanitize_text_field($merchant_track_link));
			shipflo_update_order_meta($order_id, SHIPFLO_CUSTOMER_TRACK_LINK, sanitize_text_field($customer_track_link));
			shipflo_update_order_meta($order_id, SHIPFLO_ORDER_ID, sanitize_text_field($shipflo_id));
			shipflo_update_order_meta($order_id, SHIPFLO_ERROR, '');
			shipflo_update_order_meta($order_id, SHIPFLO_LAST_ATTEMPTED, time());

			shipflo_logger('info', "[ShipFlo] Order $order_id: Successfully posted to ShipFlo.");
		} else {
			$error = $data['error'] ?? 'Unknown error';
			$retry_count = (int) shipflo_get_order_meta($order_id, SHIPFLO_RETRY_COUNT, true);
			$retry_count++;

			shipflo_update_order_meta($order_id, SHIPFLO_ERROR, sanitize_text_field($error));
			shipflo_update_order_meta($order_id, SHIPFLO_LAST_ATTEMPTED, time());
			shipflo_update_order_meta($order_id, SHIPFLO_RETRY_COUNT, $retry_count);

			shipflo_logger('error', "[ShipFlo] Order $order_id: Post failed – $error");
		}
		
		return new WP_REST_Response(['success' => true], 200);
	}

	protected function shipflo_status_map()
	{
		return [
			'cancelled' => 'cancelled',
			'delivered' => 'completed',
			'delivery_attempted' => 'processing',
		];
	}
}
