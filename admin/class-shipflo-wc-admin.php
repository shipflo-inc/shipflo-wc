<?php

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://sgssandhu.com
 * @since      1.0.0
 *
 * @package    ShipFlo_Wc
 * @subpackage ShipFlo_Wc/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    ShipFlo_Wc
 * @subpackage ShipFlo_Wc/admin
 * @author     SGS Sandhu <sgs.sandhu@gmail.com>
 */
class ShipFlo_Wc_Admin
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
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($plugin_name, $version)
	{
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles()
	{
		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/shipflo-wc-admin.css', array(), $this->version, 'all');
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts()
	{
		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/shipflo-wc-admin.js', array('jquery'), $this->version, false);
	}

	public function dump_payload()
	{
		if (!is_admin()) return;
		if (!isset($_GET['debug_shipflo_payload'])) return;
		if (!current_user_can('manage_options')) return;

		$order_id = (int) $_GET['debug_shipflo_payload'];

		if (!function_exists('wc_get_order')) {
			wp_die("WooCommerce not loaded");
		}

		$order = wc_get_order($order_id);
		if (!$order) {
			wp_die("Order ID $order_id not found.");
		}

		$shipfloOrder = new ShipFlo_WC_Order($order_id);
		$payload = $shipfloOrder->get_payload();

		header('Content-Type: application/json');
		echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		exit;
	}

	public function shipflo_register_admin_order_meta_ui()
	{
		$hpos_enabled = shipflo_is_hpos_enabled();

		if ($hpos_enabled) {
			// HPOS: Use proper action hook
			add_action(
				'woocommerce_order_actions_end',
				[self::class, 'shipflo_add_buttons_to_order_edit']
			);
		} else {
			// Legacy: Use WP meta box
			add_action('add_meta_boxes_shop_order', function () {
				add_meta_box(
					'shipflo_order_meta_box',
					__('📦 ShipFlo Order Details', 'shipflo-wc'),
					[self::class, 'shipflo_add_buttons_to_order_edit'],
					'shop_order',
					'side',
					'high'
				);
			});
		}
	}

	public static function shipflo_add_buttons_to_order_edit($order = null)
	{
		if (is_numeric($order)) {
			$order = wc_get_order($order);
		}

		if (! $order instanceof WC_Order) {
			return;
		}

		$order_id = $order->get_id();
		$order_meta = self::get_order_meta($order_id);

		if (empty($order_meta['shipflo_order_id']) && $order_meta['retry_count'] < SHIPFLO_MAX_RETRY) {
			self::render_button($order_meta['retry_url'], 'redo', 'Retry Pushing to ShipFlo');
		}

		if ($order_meta['track_url'] && filter_var($order_meta['track_url'], FILTER_VALIDATE_URL)) {
			self::render_button($order_meta['track_url'], 'tag', 'Track via ShipFlo');
		}
	}

	public function shipflo_add_admin_actions($actions, $order)
	{
		$order_id = $order->get_id();
		$order_meta = self::get_order_meta($order_id);

		if (empty($order_meta['shipflo_order_id']) && $order_meta['retry_count'] < SHIPFLO_MAX_RETRY) {
			$actions['shipflo_resend'] = self::render_action($order_meta['retry_url'], 'Retry Pushing to ShipFlo', 'shipflo-resend');
		}

		if ($order_meta['track_url'] && filter_var($order_meta['track_url'], FILTER_VALIDATE_URL)) {
			$actions['track'] = self::render_action($order_meta['track_url'], 'Track Order', 'track');
		}

		return $actions;
	}

	protected static function render_action($url, $name, $action)
	{
		return [
			'url'    => esc_url($url),
			'name'   => __($name, 'woocommerce'),
			'action' => $action,
		];
	}

	protected static function render_button($url, $icon, $text)
	{
		printf(
			'<a href="%s" class="button" style="display: inline-flex; align-items: center; gap: 4px; margin: 10px; font-size: 14px;">
				<span class="dashicons dashicons-%s" style="font-size: 14px; width: 14px; height: 14px;"></span>%s
			</a>',
			esc_url($url),
			esc_attr($icon),
			esc_html($text)
		);
	}

	protected static function get_order_meta($order_id)
	{
		$track_url = shipflo_get_order_meta($order_id, SHIPFLO_MERCHANT_TRACK_LINK, null);
		$shipflo_order_id = shipflo_get_order_meta($order_id, SHIPFLO_ORDER_ID, true);
		$shipflo_retry_count = shipflo_get_order_meta($order_id, SHIPFLO_RETRY_COUNT, true);
		$retry_url = self::build_retry_url($order_id);

		return [
			'retry_url' => $retry_url,
			'retry_count' => is_numeric($shipflo_retry_count) ? (int) $shipflo_retry_count : 0,
			'track_url' => $track_url,
			'shipflo_order_id' => is_numeric($shipflo_order_id) ? (int) $shipflo_order_id : 0,
		];
	}

	protected static function build_retry_url($order_id)
	{
		return wp_nonce_url(
			admin_url("admin-ajax.php?action=shipflo_process_order&order_id=$order_id"),
			"shipflo_process_order_$order_id"
		);
	}

	public function shipflo_add_orders_list_column($columns)
	{
		$new_columns = [];

		// Add columns before 'order_total' (or adjust as you see fit)
		foreach ($columns as $key => $column) {
			$new_columns[$key] = $column;
			if ('order_total' === $key) { // Or 'order_status', 'order_date', etc.
				$new_columns['shipflo_track_link_col'] = __('Shipflo Tracking', 'shipflo-wc');
			}
		}
		// If 'order_total' wasn't found or you want it at the end
		if (!isset($new_columns['shipflo_track_link_col'])) {
			$new_columns['shipflo_track_link_col'] = __('Shipflo Tracking', 'shipflo-wc');
		}

		return $new_columns;
	}

	public function shipflo_populate_orders_list_column($column, $order_id)
	{
		if ('shipflo_track_link_col' === $column) {
			$merchant_track_url = shipflo_get_order_meta($order_id, SHIPFLO_MERCHANT_TRACK_LINK, true);

			if ($merchant_track_url) {
				printf(
					'<a href="%s" target="_blank" class="button button-small" style="display: inline-flex;align-items: center;gap: 4px;margin: 10px;font-size: 14px;"><span class="dashicons dashicons-tag" style="font-size: 14px;width: 14px;height: 14px;"></span>Track via ShipFlo</a>',
					esc_url($merchant_track_url)
				);
			}
		}
	}
}
