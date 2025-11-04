<?php

// If this file is called directly, abort.
defined('ABSPATH') || exit;

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://sgssandhu.com
 * @since      1.0.0
 *
 * @package    ShipFlo_Wc
 * @subpackage ShipFlo_Wc/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    ShipFlo_Wc
 * @subpackage ShipFlo_Wc/includes
 * @author     SGS Sandhu <sgs.sandhu@gmail.com>
 */
class ShipFlo_Wc
{
	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      ShipFlo_Wc_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site and sets up the cron.
	 *
	 * @since    1.0.0
	 */
	public function __construct()
	{
		if (defined('SHIPFLO_WC_VERSION')) {
			$this->version = SHIPFLO_WC_VERSION;
		} else {
			$this->version = '1.0.0';
		}

		$this->plugin_name = 'shipflo-wc';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_cron_hooks();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - ShipFlo_Wc_Loader. Orchestrates the hooks of the plugin.
	 * - ShipFlo_Wc_i18n. Defines internationalization functionality.
	 * - ShipFlo_Wc_Admin. Defines all hooks for the admin area.
	 * - ShipFlo_Wc_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies()
	{
		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once SHIPFLO_WC_PLUGIN_DIR . 'includes/class-shipflo-wc-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once SHIPFLO_WC_PLUGIN_DIR . 'includes/class-shipflo-wc-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once SHIPFLO_WC_PLUGIN_DIR . 'admin/class-shipflo-wc-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once SHIPFLO_WC_PLUGIN_DIR . 'public/class-shipflo-wc-public.php';

		/**
		 * Load other dependencies
		 */
		require_once SHIPFLO_WC_PLUGIN_DIR . 'admin/class-shipflo-wc-notices.php';
		require_once SHIPFLO_WC_PLUGIN_DIR . 'admin/class-shipflo-wc-settings-tab.php';

		require_once SHIPFLO_WC_PLUGIN_DIR . 'includes/date_modifiers/class-shipflo-wc-date-picker-object.php';
		require_once SHIPFLO_WC_PLUGIN_DIR . 'includes/date_modifiers/class-coderocks-woo-delivery.php';
		require_once SHIPFLO_WC_PLUGIN_DIR . 'includes/date_modifiers/class-shipflo-wc-order-delivery-date.php';
		require_once SHIPFLO_WC_PLUGIN_DIR . 'includes/date_modifiers/shipflo-wc-order-delivery-date.php';

		require_once SHIPFLO_WC_PLUGIN_DIR . 'includes/dispatch/shipflo-wc-dispatch.php';

		require_once SHIPFLO_WC_PLUGIN_DIR . 'includes/functions/shipflo-wc-common.php';
		require_once SHIPFLO_WC_PLUGIN_DIR . 'includes/functions/shipflo-wc-logger.php';

		require_once SHIPFLO_WC_PLUGIN_DIR . 'includes/orders/order_data/class-shipflo-wc-order-core.php';
		require_once SHIPFLO_WC_PLUGIN_DIR . 'includes/orders/order_data/class-shipflo-wc-order.php';

		require_once SHIPFLO_WC_PLUGIN_DIR . 'includes/orders/class-shipflo-wc-order-management.php';

		$this->loader = new ShipFlo_Wc_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Shipflo_Wc_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale()
	{
		$plugin_i18n = new ShipFlo_Wc_i18n();

		$this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
	}

	/**
	 * Define the cron to be setup.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	// Inside ShipFlo_Wc class, e.g. in __construct() or a separate method
	private function define_cron_hooks()
	{
		// 1. Register the interval globally
		add_filter('cron_schedules', function ($schedules) {
			if (!isset($schedules['half_hour'])) {
				$schedules['half_hour'] = [
					'interval' => 30 * 60,
					'display'  => __('Every 30 Minutes', 'shipflo-wc'),
				];
			}
			return $schedules;
		});

		// 2. Schedule event if missing
		add_action('init', function () {
			if (!wp_next_scheduled('shipflo_push_logs_event')) {
				wp_schedule_event(time() + 60, 'half_hour', 'shipflo_push_logs_event');
				shipflo_logger('notice', '[ShipFlo] Scheduled recurring log push event (every 30 minutes).');
			}
		});

		// 3. Register callback
		add_action('shipflo_push_logs_event', 'shipflo_push_latest_log_to_backend');
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks()
	{
		$plugin_admin = new ShipFlo_Wc_Admin($this->get_plugin_name(), $this->get_version());

		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');

		// $this->loader->add_action('admin_init', $plugin_admin, 'dump_payload');

		if (is_admin()) {
			// Display Track button on Order edit page on Dashboard
			$this->loader->add_action('admin_init', $plugin_admin, 'shipflo_register_admin_order_meta_ui');

			// Display Track / Retry Push button on Order list page on Dashboard
			$this->loader->add_filter('woocommerce_admin_order_actions', $plugin_admin, 'shipflo_add_admin_actions', 10, 2);

			$this->loader->add_action('admin_notices', ShipFlo_Wc_Notices::class, 'shipflo_api_key_notice');

			$this->loader->add_action('admin_notices', ShipFlo_Wc_Notices::class, 'shipflo_retry_send_notice');

			$this->loader->add_action('woocommerce_settings_tabs_settings_tab_shipflo', ShipFlo_Wc_Settings_Tab::class, 'settings_tab');
			$this->loader->add_action('woocommerce_update_options_settings_tab_shipflo', ShipFlo_Wc_Settings_Tab::class, 'update_settings');

			$this->loader->add_filter('woocommerce_settings_tabs_array', ShipFlo_Wc_Settings_Tab::class, 'add_settings_tab', 50);
		}

		$this->loader->add_action('woocommerce_order_status_processing', ShipFlo_Wc_Order_Management::class, 'process_and_send');

		// retry failed orders
		$this->loader->add_action('wp_ajax_shipflo_process_order', ShipFlo_Wc_Order_Management::class, 'handle_ajax_retry');
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks()
	{
		$plugin_public = new ShipFlo_Wc_Public($this->get_plugin_name(), $this->get_version());

		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');

		// Enqueue Font libraries for customer-facing UI
		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'shipflo_enqueue_dashicons_frontend');

		$this->loader->add_action('shipflo_order_delivery_asap', ShipFlo_WC_Order_Core::class, 'shipflo_handle_asap_delivery_order_note');

		// Display Track button on Order list view on My Account
		$this->loader->add_filter('woocommerce_my_account_my_orders_actions', $plugin_public,  'shipflo_add_track_action', 10, 2);
		$this->loader->add_action('woocommerce_thankyou', $plugin_public,  'shipflo_display_customer_track_link', 20);

		// Registers Webhook endpoint to receive order updates - created or failed
		$this->loader->add_action('rest_api_init', $plugin_public, 'shipflo_register_webhook_endpoint');
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run()
	{
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name()
	{
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    ShipFlo_Wc_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader()
	{
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version()
	{
		return $this->version;
	}
}