<?php

// If this file is called directly, abort.
defined('ABSPATH') || exit;

require_once SHIPFLO_WC_PLUGIN_DIR . 'includes/functions/shipflo-wc-common.php';

/**
 * Fired during plugin activation
 *
 * @link       https://sgssandhu.com
 * @since      1.0.0
 *
 * @package    ShipFlo_Wc
 * @subpackage ShipFlo_Wc/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    ShipFlo_Wc
 * @subpackage ShipFlo_Wc/includes
 * @author     SGS Sandhu <sgs.sandhu@gmail.com>
 */
class ShipFlo_Wc_Activator
{
	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate()
	{
		self::cleanup_old_keys_and_api_data();

		// Generate the plugin's unique encryption key
		$key_generated = shipflo_generate_encryption_key();

		if (!$key_generated) {
			// Critical error: cannot generate secure key. Fail activation.
			shipflo_logger('critical', '[ShipFlo] Plugin activation failed. Could not generate secure encryption key. Please check PHP configuration (random_bytes or OpenSSL).');
			wp_die(
				__('ShipFlo Plugin Activation Error: Could not generate a secure encryption key. Please ensure your PHP version is 7.0+ or that the OpenSSL extension is enabled on your server. Plugin cannot be activated.', 'shipflo-wc'),
				__('Plugin Activation Error', 'shipflo-wc'),
				['back_link' => true]
			);
		}

		shipflo_logger('notice', '[ShipFlo] Plugin activated successfully.');

		self::setup_cron();
		shipflo_logger('notice', '[ShipFlo] Cron activated successfully.');
	}

	private static function cleanup_old_keys_and_api_data()
	{
		$existing_key = get_option(SHIPFLO_PLUGIN_ENCRYPTION_KEY_OPTION_ID);
		// if ($existing_key && !self::validate_key($existing_key)) {
		// Only remove the sensitive keys, not everything.
		shipflo_clear_api_key_and_merchant_details();
		delete_option(SHIPFLO_PLUGIN_ENCRYPTION_KEY_OPTION_ID);
		delete_transient(SHIPFLO_ACTIVE_POSTAL_CODES_TRANSIENT);

		shipflo_logger('notice', '[ShipFlo] Old encryption keys and API data cleared before activation.');
		// }
	}

	private static function validate_key($key_hex): bool
	{
		$key_bin = @hex2bin($key_hex);
		return $key_bin !== false && strlen($key_bin) === 32;
	}

	private static function setup_cron()
	{
		// 1. Register custom interval globally (not inside activation only)
		add_filter('cron_schedules', function ($schedules) {
			if (!isset($schedules['five_minutes'])) {
				$schedules['five_minutes'] = [
					'interval' => 5 * 60, // 5 minutes
					'display'  => __('Every 5 Minutes', 'shipflo-wc'),
				];
			}
			return $schedules;
		});

		// 2. Schedule event *after WP is fully loaded* to ensure the interval exists
		add_action('init', function () {
			if (!wp_next_scheduled('shipflo_push_logs_event')) {
				wp_schedule_event(time() + 60, 'five_minutes', 'shipflo_push_logs_event');
				shipflo_logger('notice', '[ShipFlo] Scheduled log push event (every 5 minutes).');
			}
		});
	}
}
