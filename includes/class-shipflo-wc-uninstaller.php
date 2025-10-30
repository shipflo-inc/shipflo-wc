<?php

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * Fired during plugin uninstallation
 *
 * @link       https://sgssandhu.com
 * @since      1.0.0
 * @package    ShipFlo_Wc
 */

/**
 * This class defines all code necessary to run during the plugin's uninstallation.
 *
 * @since 1.0.0
 */
class ShipFlo_Wc_Uninstaller 
{
	/**
	 * Runs on plugin uninstall.
	 */
	public static function uninstall() 
	{
		// Multisite check: Run uninstall logic for all sites if needed
		if ( is_multisite() ) {
			$sites = get_sites();
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				self::run_cleanup();
				restore_current_blog();
			}
		} else {
			self::run_cleanup();
		}
	}

	/**
	 * Performs actual cleanup: options, custom tables, etc.
	 */
	private static function run_cleanup() 
	{
		shipflo_clear_api_key_and_merchant_details();
		delete_option( SHIPFLO_PLUGIN_ENCRYPTION_KEY_OPTION_ID ); 
		delete_transient( SHIPFLO_ACTIVE_POSTAL_CODES_TRANSIENT );
	}
}